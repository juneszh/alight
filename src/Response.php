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

use RuntimeException;

class Response
{
    /**
     * HTTP response status codes
     * 
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     */
    private const HTTP_STATUS = [
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
     * Error response
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
    }

    /**
     * Simple template render
     * 
     * @param string $file 
     * @param array $data 
     */
    public static function render(string $file, array $data = [])
    {
        $template = App::root($file);
        if (!file_exists($template)) {
            throw new RuntimeException("Template file not found: {$template}.");
        }

        header('Content-Type: text/html; charset=utf-8', true);

        if ($data) {
            extract($data);
        }
        ob_start();
        require $template;
        self::$body = ob_get_clean();
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
}
