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
        $status = $error ?: 200;
        $json = [
            'error' => $error,
            'message' => self::HTTP_STATUS[$status] ?? '',
            'data' => new ArrayObject()
        ];

        if ($message !== null) {
            $json['message'] = $message;
        }

        if ($data !== null){
            $json['data'] = $data;
        }

        $jsonEncode = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (isset(Request::$query['jsonp'])) {
            header('Content-Type: application/javascript; charset=' . $charset, true, $status);
            echo Request::$query['jsonp'] . '(' . $jsonEncode . ')';
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
     */
    public static function cache($maxAge = 0)
    {
        if ($maxAge) {
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
            header('Cache-Control: max-age=' . $maxAge);
            header_remove('Pragma');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        } else {
            header('Expires: Sat, 03 Jun 1989 14:00:00 GMT');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        }
    }

    /** 
     * Send a set of CORS headers
     * 
     * @param mixed $force 
     */
    public static function cors($force = true)
    {
        $cors = false;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            if ($force) {
                $cors = is_bool($force) ? $origin : $force;
            } else {
                $host = $_SERVER['HTTP_HOST'] ?? '';
                $originHost = parse_url($origin)['host'] ?? '';
                if ($originHost && substr($originHost, -strlen($host)) !== $host) {
                    $configCORS = Config::get('app', 'cors') ?? [];
                    if ($configCORS) {
                        foreach ($configCORS as $domain) {
                            if ($domain === '*' || substr($originHost, -strlen($domain)) === $domain) {
                                $cors = $origin;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ($cors) {
            header('Access-Control-Allow-Origin: ' . $cors);

            if ($cors === '*') {
                header_remove('Access-Control-Allow-Credentials');
                header_remove('Vary');
            } else {
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin');
            }

            if (Request::method() === 'OPTIONS') {
                header('Access-Control-Allow-Methods: ' . join(', ', Request::HTTP_METHODS));
                header('Access-Control-Allow-Headers: Content-Type, Accept, Accept-Language, Content-Language, Authorization, X-Requested-With');
                header('Access-Control-Max-Age: 600');
            }
        }
    }
}
