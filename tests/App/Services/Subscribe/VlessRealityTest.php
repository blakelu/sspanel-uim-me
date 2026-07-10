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
}
