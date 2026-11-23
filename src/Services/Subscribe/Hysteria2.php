<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Services\Subscribe;
use function http_build_query;
use function json_decode;
use function ltrim;
use function rawurlencode;
use function str_starts_with;
use const PHP_EOL;
use const PHP_QUERY_RFC3986;

final class Hysteria2 extends Base
{
    public function getContent($user): string
    {
        $links = '';

        foreach (Subscribe::getUserNodes($user) as $node) {
            if ((int) $node->sort !== 15) {
                continue;
            }

            $uri = self::buildUri($node, $user);
            if ($uri !== null) {
                $links .= $uri . PHP_EOL;
            }
        }

        return $links;
    }

    public static function config($node): ?array
    {
        $raw = (string) $node->custom_config;
        $custom = json_decode($raw, true);
        if (! is_array($custom) || ! str_starts_with(ltrim($raw), '{')) {
            return null;
        }
        if (! array_key_exists('hysteria2', $custom)) {
            return self::legacyConfig($node, $custom);
        }
        if (! is_object(json_decode($raw)->hysteria2)) {
            return null;
        }

        $hysteria = $custom['hysteria2'];
        $finalMask = $hysteria['finalmask'] ?? [];
        $quic = $finalMask['quicParams'] ?? [];
        $udpMasks = $finalMask['udp'] ?? [];
        $nativeHop = null;
        $obfs = 'none';
        $obfsPassword = '';

        foreach ($udpMasks as $mask) {
            if (($mask['type'] ?? '') === 'udphop') {
                $nativeHop = $mask['settings'] ?? [];
            }
            if ($obfs === 'none' && ($mask['type'] ?? '') === 'salamander') {
                $obfs = 'salamander';
                $obfsPassword = (string) ($mask['settings']['password'] ?? '');
            }
        }

        $hop = self::portHopping($custom);
        if ($hop !== null) {
            $hopEnabled = ($hop['enabled'] ?? false) === true;
            $hopSource = $hop;
            $hopPorts = $hop['ports'] ?? '';
        } else {
            $hopEnabled = $nativeHop !== null;
            $hopSource = $nativeHop;
            $hopPorts = $nativeHop['remotePorts'] ?? '';
        }
        if (is_array($hopPorts)) {
            $hopPorts = implode(',', $hopPorts);
        }

        return [
            'server' => $node->server,
            'port' => (int) ($custom['offset_port_user'] ?? ($custom['offset_port_node'] ?? 443)),
            'sni' => (string) ($custom['host'] ?? $node->server),
            'insecure' => filter_var($custom['allow_insecure'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'obfs' => $obfs,
            'obfs_password' => $obfsPassword,
            'up' => $quic['brutalUp'] ?? null,
            'down' => $quic['brutalDown'] ?? null,
            'up_mbps' => self::bandwidthMbps($quic['brutalUp'] ?? null),
            'down_mbps' => self::bandwidthMbps($quic['brutalDown'] ?? null),
            'hop_enabled' => $hopEnabled,
            'ports' => $hopEnabled ? (string) $hopPorts : '',
            'hop_interval' => $hopEnabled ? self::intervalSeconds($hopSource['interval'] ?? null) : null,
            'pin_sha256' => '',
            'legacy' => false,
        ];
    }

    /** Normalize the flat configuration used by Hysteria2 adapter nodes. */
    private static function legacyConfig(object $node, array $config): array
    {
        $port = (int) ($config['offset_port_user'] ?? $config['offset_port_node'] ?? 443);
        $sni = (string) ($config['sni'] ?? $config['host'] ?? '');
        $up = (int) ($config['up_mbps'] ?? 0);
        $down = (int) ($config['down_mbps'] ?? 0);

        return [
            'server' => (string) $node->server,
            'port' => $port > 0 && $port <= 65535 ? $port : 443,
            'sni' => $sni !== '' ? $sni : (string) $node->server,
            'insecure' => filter_var($config['allow_insecure'] ?? $config['insecure'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'obfs' => (string) ($config['obfs'] ?? 'none'),
            'obfs_password' => (string) ($config['obfs_password'] ?? $config['obfs-password'] ?? ''),
            'up' => $up > 0 ? $up . ' Mbps' : null,
            'down' => $down > 0 ? $down . ' Mbps' : null,
            'up_mbps' => $up > 0 ? $up : null,
            'down_mbps' => $down > 0 ? $down : null,
            'hop_enabled' => false,
            'ports' => '',
            'hop_interval' => null,
            'pin_sha256' => (string) ($config['pin_sha256'] ?? $config['pinSHA256'] ?? ''),
            'legacy' => true,
        ];
    }

    /** Select one whole deployment object by priority, without merging lower-level ones. */
    public static function portHopping(array $custom): mixed
    {
        return $custom['portHopping'] ?? $custom['hysteria2']['portHopping']
            ?? $custom['hysteria2']['finalmask']['quicParams']['udpHop'] ?? null;
    }

    public static function buildUri($node, $user): ?string
    {
        $config = self::config($node);
        if ($config === null) {
            return null;
        }

        $query = ['sni' => $config['sni']];

        if ($config['insecure'] || $config['legacy']) {
            $query['insecure'] = $config['insecure'] ? '1' : '0';
        }
        if ($config['obfs'] !== 'none' && $config['obfs'] !== '') {
            $query['obfs'] = $config['obfs'];
            if ($config['obfs_password'] !== '') {
                $query['obfs-password'] = $config['obfs_password'];
            }
        }
        if ($config['pin_sha256'] !== '') {
            $query['pinSHA256'] = $config['pin_sha256'];
        }
        if ($config['hop_enabled']) {
            $query['mport'] = $config['ports'];
        }

        return 'hysteria2://' . rawurlencode($user->uuid) . '@' . self::formatHost($config['server']) . ':' . $config['port']
            . '/?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) . '#' . rawurlencode($node->name);
    }

    private static function formatHost(string $host): string
    {
        return str_contains($host, ':') && $host[0] !== '[' ? '[' . $host . ']' : $host;
    }

    private static function bandwidthMbps(mixed $bandwidth): ?float
    {
        if ($bandwidth === null || $bandwidth === '') {
            return null;
        }
        if (is_int($bandwidth) || is_float($bandwidth)) {
            return (float) $bandwidth / 1000000;
        }
        if (preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?)b(?:ps)?\s*$/i', (string) $bandwidth, $match) !== 1) {
            return null;
        }
        $multiplier = match (strtolower($match[2])) {
            'k' => 1 / 1024,
            'm' => 1,
            'g' => 1024,
            't' => 1024 * 1024,
            default => 1 / (1024 * 1024),
        };

        return (float) $match[1] * $multiplier;
    }

    private static function intervalSeconds(mixed $interval): int|float|null
    {
        if (is_array($interval)) {
            $interval = $interval['from'] ?? $interval['to'] ?? null;
        }

        return is_numeric($interval) ? (float) $interval : null;
    }
}
