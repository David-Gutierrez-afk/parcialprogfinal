<?php

declare(strict_types=1);

namespace App\Presentation\Repositories;

use App\Contracts\JsonResponderInterface;
use App\Contracts\SeguimientoServiceInterface;
use App\Contracts\SeguimientoValidatorInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\AuthSession;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class SeguimientoRepository
{
    private SeguimientoServiceInterface $service;
    private SeguimientoValidatorInterface $validator;
    private JsonResponderInterface $responder;

    public function __construct(
        SeguimientoServiceInterface $service,
        SeguimientoValidatorInterface $validator,
        JsonResponderInterface $responder
    ) {
        $this->service = $service;
        $this->validator = $validator;
        $this->responder = $responder;
    }

    public function health(Request $request, Response $response): Response
    {
        return $this->responder->success($response, [
            'servicio' => 'ms-seguimiento',
            'estado'   => 'activo',
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $filters = $this->validator->validateFilters($queryParams);
            $seguimientos = $this->service->list($filters);

            return $this->responder->success($response, [
                'data'    => $seguimientos->toArray(),
                'total'   => $seguimientos->count(),
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
            return $this->responder->error($response, 'Error interno al consultar los seguimientos.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $id = $this->parseId($args['id'] ?? '', 'id');
            $seguimiento = $this->service->findById($id);

            return $this->responder->success($response, [
                'data' => $seguimiento->toArray(),
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
            return $this->responder->error($response, 'Error interno al consultar el seguimiento.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function historyByIncapacidad(Request $request, Response $response, array $args): Response
    {
        try {
            $incapacidadId = $this->parseId($args['incapacidad_id'] ?? '', 'incapacidad_id');
            $history = $this->service->getHistoryByIncapacidad($incapacidadId);

            return $this->responder->success($response, $history, 200);
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
            return $this->responder->error($response, 'Error interno al consultar el historial de la incapacidad.', 500);
        }
    }

    public function store(Request $request, Response $response): Response
    {
        try {
            $payload = $this->parseJsonBody($request);
            $defaultUsuario = $this->extractAuthenticatedUsername($request);
            $validated = $this->validator->validateCreate($payload, $defaultUsuario);
            $result = $this->service->register($validated);

            return $this->responder->success($response, [
                'mensaje'         => 'Seguimiento registrado correctamente.',
                'data'            => $result['seguimiento'],
                'incapacidad'     => $result['incapacidad'],
                'estado_anterior' => $result['estado_anterior'],
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
            return $this->responder->error($response, 'Error interno al registrar el seguimiento.', 500);
        }
    }

    /**
     * @param array<string, string> $args
     */
    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        try {
            $incapacidadId = $this->parseId($args['incapacidad_id'] ?? '', 'incapacidad_id');
            $payload = $this->parseJsonBody($request);
            $defaultUsuario = $this->extractAuthenticatedUsername($request);
            $validated = $this->validator->validateStatusUpdate($payload, $defaultUsuario);
            $result = $this->service->updateIncapacidadStatus($incapacidadId, $validated);

            return $this->responder->success($response, [
                'mensaje'         => sprintf('Estado actualizado a %s y registrado en el historial.', (string) $validated['estado']),
                'data'            => $result['seguimiento'],
                'incapacidad'     => $result['incapacidad'],
                'estado_anterior' => $result['estado_anterior'],
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

    private function extractAuthenticatedUsername(Request $request): ?string
    {
        $authenticatedUser = $request->getAttribute('auth_user');
        if ($authenticatedUser instanceof AuthSession && is_string($authenticatedUser->usuario)) {
            return $authenticatedUser->usuario;
        }

        return null;
    }

    private function parseId(string $rawId, string $fieldName): int
    {
        if (!ctype_digit($rawId) || (int) $rawId <= 0) {
            throw new ValidationException(sprintf('Identificador %s inválido.', $fieldName), [
                $fieldName => 'Debe ser un número entero positivo.',
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
