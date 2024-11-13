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
     * @param string $configKey 
     * @return Medoo 
     */
    public static function init(string $configKey = ''): Medoo
    {
        if (!isset(self::$instance[$configKey])) {
            $config = self::getConfig($configKey);

            if (!isset($config['error'])) {
                $config['error'] = PDO::ERRMODE_EXCEPTION;
            }

            if ($config['type'] === 'mysql' && version_compare(PHP_VERSION, '8.1.0', '<')) {
                $config['option'][PDO::ATTR_EMULATE_PREPARES] = false;
                $config['option'][PDO::ATTR_STRINGIFY_FETCHES] = false;
            }

            self::$instance[$configKey] = new Medoo($config);
        }

        return self::$instance[$configKey];
    }

    /**
     * Get config values
     * 
     * @param string $configKey 
     * @return array 
     */
    private static function getConfig(string $configKey): array
    {
        $config = Config::get('database');
        if (!$config || !is_array($config)) {
            throw new Exception('Missing database configuration.');
        }

        if (isset($config['type']) && !is_array($config['type'])) {
            $configDatabase = $config;
        } else {
            if ($configKey) {
                if (!isset($config[$configKey]) || !is_array($config[$configKey])) {
                    throw new Exception('Missing database configuration about \'' . $configKey . '\'.');
                }
            } else {
                $configKey = key($config);
                if (!is_array($config[$configKey])) {
                    throw new Exception('Missing database configuration.');
                }
            }
            $configDatabase = $config[$configKey];
        }

        return $configDatabase;
    }
}
