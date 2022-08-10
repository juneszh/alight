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

class Route
{
    public static array $config = [];
    private static int $index = 0;
    private static string $group = '';
    private static $authHandler;
    private static $beforeHandler;
    private static array $anyMethods = [];
    public static bool $disableCache = false;

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
     * Initializes the variables
     */
    public static function init()
    {
        self::$config = [];
        self::$index = 0;
        self::$group = '';
        self::$authHandler = null;
        self::$beforeHandler = null;
        self::$anyMethods = [];
        self::$disableCache = false;
    }

    /**
     * Add route rule
     * 
     * @param array $method 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    private static function addRoute(array $method, string $pattern, callable $handler): RouteUtility
    {
        ++self::$index;
        $pattern = (self::$group ? '/' . self::$group : '') . '/' . trim($pattern, '/');

        $config = [
            'method' => $method,
            'pattern' => rtrim($pattern, '/'),
            'handler' => $handler,
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
     * Add 'GET' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function get(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['GET'], $pattern, $handler);
    }

    /**
     * Add 'HEAD' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function head(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['HEAD'], $pattern, $handler);
    }

    /**
     * Add 'POST' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function post(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['POST'], $pattern, $handler);
    }

    /**
     * Add 'DELETE' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function delete(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['DELETE'], $pattern, $handler);
    }

    /**
     * Add 'PUT' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function put(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['PUT'], $pattern, $handler);
    }

    /**
     * Add 'OPTIONS' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function options(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['OPTIONS'], $pattern, $handler);
    }

    /**
     * Add 'TRACE' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function trace(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['TRACE'], $pattern, $handler);
    }

    /**
     * Add 'PATCH' method rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function patch(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(['PATCH'], $pattern, $handler);
    }

    /**
     * Map some methods rule
     * 
     * @param array $method 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function map(array $method, string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute($method, $pattern, $handler);
    }

    /**
     * Add all methods rule
     * 
     * @param string $pattern 
     * @param callable $handler 
     * @return RouteUtility 
     */
    public static function any(string $pattern, callable $handler): RouteUtility
    {
        return self::addRoute(self::$anyMethods ?: Router::HTTP_METHODS, $pattern, $handler);
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
    public static function beforeHandler(callable $handler, array $args = [])
    {
        self::$beforeHandler = [$handler, $args];
    }

    /**
     * Set the global authorization handler
     * 
     * @param callable $handler 
     * @param array $args 
     */
    public static function authHandler(callable $handler, array $args = [])
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
