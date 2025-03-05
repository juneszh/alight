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

class Route
{
    public static array $config = [];
    private static int $index = 0;
    private static string $group = '';
    private static array $anyMethods = [];
    private static $authHandler;
    private static $beforeHandler;
    public static bool $disableCache = false;

    private function __construct() {}

    private function __destruct() {}

    private function __clone() {}


    /** 
     * Initializes the variables
     */
    public static function init()
    {
        self::$config = [];
        self::$index = 0;
        self::$group = '';
        self::$anyMethods = [];
        self::$authHandler = null;
        self::$beforeHandler = null;
        self::$disableCache = false;
    }

    /**
     * Add route
     * 
     * @param array $methods 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args 
     * @return RouteUtility 
     */
    private static function addRoute(array $methods, string $pattern, $handler, array $args): RouteUtility
    {
        ++self::$index;
        $pattern = (self::$group ? '/' . self::$group : '') . '/' . trim($pattern, '/');

        $config = [
            'methods' => $methods,
            'pattern' => rtrim($pattern, '/'),
            'handler' => $handler,
            'args' => $args,
        ];

        if (self::$authHandler) {
            $config['authHandler'] = self::$authHandler;
        }

        if (self::$beforeHandler) {
            $config['beforeHandler'] = self::$beforeHandler;
        }

        self::$config[self::$index] = $config;

        return new RouteUtility(self::$index);
    }

    /**
     * Add 'OPTIONS' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function options(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['OPTIONS'], $pattern, $handler, $args);
    }

    /**
     * Add 'HEAD' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function head(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['HEAD'], $pattern, $handler, $args);
    }

    /**
     * Add 'GET' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function get(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['GET'], $pattern, $handler, $args);
    }

    /**
     * Add 'POST' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function post(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['POST'], $pattern, $handler, $args);
    }

    /**
     * Add 'DELETE' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function delete(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['DELETE'], $pattern, $handler, $args);
    }

    /**
     * Add 'PUT' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function put(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['PUT'], $pattern, $handler, $args);
    }

    /**
     * Add 'PATCH' method route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function patch(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['PATCH'], $pattern, $handler, $args);
    }

    /**
     * Map some methods route
     * 
     * @param array $methods 
     * @param string $pattern 
     * @param callable $handler 
     * @param array $args
     * @return RouteUtility 
     */
    public static function map(array $methods, string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute($methods, $pattern, $handler, $args);
    }

    /**
     * Add all methods route
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function any(string $pattern, $handler, array $args = []): RouteUtility
    {
        return self::addRoute(self::$anyMethods ?: Request::ALLOW_METHODS, $pattern, $handler, $args);
    }

    /**
     * Specifies the methods contained in 'any'
     * 
     * @param array $methods 
     */
    public static function setAnyMethods(array $methods = [])
    {
        self::$anyMethods = $methods;
    }

    /**
     * Set a common prefix path
     * 
     * @param string $pattern 
     */
    public static function group(string $pattern)
    {
        self::$group = trim($pattern, '/');
    }

    /**
     * Call a handler before route handler be called
     * 
     * @param callable $handler 
     * @param array $args 
     */
    public static function beforeHandler($handler, array $args = [])
    {
        self::$beforeHandler = [$handler, $args];
    }

    /**
     * Set the global authorization handler
     * 
     * @param callable $handler 
     * @param array $args 
     */
    public static function authHandler($handler, array $args = [])
    {
        self::$authHandler = [$handler, $args];
    }

    /** 
     * Disable route cache
     */
    public static function disableCache()
    {
        self::$disableCache = true;
    }
}
