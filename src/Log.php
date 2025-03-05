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

use Exception;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Throwable;

class Log
{
    public static $instance = [];

    private function __construct() {}

    private function __destruct() {}

    private function __clone() {}

    /**
     * Initializes the instance
     * 
     * @param string $logName 
     * @param int $maxFiles 
     * @param null|int $filePermission 
     * @return Logger 
     */
    public static function init(string $logName, int $maxFiles = 7, ?int $filePermission = null): Logger
    {
        $logName = trim($logName, '/');
        if (!isset(self::$instance[$logName]) || !(self::$instance[$logName] instanceof Logger)) {
            $configPath = App::root(Config::get('app', 'storagePath') ?: 'storage') . '/log';
            if (!is_dir($configPath) && !@mkdir($configPath, 0777, true)) {
                throw new Exception('Failed to create log directory.');
            }

            self::$instance[$logName] = new Logger($logName);
            self::$instance[$logName]->pushHandler(new RotatingFileHandler($configPath . '/' . $logName . '.log', $maxFiles, 'debug', true, $filePermission, true));
        }

        return self::$instance[$logName];
    }

    /**
     * Default error log
     * 
     * @param Throwable $t 
     */
    public static function error(Throwable $t)
    {
        $logger = self::init('error/alight');
        $trace = $t->getTrace();
        if (isset($_SERVER['REQUEST_URI'])) {
            array_unshift($trace, ['uri' => $_SERVER['REQUEST_URI']]);
        }
        $logger->error($t->getMessage(), $trace);
    }
}
