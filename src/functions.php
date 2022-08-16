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

    Request::init();

    Response::cors(false);

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
 * Checks if it's an json format
 *
 * @param mixed $content
 * @return bool
 */
function isJson($content): bool
{
    if (!is_string($content) || !$content || is_numeric($content)) {
        return false;
    }

    $string = trim($content);
    if (!$string || !in_array($string[0], ['{', '['])) {
        return false;
    }

    $result = json_decode($content);
    return (json_last_error() === JSON_ERROR_NONE) && $result && $result !== $content;
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
