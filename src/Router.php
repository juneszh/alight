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

use ErrorException;
use Exception;
use FastRoute;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use LogicException;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use RuntimeException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Exception\LogicException as ExceptionLogicException;

class Router
{
    private static $authId;

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
     * @throws ErrorException 
     * @throws CacheInvalidArgumentException 
     * @throws ExceptionLogicException 
     */
    public static function start()
    {
        if (Request::method() === 'OPTIONS') {
            http_response_code(204);
        }

        $routeResult = self::dispatch(self::configFiles(), Request::method(), rtrim(Request::path(), '/'));
        if (!$routeResult || $routeResult[0] !== Dispatcher::FOUND) {
            if (Request::method() === 'OPTIONS') {
                if (!Response::cors('default')) {
                    http_response_code(404);
                }
            } else if (Request::method() === 'HEAD') {
                http_response_code(404);
            } else if (Request::isAjax()) {
                Response::api(404);
            } else {
                Response::errorPage(404);
            }
        } else {
            $routeData = $routeResult[1];
            $routeArgs = $routeData['args'] ? $routeResult[2] + $routeData['args'] : $routeResult[2];

            Response::eTag();
            Response::lastModified();
            Response::cors('default');

            if (isset($routeData['cache'])) {
                Response::cache($routeData['cache'], $routeData['cacheOptions'] ?? []);
            }

            if (isset($routeData['beforeHandler'])) {
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
                    self::coolDown($routeData['pattern'], $routeData['cd']);
                }
            }

            if (isset($routeData['cors'])) {
                Response::cors($routeData['cors'][0], $routeData['cors'][1], $routeData['cors'][2]);
            }

            if (!is_callable($routeData['handler'])) {
                throw new Exception('Invalid handler specified.');
            }

            call_user_func_array($routeData['handler'], $routeArgs);
        }
    }

    /**
     * Get the route configuration files
     * 
     * @return array 
     * @throws Exception 
     */
    private static function configFiles(): array
    {
        $routeFiles = [];

        $subdomain = Request::subdomain() ?: '@';

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
                    $_file = App::root($_file);
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
     * @param array $configFiles 
     * @param string $method 
     * @param string $path 
     * @return array 
     * @throws Exception 
     * @throws LogicException 
     * @throws RuntimeException 
     */
    private static function dispatch(array $configFiles, string $method, string $path = ''): array
    {
        $result = [];

        if ($configFiles && in_array($method, Request::ALLOW_METHODS)) {
            foreach ($configFiles as $_configFile) {
                $configStorage = App::root(Config::get('app', 'storagePath') ?: 'storage') . '/route/' . basename($_configFile, '.php') . '/' . filemtime($_configFile);
                if (!is_dir($configStorage)) {
                    if (!mkdir($configStorage, 0777, true)) {
                        throw new Exception('Failed to create route directory.');
                    }
                }

                $dispatcher = FastRoute\cachedDispatcher(function (RouteCollector $r) use ($method, $_configFile, $configStorage) {
                    Route::init();
                    require $_configFile;
                    foreach (Route::$config as $_route) {
                        if (!isset($_route['methods'])) {
                            continue;
                        }

                        if (!in_array($method, $_route['methods'])) {
                            continue;
                        }

                        $r->addRoute($method, $_route['pattern'], $_route);
                    }

                    $oldCacheDirs = glob(dirname($configStorage) . '/*');
                    if ($oldCacheDirs) {
                        $latestTime = substr($configStorage, -10);
                        foreach ($oldCacheDirs as $_oldDir) {
                            if (substr($_oldDir, -10) !== $latestTime) {
                                foreach (Request::ALLOW_METHODS as $_method) {
                                    if (is_file($_oldDir . '/' . $_method . '.php')) {
                                        @unlink($_oldDir . '/' . $_method . '.php');
                                    }
                                }
                                @rmdir($_oldDir);
                            }
                        }
                    }
                }, [
                    'cacheFile' => $configStorage . '/' . $method . '.php',
                    'cacheDisabled' => Route::$disableCache,
                ]);

                $result = $dispatcher->dispatch($method, $path);
                if ($result[0] === Dispatcher::FOUND) {
                    break;
                }
            }
        }

        return $result;
    }


    /**
     * Limit request interval
     * 
     * @param string $pattern 
     * @param int $cd 
     * @throws Exception 
     * @throws InvalidArgumentException 
     * @throws ErrorException 
     * @throws CacheInvalidArgumentException 
     * @throws ExceptionLogicException 
     */
    private static function coolDown(string $pattern, int $cd)
    {
        if (self::$authId) {
            $cache6 = Cache::psr6();
            $cacheKey = 'alight.route_cd.' . md5(Request::method() . ' ' . $pattern) . '.' . self::$authId;
            $cacheItem = $cache6->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                Response::api(429);
                exit;
            } else {
                $cacheItem->set(1);
                $cacheItem->expiresAfter($cd);
                $cacheItem->tag('alight.route_cd');
                $cache6->save($cacheItem);
            }
        }
    }

    /**
     * Clear route cache
     * 
     * @throws Exception 
     */
    public static function clearCache()
    {
        if (PHP_SAPI !== 'cli') {
            throw new Exception('PHP-CLI required.');
        }

        exec('rm -rf ' . App::root(Config::get('app', 'storagePath') ?: 'storage') . '/route/');
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
