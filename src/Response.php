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

use ArrayObject;
use Exception;

class Response
{
    /**
     * HTTP response status codes
     * 
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     */
    public const HTTP_STATUS = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /**
     * Common cors headers
     */
    public const CORS_HEADERS = [
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'Authorization',
    ];

    /**
     * Api response template base json/jsonp format
     * 
     * @param int $error 
     * @param null|string $message 
     * @param null|array $data
     * @param string $charset 
     * @throws Exception 
     */
    public static function api(int $error = 0, ?string $message = null, ?array $data = null, string $charset = 'utf-8')
    {
        $status = isset(self::HTTP_STATUS[$error]) ? $error : 200;
        $json = [
            'error' => $error,
            'message' => self::HTTP_STATUS[$status] ?? '',
            'data' => new ArrayObject()
        ];

        if ($message !== null) {
            $json['message'] = $message;
        }

        if ($data !== null) {
            $json['data'] = $data;
        }

        $jsonEncode = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (Request::get('jsonp')) {
            header('Content-Type: application/javascript; charset=' . $charset, true, $status);
            echo Request::get('jsonp') . '(' . $jsonEncode . ')';
        } else {
            header('Content-Type: application/json; charset=' . $charset, true, $status);
            echo $jsonEncode;
        }
    }

    /**
     * Error page
     * 
     * @param int $status 
     */
    public static function errorPage(int $status)
    {
        $errorPageHandler = Config::get('app', 'errorPageHandler');
        if (is_callable($errorPageHandler)) {
            call_user_func_array($errorPageHandler, [$status]);
        } else {
            http_response_code($status);
            echo '<h1>', $status, ' ', self::HTTP_STATUS[$status] ?? '', '</h1>';
        }
    }

    /**
     * Simple template render
     * 
     * @param string $file 
     * @param array $data 
     * @throws Exception 
     */
    public static function render(string $file, array $data = [])
    {
        $template = App::root($file);
        if (!file_exists($template)) {
            throw new Exception("Template file not found: {$template}.");
        }

        if ($data) {
            extract($data);
        }

        require $template;
    }

    /**
     *  Sent a redirect header and exit
     * 
     * @param string $url 
     * @param int $code 
     */
    public static function redirect(string $url, int $code = 303)
    {
        header('Location: ' . $url, true, $code);
    }


    /**
     * Send a set of Cache-Control headers
     * 
     * @param int $maxAge 
     * @param ?int $sMaxAge 
     * @param array $options 
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching
     */
    public static function cache(int $maxAge, ?int $sMaxAge = null, array $options = [])
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
    }

    /**
     * Send a set of CORS headers
     * 
     * @param null|string|array $allowOrigin 
     * @param null|array $allowHeaders 
     * @param null|array $allowMethods 
     * @return bool 
     * @throws Exception 
     */
    public static function cors($allowOrigin, ?array $allowHeaders = null, ?array $allowMethods = null)
    {
        $cors = false;

        $origin = Request::origin();
        if ($allowOrigin && $origin) {
            $originHost = parse_url($origin)['host'] ?? '';
            $host = Request::host();
            if ($originHost && $originHost !== $host) {
                if ($allowOrigin === 'default') {
                    $allowOrigin = Config::get('app', 'corsDomain');
                }

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
                    $cors = true;
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

        return $cors;
    }

    /**
     * Send a ETag header
     * @param string $etag 
     */
    public static function eTag(string $etag = '')
    {
        header('ETag: ' . ($etag ?: Utility::randomHex()));
    }

    /**
     * Send a Last-Modified header
     * @param null|int $timestamp 
     */
    public static function lastModified(?int $timestamp = null)
    {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $timestamp === null  ? time() : $timestamp) . ' GMT');
    }
}
