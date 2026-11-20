<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class AuthenticationException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 401)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
