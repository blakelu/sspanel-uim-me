<?php

declare(strict_types=1);

namespace App\Services;

use Redis;

final class Cache
{
    public function initRedis(): Redis
    {
        $config = self::getRedisConfig();
        $redis = new Redis();

        // 连接 Redis
        $redis->connect($config['host'], (int)$config['port'], (float)$config['connectTimeout'], null, 0, (float)$config['readTimeout']);

        // 如果有用户名和密码
        if (!empty($config['auth']['user']) || !empty($config['auth']['pass'])) {
            // Redis AUTH 支持 "username password"（Redis 6+）
            if (!empty($config['auth']['user'])) {
                $redis->auth([$config['auth']['user'], $config['auth']['pass'] ?? '']);
            } else {
                $redis->auth($config['auth']['pass']);
            }
        }

        // SSL 支持（可选，根据你的配置）
        if (!empty($config['ssl'])) {
            // 如果启用了 SSL，可以在 connect 时加上 stream context
            // 具体写法要根据 redis_ssl_context 配置来调整
        }

        return $redis;
    }

    public static function getRedisConfig(): array
    {
        $config = [
            'host' => $_ENV['redis_host'],
            'port' => $_ENV['redis_port'],
            'connectTimeout' => $_ENV['redis_connect_timeout'],
            'readTimeout' => $_ENV['redis_read_timeout'],
        ];

        if ($_ENV['redis_username'] !== '') {
            $config['auth']['user'] = $_ENV['redis_username'];
        }

        if ($_ENV['redis_password'] !== '') {
            $config['auth']['pass'] = $_ENV['redis_password'];
        }

        if ($_ENV['redis_ssl']) {
            $config['ssl'] = $_ENV['redis_ssl_context'];
        }

        return $config;
    }
}
