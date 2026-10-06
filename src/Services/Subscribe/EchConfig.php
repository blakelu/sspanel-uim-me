<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Services\Cache;
use Redis;
use Throwable;

final class EchConfig
{
    private const FRESH_SECONDS = 60;
    private const STALE_SECONDS = 86400;
    private const PROVIDERS = ['https://cloudflare-dns.com/dns-query', 'https://dns.google/resolve'];

    private static array $resolved = [];
    private static bool $cacheInitialized = false;
    private static ?Redis $cache = null;

    public static function getOptions(string $hostname, array $config): ?array
    {
        $options = $config['ech-opts'] ?? $config['ech_opts'] ?? [];
        $options = is_array($options) ? $options : [];
        if (! filter_var($options['enable'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $key = (string) ($options['config'] ?? '');
        if (! self::isValid($key)) {
            $key = '';
        }
        if (filter_var($config['ech_auto'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            // Resolve at subscription time: mobile clients need the actual ECHConfigList.
            $key = self::resolve($hostname) ?: $key;
        }

        return $key === '' ? ['enable' => true] : ['enable' => true, 'config' => $key];
    }

    public static function fromDNSResponse(array $response): string
    {
        if (($response['Status'] ?? -1) !== 0 || ! is_array($response['Answer'] ?? null)) {
            return '';
        }
        foreach ($response['Answer'] as $answer) {
            if (! is_array($answer) || ($answer['type'] ?? 0) !== 65) {
                continue;
            }
            if (preg_match('/(?:^|\s)ech="?([A-Za-z0-9+\/]+={0,2})(?:"|\s|$)/', (string) ($answer['data'] ?? ''), $match)
                && self::isValid($match[1])) {
                return $match[1];
            }
        }

        return '';
    }

    public static function isValid(string $config): bool
    {
        if ($config === '' || strlen($config) > 8192) {
            return false;
        }
        $bytes = base64_decode($config, true);
        if ($bytes === false || strlen($bytes) < 6
            || unpack('n', substr($bytes, 0, 2))[1] !== strlen($bytes) - 2) {
            return false;
        }
        $offset = 2;
        $supported = false;
        while ($offset < strlen($bytes)) {
            if ($offset + 4 > strlen($bytes)) {
                return false;
            }
            $header = unpack('nversion/nlength', substr($bytes, $offset, 4));
            $offset += 4 + $header['length'];
            if ($header['length'] === 0 || $offset > strlen($bytes)) {
                return false;
            }
            $supported = $supported || $header['version'] === 0xfe0d;
        }

        return $supported;
    }

    private static function resolve(string $hostname): string
    {
        if (isset(self::$resolved[$hostname])) {
            return self::$resolved[$hostname];
        }
        self::$resolved[$hostname] = '';
        if (strlen($hostname) > 253
            || ! filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return '';
        }

        $cache = self::cache();
        $cacheKey = 'sspanel:ech:v1:' . hash('sha256', strtolower($hostname));
        $cached = null;
        try {
            $value = $cache?->get($cacheKey);
            $cached = is_string($value) ? json_decode($value, true) : null;
        } catch (Throwable) {
            // An optional cache outage must not prevent subscription generation.
        }
        $now = time();
        $result = self::fetch($hostname, is_array($cached) ? $cached : null, self::query(...), $now);
        if ($result !== null) {
            self::$resolved[$hostname] = $result['config'];
            if ($result['fetched_at'] === $now) {
                try {
                    $cache?->setex($cacheKey, self::STALE_SECONDS, json_encode($result));
                } catch (Throwable) {
                    // Keep the fresh result even if writing the cache fails.
                }
            }
        }

        return self::$resolved[$hostname];
    }

    private static function fetch(string $hostname, ?array $cached, callable $query, int $now): ?array
    {
        $age = $now - (int) ($cached['fetched_at'] ?? 0);
        $usable = $cached !== null && $age >= 0 && $age < self::STALE_SECONDS
            && self::isValid((string) ($cached['config'] ?? ''));
        if ($usable && $age < self::FRESH_SECONDS) {
            return $cached;
        }
        foreach (self::PROVIDERS as $provider) {
            $config = self::fromDNSResponse($query($provider, $hostname));
            if ($config !== '') {
                return ['config' => $config, 'fetched_at' => $now];
            }
        }

        return $usable ? $cached : null;
    }

    private static function query(string $provider, string $hostname): array
    {
        if (! function_exists('curl_init')) {
            return [];
        }
        $curl = curl_init($provider . '?'
            . http_build_query(['name' => $hostname, 'type' => 'HTTPS']));
        $body = '';
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['Accept: application/dns-json'],
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => 1200,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 65536) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $response = json_decode($body, true);
        return $success !== false && $status === 200 && is_array($response) ? $response : [];
    }

    private static function cache(): ?Redis
    {
        if (self::$cacheInitialized) {
            return self::$cache;
        }
        self::$cacheInitialized = true;
        if (! extension_loaded('redis') || empty($_ENV['redis_host'])) {
            return null;
        }
        try {
            $config = Cache::getRedisConfig();
            $redis = new Redis();
            $redis->connect($config['host'], (int) $config['port'], 0.3, null, 0, 0.3);
            if (! empty($config['auth']['user'])) {
                $redis->auth([$config['auth']['user'], $config['auth']['pass'] ?? '']);
            } elseif (! empty($config['auth']['pass'])) {
                $redis->auth($config['auth']['pass']);
            }
            self::$cache = $redis;
        } catch (Throwable) {
            self::$cache = null;
        }

        return self::$cache;
    }
}
