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
final class VlessWebSocketSubscribeTest extends TestCase
{
    private object $user;

    protected function setUp(): void
    {
        // Replace only the database-backed node source in isolated processes.
        class_alias(VlessSubscribeSource::class, Subscribe::class);
        class_alias(VlessSubscribeConfig::class, Config::class);
        $this->user = (object) ['uuid' => '00000000-0000-4000-8000-000000000001'];
        $ws_config = [
            'protocol' => 'vless',
            'network' => 'ws',
            'security' => 'tls',
            'sni' => 'de.cf.example.com',
            'host' => 'de.cf.example.com',
            'path' => '/vless',
            'offset_port_user' => '443',
        ];
        VlessSubscribeSource::$nodes = [
            $this->node('Germany WS', $ws_config),
            $this->node('Reality', [
                'protocol' => 'vless',
                'sni' => 'www.example.com',
                'public_key' => 'public-key',
                'short_id' => 'abcd1234',
            ]),
            $this->node('VMess', ['network' => 'tcp', 'security' => 'tls']),
            $this->node('Incomplete Reality', ['protocol' => 'vless', 'sni' => 'www.example.com']),
        ];
        $_ENV['Clash_Config'] = [];
        $_ENV['Clash_Group_Indexes'] = [0];
        $_ENV['Clash_Group_Config'] = ['proxy-groups' => [['name' => 'auto', 'proxies' => []]]];
        $_ENV['SingBox_Config'] = ['outbounds' => [['outbounds' => []], ['outbounds' => []]]];
        $_ENV['V2RayJson_Config'] = ['outbounds' => []];
        $_ENV['appName'] = 'test';
    }

    #[RequiresPhpExtension('yaml')]
    public function testClashKeepsWebSocketNodeAndExistingProtocols(): void
    {
        $config = yaml_parse((new Clash())->getContent($this->user));
        $this->assertCount(3, $config['proxies']);
        $node = $config['proxies'][0];
        $this->assertSame('vless', $node['type']);
        $this->assertSame('ws', $node['network']);
        $this->assertTrue($node['tls']);
        $this->assertSame(443, $node['port']);
        $this->assertSame('de.cf.example.com', $node['servername']);
        $this->assertSame('/vless', $node['ws-opts']['path']);
        $this->assertSame('de.cf.example.com', $node['ws-opts']['headers']['Host']);
        $this->assertArrayNotHasKey('reality-opts', $node);
        $this->assertArrayNotHasKey('flow', $node);
        $this->assertSame('xtls-rprx-vision', $config['proxies'][1]['flow']);
        $this->assertSame('vmess', $config['proxies'][2]['type']);
        $this->assertSame(['Germany WS', 'Reality', 'VMess'], $config['proxy-groups'][0]['proxies']);
    }

    public function testSingBoxOutputsWebSocketAndPlainTLS(): void
    {
        $config = json_decode((new SingBox())->getContent($this->user), true);
        $this->assertCount(5, $config['outbounds']);
        $node = $config['outbounds'][2];
        $this->assertSame('vless', $node['type']);
        $this->assertSame('ws', $node['transport']['type']);
        $this->assertSame('/vless', $node['transport']['path']);
        $this->assertSame('de.cf.example.com', $node['transport']['headers']['Host']);
        $this->assertTrue($node['tls']['enabled']);
        $this->assertArrayNotHasKey('reality', $node['tls']);
        $this->assertArrayNotHasKey('flow', $node);
        $this->assertTrue($config['outbounds'][3]['tls']['reality']['enabled']);
        $this->assertSame('vmess', $config['outbounds'][4]['type']);
    }

    public function testV2RayJsonOutputsWebSocketAndTLS(): void
    {
        $config = json_decode((new V2RayJson())->getContent($this->user), true);
        $this->assertCount(3, $config['outbounds']);
        $node = $config['outbounds'][0];
        $this->assertSame('vless', $node['protocol']);
        $this->assertSame('ws', $node['streamSettings']['transport']);
        $this->assertSame('/vless', $node['streamSettings']['transportSettings']['ws']['path']);
        $this->assertSame('de.cf.example.com', $node['streamSettings']['transportSettings']['ws']['header']['Host']);
        $this->assertSame('tls', $node['streamSettings']['security']);
        $this->assertArrayNotHasKey('reality', $node['streamSettings']['securitySettings']);
        $this->assertArrayNotHasKey('flow', $node['settings']);
        $this->assertSame('reality', $config['outbounds'][1]['streamSettings']['security']);
    }

    public function testVlessAndV2RayLinksUseVlessWebSocketURI(): void
    {
        $vless = (new VlessReality())->getContent($this->user);
        $this->assertStringContainsString('security=tls', $vless);
        $this->assertStringContainsString('type=ws', $vless);
        $this->assertStringContainsString('path=%2Fvless', $vless);
        $this->assertStringContainsString('security=reality', $vless);
        $this->assertSame(2, substr_count($vless, 'vless://'));
        $v2ray = (new V2Ray())->getContent($this->user);
        $this->assertStringContainsString($vless, $v2ray);
        $this->assertSame(1, substr_count($v2ray, 'vmess://'));
    }

    private function node(string $name, array $config): object
    {
        return (object) [
            'sort' => 11,
            'name' => $name,
            'server' => 'de.cf.example.com',
            'custom_config' => json_encode($config),
        ];
    }
}

final class VlessSubscribeSource
{
    public static array $nodes = [];

    public static function getUserNodes(object $user): array
    {
        return self::$nodes;
    }
}

final class VlessSubscribeConfig
{
    public static function obtain(string $key): bool
    {
        return $key === 'enable_v2_sub';
    }
}
