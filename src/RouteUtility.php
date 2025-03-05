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
     * Enable authorization verification
     * 
     * @return RouteUtility 
     */
    public function auth(): RouteUtility
    {
        Route::$config[$this->index][__FUNCTION__] = true;
        return $this;
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
        Route::$config[$this->index][__FUNCTION__] = $maxAge;
        Route::$config[$this->index][__FUNCTION__ . 'S'] = $sMaxAge;
        Route::$config[$this->index][__FUNCTION__ . 'Options'] = $options;
        return $this;
    }

    /**
     * Set CORS header for current method and 'OPTIONS'
     * 
     * @param null|string|array $allowOrigin default|origin|*|{custom_origin}|[custom_origin1, custom_origin2] 
     * @param null|array $allowHeaders 
     * @param null|array $allowMethods 
     * @return RouteUtility 
     */
    public function cors($allowOrigin = 'default', ?array $allowHeaders = null, ?array $allowMethods = null): RouteUtility
    {
        Route::$config[$this->index][__FUNCTION__] = [$allowOrigin, $allowHeaders, $allowMethods];
        Route::options(Route::$config[$this->index]['pattern'], [Response::class, 'cors'], ['allowOrigin' => $allowOrigin, 'allowHeaders' => $allowHeaders, 'allowMethods' => $allowMethods]);
        return $this;
    }

    /**
     * Set the interval between 2 requests for each user (authorization required)
     * 
     * @param int $second 
     * @return RouteUtility 
     */
    public function debounce(int $second): RouteUtility
    {
        Route::$config[$this->index][__FUNCTION__] = $second;
        return $this;
    }

    /**
     * Compress/minify the HTML
     * 
     * @return RouteUtility 
     */
    public function minify(): RouteUtility
    {
        Route::$config[$this->index][__FUNCTION__] = true;
        return $this;
    }
}
