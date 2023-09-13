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

    private function __construct()
    {
    }

    private function __destruct()
    {
    }

    private function __clone()
    {
    }


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
            $logData = [
                'startTime' => date('Y-m-d H:i:s', self::$startTime),
            ];
            $logger->info('', ['Start', $logData]);

            $childPid = 0;
            $childCount = 0;
            foreach ($jobs as $_handler => list($_args, $_timeLimit)) {
                $childPid = pcntl_fork();
                if ($childPid === -1) {
                    $logger->critical('', ['Unable fork', $logData]);
                } else if ($childPid) {
                    // main process
                    ++$childCount;
                } else {
                    // child process
                    $logData['args'] = $_args;
                    $pid = posix_getpid();
                    $lockFile = $lockPath . '/' . str_replace('\\', '.', $_handler) . '.lock';
                    if (file_exists($lockFile)) {
                        $lastProcess = @file_get_contents($lockFile);
                        if ($lastProcess) {
                            list($lastPid, $lastTime, $lastLimit) = explode('|', $lastProcess);
                            if (posix_kill((int)$lastPid, 0)) {
                                $logData['lastTime'] = date('Y-m-d H:i:s', (int)$lastTime);
                                $logData['timeLimit'] = $lastLimit;
                                if (self::$startTime - $lastLimit <= $lastTime) {
                                    $logger->warning($_handler, ['Last running', $logData]);
                                    posix_kill($pid, SIGKILL);
                                    exit;
                                } else {
                                    $logger->warning($_handler, ['Kill last', $logData]);
                                    posix_kill((int)$lastPid, SIGKILL);
                                    sleep(1);
                                }
                            }
                        }
                    }

                    file_put_contents($lockFile, $pid . '|' . self::$startTime . '|' . $_timeLimit, LOCK_EX);
                    if (is_callable($_handler)) {
                        $logger->info($_handler, ['Running', $logData]);

                        $start = microtime(true);
                        call_user_func_array($_handler, $_args);
                        $logData['runtime'] = number_format((microtime(true) - $start), 3);
                        $logger->info($_handler, ['Finish', $logData]);
                    } else {
                        $logger->error($_handler, ['Missing handler', $logData]);
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
                $logger->info('', ['End', $logData]);
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
                    $handler = is_string($_job['handler']) ? $_job['handler'] : join('::', $_job['handler']);
                    $jobs[$handler] = [
                        $_job['args'],
                        $_job['timeLimit'] ?? self::TIME_LIMIT
                    ];
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
    public static function call(callable $handler, array $args = []): JobOption
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
