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

use RuntimeException;
use Throwable;

class ResponseException extends RuntimeException
{
    protected $statusCode;
    protected ?string $body;

    /**
     * 
     * @param mixed $code 
     * @param string $message 
     * @param null|string $body html or redirect url
     * @param null|Throwable $previous 
     */
    public function __construct($code, string $message = '', ?string $body = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->statusCode = $code;
        $this->body = $body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
}
