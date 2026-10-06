<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Models\Config;
use App\Services\Subscribe;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AnyTLSSubscribeTest extends TestCase
{
    private object $user;

    protected function setUp(): void
    {
        class_alias(AnyTLSSubscribeSource::class, Subscribe::class);
        class_alias(AnyTLSSubscribeConfig::class, Config::class);
        $this->user = (object) ['uuid' => '00000000-0000-4000-8000-000000000007'];
        AnyTLSSubscribeSource::$nodes = [
            (object) [
                'sort' => 16, 'name' => 'AnyTLS Hong Kong', 'server' => '198.51.100.10',
                'custom_config' => json_encode([
                    'protocol' => 'anytls', 'offset_port_user' => '30443', 'offset_port_node' => '8443',
                    'sni' => 'hk.example.test', 'allow_insecure' => 'false',
                ]),
            ],
            (object) [
                'sort' => 11, 'name' => 'Existing VMess', 'server' => 'vmess.example.test',
                'custom_config' => json_encode(['network' => 'tcp', 'security' => 'tls']),
            ],
        ];
        $_ENV['Clash_Config'] = [];
        $_ENV['Clash_Group_Indexes'] = [0];
        $_ENV['Clash_Group_Config'] = ['proxy-groups' => [['name' => 'auto', 'proxies' => []]]];
        $_ENV['SingBox_Config'] = ['outbounds' => [['outbounds' => []], ['outbounds' => []]]];
        $_ENV['V2RayJson_Config'] = ['outbounds' => []];
        $_ENV['appName'] = 'test';
    }

    #[RequiresPhpExtension('yaml')]
    public function testClashUsesAnyTLSPasswordAndNATPublicPort(): void
    {
        $result = yaml_parse((new Clash())->getContent($this->user));
        $this->assertCount(2, $result['proxies']);
        $node = $result['proxies'][0];
        $this->assertSame('anytls', $node['type']);
        $this->assertSame($this->user->uuid, $node['password']);
        $this->assertSame(30443, $node['port']);
        $this->assertSame('hk.example.test', $node['sni']);
        $this->assertFalse($node['skip-cert-verify']);
        $this->assertTrue($node['udp']);
        $this->assertArrayNotHasKey('uuid', $node);
        $this->assertArrayNotHasKey('reality-opts', $node);
        $this->assertSame('vmess', $result['proxies'][1]['type']);
        $this->assertSame(['AnyTLS Hong Kong', 'Existing VMess'], $result['proxy-groups'][0]['proxies']);
    }

    public function testSingBoxHasVerifiedTLSAndNoVlessFields(): void
    {
        $result = json_decode((new SingBox())->getContent($this->user), true);
        $node = $result['outbounds'][2];
        $this->assertSame('anytls', $node['type']);
        $this->assertSame($this->user->uuid, $node['password']);
        $this->assertSame(30443, $node['server_port']);
        $this->assertTrue($node['tls']['enabled']);
        $this->assertSame('hk.example.test', $node['tls']['server_name']);
        $this->assertFalse($node['tls']['insecure']);
        $this->assertArrayNotHasKey('flow', $node);
        $this->assertArrayNotHasKey('reality', $node['tls']);
    }

    public function testURIAndMixedSubscriptionKeepProtocolIdentity(): void
    {
        $content = (new AnyTLS())->getContent($this->user);
        $this->assertSame(1, substr_count($content, 'anytls://'));
        $this->assertStringContainsString('@198.51.100.10:30443?', $content);
        $this->assertStringContainsString('sni=hk.example.test', $content);
        $this->assertStringContainsString('insecure=0', $content);
        $mixed = (new V2Ray())->getContent($this->user);
        $this->assertStringContainsString($content, $mixed);
        $this->assertSame(1, substr_count($mixed, 'vmess://'));
        // Xray's JSON format cannot represent AnyTLS: omit it rather than
        // emitting a VMess node with the wrong authentication parameters.
        $json = json_decode((new V2RayJson())->getContent($this->user), true);
        $this->assertCount(1, $json['outbounds']);
        $this->assertSame('vmess', $json['outbounds'][0]['protocol']);
    }

    public function testURIHandlesIPv6AndEncodesPasswords(): void
    {
        $node = AnyTLSSubscribeSource::$nodes[0];
        $node->server = '2001:db8::1';
        $this->user->uuid = 'password:@/#?';
        $uri = AnyTLS::buildURI($node, $this->user);
        $this->assertStringContainsString('anytls://password%3A%40%2F%23%3F@[2001:db8::1]:30443?', $uri);
        $this->assertFalse(AnyTLS::isInsecure(['insecure' => 'false']));
        $this->assertTrue(AnyTLS::isInsecure(['insecure' => '1']));
    }
}

final class AnyTLSSubscribeSource
{
    public static array $nodes = [];

    public static function getUserNodes(object $user): array
    {
        return self::$nodes;
    }
}

final class AnyTLSSubscribeConfig
{
    public static function obtain(string $key): bool
    {
        return $key === 'enable_v2_sub';
    }
}
