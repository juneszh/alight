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

    private static ?array $authHandler = null;

    private static array $beforeHandlers = [];

    private static array $afterHandlers = [];

    public static bool $disableCache = false;

    private function __construct() {}

    private function __clone() {}


    /** 
     * Initializes the variables
     */
    public static function init(): void
    {
        self::$config = [];
        self::$index = 0;
        self::$group = '';
        self::$anyMethods = [];
        self::$authHandler = null;
        self::$beforeHandlers = [];
        self::$afterHandlers = [];
        self::$disableCache = false;
    }

    /**
     * Add route
     *
     * @param callable|string $handler
     */
    private static function addRoute(array $methods, string $pattern, mixed $handler, array $args): RouteUtility
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

        if (self::$beforeHandlers) {
            $config['beforeGlobal'] = self::$beforeHandlers;
        }

        if (self::$afterHandlers) {
            $config['afterGlobal'] = self::$afterHandlers;
        }

        self::$config[self::$index] = $config;

        return new RouteUtility(self::$index);
    }

    /**
     * Add 'OPTIONS' method route
     *
     * @param callable|string $handler
     */
    public static function options(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['OPTIONS'], $pattern, $handler, $args);
    }

    /**
     * Add 'HEAD' method route
     *
     * @param callable|string $handler
     */
    public static function head(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['HEAD'], $pattern, $handler, $args);
    }

    /**
     * Add 'GET' method route
     *
     * @param callable|string $handler
     */
    public static function get(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['GET'], $pattern, $handler, $args);
    }

    /**
     * Add 'POST' method route
     *
     * @param callable|string $handler
     */
    public static function post(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['POST'], $pattern, $handler, $args);
    }

    /**
     * Add 'DELETE' method route
     *
     * @param callable|string $handler
     */
    public static function delete(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['DELETE'], $pattern, $handler, $args);
    }

    /**
     * Add 'PUT' method route
     *
     * @param callable|string $handler
     */
    public static function put(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['PUT'], $pattern, $handler, $args);
    }

    /**
     * Add 'PATCH' method route
     *
     * @param callable|string $handler
     */
    public static function patch(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(['PATCH'], $pattern, $handler, $args);
    }

    /**
     * Map some methods route
     *
     * @param callable|string $handler
     */
    public static function map(array $methods, string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute($methods, $pattern, $handler, $args);
    }

    /**
     * Add all methods route
     *
     * @param callable|string $handler
     */
    public static function any(string $pattern, mixed $handler, array $args = []): RouteUtility
    {
        return self::addRoute(self::$anyMethods ?: Request::ALLOW_METHODS, $pattern, $handler, $args);
    }

    /**
     * Specifies the methods contained in 'any'
     */
    public static function setAnyMethods(array $methods = []): void
    {
        self::$anyMethods = $methods;
    }

    /**
     * Set a common prefix path
     */
    public static function group(string $pattern): void
    {
        self::$group = trim($pattern, '/');
    }

    /**
     * Call a handler before route handler be called
     *
     * @param callable|string $handler
     */
    public static function beforeHandler(mixed $handler, array $args = []): void
    {
        self::$beforeHandlers[] = [$handler, $args];
    }

    /**
     * Call a handler after route handler be called
     *
     * @param callable|string $handler
     */
    public static function afterHandler(mixed $handler, array $args = []): void
    {
        self::$afterHandlers[] = [$handler, $args];
    }

    /**
     * Set the global authorization handler
     *
     * @param callable|string $handler
     */
    public static function authHandler(mixed $handler, array $args = []): void
    {
        self::$authHandler = [$handler, $args];
    }

    /** 
     * Disable route cache
     */
    public static function disableCache(): void
    {
        self::$disableCache = true;
    }
}
