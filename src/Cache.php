<?php

declare(strict_types=1);

/*
 * This file is part of the Alight package.
 *
 * (c) June So <june@alight.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alight;

use Exception;
use Memcached;
use Redis;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Psr16Cache;

class Cache
{
    public static array $instance = [];
    private const DEFAULT_CONFIG = [
        'type' => '',
        'dsn' => '',
        'options' => [],
        'namespace' => '',
        'defaultLifetime' => 0,
    ];

    private function __construct() {}

    private function __destruct() {}

    private function __clone() {}

    /**
     * Initializes the instance (psr16 alias)
     * 
     * @param string $configKey 
     * @return Psr16Cache 
     */
    public static function init(string $configKey = ''): Psr16Cache
    {
        return self::psr16($configKey);
    }

    /**
     * Initializes the psr16 instance
     * 
     * @param string $configKey 
     * @return Psr16Cache 
     */
    public static function psr16(string $configKey = ''): Psr16Cache
    {
        if (isset(self::$instance[$configKey][__FUNCTION__])) {
            $psr16Cache = self::$instance[$configKey][__FUNCTION__];
        } else {
            $psr16Cache = new Psr16Cache(self::psr6($configKey));
            self::$instance[$configKey][__FUNCTION__] = $psr16Cache;
        }

        return $psr16Cache;
    }

    /**
     * Initializes the psr6 instance with tags
     * 
     * @param string $configKey 
     * @return TagAwareAdapter 
     */
    public static function psr6(string $configKey = '')
    {
        if (isset(self::$instance[$configKey][__FUNCTION__])) {
            $psr6Cache = self::$instance[$configKey][__FUNCTION__];
        } else {
            $config = self::getConfig($configKey);
            if ($config['type']) {
                if ($config['type'] === 'file') {
                    $directory = App::root(Config::get('app', 'storagePath') ?: 'storage') . '/cache';
                    $psr6Cache = new FilesystemTagAwareAdapter($config['namespace'], $config['defaultLifetime'], $directory);
                } elseif ($config['type'] === 'redis') {
                    $client = self::redis($configKey);
                    $psr6Cache = new RedisTagAwareAdapter($client, $config['namespace'], $config['defaultLifetime']);
                } elseif ($config['type'] === 'memcached') {
                    $client = self::memcached($configKey);
                    $psr6Cache = new TagAwareAdapter(new MemcachedAdapter($client, $config['namespace'], $config['defaultLifetime']));
                } else {
                    $customCacheAdapter = Config::get('app', 'cacheAdapter');
                    if (!is_callable($customCacheAdapter)) {
                        throw new Exception('Invalid cacheAdapter specified.');
                    }
                    $psr6Cache = new TagAwareAdapter(call_user_func($customCacheAdapter, $config));
                }
            } else {
                $psr6Cache = new TagAwareAdapter(new NullAdapter);
            }
            self::$instance[$configKey][__FUNCTION__] = $psr6Cache;
        }

        return $psr6Cache;
    }

    /**
     * Initializes the memcached client
     * 
     * @param string $configKey 
     * @return Memcached 
     */
    public static function memcached(string $configKey = 'memcached'): Memcached
    {
        if (isset(self::$instance[$configKey]['client'])) {
            $client = self::$instance[$configKey]['client'];
        } else {
            $config = self::getConfig($configKey);
            if ($config['type'] !== 'memcached') {
                throw new Exception('Incorrect type in cache configuration \'' . $configKey . '\'.');
            }
            $client = MemcachedAdapter::createConnection($config['dsn'], $config['options']);
            self::$instance[$configKey]['client'] = $client;
        }
        return $client;
    }

    /**
     * Initializes the redis client
     * 
     * @param string $configKey 
     * @return Redis 
     */
    public static function redis(string $configKey = 'redis'): Redis
    {
        if (isset(self::$instance[$configKey]['client'])) {
            $client = self::$instance[$configKey]['client'];
        } else {
            $config = self::getConfig($configKey);
            if ($config['type'] !== 'redis') {
                throw new Exception('Incorrect type in cache configuration \'' . $configKey . '\'.');
            }
            $client = RedisAdapter::createConnection($config['dsn'], $config['options']);
            self::$instance[$configKey]['client'] = $client;
        }
        return $client;
    }

    /**
     * Get config values
     * 
     * @param string $configKey 
     * @return array 
     */
    private static function getConfig(string $configKey): array
    {
        $config = Config::get('cache');
        if (!$config || !is_array($config)) {
            throw new Exception('Missing cache configuration.');
        }

        if (isset($config['type']) && !is_array($config['type'])) {
            $configCache = $config;
        } else {
            if ($configKey) {
                if (!isset($config[$configKey]) || !is_array($config[$configKey])) {
                    throw new Exception('Missing cache configuration about \'' . $configKey . '\'.');
                }
            } else {
                $configKey = key($config);
                if (!is_array($config[$configKey])) {
                    throw new Exception('Missing cache configuration.');
                }
            }
            $configCache = $config[$configKey];
        }

        return array_replace_recursive(self::DEFAULT_CONFIG, $configCache);
    }

    /**
     * Pruning expired cache items
     * 
     * @param array $types 
     * @see https://symfony.com/doc/current/components/cache/cache_pools.html#component-cache-cache-pool-prune
     */
    public static function prune(array $types = ['file'])
    {
        $config = Config::get('cache');
        if ($config && is_array($config)) {
            if (isset($config['type'])) {
                $config = ['' => $config];
            }
            foreach ($config as $_key => $_config) {
                if (in_array($_config['type'] ?? '', $types)) {
                    self::psr6($_key)->prune();
                }
            }
        }
    }
}
