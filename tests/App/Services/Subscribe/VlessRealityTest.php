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
}
