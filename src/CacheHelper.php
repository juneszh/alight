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
     * @param array<int, mixed>|string $key Set a string as the cache key, or an argument list used to generate one.
     * @param int|null $time Positive values cache for seconds; 0/-1 deletes; below -1 updates; null bypasses cache.
     * @param mixed $value If it is an anonymous function, it will be called only when the cache expires. Return null to not save the cache.
     * @param int $nullTime Cache lifetime when the value callback returns null.
     * @param bool $addTag Whether to add a function-name invalidation tag.
     */
    public static function get(array|string $key = [], ?int $time = null, mixed $value = null, int $nullTime = 0, bool $addTag = true, string $configKey = ''): mixed
    {
        $return = null;

        if ($time === null) {
            $return = ($value instanceof Closure) ? $value() : $value;
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
                        $return = $cache->get($key[0], function (ItemInterface $item, bool &$save) use ($key, $time, $value, $nullTime, $addTag): mixed {
                            $tags = $addTag ? array_slice($key, 1) : [];
                            if ($tags) {
                                $item->tag($tags);
                            }

                            $return = $value();
                            if ($return === null) {
                                if ($nullTime > 0) {
                                    $item->expiresAfter($nullTime);
                                } else {
                                    $save = false;
                                }
                            } else {
                                $item->expiresAfter($time);
                            }

                            return $return;
                        });
                    } elseif ($value === null) {
                        $item = $cache->getItem($key[0]);
                        $return = $item->get();
                    } else {
                        $item = $cache->getItem($key[0]);

                        $tags = $addTag ? array_slice($key, 1) : [];
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
     * Get the cache expiry
     *
     */
    public static function getExpiry(array|string $key = [], string $configKey = ''): int
    {
        $return = 0;

        $key = is_array($key) ? self::key($key) : ($key ? [$key] : []);
        if ($key) {
            $cache = Cache::psr6($configKey);
            $item = $cache->getItem($key[0]);
            $return = $item->getMetadata()[ItemInterface::METADATA_EXPIRY] ?? 0;
        }

        return $return;
    }

    /**
     * Generate keys
     *
     * @param array<int, mixed> $args
     * @param callable|null $classFunction
     * @return list<string>
     */
    public static function key(array $args, ?callable $classFunction = null): array
    {
        $keyItems = [];
        $class = '';
        $function = '';
        $chars = str_split(ItemInterface::RESERVED_CHARACTERS);

        if (is_array($classFunction)) {
            $class = str_replace($chars, '_', is_object($classFunction[0]) ? $classFunction[0]::class : $classFunction[0]);
            $function = $classFunction[1];
        } else {
            $backtrace = debug_backtrace();
            if (isset($backtrace[1])) {
                if (($backtrace[1]['class'] ?? '') !== self::class) {
                    $target = $backtrace[1];
                } else {
                    $target = $backtrace[2] ?? [];
                }

                if ($target) {
                    $class = str_replace($chars, '_', $target['class'] ?? str_replace(App::root(), '', $target['file']));
                    $function = $target['function'];
                }
            }
        }

        array_walk($args, function (mixed &$value): void {
            if (is_array($value)) {
                $value = join('_', array_map(fn(mixed $_v): string => var_export($_v, true), $value));
            } elseif (is_bool($value) || is_null($value)) {
                $value = var_export($value, true);
            }
        });

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
     * @param array{0: object|class-string, 1?: string} $classFunction
     */
    public static function clear(array $classFunction, string $configKey = ''): bool
    {
        $cache = Cache::psr6($configKey);
        $chars = str_split(ItemInterface::RESERVED_CHARACTERS);

        $class = str_replace($chars, '_', is_object($classFunction[0]) ? $classFunction[0]::class : $classFunction[0]);
        $functions = isset($classFunction[1]) ? [$classFunction[1]] : get_class_methods($classFunction[0]);
        $tags = [];

        foreach ($functions as $function) {
            $key = $class . '.' . $function;
            $tags[] = $key;
            $cache->deleteItem($key);
        }

        return $cache->invalidateTags($tags);
    }
}
