<?php

declare(strict_types=1);

namespace App\Services\Subscribe;


use App\Utils\Tools;
use function http_build_query;
use function is_array;
use function json_decode;
use function rawurlencode;
use function strtolower;
use const PHP_QUERY_RFC3986;

final class VlessReality
{
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
		return self::getServerName($config) !== ''
			&& self::getPublicKey($config) !== ''
			&& self::getShortID($config) !== '';
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

		return 'vless://' . rawurlencode((string) $user->uuid)
			. '@' . $uri_server . ':' . self::getPort($config) . '?'
			. http_build_query($query, '', '&', PHP_QUERY_RFC3986)
			. '#' . rawurlencode((string) $node->name);
	}
}
