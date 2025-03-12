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

class Config
{
    public const FILE = 'config/app.php';
    private static array $config = [];
    private static array $default = [
        'app' => [
            'debug' => false, // Whether to enable error message output
            'timezone' => null, // Default timezone follows php.ini
            'storagePath' => 'storage', // The storage path of the files generated at runtime by framework
            'domainLevel' => 2, // Get subdomains for route. For example, set 3 to match 'a' when the domain is like 'a.b.co.jp'
            'corsDomain' => null, // Set a default domain array for CORS, or follow 'origin' header when set 'origin'
            'corsHeaders' => null, // Set a default header array for CORS
            'corsMethods' => null, // Set a default header array for CORS
            'cacheAdapter' => null, // Extended cache adapter based on symfony/cache
            'errorHandler' => null, // Override error handler
            'errorPageHandler' => null, // Override error page handler
        ],

        /* 
        'route' => 'config/route.php',
        'route' => ['config/route/www.php', 'config/route/api.php'],
        'route' => [
            '*' => 'config/route/www.php',
            'api' => ['config/route/api.php', 'config/route/api2.php'],
        ],
         */
        'route' => null,

        /* 
        'database' => [
            'type' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'alight',
            'username' => '',
            'password' => '',
        ],
        'database' => [
            'main' => [ 
                'type' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'alight',
                'username' => '',
                'password' => '',
            ],
            'remote' => [ 
                'type' => 'mysql',
                'host' => '1.1.1.1',
                'database' => 'alight_remote',
                'username' => '',
                'password' => '',
            ],
        ], 
         */
        'database' => [], //More options see https://medoo.in/api/new

        /* 
        'cache' => [
            'file' => [ 
                'type' => 'file',
            ],
            'memcached' => [ 
                'type' => 'memcached',
                'dsn' => 'memcached://localhost',
            ],
            'redis' => [ 
                'type' => 'redis',
                'dsn' => 'redis://localhost',
            ],
        ], 
         */
        'cache' => [],

        /* 
        'job' => 'config/job.php',
         */
        'job' => null
    ];

    /**
     * Merge default configuration and user configuration
     * 
     * @return array 
     */
    private static function init()
    {
        $configFile = App::root(self::FILE);
        if (!file_exists($configFile)) {
            throw new Exception('Missing configuration file: ' . self::FILE);
        }

        $userConfig = require $configFile;

        return array_replace_recursive(self::$default, $userConfig);
    }

    /**
     * Get config values
     * 
     * @param string[] $keys 
     * @return mixed 
     */
    public static function get(string ...$keys)
    {
        if (!self::$config) {
            self::$config = self::init();
        }

        $value = self::$config;
        if ($keys) {
            foreach ($keys as $key) {
                if (isset($value[$key])) {
                    $value = $value[$key];
                } else {
                    $value = null;
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * Set config values
     * 
     * @param string $class 
     * @param null|string $option 
     * @param mixed $value 
     */
    public static function set(string $class, ?string $option, $value)
    {
        if (!self::$config) {
            self::$config = self::init();
        }

        if ($option) {
            self::$config[$class][$option] = $value;
        } else {
            self::$config[$class] = $value;
        }
    }
}
