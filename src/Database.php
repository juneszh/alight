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

use Exception;
use Medoo\Medoo;
use PDO;

class Database
{
    public static array $instance = [];

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
     * Initializes the instance
     * 
     * @param null|string $key 
     * @return Medoo 
     * @throws Exception 
     */
    public static function init(string $key = ''): Medoo
    {
        $key = (string) $key;
        if (!isset(self::$instance[$key])) {
            $config = self::getConfig($key);

            if (!isset($config['error'])) {
                $config['error'] = PDO::ERRMODE_EXCEPTION;
            }

            self::$instance[$key] = new Medoo($config);
        }

        return self::$instance[$key];
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
        $config = Config::get('database');
        if (!$config || !is_array($config)) {
            throw new Exception('Missing database configuration.');
        }

        if (isset($config['type']) && is_string($config['type'])) {
            $configDatabase = $config;
        } else {
            if ($key) {
                if (!isset($config[$key]) || !is_array($config[$key])) {
                    throw new Exception('Missing database configuration about \'' . $key . '\'.');
                }
            } else {
                $key = key($config);
                if (!is_array($config[$key])) {
                    throw new Exception('Missing database configuration.');
                }
            }
            $configDatabase = $config[$key];
        }

        return $configDatabase;
    }
}
