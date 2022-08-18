<?php

declare(strict_types=1);

/*
 * This file is part of the Alight package.
 *
 * (c) June So <alight@juneszh.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alight;

use ErrorException;
use Exception;
use Memcached;
use Redis;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
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
     * @param string $key 
     * @return Psr16Cache 
     * @throws Exception 
     * @throws ErrorException 
     * @throws InvalidArgumentException 
     * @throws InvalidArgumentException 
     */
    public static function init(string $key = ''): Psr16Cache
    {
        return self::psr16($key);
    }

    /**
     * Initializes the psr16 instance
     * 
     * @param string $key 
     * @return Psr16Cache 
     * @throws Exception 
     * @throws ErrorException 
     * @throws InvalidArgumentException 
     * @throws InvalidArgumentException 
     */
    public static function psr16(string $key = ''): Psr16Cache
    {
        if (isset(self::$instance[$key][__FUNCTION__])) {
            $psr16Cache = self::$instance[$key][__FUNCTION__];
        } else {
            $psr6Cache = self::psr6($key);
            $psr16Cache = new Psr16Cache($psr6Cache);
            self::$instance[$key][__FUNCTION__] = $psr16Cache;
        }

        return $psr16Cache;
    }

    /**
     * Initializes the psr6 instance
     * 
     * @param string $key 
     * @return AbstractAdapter 
     * @throws Exception 
     * @throws ErrorException 
     * @throws InvalidArgumentException 
     * @throws InvalidArgumentException 
     */
    public static function psr6(string $key = ''): AbstractAdapter
    {
        if (isset(self::$instance[$key][__FUNCTION__])) {
            $psr6Cache = self::$instance[$key][__FUNCTION__];
        } else {
            $config = self::getConfig($key);
            switch ($config['type']) {
                case 'file':
                    $directory = App::rootPath(Config::get('app', 'storagePath') ?: 'storage') . '/cache';
                    $psr6Cache = new FilesystemAdapter($config['namespace'], $config['defaultLifetime'], $directory);
                    break;
                case 'memcached':
                    $client = self::memcached($key);
                    $psr6Cache = new MemcachedAdapter($client, $config['namespace'], $config['defaultLifetime']);
                    break;
                case 'redis':
                    $client = self::redis($key);
                    $psr6Cache = new RedisAdapter($client, $config['namespace'], $config['defaultLifetime']);
                    break;
                default:
                    $customCacheAdapter = Config::get('app', 'cacheAdapter');
                    if ($customCacheAdapter === null) {
                        $psr6Cache = new NullAdapter;
                    } else {
                        if (!is_callable($customCacheAdapter)) {
                            throw new Exception('Invalid cacheAdapter specified.');
                        }
                        $psr6Cache = call_user_func($customCacheAdapter, $config);
                    }
                    break;
            }
            self::$instance[$key][__FUNCTION__] = $psr6Cache;
        }

        return $psr6Cache;
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
