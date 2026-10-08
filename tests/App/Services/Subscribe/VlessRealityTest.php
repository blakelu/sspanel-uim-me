<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use PHPUnit\Framework\TestCase;
use stdClass;

final class VlessRealityTest extends TestCase
{
    public function testBuildURI(): void
    {
        $node = new stdClass();
        $node->name = 'Korea VLESS';
        $node->server = 'kr.example.com';
        $node->custom_config = json_encode([
            'protocol' => 'vless',
            'offset_port_user' => 8443,
            'sni' => 'www.microsoft.com',
            'public_key' => 'server-public-key',
            'short_id' => '0123456789abcdef',
            'fingerprint' => 'chrome',
            'flow' => 'xtls-rprx-vision',
        ]);

        $user = new stdClass();
        $user->uuid = '60825ac6-b65c-4bc9-a8b3-f2e9479a0c04';

        $this->assertSame(
            'vless://60825ac6-b65c-4bc9-a8b3-f2e9479a0c04@kr.example.com:8443?'
            . 'encryption=none&flow=xtls-rprx-vision&security=reality&sni=www.microsoft.com&fp=chrome'
            . '&pbk=server-public-key&sid=0123456789abcdef&type=tcp#Korea%20VLESS',
            VlessReality::buildURI($node, $user)
        );
    }

    public function testSupportsAliasesAndIPv6(): void
    {
        $node = new stdClass();
        $node->name = 'IPv6 VLESS';
        $node->server = '2001:db8::1';
        $node->custom_config = json_encode([
            'protocol' => 'VLESS',
            'serverName' => 'www.microsoft.com',
            'pbk' => 'public-key',
            'sid' => 'abcd1234',
        ]);

        $user = new stdClass();
        $user->uuid = 'user@example.com';

        $this->assertStringStartsWith(
            'vless://user%40example.com@[2001:db8::1]:443?',
            VlessReality::buildURI($node, $user)
        );
    }

    public function testIncompleteRealityConfigurationIsSkipped(): void
    {
        $node = new stdClass();
        $node->name = 'Invalid';
        $node->server = 'node.example.com';
        $node->custom_config = json_encode([
            'protocol' => 'vless',
            'sni' => 'www.microsoft.com',
        ]);

        $user = new stdClass();
        $user->uuid = '60825ac6-b65c-4bc9-a8b3-f2e9479a0c04';

        $this->assertSame('', VlessReality::buildURI($node, $user));
    }

    public function testWebSocketTLSDoesNotRequireRealityKeysOrFlow(): void
    {
        $node = (object) [
            'name' => 'Germany WS',
            'server' => 'de.cf.example.com',
            'custom_config' => json_encode([
                'protocol' => 'vless',
                'network' => 'ws',
                'security' => 'tls',
                'sni' => 'de.cf.example.com',
                'host' => 'de.cf.example.com',
                'path' => '/vless?ed=2048',
                'flow' => 'xtls-rprx-vision',
            ]),
        ];
        $user = (object) ['uuid' => '00000000-0000-4000-8000-000000000001'];

        $this->assertSame(
            'vless://00000000-0000-4000-8000-000000000001@de.cf.example.com:443?'
            . 'encryption=none&security=tls&sni=de.cf.example.com&type=ws'
            . '&host=de.cf.example.com&path=%2Fvless%3Fed%3D2048&fp=chrome#Germany%20WS',
            VlessReality::buildURI($node, $user)
        );
        $this->assertSame('', VlessReality::getFlow(VlessReality::getConfig($node)));
    }

    public function testWebSocketOptionsSupportNestedAliasesAndHostArrays(): void
    {
        foreach (['ws-opts', 'ws_opts', 'wsSettings'] as $alias) {
            $options = VlessReality::getWebSocketOptions([
                'sni' => 'tls.example.com',
                $alias => ['path' => '/ws', 'headers' => ['Host' => ['ws.example.com']]],
            ]);

            $this->assertSame('/ws', $options['path']);
            $this->assertSame('ws.example.com', $options['headers']['Host']);
        }
    }

    public function testWebSocketTLSRejectsMissingSNIAndInvalidPath(): void
    {
        $config = ['network' => 'ws', 'security' => 'tls', 'path' => '/vless'];
        $this->assertFalse(VlessReality::isConfigured($config));
        $config['sni'] = 'ws.example.com';
        $this->assertTrue(VlessReality::isConfigured($config));
        $config['path'] = 'missing-slash';
        $this->assertFalse(VlessReality::isConfigured($config));
        $config['path'] = '/vless';
        $config['security'] = 'none';
        $this->assertFalse(VlessReality::isConfigured($config));
    }

    public function testXHTTPRealityExportsPathModeAndKeepsKeysWithoutVision(): void
    {
        $config = [
            'protocol' => 'vless', 'network' => 'xhttp', 'security' => 'reality',
            'sni' => 'www.example.com', 'public_key' => 'public-key', 'short_id' => 'abcd1234',
            'path' => '/proxy/xhttp', 'mode' => 'stream-one', 'host' => 'http.example.com',
            'flow' => 'xtls-rprx-vision', 'offset_port_user' => 30443,
        ];
        $node = (object) ['name' => 'XHTTP', 'server' => '2001:db8::1', 'custom_config' => json_encode($config)];
        $uri = VlessReality::buildURI($node, (object) ['uuid' => '00000000-0000-4000-8000-000000000001']);
        $this->assertStringStartsWith('vless://00000000-0000-4000-8000-000000000001@[2001:db8::1]:30443?', $uri);
        parse_str(parse_url($uri, PHP_URL_QUERY), $query);
        $this->assertSame('xhttp', $query['type']);
        $this->assertSame('reality', $query['security']);
        $this->assertSame('/proxy/xhttp', $query['path']);
        $this->assertSame('stream-one', $query['mode']);
        $this->assertSame('http.example.com', $query['host']);
        $this->assertSame('public-key', $query['pbk']);
        $this->assertSame('abcd1234', $query['sid']);
        $this->assertArrayNotHasKey('flow', $query);
        $this->assertSame('', VlessReality::getFlow($config));
    }

    public function testXHTTPAliasesAndInvalidConfiguration(): void
    {
        $base = ['network' => 'splithttp', 'sni' => 'www.example.com', 'pbk' => 'public-key', 'sid' => 'abcd1234'];
        foreach (['xhttp-opts', 'xhttp_opts', 'xhttpSettings', 'splithttpSettings'] as $alias) {
            $config = $base + [$alias => ['path' => '/test', 'mode' => 'packet-up']];
            $this->assertTrue(VlessReality::isConfigured($config));
            $this->assertSame('xhttp', VlessReality::getNetwork($config));
            $this->assertSame(['path' => '/test', 'mode' => 'packet-up'], VlessReality::getXHTTPOptions($config));
        }
        $this->assertFalse(VlessReality::isConfigured($base + ['path' => 'invalid']));
        $this->assertFalse(VlessReality::isConfigured($base + ['mode' => 'invalid']));
        $this->assertFalse(VlessReality::isConfigured($base + ['security' => 'tls']));
        unset($base['pbk']);
        $this->assertFalse(VlessReality::isConfigured($base));
    }
}
