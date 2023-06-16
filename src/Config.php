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

class Config
{
    private static array $default = [
        'app' => [
            'debug' => false, // Whether to enable error message output
            'timezone' => null, // Default timezone follows php.ini
            'storagePath' => 'storage', // The storage path of the files generated at runtime by framework
            'domainLevel' => 2, // Get subdomains for route. For example, set 3 to match 'a' when the domain is like 'a.b.co.jp'
            'corsDomain' => [], // Which domains need to send cors headers
            'cacheAdapter' => null, // Extended cache adapter based on symfony/cache
            'errorHandler' => null, // Override error handler
            'errorPageHandler' => null, // Override error page handler
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
     */
    private static function init()
    {
        $configFile = App::root('config/app.php');
        if (!file_exists($configFile)) {
            throw new Exception('Missing configuration file: config/app.php');
        }

        $userConfig = require $configFile;

        return array_replace_recursive(self::$default, $userConfig);
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
        static $config = null;
        if ($config === null) {
            $config = self::init();
        }
        return $option !== null ? ($config[$class][$option] ?? null) : ($config[$class] ?? null);
    }
}
