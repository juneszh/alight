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
    protected int $status;
    protected ?string $body;

    public function __construct(int $status = 500, string $message = '', ?string $body = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->status = $status;
        $this->body = $body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
}
