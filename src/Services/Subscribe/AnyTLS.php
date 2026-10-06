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

final class AnyTLS extends Base
{
    public function getContent($user): string
    {
        $links = '';
        foreach (Subscribe::getUserNodes($user) as $node) {
            if ((int) $node->sort === 16) {
                $links .= self::buildURI($node, $user) . PHP_EOL;
            }
        }

        return $links;
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
        return (string) ($config['sni'] ?? $config['host'] ?? $node->server);
    }

    public static function isInsecure(array $config): bool
    {
        return filter_var($config['allow_insecure'] ?? $config['insecure'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function buildURI(object $node, object $user): string
    {
        $config = self::getConfig($node);
        $server = (string) $node->server;
        $server = Tools::isIPv6($server) ? '[' . $server . ']' : $server;
        $query = [
            'sni' => self::getSNI($node, $config),
            'insecure' => self::isInsecure($config) ? '1' : '0',
        ];

        return 'anytls://' . rawurlencode((string) $user->uuid)
            . '@' . $server . ':' . self::getPort($config) . '?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . '#' . rawurlencode((string) $node->name);
    }
}
