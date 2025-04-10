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
     * @param ?int $time Greater than 0 means caching for seconds; equal to 0 means permanent caching; less than 0 means deleting the cache; null means return the $value without using the cache
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
                if ($time < 0) {
                    $cache->delete($key[0]);
                } else {
                    if ($value instanceof Closure) {
                        $return = $cache->get($key[0], function (ItemInterface $item, &$save) use ($key, $time, $value) {
                            $tags = array_slice($key, 1);
                            if ($tags) {
                                $item->tag($tags);
                            }

                            if ($time > 0) {
                                $item->expiresAfter($time);
                            }

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

                        if ($time > 0) {
                            $item->expiresAfter($time);
                        }

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
     * @return array 
     */
    public static function key(array $args): array
    {
        $keyItems = [];
        $class = '';
        $function = '';

        $backtrace = debug_backtrace();
        if (isset($backtrace[1])) {
            if (($backtrace[1]['class'] ?? '') !== __CLASS__) {
                $target = $backtrace[1];
            } else {
                $target = $backtrace[2] ?? [];
            }

            if ($target) {
                $chars = str_split(ItemInterface::RESERVED_CHARACTERS);
                $class = str_replace($chars, '_', $target['class'] ?? str_replace(App::root(), '', $target['file']));
                $function = $target['function'] ?? '';
                if ($function) {
                    $keyItems = [$class, $function, ...$args];
                } else {
                    $keyItems = [$class, ...$args];
                }
            }
        }

        $return = [join('.', $keyItems)];
        
        if ($class) {
            $return[] = $class;
        }

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
    public static function clear(callable $classFunction, string $configKey = ''): bool
    {
        if (is_array($classFunction) && $classFunction) {
            $cache = Cache::psr6($configKey);
            $chars = str_split(ItemInterface::RESERVED_CHARACTERS);

            $class = is_object($classFunction[0]) ? get_class($classFunction[0]) : $classFunction[0];
            $function = $classFunction[1] ?? '';

            if ($function) {
                $tags = [str_replace($chars, '_', $class) . '.' . str_replace($chars, '_', $function)];
            } else {
                $tags = [str_replace($chars, '_', $class)];
            }

            return $cache->invalidateTags($tags);
        }
        return false;
    }
}
