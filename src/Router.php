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

use FastRoute;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use RuntimeException;

class Router
{
    private function __construct() {}

    private function __destruct() {}

    private function __clone() {}

    public static array $setting = [];

    /**
     * Router start
     */
    public static function start()
    {
        self::$setting = [];
        Response::init();

        $routeResult = self::dispatch(self::configFiles(), Request::method(), rtrim(Request::path(), '/'));
        if (!$routeResult || $routeResult[0] !== Dispatcher::FOUND) {
            if (in_array(Request::method(), ['OPTIONS', 'HEAD'])) {
                http_response_code(404);
            } else {
                Response::error(404);
            }
        } else {
            self::$setting = $routeResult[1];
            self::$setting['args'] = $routeResult[1]['args'] ? $routeResult[2] + $routeResult[1]['args'] : $routeResult[2];

            $queue = array_merge(
                self::$setting['beforeGlobal'] ?? [],
                self::$setting['before'] ?? [],
                [[self::$setting['handler'], self::$setting['args']]],
                self::$setting['after'] ?? [],
                self::$setting['afterGlobal'] ?? []
            );
            foreach ($queue as $_hander) {
                try {
                    call_user_func_array($_hander[0], $_hander[1]);
                } catch (ResponseException $e) {
                    $code = $e->getStatusCode();
                    $status = isset(Response::HTTP_STATUS[$code]) ? $code : 200;
                    if ($e->getBody() !== null) {
                        http_response_code($status);
                        Response::$body = $e->getBody();
                    } elseif (in_array($status, [300, 301, 302, 303, 307, 308])) {
                        Response::redirect($e->getBody(), $status);
                        Response::$body = '';
                    } else {
                        Response::error($code, $e->getMessage() ?: null);
                    }
                    break;
                }
            }
        }

        Response::emitter();
    }


    /**
     * Get the route configuration files
     * 
     * @return array 
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
                        throw new RuntimeException('Missing route file: ' . $_file . '.');
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
     */
    private static function dispatch(array $configFiles, string $method, string $path = ''): array
    {
        $result = [];

        if ($configFiles && in_array($method, Request::ALLOW_METHODS)) {
            foreach ($configFiles as $_configFile) {
                $configStorage = App::root(Config::get('app', 'storagePath') ?: 'storage') . '/route/' . basename($_configFile, '.php') . '/' . filemtime($_configFile);
                if (!is_dir($configStorage) && !@mkdir($configStorage, 0777, true)) {
                    throw new RuntimeException('Failed to create route directory.');
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
     * Get authorized user id
     * 
     * @param mixed $setId
     * @return mixed 
     */

    public static function getAuthId($setId = null)
    {
        static $authId = null;
        if ($setId !== null) {
            $authId = $setId;
        }
        return $authId;
    }

    /**
     * Clear route cache
     */
    public static function clearCache()
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('PHP-CLI required.');
        }

        exec('rm -rf ' . App::root(Config::get('app', 'storagePath') ?: 'storage') . '/route/');
    }
}
