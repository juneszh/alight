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

class Request
{
    public const ALLOW_METHODS = ['OPTIONS', 'HEAD', 'GET', 'POST', 'DELETE', 'PUT', 'PATCH'];

    /**
     * Property getter
     * 
     * @param string $property 
     * @param string $key 
     * @param mixed $default  
     * @param mixed $set 
     * @return mixed 
     */
    private static function getter(array &$property, string $key, $default, $set = null)
    {
        if ($key) {
            if ($set !== null) {
                $property[$key] = $set;
            }

            if (isset($property[$key])) {
                switch (gettype($default)) {
                    case 'boolean':
                        return (bool) $property[$key];
                    case 'integer':
                        return (int) $property[$key];
                    case 'double':
                        return (float) $property[$key];
                    case 'string':
                        return (string) $property[$key];
                    case 'array':
                        return (array) $property[$key];
                    default:
                        return $property[$key];
                }
            } else {
                return $default;
            }
        } else {
            return $property;
        }
    }

    /**
     * Get HTTP header value, or $default if unset
     * 
     * @param string $key 
     * @param mixed $default 
     * @param mixed $set 
     * @return mixed 
     */
    public static function header(string $key = '', $default = null, $set = null)
    {
        static $header = null;
        if ($header === null) {
            $header = apache_request_headers() ?: [];
        }
        return self::getter($header, $key, $default, $set);
    }

    /**
     * Get $_GET value, or $default if unset
     * 
     * @param string $key 
     * @param mixed $default 
     * @param mixed $set 
     * @return mixed 
     */
    public static function get(string $key = '', $default = null, $set = null)
    {
        static $get = null;
        if ($get === null) {
            $get = $_GET ?: [];
        }
        return self::getter($get, $key, $default, $set);
    }

    /**
     * Get $_POST value (including json body), or $default if unset
     * 
     * @param string $key 
     * @param mixed $default 
     * @param mixed $set 
     * @return mixed 
     */
    public static function post(string $key = '', $default = null, $set = null)
    {
        static $post = null;
        if ($post === null) {
            $post = $_POST ?: [];
            if (in_array(self::method(), ['POST', 'PUT', 'DELETE', 'PATCH']) && self::isJson()) {
                if (Utility::isJson(self::body())) {
                    $post = json_decode(self::body(), true);
                }
            }
        }
        return self::getter($post, $key, $default, $set);
    }

    /**
     * Simulate $_REQUEST, contains the contents of get(), post()
     * 
     * @param string $key 
     * @param mixed $default 
     * @param mixed $set 
     * @return mixed 
     */
    public static function request(string $key = '', $default = null, $set = null)
    {
        static $request = null;
        if ($request === null) {
            $request = array_replace_recursive(self::get(), self::post());
        }
        return self::getter($request, $key, $default, $set);
    }

    /**
     * Get $_COOKIE value, or $default if unset
     * 
     * @param string $key 
     * @param mixed $default 
     * @param mixed $set 
     * @return mixed 
     */
    public static function cookie(string $key = '', $default = null, $set = null)
    {
        static $cookie = null;
        if ($cookie === null) {
            $cookie = $_COOKIE ?: [];
        }
        return self::getter($cookie, $key, $default, $set);
    }

    /**
     * Get $_FILES value, or $default if unset
     * 
     * @param string $key 
     * @param mixed $default 
     * @param mixed $set 
     * @return mixed 
     */
    public static function file(string $key = '', $default = null, $set = null)
    {
        static $file = null;
        if ($file === null) {
            $file = $_FILES ?: [];
        }
        return self::getter($file, $key, $default, $set);
    }

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
     * Get the User-Agent
     * 
     * @return string 
     */
    public static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get the Referrer
     * 
     * @return string 
     */
    public static function referrer(): string
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
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
            } elseif (Request::post('_method')) {
                $method = strtoupper(Request::post('_method'));
            } elseif (Request::get('_method')) {
                $method = strtoupper(Request::get('_method'));
            } else {
                $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
            }
        }

        return $method;
    }

    /**
     * Get the request scheme
     * 
     * @return string 
     */
    public static function scheme(): string
    {
        static $scheme = null;
        if ($scheme === null) {
            if (
                (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on')
                ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                ||
                (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on')
                ||
                (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
            ) {
                $scheme = 'https';
            } else {
                $scheme = 'http';
            }
        }

        return $scheme;
    }

    /**
     * Get the request host
     * 
     * @return string 
     */
    public static function host(): string
    {
        static $host = null;
        if ($host === null) {
            if (isset($_SERVER['HTTP_HOST'])) {
                $host = $_SERVER['HTTP_HOST'];
            } elseif (isset($_SERVER['SERVER_NAME'])) {
                $host = $_SERVER['SERVER_NAME'];
            } else {
                $host = '';
            }
        }

        return $host;
    }

    /**
     * Get the request origin
     * 
     * @return string 
     */
    public static function origin(): string
    {
        static $origin = null;
        if ($origin === null) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        }

        return $origin;
    }

    /**
     * Get the base url
     * 
     * @return string 
     */
    public static function baseUrl(): string
    {
        return self::scheme() . '://' . self::host();
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
            $subdomain = join('.', array_slice(explode('.', self::host()), 0, -$configDomainLevel));
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
