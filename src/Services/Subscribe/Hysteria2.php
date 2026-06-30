<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Services\Subscribe;
use App\Utils\Tools;
use function filter_var;
use function http_build_query;
use function is_array;
use function json_decode;
use function rawurlencode;
use const FILTER_VALIDATE_BOOLEAN;
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

            $links .= self::buildURI($node, $user) . PHP_EOL;
        }

        return $links;
    }

    public static function buildURI(object $node, object $user): string
    {
        $config = self::getConfig($node);
        $server = (string) $node->server;
        $uri_server = Tools::isIPv6($server) ? '[' . $server . ']' : $server;
        $query = [
            'sni' => self::getSNI($node, $config),
            'insecure' => self::isInsecure($config) ? '1' : '0',
        ];

        $obfs = self::getObfs($config);
        $obfs_password = self::getObfsPassword($config);
        $pin_sha256 = (string) ($config['pin_sha256'] ?? $config['pinSHA256'] ?? '');

        if ($obfs !== '') {
            $query['obfs'] = $obfs;
            if ($obfs_password !== '') {
                $query['obfs-password'] = $obfs_password;
            }
        }
        if ($pin_sha256 !== '') {
            $query['pinSHA256'] = $pin_sha256;
        }

        return 'hysteria2://' . rawurlencode((string) $user->uuid)
            . '@' . $uri_server . ':' . self::getPort($config) . '/?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . '#' . rawurlencode((string) $node->name);
    }

    public static function getConfig(object $node): array
    {
        $config = json_decode((string) $node->custom_config, true);

        return is_array($config) ? $config : [];
    }

    public static function getPort(array $config): int
    {
        $port = (int) ($config['offset_port_user'] ?? $config['offset_port_node'] ?? 443);

        return $port > 0 && $port <= 65535 ? $port : 443;
    }

    public static function getSNI(object $node, array $config): string
    {
        $sni = (string) ($config['sni'] ?? $config['host'] ?? '');

        return $sni !== '' ? $sni : (string) $node->server;
    }

    public static function isInsecure(array $config): bool
    {
        return filter_var(
            $config['allow_insecure'] ?? $config['insecure'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function getObfs(array $config): string
    {
        return (string) ($config['obfs'] ?? '');
    }

    public static function getObfsPassword(array $config): string
    {
        return (string) ($config['obfs_password'] ?? $config['obfs-password'] ?? '');
    }
}
