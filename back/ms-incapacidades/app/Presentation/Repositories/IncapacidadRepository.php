<?php

declare(strict_types=1);

namespace App\Presentation\Repositories;

use App\Contracts\IncapacidadServiceInterface;
use App\Contracts\IncapacidadValidatorInterface;
use App\Contracts\JsonResponderInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class IncapacidadRepository
{
    private IncapacidadServiceInterface $service;
    private IncapacidadValidatorInterface $validator;
    private JsonResponderInterface $responder;

    public function __construct(
        IncapacidadServiceInterface $service,
        IncapacidadValidatorInterface $validator,
        JsonResponderInterface $responder
    ) {
        $this->service = $service;
        $this->validator = $validator;
        $this->responder = $responder;
    }

    public function health(Request $request, Response $response): Response
    {
        return $this->responder->success($response, [
            'servicio' => 'ms-incapacidades',
            'estado'   => 'activo',
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $filters = $this->validator->validateFilters($queryParams);
            $incapacidades = $this->service->list($filters);

            return $this->responder->success($response, [
                'data'    => $incapacidades->toArray(),
                'total'   => $incapacidades->count(),
                'filtros' => $filters,
            ], 200);
        } catch (ValidationException $validationException) {
            return $this->responder->error(
                $response,
                $validationException->getMessage(),
                422,
                $validationException->getErrors()
            );
        } catch (Throwable $unexpectedError) {
            return $this->responder->error($response, 'Error interno al consultar las incapacidades.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $this->parseId($args['id'] ?? '');
            $incapacidad = $this->service->findById($id);

            return $this->responder->success($response, [
                'data' => $incapacidad->toArray(),
            ], 200);
        } catch (ValidationException $validationException) {
            return $this->responder->error(
                $response,
                $validationException->getMessage(),
                422,
                $validationException->getErrors()
            );
        } catch (NotFoundException $notFoundException) {
            return $this->responder->error($response, $notFoundException->getMessage(), 404);
        } catch (Throwable $unexpectedError) {
            return $this->responder->error($response, 'Error interno al consultar la incapacidad.', 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $payload = $this->parseJsonBody($request);
            $validated = $this->validator->validateCreate($payload);
            $incapacidad = $this->service->create($validated);

            return $this->responder->success($response, [
                'mensaje' => 'Incapacidad médica registrada correctamente.',
                'data'    => $incapacidad->toArray(),
            ], 201);
        } catch (ValidationException $validationException) {
            return $this->responder->error(
                $response,
                $validationException->getMessage(),
                422,
                $validationException->getErrors()
            );
        } catch (NotFoundException $notFoundException) {
            return $this->responder->error($response, $notFoundException->getMessage(), 404);
        } catch (ConflictException $conflictException) {
            return $this->responder->error($response, $conflictException->getMessage(), 409);
        } catch (Throwable $unexpectedError) {
            return $this->responder->error($response, 'Error interno al registrar la incapacidad.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $this->parseId($args['id'] ?? '');
            $payload = $this->parseJsonBody($request);
            $validated = $this->validator->validateUpdate($payload);
            $incapacidad = $this->service->update($id, $validated);

            return $this->responder->success($response, [
                'mensaje' => 'Incapacidad médica actualizada correctamente.',
                'data'    => $incapacidad->toArray(),
            ], 200);
        } catch (ValidationException $validationException) {
            return $this->responder->error(
                $response,
                $validationException->getMessage(),
                422,
                $validationException->getErrors()
            );
        } catch (NotFoundException $notFoundException) {
            return $this->responder->error($response, $notFoundException->getMessage(), 404);
        } catch (ConflictException $conflictException) {
            return $this->responder->error($response, $conflictException->getMessage(), 409);
        } catch (Throwable $unexpectedError) {
            return $this->responder->error($response, 'Error interno al actualizar la incapacidad.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function finalize(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $this->parseId($args['id'] ?? '');
            $incapacidad = $this->service->finalize($id);

            return $this->responder->success($response, [
                'mensaje' => 'Incapacidad médica marcada como finalizada.',
                'data'    => $incapacidad->toArray(),
            ], 200);
        } catch (ValidationException $validationException) {
            return $this->responder->error(
                $response,
                $validationException->getMessage(),
                422,
                $validationException->getErrors()
            );
        } catch (NotFoundException $notFoundException) {
            return $this->responder->error($response, $notFoundException->getMessage(), 404);
        } catch (ConflictException $conflictException) {
            return $this->responder->error($response, $conflictException->getMessage(), 409);
        } catch (Throwable $unexpectedError) {
            return $this->responder->error($response, 'Error interno al finalizar la incapacidad.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function changeStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $this->parseId($args['id'] ?? '');
            $payload = $this->parseJsonBody($request);
            $nuevoEstado = $this->validator->validateStatusChange($payload);
            $incapacidad = $this->service->changeStatus($id, $nuevoEstado);

            return $this->responder->success($response, [
                'mensaje' => sprintf('Estado de la incapacidad actualizado a %s.', $nuevoEstado),
                'data'    => $incapacidad->toArray(),
            ], 200);
        } catch (ValidationException $validationException) {
            return $this->responder->error(
                $response,
                $validationException->getMessage(),
                422,
                $validationException->getErrors()
            );
        } catch (NotFoundException $notFoundException) {
            return $this->responder->error($response, $notFoundException->getMessage(), 404);
        } catch (ConflictException $conflictException) {
            return $this->responder->error($response, $conflictException->getMessage(), 409);
        } catch (Throwable $unexpectedError) {
            return $this->responder->error($response, 'Error interno al actualizar el estado de la incapacidad.', 500);
        }
    }

    private function parseId(string $rawId): int
    {
        if (!ctype_digit($rawId) || (int) $rawId <= 0) {
            throw new ValidationException('Identificador de incapacidad inválido.', [
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
