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

use Closure;
use Symfony\Contracts\Cache\ItemInterface;

class CacheHelper
{
    /**
     * Get result with the cache helper
     * 
     * @param array|string $key Set a string as the cache key, or set the args array to generate the cache key like: class.function.args
     * @param ?int $time Greater than 0 means caching for seconds; equal to 0/-1 means deleting the cache; less than -1 means update the cache witch new $value; null means return the $value without using the cache
     * @param mixed $value If it is an anonymous function, it will be called only when the cache expires. Return null to not save the cache.
     * @param string $configKey
     * @return mixed 
     */
    public static function get($key = [], ?int $time, $value = null, string $configKey = '')
    {
        $return = null;

        if ($time === null) {
            $return = ($value instanceof Closure) ? call_user_func($value) : $value;
        } else {
            $key = is_array($key) ? self::key($key) : ($key ? [$key] : []);
            if ($key) {
                $cache = Cache::psr6($configKey);
                if ($time <= 0) {
                    $cache->delete($key[0]);
                }
                if ($time && $time !== -1) {
                    $time = abs($time);
                    if ($value instanceof Closure) {
                        $return = $cache->get($key[0], function (ItemInterface $item, &$save) use ($key, $time, $value) {
                            $tags = array_slice($key, 1);
                            if ($tags) {
                                $item->tag($tags);
                            }

                            $item->expiresAfter($time);

                            $return = call_user_func($value);
                            if ($return === null) {
                                $save = false;
                            }

                            return $return;
                        });
                    } elseif ($value === null) {
                        $item = $cache->getItem($key[0]);
                        $return = $item->get();
                    } else {
                        $item = $cache->getItem($key[0]);

                        $tags = array_slice($key, 1);
                        if ($tags) {
                            $item->tag($tags);
                        }

                        $item->expiresAfter($time);
                        $item->set($value);
                        $cache->save($item);
                        $return = $value;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Generate keys
     * 
     * @param array $args 
     * @param null|callable $classFunction 
     * @return array 
     */
    public static function key(array $args, ?callable $classFunction = null): array
    {
        $keyItems = [];
        $class = '';
        $function = '';
        $chars = str_split(ItemInterface::RESERVED_CHARACTERS);

        if (is_array($classFunction) && $classFunction) {
            $class = str_replace($chars, '_', is_object($classFunction[0]) ? get_class($classFunction[0]) : $classFunction[0]);
            $function = $classFunction[1] ?? '';
        } else {
            $backtrace = debug_backtrace();
            if (isset($backtrace[1])) {
                if (($backtrace[1]['class'] ?? '') !== __CLASS__) {
                    $target = $backtrace[1];
                } else {
                    $target = $backtrace[2] ?? [];
                }

                if ($target) {
                    $class = str_replace($chars, '_', $target['class'] ?? str_replace(App::root(), '', $target['file']));
                    $function = $target['function'] ?? '';
                }
            }
        }

        foreach ($args as $_index => $_arg) {
            if (is_array($_arg)) {
                $args[$_index] = join('_', $_arg);
            }
        }

        if ($function) {
            $keyItems = [$class, $function, ...$args];
        } else {
            $keyItems = [$class, ...$args];
        }
        $return = [join('.', $keyItems)];

        if ($function && $args) {
            $return[] = join('.', [$class, $function]);
        }

        return $return;
    }

    /**
     * Batch clear cache by [class] or [class, function]
     * 
     * @param callable $classFunction 
     * @param string $configKey
     * @return bool 
     */
    public static function clear($classFunction, string $configKey = ''): bool
    {
        if (is_array($classFunction) && $classFunction) {
            $cache = Cache::psr6($configKey);
            $chars = str_split(ItemInterface::RESERVED_CHARACTERS);

            $class = str_replace($chars, '_', is_object($classFunction[0]) ? get_class($classFunction[0]) : $classFunction[0]);
            $functions = isset($classFunction[1]) ? [$classFunction[1]] : get_class_methods($classFunction[0]);

            foreach ($functions as $function) {
                $key = $class . '.' . $function;
                $tags = [$key];
                $cache->deleteItem($key);
            }

            return $cache->invalidateTags($tags);
        }
        return false;
    }
}
