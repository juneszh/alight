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

class Config
{
    public static string $configFile = '';
    /**
     * Default configuration
     * 
     * @var array
     */
    public static array $config = [
        'app' => [
            'debug' => false, //Whether to enable error message output
            'timezone' => null, //Default timezone follows php.ini
            'storagePath' => 'storage', //The storage path of the files generated at runtime by framework
            'viewPath' => '', //The 'view' files path
            'domainLevel' => 2, //Get subdomains for route. For example, set 3 to match 'a' when the domain is like 'a.b.co.jp'
            'corsDomain' => [], //Which domains need to send cors headers
            'errorHandler' => null, //Override error handler
            'cacheAdapter' => null, //Extended cache adapter based on symfony/cache
        ],
        'route' => null,
        // 'route' => 'config/route.php',
        // 'route' => ['config/route/www.php', 'config/route/api.php'],
        // 'route' => [
        //     '*' => 'config/route/www.php',
        //     'api' => ['config/route/api.php', 'config/route/api2.php'],
        // ],
        'database' => [], //More options see https://medoo.in/api/new
        // 'database' => [
        //     'type' => 'mysql',
        //     'host' => '127.0.0.1',
        //     'database' => 'alight',
        //     'username' => '',
        //     'password' => '',
        // ],
        // 'database' => [
        //     'main' => [ 
        //         'type' => 'mysql',
        //         'host' => '127.0.0.1',
        //         'database' => 'alight',
        //         'username' => '',
        //         'password' => '',
        //     ],
        //     'remote' => [ 
        //         'type' => 'mysql',
        //         'host' => '1.1.1.1',
        //         'database' => 'alight_remote',
        //         'username' => '',
        //         'password' => '',
        //     ],
        // ],
        'cache' => [
            'type' => 'file',
        ],
        // 'cache' => [
        //     'file' => [ 
        //         'type' => 'file',
        //     ],
        //     'memcached' => [ 
        //         'type' => 'memcached',
        //         'dsn' => 'memcached://localhost',
        //     ],
        //     'redis' => [ 
        //         'type' => 'redis',
        //         'dsn' => 'redis://localhost',
        //     ],
        // ],
        'job' => null
        // 'job' => 'config/job.php',
        // 'job' => ['config/job/hourly.php', 'config/job/daily.php'],
    ];

    /**
     * Merge default configuration and user configuration
     * 
     * @param mixed $config 
     */
    public static function init($config)
    {
        if ($config) {
            if (is_string($config)) {
                $configFile = App::rootPath($config);
                if (file_exists($configFile)) {
                    $config = require $configFile;
                    self::$configFile = $configFile;
                }
            }

            if (is_array($config)) {
                self::$config = array_replace_recursive(self::$config, $config);
            }
        }
    }

    /**
     * Get config values
     * 
     * @param string $class
     * @param null|string $option
     * @return mixed
     */
    public static function get(string $class, ?string $option = null)
    {
        return $option !== null ? (self::$config[$class][$option] ?? null) : (self::$config[$class] ?? null);
    }
}
