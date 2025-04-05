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

use Symfony\Contracts\Cache\ItemInterface;

class CacheHelper
{
    /**
     * Get result with the cache helper
     * 
     * @param array|string $key Set a string as the cache key, or set the args array to generate the cache key like: class.function.args
     * @param ?int $expiresAfter Greater than 0 means caching for seconds; equal to 0 means permanent caching; less than 0 means deleting the cache; null means running the callback without using the cache
     * @param callable $callback Callback function used to return the cache value, return null to not save the cache
     * @param string $configKey
     * @return mixed 
     */
    public static function get($key = [], ?int $expiresAfter, callable $callback, string $configKey = '')
    {
        $return = null;

        if ($expiresAfter === null) {
            $return = call_user_func($callback);
        } else {
            $key = is_array($key) ? self::key($key) : ($key ? [$key] : []);
            if ($key) {
                $cache = Cache::psr6($configKey);
                if ($expiresAfter >= 0) {
                    $return = $cache->get($key[0], function (ItemInterface $item, &$save) use ($key, $expiresAfter, $callback) {
                        $tags = array_slice($key, 1);
                        if ($tags) {
                            $item->tag($tags);
                        }

                        if ($expiresAfter > 0) {
                            $item->expiresAfter($expiresAfter);
                        }

                        $return = call_user_func($callback);
                        if ($return === null){
                            $save = false;
                        }

                        return $return;
                    });
                } else {
                    $return = $cache->delete($key[0]);
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
                    array_unshift($args, $class, $function);
                } else {
                    array_unshift($args, $class);
                }
            }
        }

        $return = [join('.', $args)];
        if ($function) {
            $return[] = join('.', [$class, $function]);
        }
        if ($class) {
            $return[] = $class;
        }

        return $return;
    }

    /**
     * Batch clear cache by class or class.function
     * 
     * @param string $class 
     * @param string $function 
     * @param string $configKey
     * @return bool 
     */
    public static function clear(string $class, string $function = '', string $configKey = ''): bool
    {
        $cache = Cache::psr6($configKey);
        $chars = str_split(ItemInterface::RESERVED_CHARACTERS);

        if ($function) {
            $tags = [str_replace($chars, '_', $class) . '.' . str_replace($chars, '_', $function)];
        } else {
            $tags = [str_replace($chars, '_', $class)];
        }

        return $cache->invalidateTags($tags);
    }
}
