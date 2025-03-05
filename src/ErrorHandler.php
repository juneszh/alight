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

use Whoops\Run;
use Whoops\Exception\Formatter;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;

class ErrorHandler
{
    /**
     * Initializes error handler
     */
    public static function start()
    {
        $whoops = new Run;

        if (Request::method()) {
            if (Config::get('app', 'debug')) {
                if (Request::isAjax()) {
                    $whoops->pushHandler(function ($exception, $inspector, $run) {
                        Response::api(500, null, Formatter::formatExceptionAsDataArray($inspector, false));
                        return Handler::QUIT;
                    });
                } else {
                    $whoops->pushHandler(new PrettyPageHandler);
                }
            } else {
                $whoops->pushHandler(function ($exception, $inspector, $run) {
                    if (Request::isAjax()) {
                        Response::api(500);
                    } else {
                        Response::errorPage(500);
                    }
                    return Handler::QUIT;
                });
            }
        }

        $whoops->pushHandler(function ($exception, $inspector, $run) {
            $errorHandler = Config::get('app', 'errorHandler');
            if (is_callable($errorHandler)) {
                call_user_func_array($errorHandler, [$exception]);
            } else {
                Log::error($exception);
            }
            return Handler::DONE;
        });

        $whoops->register();
    }
}
