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

use LogicException;

class Utility
{
    /**
     * Create a random hex string
     */
    public static function randomHex(int $length = 32): string
    {
        if ($length % 2 !== 0) {
            throw new LogicException('length must be even.');
        }

        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Create a unique number string
     */
    public static function uniqueNumber(int $length = 16): string
    {
        if ($length < 16) {
            throw new LogicException('Length must be greater than 15.');
        }

        $dateTime = date('ymdHis');
        $microTime = substr((string) floor(microtime(true) * 1000), -3);
        $randLength = $length - 15;
        $randNumber = str_pad((string) random_int(0, 10 ** $randLength - 1), $randLength, '0', STR_PAD_LEFT);
        return $dateTime . $microTime . $randNumber;
    }

    /**
     * Checks if it's an json format
     */
    public static function isJson(mixed $content): bool
    {
        if (!is_string($content) || !$content || is_numeric($content)) {
            return false;
        }

        $string = trim($content);
        if (!$string || !in_array($string[0], ['{', '['], true)) {
            return false;
        }

        return json_validate($content);
    }

    /**
     * Two-dimensional array filter and enum maker
     */
    public static function arrayFilter(array $array, array $filter = [], ?string $enumKey = null, ?string $enumValue = null): array
    {
        if ($array) {
            if ($filter) {
                $array = array_values(array_filter($array, function (array $value) use ($filter): bool {
                    foreach ($filter as $k => $v) {
                        $symbol = '';
                        $bracketStart = strrpos($k, '[');
                        if ($bracketStart) {
                            $symbol = substr($k, $bracketStart + 1, -1);
                            $k = substr($k, 0, $bracketStart);
                        }

                        if (!isset($value[$k])) {
                            return false;
                        } elseif (is_array($v)) {
                            if ($symbol === '!') {
                                if (in_array($value[$k], $v)) {
                                    return false;
                                }
                            } elseif ($symbol === '<>') {
                                if ($value[$k] < $v[0] && $value[$k] > $v[0]) {
                                    return false;
                                }
                            } elseif ($symbol === '><') {
                                if ($value[$k] >= $v[0] && $value[$k] <= $v[0]) {
                                    return false;
                                }
                            } else {
                                if (!in_array($value[$k], $v)) {
                                    return false;
                                }
                            }
                        } else {
                            if ($symbol === '!') {
                                if ($value[$k] == $v) {
                                    return false;
                                }
                            } elseif ($symbol === '>') {
                                if ($value[$k] <= $v) {
                                    return false;
                                }
                            } elseif ($symbol === '>=') {
                                if ($value[$k] < $v) {
                                    return false;
                                }
                            } elseif ($symbol === '<') {
                                if ($value[$k] >= $v) {
                                    return false;
                                }
                            } elseif ($symbol === '<=') {
                                if ($value[$k] > $v) {
                                    return false;
                                }
                            } else {
                                if ($value[$k] != $v) {
                                    return false;
                                }
                            }
                        }
                    }

                    return true;
                }));
            }

            if ($enumKey || $enumValue) {
                $array = array_column($array, $enumValue, $enumKey);
            }
        }

        return $array;
    }

    /**
     * Pad a leading zero to the number
     */
    public static function numberPad(int $number, int $length = 2): string
    {
        return str_pad((string)$number, $length, '0', STR_PAD_LEFT);
    }
}
