<?php

declare(strict_types=1);

namespace App\Validators;

use App\Contracts\SeguimientoValidatorInterface;
use App\Exceptions\ValidationException;
use DateTimeImmutable;

class SeguimientoValidator implements SeguimientoValidatorInterface
{
    private const ALLOWED_STATES = [
        'registrada',
        'en_revision',
        'aprobada',
        'rechazada',
        'finalizada',
    ];

    public function validateCreate(array $data, ?string $defaultUsuario = null): array
    {
        $clean = [];
        $errors = [];

        $incapacidadId = $this->parsePositiveInt($data['incapacidad_id'] ?? null);
        if ($incapacidadId === null) {
            $errors['incapacidad_id'] = 'La incapacidad asociada es obligatoria y debe ser un identificador numérico positivo.';
        } else {
            $clean['incapacidad_id'] = $incapacidadId;
        }

        $fecha = $this->extractTrimmedString($data, 'fecha');
        if ($fecha === '') {
            $clean['fecha'] = (new DateTimeImmutable('today'))->format('Y-m-d');
        } elseif (!$this->isValidDate($fecha)) {
            $errors['fecha'] = 'La fecha debe tener un formato válido (YYYY-MM-DD).';
        } else {
            $clean['fecha'] = $fecha;
        }

        $comentario = $this->extractTrimmedString($data, 'comentario');
        if ($comentario === '') {
            $errors['comentario'] = 'El comentario de seguimiento es obligatorio y no puede estar vacío.';
        } elseif (mb_strlen($comentario) < 3) {
            $errors['comentario'] = 'El comentario de seguimiento debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($comentario) > 2000) {
            $errors['comentario'] = 'El comentario de seguimiento no puede superar 2000 caracteres.';
        } else {
            $clean['comentario'] = $comentario;
        }

        $estado = strtolower($this->extractTrimmedString($data, 'estado'));
        if ($estado === '') {
            $errors['estado'] = 'El estado actualizado es obligatorio.';
        } elseif (!in_array($estado, self::ALLOWED_STATES, true)) {
            $errors['estado'] = 'Estado no válido. Valores permitidos: ' . implode(', ', self::ALLOWED_STATES) . '.';
        } else {
            $clean['estado'] = $estado;
        }

        $usuarioResponsable = $this->extractTrimmedString($data, 'usuario_responsable');
        if ($usuarioResponsable === '' && $defaultUsuario !== null) {
            $usuarioResponsable = trim($defaultUsuario);
        }

        if ($usuarioResponsable === '') {
            $errors['usuario_responsable'] = 'El usuario responsable es obligatorio.';
        } elseif (mb_strlen($usuarioResponsable) > 100) {
            $errors['usuario_responsable'] = 'El usuario responsable no puede superar 100 caracteres.';
        } else {
            $clean['usuario_responsable'] = $usuarioResponsable;
        }

        if ($errors !== []) {
            throw new ValidationException('Los datos del seguimiento no son válidos.', $errors);
        }

        return $clean;
    }

    public function validateStatusUpdate(array $data, ?string $defaultUsuario = null): array
    {
        $clean = [];
        $errors = [];

        $estado = strtolower($this->extractTrimmedString($data, 'estado'));
        if ($estado === '') {
            $errors['estado'] = 'El nuevo estado es obligatorio.';
        } elseif (!in_array($estado, self::ALLOWED_STATES, true)) {
            $errors['estado'] = 'Estado no válido. Valores permitidos: ' . implode(', ', self::ALLOWED_STATES) . '.';
        } else {
            $clean['estado'] = $estado;
        }

        $comentario = $this->extractTrimmedString($data, 'comentario');
        if ($comentario === '') {
            $errors['comentario'] = 'Debe incluir un comentario que justifique el cambio de estado.';
        } elseif (mb_strlen($comentario) < 3) {
            $errors['comentario'] = 'El comentario debe tener al menos 3 caracteres.';
        } elseif (mb_strlen($comentario) > 2000) {
            $errors['comentario'] = 'El comentario no puede superar 2000 caracteres.';
        } else {
            $clean['comentario'] = $comentario;
        }

        $fecha = $this->extractTrimmedString($data, 'fecha');
        if ($fecha === '') {
            $clean['fecha'] = (new DateTimeImmutable('today'))->format('Y-m-d');
        } elseif (!$this->isValidDate($fecha)) {
            $errors['fecha'] = 'La fecha debe tener un formato válido (YYYY-MM-DD).';
        } else {
            $clean['fecha'] = $fecha;
        }

        $usuarioResponsable = $this->extractTrimmedString($data, 'usuario_responsable');
        if ($usuarioResponsable === '' && $defaultUsuario !== null) {
            $usuarioResponsable = trim($defaultUsuario);
        }

        if ($usuarioResponsable === '') {
            $errors['usuario_responsable'] = 'El usuario responsable es obligatorio.';
        } elseif (mb_strlen($usuarioResponsable) > 100) {
            $errors['usuario_responsable'] = 'El usuario responsable no puede superar 100 caracteres.';
        } else {
            $clean['usuario_responsable'] = $usuarioResponsable;
        }

        if ($errors !== []) {
            throw new ValidationException('Los datos para actualizar el estado no son válidos.', $errors);
        }

        return $clean;
    }

    public function validateFilters(array $queryParams): array
    {
        $filters = [];
        $errors = [];

        if (isset($queryParams['incapacidad_id']) && trim((string) $queryParams['incapacidad_id']) !== '') {
            $incapacidadId = $this->parsePositiveInt($queryParams['incapacidad_id']);
            if ($incapacidadId === null) {
                $errors['incapacidad_id'] = 'El filtro incapacidad_id debe ser un número entero positivo.';
            } else {
                $filters['incapacidad_id'] = $incapacidadId;
            }
        }

        $estado = strtolower($this->extractTrimmedString($queryParams, 'estado'));
        if ($estado !== '') {
            if (!in_array($estado, self::ALLOWED_STATES, true)) {
                $errors['estado'] = 'El filtro estado no es válido.';
            } else {
                $filters['estado'] = $estado;
            }
        }

        $usuarioResponsable = $this->extractTrimmedString($queryParams, 'usuario_responsable');
        if ($usuarioResponsable !== '') {
            $filters['usuario_responsable'] = $usuarioResponsable;
        }

        foreach (['fecha', 'fecha_inicio', 'fecha_fin'] as $dateField) {
            $rawDate = $this->extractTrimmedString($queryParams, $dateField);
            if ($rawDate !== '') {
                if (!$this->isValidDate($rawDate)) {
                    $errors[$dateField] = sprintf('El filtro %s debe tener formato YYYY-MM-DD.', $dateField);
                } else {
                    $filters[$dateField] = $rawDate;
                }
            }
        }

        if (isset($filters['fecha_inicio'], $filters['fecha_fin'])) {
            if ($filters['fecha_fin'] < $filters['fecha_inicio']) {
                $errors['fecha_fin'] = 'El filtro fecha_fin no puede ser menor que fecha_inicio.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Los filtros de búsqueda no son válidos.', $errors);
        }

        return $filters;
    }

    private function isValidDate(string $date): bool
    {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsedDate !== false && $parsedDate->format('Y-m-d') === $date;
    }

    /**
     * @param mixed $value
     */
    private function parsePositiveInt($value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && ctype_digit($trimmed) && (int) $trimmed > 0) {
                return (int) $trimmed;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function extractTrimmedString(array $source, string $key): string
    {
        if (!isset($source[$key]) || !is_scalar($source[$key])) {
            return '';
        }

        return trim((string) $source[$key]);
    }
}
