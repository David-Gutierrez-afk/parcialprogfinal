<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\JsonResponderInterface;
use Psr\Http\Message\ResponseInterface as Response;

class JsonResponder implements JsonResponderInterface
{
    public function success(Response $response, array $payload, int $status = 200): Response
    {
        $body = array_merge(['ok' => true], $payload);

        return $this->writeJson($response, $body, $status);
    }

    public function error(Response $response, string $message, int $status = 400, array $errors = []): Response
    {
        $body = [
            'ok'      => false,
            'mensaje' => $message,
        ];

        if ($errors !== []) {
            $body['errores'] = $errors;
        }

        return $this->writeJson($response, $body, $status);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function writeJson(Response $response, array $body, int $status): Response
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($json !== false ? $json : '{}');

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
