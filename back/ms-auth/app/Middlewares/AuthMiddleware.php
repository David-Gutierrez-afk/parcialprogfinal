<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Contracts\JsonResponderInterface;
use App\Contracts\TokenValidatorInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private TokenValidatorInterface $tokenValidator;
    private JsonResponderInterface $responder;

    public function __construct(
        TokenValidatorInterface $tokenValidator,
        JsonResponderInterface $responder
    ) {
        $this->tokenValidator = $tokenValidator;
        $this->responder = $responder;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->responder->error(
                new SlimResponse(),
                'Token de autenticación requerido.',
                401
            );
        }

        $usuario = $this->tokenValidator->validateToken($token);

        if ($usuario === null) {
            return $this->responder->error(
                new SlimResponse(),
                'Sesión inválida o expirada. Inicie sesión nuevamente.',
                401
            );
        }

        $authenticatedRequest = $request
            ->withAttribute('auth_user', $usuario)
            ->withAttribute('auth_token', $token);

        return $handler->handle($authenticatedRequest);
    }

    private function extractToken(Request $request): ?string
    {
        $authHeader = trim($request->getHeaderLine('Authorization'));
        if ($authHeader !== '') {
            if (stripos($authHeader, 'Bearer ') === 0) {
                $token = trim(substr($authHeader, 7));
                return $token !== '' ? $token : null;
            }

            return $authHeader;
        }

        $fallbackHeader = trim($request->getHeaderLine('X-Auth-Token'));
        return $fallbackHeader !== '' ? $fallbackHeader : null;
    }
}
