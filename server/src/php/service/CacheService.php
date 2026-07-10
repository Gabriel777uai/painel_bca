<?php

namespace Service;

use Predis\Client as RedisClient;
use Exception;

class CacheService
{
    private ?RedisClient $redis = null;
    private bool $isEnabled = false;

    public function __construct()
    {
        try {
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = $_ENV['REDIS_PORT'] ?? 6379;
            $password = $_ENV['REDIS_PASSWORD'] ?? null;

            $options = [
                'scheme'   => 'tcp',
                'host'     => $host,
                'port'     => $port,
                'timeout'  => 0.5, // Fail fast (0.5s) if Redis is down
            ];

            if (!empty($password)) {
                $options['password'] = $password;
            }

            $this->redis = new RedisClient($options);
            // Test connection using ping
            $this->redis->ping();
            $this->isEnabled = true;
        } catch (Exception $e) {
            // Fail silently to bypass Redis caching and fallback to database queries
            $this->isEnabled = false;
            $this->redis = null;
        }
    }

    /**
     * Gets a value from cache
     */
    public function get(string $key)
    {
        if (!$this->isEnabled || !$this->redis) {
            return null;
        }
        try {
            $val = $this->redis->get($key);
            return $val !== null ? unserialize($val) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Sets a value in cache with a TTL (default 300 seconds / 5 minutes)
     */
    public function set(string $key, $value, int $ttl = 300): void
    {
        if (!$this->isEnabled || !$this->redis) {
            return;
        }
        try {
            $serialized = serialize($value);
            $this->redis->setex($key, $ttl, $serialized);
        } catch (Exception $e) {
            // Fail silently
        }
    }

    /**
     * Deletes a key from cache
     */
    public function delete(string $key): void
    {
        if (!$this->isEnabled || !$this->redis) {
            return;
        }
        try {
            $this->redis->del($key);
        } catch (Exception $e) {
            // Fail silently
        }
    }
}
