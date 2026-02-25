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

use LogicException;
use voku\helper\HtmlMin;

class RouteMiddleware
{
    /**
     * Common cors headers
     */
    private const CORS_HEADERS = [
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'Authorization',
    ];

    /**
     * Get authorized user id
     * 
     * @param mixed $setId
     * @return mixed 
     */

    public static function getAuthId($setId = null)
    {
        static $authId = null;
        if ($setId !== null) {
            $authId = $setId;
        }
        return $authId;
    }

    /**
     * Authorization verification
     * 
     * @param int $debounce $debounce set the interval seconds between 2 requests for each user
     * @return bool 
     */
    public static function auth(int $debounce = 0): bool
    {
        if (!isset(Router::$setting['cache'])) {
            self::cache(0);
        }

        if (Router::$setting['authHandler'] ?? []) {
            $authId = call_user_func_array(Router::$setting['authHandler'][0], Router::$setting['authHandler'][1]);
            self::getAuthId($authId);

            if (!$authId) {
                return false;
            }

            if ($debounce) {
                $cache = Cache::init();
                $cacheKey = 'Alight_RouteMiddleware.debounce.' . md5(Request::method() . ' ' . Router::$setting['pattern']) . '.' . $authId;
                if ($cache->has($cacheKey)) {
                    Response::error(429);
                    return false;
                } else {
                    $cache->set($cacheKey, 1, $debounce);
                }
            }
        } else {
            Log::error(new LogicException('Missing authHandler definition.'));
            Response::error(500);
            return false;
        }

        return true;
    }

    /**
     * Send a set of Cache-Control headers
     * 
     * @param int $maxAge 
     * @param ?int $sMaxAge 
     * @param array $options 
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching
     * @return bool 
     */
    public static function cache(int $maxAge, ?int $sMaxAge = null, array $options = []): bool
    {
        if (!$maxAge && !$sMaxAge) {
            $cacheControl = ['no-cache'];
            header('Pragma: no-cache');
        } else {
            $cacheControl = ['max-age=' . $maxAge];
            if ($maxAge === 0) {
                $cacheControl[] = 'must-revalidate';
            }
            if ($sMaxAge !== null) {
                $cacheControl[] = 's-maxage=' . $sMaxAge;
                if ($sMaxAge === 0) {
                    $cacheControl[] = 'proxy-revalidate';
                }
            }
            header_remove('Pragma');
        }
        if ($options) {
            $cacheControl = array_unique(array_merge($cacheControl, $options));
        }
        header('Cache-Control: ' . join(', ', $cacheControl));

        return true;
    }


    /**
     * Send a set of CORS headers
     * 
     * @param null|string|array $allowOrigin 
     * @param null|array $allowHeaders 
     * @param null|array $allowMethods 
     * @return bool 
     */
    public static function cors($allowOrigin, ?array $allowHeaders = null, ?array $allowMethods = null)
    {
        $origin = Request::origin();
        if ($allowOrigin && $origin) {
            $originHost = parse_url($origin)['host'] ?? '';
            $host = Request::host();
            if ($originHost && $originHost !== $host) {
                if (is_array($allowOrigin)) {
                    foreach ($allowOrigin as $domain) {
                        if ($domain && substr($originHost, -strlen($domain)) === $domain) {
                            $allowOrigin = $origin;
                            break;
                        }
                    }
                } elseif ($allowOrigin === '*') {
                    $allowOrigin = '*';
                } elseif ($allowOrigin === 'origin') {
                    $allowOrigin = $origin;
                } elseif ($allowOrigin && substr($originHost, -strlen($allowOrigin)) === $allowOrigin) {
                    $allowOrigin = $origin;
                } else {
                    $allowOrigin = null;
                }

                if (is_string($allowOrigin)) {
                    header('Access-Control-Allow-Origin: ' . $allowOrigin);
                    header('Access-Control-Allow-Credentials: true');
                    header('Vary: Origin');

                    if (Request::method() === 'OPTIONS') {
                        $allowHeaders = $allowHeaders ?: (Config::get('app', 'corsHeaders') ?: self::CORS_HEADERS);
                        $allowMethods = $allowMethods ?: (Config::get('app', 'corsMethods') ?: Request::ALLOW_METHODS);
                        header('Access-Control-Allow-Headers: ' . join(', ', $allowHeaders));
                        header('Access-Control-Allow-Methods: ' . join(', ', $allowMethods));
                        header('Access-Control-Max-Age: 86400');
                        self::cache(0);
                    }
                }
            }
        }

        return true;
    }

    /**
     * html minify
     * 
     * @return bool 
     */
    public static function minify(): bool
    {
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveOmittedQuotes(false);
        $htmlMin->doRemoveOmittedHtmlTags(false);
        Response::$body = $htmlMin->minify(Response::$body);

        return true;
    }
}
