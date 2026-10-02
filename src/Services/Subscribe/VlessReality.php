<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Services\Subscribe;
use App\Utils\Tools;
use function http_build_query;
use function is_array;
use function json_decode;
use function rawurlencode;
use function str_starts_with;
use function strtolower;
use const PHP_EOL;
use const PHP_QUERY_RFC3986;

final class VlessReality extends Base
{
    public function getContent($user): string
    {
        $links = '';

        foreach (Subscribe::getUserNodes($user) as $node) {
            if ((int) $node->sort !== 11) {
                continue;
            }

            $uri = self::buildURI($node, $user);
            if ($uri !== '') {
                $links .= $uri . PHP_EOL;
            }
        }

        return $links;
    }

    public static function getConfig(object $node): array
    {
        $config = json_decode((string) $node->custom_config, true);

        return is_array($config) ? $config : [];
    }

    public static function isEnabled(array $config): bool
    {
        return strtolower((string) ($config['protocol'] ?? '')) === 'vless';
    }

    public static function isConfigured(array $config): bool
    {
        if (self::isWebSocketTLS($config)) {
            return self::getServerName($config) !== ''
                && str_starts_with(self::getWebSocketOptions($config)['path'], '/');
        }

        return self::getNetwork($config) === 'tcp'
            && self::getSecurity($config) === 'reality'
            && self::getServerName($config) !== ''
            && self::getPublicKey($config) !== ''
            && self::getShortID($config) !== '';
    }

    public static function getNetwork(array $config): string
    {
        return strtolower((string) ($config['network'] ?? 'tcp'));
    }

    public static function getSecurity(array $config): string
    {
        return strtolower((string) ($config['security'] ?? 'reality'));
    }

    public static function isWebSocketTLS(array $config): bool
    {
        return self::getNetwork($config) === 'ws' && self::getSecurity($config) === 'tls';
    }

    public static function getWebSocketOptions(array $config): array
    {
        $options = $config['ws-opts'] ?? $config['ws_opts'] ?? $config['wsSettings'] ?? [];
        $options = is_array($options) ? $options : [];
        $headers = $options['headers'] ?? $config['header']['request']['headers'] ?? [];
        $headers = is_array($headers) ? $headers : [];
        $host = $headers['Host'] ?? $options['host'] ?? $config['host'] ?? self::getServerName($config);
        $headers['Host'] = (string) (is_array($host) ? ($host[0] ?? '') : $host);
        $options['path'] = (string) ($options['path'] ?? $config['path']
            ?? $config['header']['request']['path'][0] ?? '/');
        $options['headers'] = $headers;
        unset($options['host']);

        return $options;
    }

    public static function getPort(array $config): int
    {
        $port = (int) ($config['offset_port_user'] ?? $config['offset_port_node'] ?? 443);

        return $port > 0 && $port <= 65535 ? $port : 443;
    }

    public static function getServerName(array $config): string
    {
        return (string) ($config['sni'] ?? $config['server_name'] ?? $config['serverName'] ?? '');
    }

    public static function getPublicKey(array $config): string
    {
        return (string) ($config['public_key'] ?? $config['publicKey'] ?? $config['pbk'] ?? '');
    }

    public static function getShortID(array $config): string
    {
        return (string) ($config['short_id'] ?? $config['shortId'] ?? $config['sid'] ?? '');
    }

    public static function getFingerprint(array $config): string
    {
        return (string) ($config['fingerprint'] ?? $config['fp'] ?? 'chrome');
    }

    public static function getFlow(array $config): string
    {
        if (self::isWebSocketTLS($config)) {
            return '';
        }

        return (string) ($config['flow'] ?? 'xtls-rprx-vision');
    }

    public static function buildURI(object $node, object $user): string
    {
        $config = self::getConfig($node);
        if (! self::isEnabled($config) || ! self::isConfigured($config)) {
            return '';
        }

        $server = (string) $node->server;
        $uri_server = Tools::isIPv6($server) ? '[' . $server . ']' : $server;
        $query = [
            'encryption' => 'none',
            'flow' => self::getFlow($config),
            'security' => 'reality',
            'sni' => self::getServerName($config),
            'fp' => self::getFingerprint($config),
            'pbk' => self::getPublicKey($config),
            'sid' => self::getShortID($config),
            'type' => 'tcp',
        ];

        if (self::isWebSocketTLS($config)) {
            $ws_options = self::getWebSocketOptions($config);
            $query = [
                'encryption' => 'none',
                'security' => 'tls',
                'sni' => self::getServerName($config),
                'type' => 'ws',
                'host' => $ws_options['headers']['Host'],
                'path' => $ws_options['path'],
                'fp' => self::getFingerprint($config),
            ];
        }

        return 'vless://' . rawurlencode((string) $user->uuid)
            . '@' . $uri_server . ':' . self::getPort($config) . '?'
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . '#' . rawurlencode((string) $node->name);
    }
}
