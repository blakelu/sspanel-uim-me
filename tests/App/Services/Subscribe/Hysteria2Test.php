<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use PHPUnit\Framework\TestCase;
use stdClass;

final class Hysteria2Test extends TestCase
{
    public function testBuildURI(): void
    {
        $node = new stdClass();
        $node->name = 'Korea HY2';
        $node->server = 'korea.hy2.example.com';
        $node->custom_config = json_encode([
            'offset_port_user' => 8443,
            'sni' => 'korea.hy2.example.com',
            'allow_insecure' => false,
            'obfs' => 'salamander',
            'obfs_password' => 'obfs secret',
        ]);

        $user = new stdClass();
        $user->uuid = '60825ac6-b65c-4bc9-a8b3-f2e9479a0c04';

        $this->assertSame(
            'hysteria2://60825ac6-b65c-4bc9-a8b3-f2e9479a0c04@korea.hy2.example.com:8443/'
            . '?sni=korea.hy2.example.com&insecure=0&obfs=salamander&obfs-password=obfs%20secret#Korea%20HY2',
            Hysteria2::buildURI($node, $user)
        );
    }

    public function testBuildURIUsesSafeDefaultsAndIPv6Brackets(): void
    {
        $node = new stdClass();
        $node->name = 'IPv6';
        $node->server = '2001:db8::1';
        $node->custom_config = '{}';

        $user = new stdClass();
        $user->uuid = 'user@example.com';

        $this->assertSame(
            'hysteria2://user%40example.com@[2001:db8::1]:443/?sni=2001%3Adb8%3A%3A1&insecure=0#IPv6',
            Hysteria2::buildURI($node, $user)
        );
    }
}
