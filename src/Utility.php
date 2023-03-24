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

use Exception;

class Utility
{
    /**
     * Create a random hex string
     * 
     * @param int $length 
     * @return string 
     * @throws Exception 
     */
    public static function randomHex(int $length = 32): string
    {
        if ($length % 2 !== 0) {
            throw new Exception('length must be even.');
        }
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Create a unique number string
     * 
     * @param int $length 
     * @return string 
     * @throws Exception 
     */
    public static function uniqueNumber(int $length = 16): string
    {
        if ($length < 16) {
            throw new Exception('Length must be greater than 15.');
        }
        $dateTime = date('ymdHis');
        $microTime = substr((string) floor(microtime(true) * 1000), -3);
        $randLength = $length - 15;
        $randNumber = str_pad((string) random_int(0, pow(10, $randLength) - 1), $randLength, '0', STR_PAD_LEFT);
        return $dateTime . $microTime . $randNumber;
    }

    /**
     * Checks if it's an json format
     *
     * @param mixed $content
     * @return bool
     */
    public static function isJson($content): bool
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
     * Two-dimensional array filter and enum maker
     * 
     * @param array $array 
     * @param array $filter 
     * @param null|string $enumKey
     * @param null|string $enumValue
     * @return array 
     */
    public static function arrayFilter(array $array, array $filter = [], ?string $enumKey = null, ?string $enumValue = null): array
    {
        if ($array) {
            if ($filter) {
                $array = array_values(array_filter($array, function ($value) use ($filter) {
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
     * 
     * @param int $number 
     * @param int $length 
     * @return string 
     */
    public static function numberPad(int $number, int $length = 2): string
    {
        return str_pad((string)$number, $length, '0', STR_PAD_LEFT);
    }
}
