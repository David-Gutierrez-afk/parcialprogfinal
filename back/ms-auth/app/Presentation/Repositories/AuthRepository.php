<?php

declare(strict_types=1);

namespace App\Presentation\Repositories;

use App\Contracts\AuthServiceInterface;
use App\Contracts\AuthValidatorInterface;
use App\Contracts\JsonResponderInterface;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Models\Usuario;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class AuthRepository
{
    private AuthServiceInterface $authService;
    private AuthValidatorInterface $validator;
    private JsonResponderInterface $responder;

    public function __construct(
        AuthServiceInterface $authService,
        AuthValidatorInterface $validator,
        JsonResponderInterface $responder
    ) {
        $this->authService = $authService;
        $this->validator = $validator;
        $this->responder = $responder;
    }

    public function health(Request $request, Response $response): Response
    {
        return $this->responder->success($response, [
            'servicio' => 'ms-auth',
            'estado'   => 'activo',
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        try {
            $payload = $this->parseJsonBody($request);
            $credentials = $this->validator->validateLogin($payload);
            $result = $this->authService->login(
                $credentials['identificador'],
                $credentials['contrasena']
            );

            return $this->responder->success($response, [
                'mensaje' => 'Inicio de sesión exitoso.',
                'token'   => $result['token'],
                'usuario' => $result['usuario']->toArray(),
            ], 200);
        } catch (ValidationException $ex) {
            return $this->responder->error($response, $ex->getMessage(), 422, $ex->getErrors());
        } catch (AuthenticationException $ex) {
            return $this->responder->error($response, $ex->getMessage(), $ex->getStatusCode());
        } catch (Throwable $ex) {
            return $this->responder->error($response, 'Error interno al iniciar sesión.', 500);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        try {
            /** @var Usuario $usuario */
            $usuario = $request->getAttribute('auth_user');
            $this->authService->logout($usuario);

            return $this->responder->success($response, [
                'mensaje' => 'Sesión cerrada correctamente.',
            ], 200);
        } catch (Throwable $ex) {
            return $this->responder->error($response, 'Error interno al cerrar sesión.', 500);
        }
    }

    public function validate(Request $request, Response $response): Response
    {
        /** @var Usuario $usuario */
        $usuario = $request->getAttribute('auth_user');

        return $this->responder->success($response, [
            'valida'  => true,
            'usuario' => $usuario->toArray(),
        ], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(Request $request): array
    {
        $rawBody = (string) $request->getBody();
        if (trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }
}
