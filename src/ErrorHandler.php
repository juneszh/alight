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

use Whoops\Run;
use Whoops\Exception\Formatter;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;
use InvalidArgumentException;

class ErrorHandler
{
    /**
     * Initializes error handler
     * 
     * @throws InvalidArgumentException 
     */
    public static function init()
    {
        $customErrorHandler = Config::get('app', 'errorHandler');
        if (is_callable($customErrorHandler)) {
            call_user_func($customErrorHandler);
        } else {
            $whoops = new Run;

            if ($_SERVER['REQUEST_METHOD'] ?? '') {
                $isAjax = isAjaxRequest();
                if (Config::get('app', 'debug')) {
                    if ($isAjax) {
                        $whoops->pushHandler(function ($exception, $inspector, $run) {
                            apiResponse(500, Formatter::formatExceptionAsDataArray($inspector, false));
                            return Handler::QUIT;
                        });
                    } else {
                        $whoops->pushHandler(new PrettyPageHandler);
                    }
                } else {
                    if ($isAjax) {
                        $whoops->pushHandler(function ($exception, $inspector, $run) {
                            apiResponse(500);
                            return Handler::QUIT;
                        });
                    } else {
                        $whoops->pushHandler(function ($exception, $inspector, $run) {
                            http_response_code(500);
                            return Handler::QUIT;
                        });
                    }
                }
            }

            $whoops->pushHandler(function ($exception, $inspector, $run) {
                Log::error($exception);
                return Handler::DONE;
            });

            $whoops->register();
        }
    }
}
