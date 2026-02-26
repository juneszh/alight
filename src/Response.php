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
use RuntimeException;

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
    private const CORS_HEADERS = [
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'Authorization',
    ];

    public static string $body = '';
    public static int $lastModified = 0;

    /** 
     * Initializes 
     */
    public static function init()
    {
        self::$body = '';
        self::$lastModified = 0;
    }

    /** 
     * Output body and lastModified
     */
    public static function emitter()
    {
        $notModified = false;

        $lastModified = self::$lastModified ?: time();
        $clientModified = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');

        if ($clientModified !== false && $clientModified >= $lastModified) {
            $notModified = true;
        }

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

        if ($notModified) {
            http_response_code(304);
        } elseif (!in_array(Request::method(), ['OPTIONS', 'HEAD'])) {
            echo self::$body;
        }
    }

    /**
     * Api response template base json/jsonp format
     * 
     * @param int $error 
     * @param null|string $message 
     * @param null|array $data
     * @param null|array $extraData
     */
    public static function api(int $error = 0, ?string $message = null, ?array $data = null, ?array $extraData = null)
    {
        $status = isset(self::HTTP_STATUS[$error]) ? $error : 200;
        $json = [
            'error' => $error,
            'message' => self::HTTP_STATUS[$status] ?? ''
        ];

        if ($message !== null) {
            $json['message'] = $message;
        }

        if ($data !== null) {
            $json['data'] = $data;
        }

        if ($extraData !== null) {
            $json = array_merge($json, $extraData);
        }

        $jsonEncode = json_encode($json, JSON_UNESCAPED_UNICODE);
        if (Request::query('jsonp')) {
            header('Content-Type: application/javascript; charset=utf-8', true, $status);
            self::$body = Request::query('jsonp') . '(' . $jsonEncode . ')';
        } else {
            header('Content-Type: application/json; charset=utf-8', true, $status);
            self::$body = $jsonEncode;
        }
    }

    /**
     * Error page
     * 
     * @param int $status 
     * @param null|string $message 
     * @param null|array $data
     */
    public static function errorPage(int $status = 0, ?string $message = null, ?array $data = null)
    {
        if (!isset(self::HTTP_STATUS[$status])) {
            $status = 200;
        }
        header('Content-Type: text/html; charset=utf-8', true, $status);

        $errorPageHandler = Config::get('app', 'errorPageHandler');
        if (is_callable($errorPageHandler)) {
            call_user_func_array($errorPageHandler, [$status, $message, $data]);
        } else {
            self::$body = '<h1>' . $status . ' ' . ($message ?: self::HTTP_STATUS[$status] ?? '') . '</h1>';
        }
    }

    /**
     * Auto detect request type and output error api or page
     * 
     * @param int $status 
     * @param null|string $message 
     * @param null|array $data
     */
    public static function error(int $status = 0, ?string $message = null, ?array $data = null)
    {
        if (Request::isAjax()) {
            Response::api($status, $message, $data);
        } else {
            Response::errorPage($status, $message, $data);
        }
    }

    /**
     * Simple template render
     * 
     * @param string $file 
     * @param array $data 
     */
    public static function render(string $file, array $data = [])
    {
        header('Content-Type: text/html; charset=utf-8', true);

        ob_start();
        self::template($file, $data);
        self::$body = ob_get_clean();
    }

    /**
     * build template data
     * 
     * @param string $file 
     * @param array $data 
     */
    public static function template(string $file, array $data = [])
    {
        $template = App::root($file);
        if (!file_exists($template)) {
            throw new RuntimeException("Template file not found: {$template}.");
        }

        if ($data) {
            extract($data);
        }
        require $template;
    }

    /**
     *  Sent a redirect header
     * 
     * @param string $url 
     * @param int $status 
     */
    public static function redirect(string $url, int $status = 303)
    {
        header('Location: ' . $url, true, $status);
    }


    /**
     * Authorization verification
     * 
     * @param int $debounce $debounce set the interval seconds between 2 requests for each user
     */
    public static function auth(int $debounce = 0)
    {
        if (!isset(Router::$setting['cache'])) {
            self::cache(0);
        }

        if (Router::$setting['authHandler'] ?? []) {
            $authId = call_user_func_array(Router::$setting['authHandler'][0], Router::$setting['authHandler'][1]);
            if (!$authId) {
                throw new ResponseException(401);
            }
            Router::getAuthId($authId);

            if ($debounce) {
                $cache = Cache::init();
                $cacheKey = 'Alight_RouteMiddleware.debounce.' . md5(Request::method() . ' ' . Router::$setting['pattern']) . '.' . $authId;
                if ($cache->has($cacheKey)) {
                    throw new ResponseException(429);
                } else {
                    $cache->set($cacheKey, 1, $debounce);
                }
            }
        } else {
            throw new LogicException('Missing authHandler definition.');
        }
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
    }

    /**
     * body minify
     */
    public static function minify()
    {
        $htmlMin = new HtmlMin();
        $htmlMin->doRemoveOmittedQuotes(false);
        $htmlMin->doRemoveOmittedHtmlTags(false);
        Response::$body = $htmlMin->minify(Response::$body);
    }
}
