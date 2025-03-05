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

class App
{
    /**
     * Starts the framework
     */
    public static function start()
    {
        $timezone = Config::get('app', 'timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }

        ErrorHandler::start();

        Router::start();
    }

    /**
     * Get a path relative to project's root
     * 
     * @param string $path Relative to file system's root when first character is '/'
     * @return string 
     */
    public static function root(string $path = ''): ?string
    {
        if ($path === null) {
            return null;
        } elseif (($path[0] ?? '') === '/') {
            return rtrim($path, '/');
        } else {
            static $rootPath = null;
            if ($rootPath === null) {
                $rootPath = dirname(__DIR__, 4);
            }
            return $rootPath . '/' . rtrim($path, '/');
        }
    }
}
