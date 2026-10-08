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
        $this->assertArrayNotHasKey('ech-opts', $node);
        $this->assertSame('xtls-rprx-vision', $config['proxies'][1]['flow']);
        $this->assertSame('vmess', $config['proxies'][2]['type']);
        $this->assertSame(['Germany WS', 'Reality', 'VMess'], $config['proxy-groups'][0]['proxies']);
    }

    #[RequiresPhpExtension('yaml')]
    public function testClashExportsECHAndKeepsOriginNamesWhenUsingAnEdgeAddress(): void
    {
        $node = VlessSubscribeSource::$nodes[0];
        $config = json_decode($node->custom_config, true);
        $key = 'AEX+DQBBBwAgACA0ZfO9G0mfh7bIMcIFxViIDwERQ/Cfw2FNVgylWE7yNAAEAAEAAQASY2xvdWRmbGFyZS1lY2guY29tAAA=';
        $config['connect_address'] = '104.21.42.5';
        $config['ech-opts'] = ['enable' => true, 'config' => $key];
        $node->custom_config = json_encode($config);

        $result = yaml_parse((new Clash())->getContent($this->user));
        $this->assertCount(3, $result['proxies']);
        $ws = $result['proxies'][0];
        $this->assertSame('104.21.42.5', $ws['server']);
        $this->assertSame('de.cf.example.com', $ws['servername']);
        $this->assertSame('de.cf.example.com', $ws['ws-opts']['headers']['Host']);
        $this->assertSame('/vless', $ws['ws-opts']['path']);
        $this->assertSame(['enable' => true, 'config' => $key], $ws['ech-opts']);
        $this->assertArrayNotHasKey('client-fingerprint', $ws);
        $this->assertArrayNotHasKey('ech-opts', $result['proxies'][1]);
        $this->assertSame('chrome', $result['proxies'][1]['client-fingerprint']);
        $this->assertArrayNotHasKey('ech-opts', $result['proxies'][2]);
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

    #[RequiresPhpExtension('yaml')]
    public function testXHTTPRealityClashAndURIHaveMatchingTransportAndCredentials(): void
    {
        VlessSubscribeSource::$nodes[] = $this->node('XHTTP', [
            'protocol' => 'vless', 'network' => 'xhttp', 'security' => 'reality',
            'sni' => 'www.example.com', 'public_key' => 'public-key', 'short_id' => 'abcd1234',
            'path' => '/xhttp', 'mode' => 'auto', 'flow' => 'xtls-rprx-vision',
        ]);
        $config = yaml_parse((new Clash())->getContent($this->user));
        $this->assertCount(4, $config['proxies']);
        $node = $config['proxies'][3];
        $this->assertSame('vless', $node['type']);
        $this->assertSame('xhttp', $node['network']);
        $this->assertSame($this->user->uuid, $node['uuid']);
        $this->assertSame(['path' => '/xhttp', 'mode' => 'auto'], $node['xhttp-opts']);
        $this->assertSame(['public-key' => 'public-key', 'short-id' => 'abcd1234'], $node['reality-opts']);
        $this->assertSame(['h2'], $node['alpn']);
        $this->assertArrayNotHasKey('flow', $node);
        $this->assertArrayNotHasKey('ws-opts', $node);
        $this->assertSame(['Germany WS', 'Reality', 'VMess', 'XHTTP'], $config['proxy-groups'][0]['proxies']);
        foreach ([new VlessReality(), new V2Ray()] as $exporter) {
            $content = $exporter->getContent($this->user);
            $this->assertStringContainsString('type=xhttp&path=%2Fxhttp&mode=auto', $content);
            $this->assertSame(3, substr_count($content, 'vless://'));
        }
        // Unsupported exporters must not downgrade the XHTTP transport to TCP.
        $singbox = json_decode((new SingBox())->getContent($this->user), true);
        $this->assertCount(5, $singbox['outbounds']);
        $json = json_decode((new V2RayJson())->getContent($this->user), true);
        $this->assertCount(3, $json['outbounds']);
    }

    #[RequiresPhpExtension('yaml')]
    public function testXHTTPTLSExportsWithoutRealityCredentialsOrFlow(): void
    {
        $config = [
            'protocol' => 'vless', 'network' => 'xhttp', 'security' => 'tls',
            'sni' => 'cf.example.com', 'path' => '/xhttp', 'mode' => 'packet-up',
            'host' => 'cf.example.com', 'flow' => 'xtls-rprx-vision',
        ];
        $source = $this->node('XHTTP TLS', $config);
        VlessSubscribeSource::$nodes[] = $source;
        $result = yaml_parse((new Clash())->getContent($this->user));
        $node = $result['proxies'][3];
        $this->assertTrue($node['tls']);
        $this->assertSame('xhttp', $node['network']);
        $this->assertSame(['path' => '/xhttp', 'mode' => 'packet-up', 'host' => 'cf.example.com'], $node['xhttp-opts']);
        $this->assertArrayNotHasKey('reality-opts', $node);
        $this->assertArrayNotHasKey('flow', $node);
        parse_str(parse_url(VlessReality::buildURI($source, $this->user), PHP_URL_QUERY), $query);
        $this->assertSame('tls', $query['security']);
        $this->assertSame('xhttp', $query['type']);
        $this->assertSame('packet-up', $query['mode']);
        foreach (['flow', 'pbk', 'sid'] as $key) {
            $this->assertArrayNotHasKey($key, $query);
        }
        foreach ([['path' => 'invalid'], ['mode' => 'invalid'], ['sni' => '']] as $invalid) {
            $this->assertFalse(VlessReality::isConfigured(array_replace($config, $invalid)));
        }
        $this->assertCount(5, json_decode((new SingBox())->getContent($this->user), true)['outbounds']);
        $this->assertCount(3, json_decode((new V2RayJson())->getContent($this->user), true)['outbounds']);
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
