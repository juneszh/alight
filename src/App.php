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

use Composer\Autoload\ClassLoader;
use ErrorException;
use Exception;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Cache\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;

class App
{
    /**
     * Starts the framework
     * 
     * @param mixed $config 
     * @throws InvalidArgumentException 
     * @throws Exception 
     * @throws LogicException 
     * @throws RuntimeException 
     * @throws ErrorException 
     * @throws ExceptionInvalidArgumentException 
     * @throws ExceptionInvalidArgumentException 
     */
    public static function start($config)
    {
        Config::init($config);

        $timezone = Config::get('app', 'timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }

        ErrorHandler::init();

        Request::init();

        Response::cors(false);

        Router::start();
    }

    /**
     * Get a path relative to project's root
     * 
     * @param string $path Relative to file system's root when first character is '/'
     * @return string 
     */
    public static function rootPath(string $path = ''): string
    {
        if ($path === null) {
            return null;
        } elseif (($path[0] ?? '') === '/') {
            return rtrim($path, '/');
        } else {
            static $rootPath = null;
            if ($rootPath === null) {
                $rootPath = dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 3);
            }
            return $rootPath . '/' . rtrim($path, '/');
        }
    }
}
