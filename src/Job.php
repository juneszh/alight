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
use InvalidArgumentException;

class Job
{
    public static array $config = [];
    private static int $index = 0;
    public static int $startTime;
    private const TIME_LIMIT = 3600;

    private function __construct() {}

    private function __destruct() {}

    private function __clone() {}


    /**
     * Start run with fork process
     * 
     * @return never 
     * @throws InvalidArgumentException 
     * @throws Exception 
     */
    public static function start()
    {
        $timezone = Config::get('app', 'timezone');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }

        ErrorHandler::start();

        self::$startTime = time();

        $jobs = self::getJobs();
        if ($jobs) {
            $lockPath = App::root(Config::get('app', 'storagePath') ?: 'storage') . '/job';
            if (!is_dir($lockPath)) {
                if (!mkdir($lockPath, 0777, true)) {
                    throw new Exception('Failed to create job lock directory.');
                }
            }

            $logger = Log::init('job/scheduler');

            $start = microtime(true);
            $childPid = 0;
            $childCount = 0;
            foreach ($jobs as $_key => list($_handler, $_args, $_timeLimit)) {
                $lockFile = $lockPath . '/' . str_replace('\\', '.', $_key) . '.lock';
                if (file_exists($lockFile)) {
                    $lastProcess = @file_get_contents($lockFile);
                    if ($lastProcess) {
                        list($lastPid, $lastTime, $lastLimit) = explode('|', $lastProcess);
                        if (posix_kill((int)$lastPid, 0)) {
                            if ($lastLimit <= 0) {
                                continue;
                            } elseif (self::$startTime - $lastLimit <= $lastTime) {
                                $logger->warning($_handler, ['Last running', ['lastTime' => date('Y-m-d H:i:s', (int)$lastTime), 'timeLimit' => $lastLimit]]);
                                continue;
                            } else {
                                $logger->warning($_handler, ['Kill last', ['lastTime' => date('Y-m-d H:i:s', (int)$lastTime), 'timeLimit' => $lastLimit]]);
                                posix_kill((int)$lastPid, SIGKILL);
                                sleep(1);
                            }
                        }
                    }
                }

                $childPid = pcntl_fork();
                if ($childPid === -1) {
                    $logger->critical('', ['Unable fork']);
                } else if ($childPid) {
                    // main process
                    if ($childCount === 0) {
                        $logger->info('', ['Start']);
                    }
                    ++$childCount;
                } else {
                    // child process
                    $pid = posix_getpid();

                    file_put_contents($lockFile, $pid . '|' . self::$startTime . '|' . $_timeLimit, LOCK_EX);
                    $logData = $_args ? ['args' => $_args] : [];
                    if (is_callable($_handler)) {
                        $logger->info($_handler, array_merge(['Run'], $logData ? [$logData] : []));

                        $_start = microtime(true);
                        call_user_func_array($_handler, $_args);
                        $logData['runTime'] = number_format((microtime(true) - $_start), 3);

                        $logger->info($_handler, array_merge(['Done'], $logData ? [$logData] : []));
                    } else {
                        $logger->error($_handler, array_merge(['Missing handler'], $logData ? [$logData] : []));
                    }
                    // must break foreach in child process
                    break;
                }
            }

            if ($childPid) {
                // main process
                $status = null;
                for ($i = 0; $i < $childCount; ++$i) {
                    pcntl_wait($status, 0);
                }
                $logger->info('', ['End', ['startTime' => date('Y-m-d H:i:s', self::$startTime), 'runTime' => number_format((microtime(true) - $start), 3)]]);
            }
        }
        exit;
    }

    /**
     * Get the jobs to run this time 
     * 
     * @return array 
     * @throws Exception 
     */
    private static function getJobs(): array
    {
        $jobs = [];

        $configjob = Config::get('job');
        if ($configjob) {
            $file = App::root($configjob);
            if (!is_file($file)) {
                throw new Exception('Missing job file: ' . $file . '.');
            }
            require $file;
        }

        if (self::$config) {
            $rules = self::getRules();
            foreach (self::$config as $_job) {
                if (isset($rules[$_job['rule']])) {
                    $_handler = is_string($_job['handler']) ? $_job['handler'] : join('::', $_job['handler']);
                    $_key = $_handler . ($_job['args'] ? '.' . md5(json_encode($_job['args'])) : '');
                    $_timeLimit = $_job['timeLimit'] ?? self::TIME_LIMIT;

                    if (!isset($jobs[$_key])) {
                        $jobs[$_key] = [
                            $_handler,
                            $_job['args'],
                            $_timeLimit
                        ];
                    } elseif ($jobs[$_key]['timeLimit'] > $_timeLimit) {
                        $jobs[$_key]['timeLimit'] = $_timeLimit;
                    }
                }
            }
        }

        return $jobs;
    }

    /**
     * Get the rules to run this time 
     * 
     * @return array 
     */
    private static function getRules(): array
    {
        list($Y, $m, $d, $w, $H, $i) = explode(' ', date('Y m d w H i', self::$startTime));

        $rules = [
            '*' => true,
            '*/1' => true,
            '*/1:' . $i => true,
            $i => true,
            $H . ':' . $i => true,
            $w . ' ' . $H . ':' . $i => true,
            $d . ' ' . $H . ':' . $i => true,
            $m . '-' . $d . ' ' . $H . ':' . $i => true,
            $Y . '-' . $m . '-' . $d . ' ' . $H . ':' . $i => true,
        ];

        for ($iSub = 2; $iSub <= 59; ++$iSub) {
            if ($i % $iSub === 0) {
                $rules['*/' . $iSub] = true;
            }
        }

        for ($hSub = 2; $hSub <= 23; ++$hSub) {
            if ($H % $hSub === 0) {
                $rules['*/' . $hSub . ':' . $i] = true;
            }
        }

        return $rules;
    }

    /**
     * Push a handler to scheduler (Default is minutely)
     * 
     * @param callable $handler 
     * @param array $args
     * @return JobOption 
     */
    public static function call($handler, array $args = []): JobOption
    {
        ++self::$index;
        self::$config[self::$index] = [
            'handler' => $handler,
            'args' => $args,
            'rule' => '*'
        ];

        return new JobOption(self::$index);
    }
}
