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
    /**
     *
     * @param string|null $body HTML response body or redirect URL.
     */
    public function __construct(protected mixed $statusCode, string $message = '', protected ?string $body = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): mixed
    {
        return $this->statusCode;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
}
