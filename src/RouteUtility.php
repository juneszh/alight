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
    public function __construct(private readonly int $index)
    {
    }

    /**
     *
     * @param callable|string $handler
     */
    public function before(mixed $handler, array $args = []): static
    {
        Route::$config[$this->index][__FUNCTION__][] = [$handler, $args];
        return $this;
    }

    /**
     *
     * @param callable|string $handler
     */
    public function after(mixed $handler, array $args = []): static
    {
        Route::$config[$this->index][__FUNCTION__][] = [$handler, $args];
        return $this;
    }

    /**
     * Enable authorization verification
     *
     * @param int $debounce set the interval seconds between 2 requests for each user
     */
    public function auth(int $debounce = 0): static
    {
        return $this->before([Response::class, 'auth'], [$debounce]);
    }

    /**
     * Send a Cache-Control header
     */
    public function cache(int $maxAge, ?int $sMaxAge = null, array $options = []): static
    {
        return $this->before([Response::class, 'cache'], [$maxAge, $sMaxAge, $options]);
    }

    /**
     * Set CORS header for current method and 'OPTIONS'
     *
     * @param list<string>|string|null $allowOrigin Use origin, *, one custom origin, or a list of origins.
     */
    public function cors(mixed $allowOrigin, ?array $allowHeaders = null, ?array $allowMethods = null): static
    {
        Route::options(Route::$config[$this->index]['pattern'], [Response::class, 'cors'], [$allowOrigin, $allowHeaders, $allowMethods]);
        return $this->before([Response::class, 'cors'], [$allowOrigin, $allowHeaders, $allowMethods]);
    }

    /**
     * Compress/minify the HTML
     */
    public function minify(): static
    {
        return $this->after([Response::class, 'minify']);
    }
}
