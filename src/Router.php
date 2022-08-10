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

use ErrorException;
use Exception;
use FastRoute;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use LogicException;
use RuntimeException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class Router
{
    private static $authId;
    public const HTTP_METHODS = ['GET', 'HEAD', 'POST', 'DELETE', 'PUT', 'OPTIONS', 'TRACE', 'PATCH'];
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

    private function __construct()
    {
    }

    private function __destruct()
    {
    }

    private function __clone()
    {
    }

    /**
     * Router start
     * 
     * @throws Exception 
     * @throws LogicException 
     * @throws RuntimeException 
     * @throws InvalidArgumentException 
     * @throws InvalidArgumentException 
     */
    public static function start()
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
        if (!in_array($requestMethod, self::HTTP_METHODS)) {
            http_response_code(404);
            exit;
        }

        $routeResult = self::getResult($requestMethod);
        if (!$routeResult || $routeResult[0] !== Dispatcher::FOUND) {
            if ($requestMethod !== 'OPTIONS') {
                http_response_code(404);
            }
            exit;
        }

        $routeData = $routeResult[1];
        $routeArgs = $routeResult[2];

        if (isset($routeData['cache'])) {
            self::cacheHeader($routeData['cache']);
        }

        if (isset($routeData['cors'])) {
            if (($routeData['cache'] ?? 0) > 0) {
                $routeData['cors'] = '*';
            }
            self::corsHeader($routeData['cors']);
        }

        if ($routeData['beforeHandler'] ?? []) {
            if (!is_callable($routeData['beforeHandler'][0])) {
                throw new Exception('Invalid beforeHandler specified.');
            }
            call_user_func_array($routeData['beforeHandler'][0], $routeData['beforeHandler'][1]);
        }

        if (isset($routeData['auth'])) {
            if ($routeData['authHandler'] ?? []) {
                if (!is_callable($routeData['authHandler'][0])) {
                    throw new Exception('Invalid authHandler specified.');
                }
                self::$authId = call_user_func_array($routeData['authHandler'][0], $routeData['authHandler'][1]);
            } else {
                throw new Exception('Missing authHandler definition.');
            }

            if ($routeData['cd'] ?? 0) {
                self::coolDown(md5($requestMethod . ' ' . $routeData['pattern']), $routeData['cd']);
            }
        }

        if (!is_callable($routeData['handler'])) {
            throw new Exception('Invalid handler specified.');
        }

        call_user_func_array($routeData['handler'], $routeArgs);
    }

    /**
     * Get the route configuration files
     * 
     * @return array 
     * @throws Exception 
     */
    private static function getFiles(): array
    {
        $routeFiles = [];

        $configDomainLevel = (int) Config::get('app', 'domainLevel');
        $subdomain = join('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -$configDomainLevel));
        if (!$subdomain) {
            $subdomain = '@';
        }

        $configRoute = Config::get('route');
        if (is_string($configRoute)) {
            $configRoute = [$configRoute];
        }

        foreach ($configRoute as $_subDomain => $_files) {
            if (is_string($_subDomain) && $_subDomain !== '*' && $_subDomain !== $subdomain) {
                continue;
            }

            if ($_files) {
                if (is_string($_files)) {
                    $_files = [$_files];
                }

                foreach ($_files as $_file) {
                    $_file = rootPath($_file);
                    if (!is_file($_file)) {
                        throw new Exception('Missing route file: ' . $_file . '.');
                    }

                    $routeFiles[] = $_file;
                }
            }
        }

        return $routeFiles;
    }

    /**
     * Get the results from FastRoute dispatcher 
     * 
     * @param string $requestMethod 
     * @return array 
     * @throws Exception 
     * @throws LogicException 
     * @throws RuntimeException 
     */
    private static function getResult(string $requestMethod): array
    {
        $routeResult = [];

        $routeFiles = self::getFiles();
        if ($routeFiles) {
            if ($requestMethod === 'OPTIONS') {
                header('Allow: ' . join(', ', self::HTTP_METHODS));
            }

            $requestUri = $_SERVER['REQUEST_URI'];
            if (false !== $pos = strpos($requestUri, '?')) {
                $requestUri = substr($requestUri, 0, $pos);
            }
            $requestUri = rtrim(rawurldecode($requestUri), '/');

            foreach ($routeFiles as $_routeFile) {
                $configStorage = rootPath(Config::get('app', 'storagePath') ?: 'storage') . '/route/' . basename($_routeFile, '.php') . '/' . filemtime($_routeFile);
                if (!is_dir($configStorage)) {
                    if (!mkdir($configStorage, 0777, true)) {
                        throw new Exception('Failed to create route directory.');
                    }
                }

                $dispatcher = FastRoute\cachedDispatcher(function (RouteCollector $r) use ($requestMethod, $_routeFile, $configStorage) {
                    Route::init();
                    require $_routeFile;
                    foreach (Route::$config as $_route) {
                        if (!isset($_route['method'])) {
                            continue;
                        }

                        if (!in_array($requestMethod, $_route['method'])) {
                            continue;
                        }

                        $r->addRoute($requestMethod, $_route['pattern'], $_route);
                    }

                    $oldCacheDirs = glob(dirname($configStorage) . '/*');
                    if ($oldCacheDirs) {
                        $latestTime = substr($configStorage, -10);
                        foreach ($oldCacheDirs as $_oldDir) {
                            if (substr($_oldDir, -10) !== $latestTime) {
                                foreach (self::HTTP_METHODS as $_method) {
                                    if (is_file($_oldDir . '/' . $_method . '.php')) {
                                        @unlink($_oldDir . '/' . $_method . '.php');
                                    }
                                }
                                @rmdir($_oldDir);
                            }
                        }
                    }
                }, [
                    'cacheFile' => $configStorage . '/' . $requestMethod . '.php',
                    'cacheDisabled' => Route::$disableCache,
                ]);

                $routeResult = $dispatcher->dispatch($requestMethod, $requestUri);
                if ($routeResult[0] === Dispatcher::FOUND) {
                    break;
                }
            }
        }

        return $routeResult;
    }

    /**
     * Send a set of Cache-Control headers
     * 
     * @param int $maxAge 
     */
    public static function cacheHeader($maxAge = 0)
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
    public static function corsHeader($force = true)
    {
        $cors = false;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            if ($force) {
                $cors = is_bool($force) ? $origin : $force;
            } else {
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if (substr($origin, -strlen($host)) !== $host) {
                    $configCORS = Config::get('app', 'cors') ?? [];
                    if ($configCORS && substr($origin, -strlen($host)) !== $host) {
                        foreach ($configCORS as $domain) {
                            if ($domain === '*' || substr($origin, -strlen($domain)) === $domain) {
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

            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                header('Access-Control-Allow-Methods: ' . join(', ', self::HTTP_METHODS));
                header('Access-Control-Allow-Headers: Content-Type, Accept, Accept-Language, Content-Language, Authorization, X-Requested-With');
                header('Access-Control-Max-Age: 600');
            }
        }
    }

    /**
     * Limit request interval
     * 
     * @param mixed $routeMD5 
     * @param mixed $cd 
     * @throws Exception 
     * @throws ErrorException 
     * @throws InvalidArgumentException 
     * @throws InvalidArgumentException 
     */
    public static function coolDown($routeMD5, $cd)
    {
        if (self::$authId) {
            $cache = Cache::init();
            $cacheKey = 'route_cd_' . $routeMD5 . '_' . self::$authId;
            if ($cache->has($cacheKey)) {
                apiResponse(429);
                exit;
            } else {
                $cache->set($cacheKey, 1, $cd);
            }
        }
    }

    /**
     * Get authorized user id
     * 
     * @return mixed 
     */
    public static function getAuthId()
    {
        return self::$authId;
    }
}
