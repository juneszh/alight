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

class RouteUtility
{
    private int $index;

    public function __construct(int $index)
    {
        $this->index = $index;
        return $this;
    }

    /**
     * 
     * @param callable $handler 
     * @param array $args 
     * @return RouteUtility 
     */
    public function before($handler, array $args = []): RouteUtility
    {
        Route::$config[$this->index][__FUNCTION__][] = [$handler, $args];
        return $this;
    }

    /**
     * 
     * @param callable $handler 
     * @param array $args 
     * @return RouteUtility 
     */
    public function after($handler, array $args = []): RouteUtility
    {
        Route::$config[$this->index][__FUNCTION__][] = [$handler, $args];
        return $this;
    }

    /**
     * Enable authorization verification
     * 
     * @param int $debounce set the interval seconds between 2 requests for each user
     * @return RouteUtility 
     */
    public function auth(int $debounce = 0): RouteUtility
    {
        return $this->before([RouteMiddleware::class, __FUNCTION__], [$debounce]);
    }

    /**
     * Send a Cache-Control header
     * 
     * @param int $maxAge
     * @param ?int $sMaxAge
     * @param array $options 
     * @return RouteUtility 
     */
    public function cache(int $maxAge, ?int $sMaxAge = null, array $options = []): RouteUtility
    {
        return $this->before([RouteMiddleware::class, __FUNCTION__], [$maxAge, $sMaxAge, $options]);
    }

    /**
     * Set CORS header for current method and 'OPTIONS'
     * 
     * @param null|string|array $allowOrigin origin|*|{custom_origin}|[custom_origin1, custom_origin2] 
     * @param null|array $allowHeaders 
     * @param null|array $allowMethods 
     * @return RouteUtility 
     */
    public function cors($allowOrigin, ?array $allowHeaders = null, ?array $allowMethods = null): RouteUtility
    {
        Route::options(Route::$config[$this->index]['pattern'], [Response::class, 'cors'], [$allowOrigin, $allowHeaders, $allowMethods]);
        return $this->before([RouteMiddleware::class, __FUNCTION__], [$allowOrigin, $allowHeaders, $allowMethods]);
    }

    /**
     * Compress/minify the HTML
     * 
     * @return RouteUtility 
     */
    public function minify(): RouteUtility
    {
        return $this->after([RouteMiddleware::class, __FUNCTION__]);
    }
}
