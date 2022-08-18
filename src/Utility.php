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
     * Create a Unique ID
     * 
     * @param int $length 
     * @return string 
     * @throws Exception 
     */
    public static function uid(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
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
     * Two-dimensional array filter
     * 
     * @param array $array 
     * @param array $filter 
     * @return array 
     */
    public static function arrayFilter(array $array, array $filter): array
    {
        if ($filter) {
            $array = array_filter($array, function ($value) use ($filter) {
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
            });
        }

        return $array;
    }
}
