<?php

declare(strict_types=1);

namespace App\Presentation\Repositories;

use App\Contracts\EmpleadoServiceInterface;
use App\Contracts\EmpleadoValidatorInterface;
use App\Contracts\JsonResponderInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class EmpleadoRepository
{
    private EmpleadoServiceInterface $service;
    private EmpleadoValidatorInterface $validator;
    private JsonResponderInterface $responder;

    public function __construct(
        EmpleadoServiceInterface $service,
        EmpleadoValidatorInterface $validator,
        JsonResponderInterface $responder
    ) {
        $this->service = $service;
        $this->validator = $validator;
        $this->responder = $responder;
    }

    public function health(Request $request, Response $response): Response
    {
        return $this->responder->success($response, [
            'servicio' => 'ms-empleados',
            'estado'   => 'activo',
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        try {
            $empleados = $this->service->list();

            return $this->responder->success($response, [
                'data'  => $empleados->toArray(),
                'total' => $empleados->count(),
            ], 200);
        } catch (Throwable $ex) {
            return $this->responder->error($response, 'Error interno al consultar los empleados.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $this->parseId($args['id'] ?? '');
            $empleado = $this->service->findById($id);

            return $this->responder->success($response, [
                'data' => $empleado->toArray(),
            ], 200);
        } catch (ValidationException $ex) {
            return $this->responder->error($response, $ex->getMessage(), 422, $ex->getErrors());
        } catch (NotFoundException $ex) {
            return $this->responder->error($response, $ex->getMessage(), 404);
        } catch (Throwable $ex) {
            return $this->responder->error($response, 'Error interno al consultar el empleado.', 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $payload = $this->parseJsonBody($request);
            $validated = $this->validator->validateCreate($payload);
            $empleado = $this->service->create($validated);

            return $this->responder->success($response, [
                'mensaje' => 'Empleado registrado correctamente.',
                'data'    => $empleado->toArray(),
            ], 201);
        } catch (ValidationException $ex) {
            return $this->responder->error($response, $ex->getMessage(), 422, $ex->getErrors());
        } catch (ConflictException $ex) {
            return $this->responder->error($response, $ex->getMessage(), 409);
        } catch (Throwable $ex) {
            return $this->responder->error($response, 'Error interno al registrar el empleado.', 500);
        }
    }

    private function parseId(string $rawId): int
    {
        if (!ctype_digit($rawId) || (int) $rawId <= 0) {
            throw new ValidationException('Identificador de empleado inválido.', [
                'id' => 'El id debe ser un número entero positivo.',
            ]);
        }

        return (int) $rawId;
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
