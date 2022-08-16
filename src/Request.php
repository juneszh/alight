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

class Request
{
    public const HTTP_METHODS = ['GET', 'HEAD', 'POST', 'DELETE', 'PUT', 'OPTIONS', 'TRACE', 'PATCH'];
    public static array $query;
    public static array $data;
    public static array $cookie;

    /**
     * Checks if it's an ajax request
     *
     * @return bool
     */
    public static function isAjax(): bool
    {
        static $ajax = null;
        if ($ajax === null) {
            $ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        }

        return $ajax;
    }

    /**
     * Checks if it's an json request
     *
     * @return bool
     */
    public static function isJson(): bool
    {
        static $json = null;
        if ($json === null) {
            $json = isset($_SERVER['CONTENT_TYPE']) && (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
        }

        return $json;
    }

    /**
     * Get the client IP (compatible proxy)
     * 
     * @return string
     */
    public static function ip(): string
    {
        static $ip = null;
        if ($ip === null) {
            $ipProxy = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) : [];
            $ip = $ipProxy[0] ?? ($_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_CLIENT_IP'] ?? ''));
        }

        return $ip;
    }

    /**
     * Get the request method
     * 
     * @return string 
     */
    public static function method(): string
    {
        static $method = null;
        if ($method === null) {
            if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            } elseif (isset(Request::$query['_method'])) {
                $method = strtoupper(Request::$query['_method']);
            } elseif (isset(Request::$data['_method'])) {
                $method = strtoupper(Request::$data['_method']);
            } else {
                $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
            }
        }

        return $method;
    }

    /**
     * Get the request subdomain
     * 
     * @return string 
     */
    public static function subdomain(): string
    {
        static $subdomain = null;
        if ($subdomain === null) {
            $configDomainLevel = (int) Config::get('app', 'domainLevel');
            $subdomain = join('.', array_slice(explode('.', $_SERVER['HTTP_HOST'] ?? ''), 0, -$configDomainLevel));
        }

        return $subdomain;
    }

    /**
     * Get the request path
     * 
     * @return string 
     */
    public static function path(): string
    {
        static $path = null;
        if ($path === null) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            if (false !== $pos = strpos($path, '?')) {
                $path = substr($path, 0, $pos);
            }
        }

        return $path;
    }

    /**
     * Get the request body
     * 
     * @return string 
     */
    public static function body(): string
    {
        static $body = null;
        if ($body === null) {
            $body = file_get_contents('php://input') ?: '';
        }

        return $body;
    }
}
