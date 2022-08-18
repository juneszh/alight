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
        if (!in_array(Request::method(), Request::HTTP_METHODS)) {
            http_response_code(404);
            exit;
        }

        $routeResult = self::getResult();
        if (!$routeResult || $routeResult[0] !== Dispatcher::FOUND) {
            if (Request::method() !== 'OPTIONS') {
                http_response_code(404);
            }
            exit;
        }

        $routeData = $routeResult[1];
        $routeArgs = $routeResult[2];

        if (isset($routeData['cache'])) {
            Response::cache($routeData['cache']);
        }

        if (isset($routeData['cors'])) {
            if (($routeData['cache'] ?? 0) > 0) {
                $routeData['cors'] = '*';
            }
            Response::cors($routeData['cors']);
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
                self::coolDown($routeData['pattern'], $routeData['cd']);
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
                    $_file = App::rootPath($_file);
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
     * @return array 
     * @throws Exception 
     * @throws LogicException 
     * @throws RuntimeException 
     */
    private static function getResult(): array
    {
        $result = [];

        $routeFiles = self::getFiles();
        if ($routeFiles) {
            if (Request::method() === 'OPTIONS') {
                header('Allow: ' . join(', ', Request::HTTP_METHODS));
            }

            $requestPath = rtrim(Request::path(), '/');

            foreach ($routeFiles as $_routeFile) {
                $configStorage = App::rootPath(Config::get('app', 'storagePath') ?: 'storage') . '/route/' . basename($_routeFile, '.php') . '/' . filemtime($_routeFile);
                if (!is_dir($configStorage)) {
                    if (!mkdir($configStorage, 0777, true)) {
                        throw new Exception('Failed to create route directory.');
                    }
                }

                $dispatcher = FastRoute\cachedDispatcher(function (RouteCollector $r) use ($_routeFile, $configStorage) {
                    Route::init();
                    require $_routeFile;
                    foreach (Route::$config as $_route) {
                        if (!isset($_route['method'])) {
                            continue;
                        }

                        if (!in_array(Request::method(), $_route['method'])) {
                            continue;
                        }

                        $r->addRoute(Request::method(), $_route['pattern'], $_route);
                    }

                    $oldCacheDirs = glob(dirname($configStorage) . '/*');
                    if ($oldCacheDirs) {
                        $latestTime = substr($configStorage, -10);
                        foreach ($oldCacheDirs as $_oldDir) {
                            if (substr($_oldDir, -10) !== $latestTime) {
                                foreach (Request::HTTP_METHODS as $_method) {
                                    if (is_file($_oldDir . '/' . $_method . '.php')) {
                                        @unlink($_oldDir . '/' . $_method . '.php');
                                    }
                                }
                                @rmdir($_oldDir);
                            }
                        }
                    }
                }, [
                    'cacheFile' => $configStorage . '/' . Request::method() . '.php',
                    'cacheDisabled' => Route::$disableCache,
                ]);

                $result = $dispatcher->dispatch(Request::method(), $requestPath);
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
     * @throws ErrorException 
     * @throws InvalidArgumentException 
     * @throws InvalidArgumentException 
     */
    private static function coolDown(string $pattern, int $cd)
    {
        if (self::$authId) {
            $cache = Cache::init();
            $cacheKey = 'route_cd_' . md5(Request::method() . ' ' . $pattern) . '_' . self::$authId;
            if ($cache->has($cacheKey)) {
                Response::api(429);
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
