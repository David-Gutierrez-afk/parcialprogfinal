<?php

declare(strict_types=1);

namespace App\Contracts;

use Psr\Http\Message\ResponseInterface as Response;

interface JsonResponderInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function success(Response $response, array $payload, int $status = 200): Response;

    /**
     * @param array<string, mixed> $errors
     */
    public function error(Response $response, string $message, int $status = 400, array $errors = []): Response;
}
