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
use Composer\Autoload\ClassLoader;
use ErrorException;
use Exception;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Cache\Exception\InvalidArgumentException as ExceptionInvalidArgumentException;

/**
 * Starts the framework
 * 
 * @param mixed $config 
 * @throws InvalidArgumentException 
 * @throws Exception 
 * @throws LogicException 
 * @throws RuntimeException 
 * @throws ErrorException 
 * @throws ExceptionInvalidArgumentException 
 * @throws ExceptionInvalidArgumentException 
 */
function start($config)
{
    Config::init($config);

    $timezone = Config::get('app', 'timezone');
    if ($timezone) {
        date_default_timezone_set($timezone);
    }

    ErrorHandler::init();

    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH']) && isJsonRequest()) {
        $body = file_get_contents('php://input');
        if (isJson($body)) {
            $_POST = json_decode($body, true);
        }
    }

    Router::corsHeader(false);

    Router::start();
}

/**
 * Get a path relative to project's root
 * 
 * @param string $path Relative to file system's root when first character is '/'
 * @return string 
 */
function rootPath(string $path = ''): string
{
    if ($path === null) {
        return null;
    } elseif (($path[0] ?? '') === '/') {
        return rtrim($path, '/');
    } else {
        static $rootPath = null;
        if ($rootPath === null) {
            $rootPath = dirname((new ReflectionClass(ClassLoader::class))->getFileName(), 3);
        }
        return $rootPath . '/' . rtrim($path, '/');
    }
}


/**
 * Api response template base json/jsonp format
 * 
 * @param int $error 
 * @param mixed $content string: message, array|object: data
 * @param string $charset 
 * @throws Exception 
 */
function apiResponse(int $error = 0, $content = null, string $charset = 'utf-8')
{
    $code = 200;
    $json = [
        'error' => (int)$error,
        'message' => Router::HTTP_STATUS[$code],
        'data' => new ArrayObject()
    ];

    if ($error && isset(Router::HTTP_STATUS[$error])) {
        $code = $error;
        $json['message'] = Router::HTTP_STATUS[$error];
    }

    if (!is_null($content)) {
        if (is_array($content)) {
            if ($content) {
                if (array_keys($content) !== range(0, count($content) - 1)) {
                    $json['data'] = $content;
                } else {
                    throw new Exception('apiResponse() expects \'data\' to be associative array or arrayObject.');
                }
            }
        } elseif (is_object($content)) {
            $json['data'] = $content;
        } else {
            $json['message'] = $content;
        }
    }

    $jsonEncode = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (isset($_GET['jsonp'])) {
        header('Content-Type: application/javascript; charset=' . $charset, true, $code);
        echo $_GET['jsonp'] . '(' . $jsonEncode . ')';
    } else {
        header('Content-Type: application/json; charset=' . $charset, true, $code);
        echo $jsonEncode;
    }
}

/**
 * Create a Unique ID
 * 
 * @param int $length 
 * @return string 
 * @throws Exception 
 */
function uid(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Checks if it's an ajax request
 *
 * @return bool
 */
function isAjaxRequest(): bool
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

/**
 * Checks if it's an json request
 *
 * @return bool
 */
function isJsonRequest(): bool
{
    return (($_SERVER['CONTENT_TYPE'] ?? '') && (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false));
}

/**
 * Checks if it's an json format
 *
 * @param mixed $string
 * @return bool
 */
function isJson($string): bool
{
    // 1. Speed up the checking & prevent exception throw when non string is passed
    if (is_numeric($string) || !is_string($string) || !$string) {
        return false;
    }

    $cleaned_str = trim($string);
    if (!$cleaned_str || !in_array($cleaned_str[0], ['{', '['])) {
        return false;
    }

    // 2. Actual checking
    $str = json_decode($string);
    return (json_last_error() === JSON_ERROR_NONE) && $str && $str !== $string;
}

/**
 * Simple template render
 * 
 * @param string $file 
 * @param array $data 
 * @throws Exception 
 */
function render(string $file, array $data = [])
{
    $viewPath = Config::get('app', 'viewPath') ?: '';
    $template = rootPath(($viewPath && $file[0] !== '/') ? trim($viewPath, '/') . '/' . $file : $file);
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
function redirect(string $url, int $code = 303)
{
    header('Location: ' . $url, true, $code);
}


/**
 * Get the client IP (compatible proxy)
 * 
 * @return string
 */
function getClientIP(): string
{
    $ipProxy = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) : [];
    return $ipProxy[0] ?? ($_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_CLIENT_IP'] ?? ''));
}

/**
 * Two-dimensional array filter
 * 
 * @param array $array 
 * @param array $filter 
 * @return array 
 */
function arrayFilter(array $array, array $filter): array
{
    if ($filter) {
        $array = array_filter($array, function ($value) use ($filter) {
            foreach ($filter as $k => $v) {
                if (!isset($value[$k])) {
                    return false;
                } elseif (is_array($v)) {
                    if (!in_array($value[$k], $v)) {
                        return false;
                    }
                } else {
                    if ($value[$k] != $v) {
                        return false;
                    }
                }
            }
            return true;
        });
    }

    return $array;
}
