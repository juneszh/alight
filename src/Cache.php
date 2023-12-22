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

use ErrorException;
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
use Symfony\Component\Cache\Exception\InvalidArgumentException;

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

    private function __construct()
    {
    }

    private function __destruct()
    {
    }

    private function __clone()
    {
    }

    /**
     * Initializes the instance (psr16 alias)
     * 
     * @param string[] $keys 
     * @return Psr16Cache 
     * @throws Exception 
     * @throws InvalidArgumentException 
     * @throws ErrorException 
     */
    public static function init(string ...$keys): Psr16Cache
    {
        return self::psr16(...$keys);
    }

    /**
     * Initializes the psr16 instance
     * 
     * @param string[] $keys 
     * @return Psr16Cache 
     * @throws Exception 
     * @throws InvalidArgumentException 
     * @throws ErrorException 
     */
    public static function psr16(string ...$keys): Psr16Cache
    {
        $key = join('||', $keys);

        if (isset(self::$instance[$key][__FUNCTION__])) {
            $psr16Cache = self::$instance[$key][__FUNCTION__];
        } else {
            $psr16Cache = new Psr16Cache(self::psr6(...$keys));
            self::$instance[$key][__FUNCTION__] = $psr16Cache;
        }

        return $psr16Cache;
    }

    /**
     * Initializes the psr6 instance with tags
     * 
     * @param string $key 
     * @return TagAwareAdapter 
     * @throws Exception 
     * @throws InvalidArgumentException 
     * @throws ErrorException 
     */
    public static function psr6(string $key = '')
    {
        if (isset(self::$instance[$key][__FUNCTION__])) {
            $tagCache = self::$instance[$key][__FUNCTION__];
        } else {
            $config = self::getConfig($key);
            if ($config['type']) {
                if ($config['type'] === 'file') {
                    $directory = App::root(Config::get('app', 'storagePath') ?: 'storage') . '/cache';
                    $tagCache = new FilesystemTagAwareAdapter($config['namespace'], $config['defaultLifetime'], $directory);
                } elseif ($config['type'] === 'redis') {
                    $client = self::redis($key);
                    $tagCache = new RedisTagAwareAdapter($client, $config['namespace'], $config['defaultLifetime']);
                } elseif ($config['type'] === 'memcached') {
                    $client = self::memcached($key);
                    $tagCache = new TagAwareAdapter(new MemcachedAdapter($client, $config['namespace'], $config['defaultLifetime']));
                } else {
                    $customCacheAdapter = Config::get('app', 'cacheAdapter');
                    if (!is_callable($customCacheAdapter)) {
                        throw new Exception('Invalid cacheAdapter specified.');
                    }
                    $tagCache = new TagAwareAdapter(call_user_func($customCacheAdapter, $config));
                }
            } else {
                $tagCache = new TagAwareAdapter(new NullAdapter);
            }
            self::$instance[$key][__FUNCTION__] = $tagCache;
        }

        return $tagCache;
    }

    /**
     * Initializes the memcached client
     * 
     * @param string $key 
     * @return Memcached 
     * @throws Exception 
     * @throws ErrorException 
     */
    public static function memcached(string $key = ''): Memcached
    {
        if (isset(self::$instance[$key]['client'])) {
            $client = self::$instance[$key]['client'];
        } else {
            $config = self::getConfig($key);
            if ($config['type'] !== 'memcached') {
                throw new Exception('Incorrect type in cache configuration \'' . $key . '\'.');
            }
            $client = MemcachedAdapter::createConnection($config['dsn'], $config['options']);
            self::$instance[$key]['client'] = $client;
        }
        return $client;
    }

    /**
     * Initializes the redis client
     * 
     * @param string $key 
     * @return Redis 
     * @throws Exception 
     * @throws InvalidArgumentException 
     */
    public static function redis(string $key = ''): Redis
    {
        if (isset(self::$instance[$key]['client'])) {
            $client = self::$instance[$key]['client'];
        } else {
            $config = self::getConfig($key);
            if ($config['type'] !== 'redis') {
                throw new Exception('Incorrect type in cache configuration \'' . $key . '\'.');
            }
            $client = RedisAdapter::createConnection($config['dsn'], $config['options']);
            self::$instance[$key]['client'] = $client;
        }
        return $client;
    }

    /**
     * Get config values
     * 
     * @param string $key 
     * @return array 
     * @throws Exception 
     */
    private static function getConfig(string $key): array
    {
        $config = Config::get('cache');
        if (!$config || !is_array($config)) {
            throw new Exception('Missing cache configuration.');
        }

        if (isset($config['type']) && !is_array($config['type'])) {
            $configCache = $config;
        } else {
            if ($key) {
                if (!isset($config[$key]) || !is_array($config[$key])) {
                    throw new Exception('Missing cache configuration about \'' . $key . '\'.');
                }
            } else {
                $key = key($config);
                if (!is_array($config[$key])) {
                    throw new Exception('Missing cache configuration.');
                }
            }
            $configCache = $config[$key];
        }

        return array_replace_recursive(self::DEFAULT_CONFIG, $configCache);
    }
}
