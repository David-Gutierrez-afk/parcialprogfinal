<?php

declare(strict_types=1);

namespace App\Validators;

use App\Contracts\IncapacidadValidatorInterface;
use App\Exceptions\ValidationException;
use DateTimeImmutable;

class IncapacidadValidator implements IncapacidadValidatorInterface
{
    private const ALLOWED_TYPES = [
        'enfermedad_general',
        'accidente_laboral',
        'licencia_medica',
        'incapacidad_temporal',
    ];

    private const ALLOWED_STATES = [
        'registrada',
        'en_revision',
        'aprobada',
        'rechazada',
        'finalizada',
    ];

    private const EDITABLE_FIELDS = [
        'fecha_inicio',
        'fecha_fin',
        'tipo',
        'diagnostico_general',
        'entidad_medica',
        'observaciones',
        'estado',
    ];

    public function validateCreate(array $data): array
    {
        $clean = [];
        $errors = [];

        $empleadoId = $this->parsePositiveInt($data['empleado_id'] ?? null);
        if ($empleadoId === null) {
            $errors['empleado_id'] = 'El empleado es obligatorio y debe ser un identificador numérico positivo.';
        } else {
            $clean['empleado_id'] = $empleadoId;
        }

        $fechaInicio = $this->extractTrimmedString($data, 'fecha_inicio');
        if ($fechaInicio === '') {
            $errors['fecha_inicio'] = 'La fecha de inicio es obligatoria.';
        } elseif (!$this->isValidDate($fechaInicio)) {
            $errors['fecha_inicio'] = 'La fecha de inicio debe tener un formato válido (YYYY-MM-DD).';
        } else {
            $clean['fecha_inicio'] = $fechaInicio;
        }

        $fechaFin = $this->extractTrimmedString($data, 'fecha_fin');
        if ($fechaFin === '') {
            $errors['fecha_fin'] = 'La fecha fin es obligatoria.';
        } elseif (!$this->isValidDate($fechaFin)) {
            $errors['fecha_fin'] = 'La fecha fin debe tener un formato válido (YYYY-MM-DD).';
        } else {
            $clean['fecha_fin'] = $fechaFin;
        }

        if (isset($clean['fecha_inicio'], $clean['fecha_fin'])) {
            if ($clean['fecha_fin'] < $clean['fecha_inicio']) {
                $errors['fecha_fin'] = 'La fecha fin no puede ser menor a la fecha de inicio.';
            }
        }

        $tipo = strtolower($this->extractTrimmedString($data, 'tipo'));
        if ($tipo === '') {
            $errors['tipo'] = 'El tipo de incapacidad es obligatorio.';
        } elseif (!in_array($tipo, self::ALLOWED_TYPES, true)) {
            $errors['tipo'] = 'Tipo de incapacidad no válido. Valores permitidos: ' . implode(', ', self::ALLOWED_TYPES) . '.';
        } else {
            $clean['tipo'] = $tipo;
        }

        $diagnostico = $this->extractTrimmedString($data, 'diagnostico_general');
        if ($diagnostico === '') {
            $errors['diagnostico_general'] = 'El diagnóstico general es obligatorio.';
        } elseif (mb_strlen($diagnostico) > 2000) {
            $errors['diagnostico_general'] = 'El diagnóstico general no puede superar 2000 caracteres.';
        } else {
            $clean['diagnostico_general'] = $diagnostico;
        }

        $entidadMedica = $this->extractTrimmedString($data, 'entidad_medica');
        if ($entidadMedica === '') {
            $errors['entidad_medica'] = 'La entidad médica es obligatoria.';
        } elseif (mb_strlen($entidadMedica) > 150) {
            $errors['entidad_medica'] = 'La entidad médica no puede superar 150 caracteres.';
        } else {
            $clean['entidad_medica'] = $entidadMedica;
        }

        $observaciones = $this->extractTrimmedString($data, 'observaciones');
        if (mb_strlen($observaciones) > 2000) {
            $errors['observaciones'] = 'Las observaciones no pueden superar 2000 caracteres.';
        } else {
            $clean['observaciones'] = $observaciones !== '' ? $observaciones : null;
        }

        $estado = strtolower($this->extractTrimmedString($data, 'estado'));
        if ($estado === '') {
            $clean['estado'] = 'registrada';
        } elseif (!in_array($estado, self::ALLOWED_STATES, true)) {
            $errors['estado'] = 'Estado no válido. Valores permitidos: ' . implode(', ', self::ALLOWED_STATES) . '.';
        } elseif ($estado === 'finalizada') {
            $errors['estado'] = 'No se puede registrar una incapacidad directamente en estado finalizada.';
        } else {
            $clean['estado'] = $estado;
        }

        if ($errors !== []) {
            throw new ValidationException('Los datos de la incapacidad no son válidos.', $errors);
        }

        return $clean;
    }

    public function validateUpdate(array $data): array
    {
        $clean = [];
        $errors = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'observaciones') {
                $observaciones = $this->extractTrimmedString($data, 'observaciones');
                if (mb_strlen($observaciones) > 2000) {
                    $errors['observaciones'] = 'Las observaciones no pueden superar 2000 caracteres.';
                } else {
                    $clean['observaciones'] = $observaciones !== '' ? $observaciones : null;
                }
                continue;
            }

            $value = $this->extractTrimmedString($data, $field);
            if ($value === '') {
                $errors[$field] = sprintf('El campo %s no puede quedar vacío.', $field);
                continue;
            }

            if ($field === 'fecha_inicio' || $field === 'fecha_fin') {
                if (!$this->isValidDate($value)) {
                    $errors[$field] = sprintf('El campo %s debe tener un formato válido (YYYY-MM-DD).', $field);
                } else {
                    $clean[$field] = $value;
                }
                continue;
            }

            if ($field === 'tipo') {
                $normalizedType = strtolower($value);
                if (!in_array($normalizedType, self::ALLOWED_TYPES, true)) {
                    $errors['tipo'] = 'Tipo de incapacidad no válido.';
                } else {
                    $clean['tipo'] = $normalizedType;
                }
                continue;
            }

            if ($field === 'estado') {
                $normalizedState = strtolower($value);
                if (!in_array($normalizedState, self::ALLOWED_STATES, true)) {
                    $errors['estado'] = 'Estado de incapacidad no válido.';
                } else {
                    $clean['estado'] = $normalizedState;
                }
                continue;
            }

            if ($field === 'entidad_medica' && mb_strlen($value) > 150) {
                $errors['entidad_medica'] = 'La entidad médica no puede superar 150 caracteres.';
                continue;
            }

            if ($field === 'diagnostico_general' && mb_strlen($value) > 2000) {
                $errors['diagnostico_general'] = 'El diagnóstico general no puede superar 2000 caracteres.';
                continue;
            }

            $clean[$field] = $value;
        }

        if ($clean === [] && $errors === []) {
            throw new ValidationException('Debe enviar al menos un campo para actualizar la incapacidad.', [
                'payload' => 'No se recibieron campos editables.',
            ]);
        }

        if (isset($clean['fecha_inicio'], $clean['fecha_fin'])) {
            if ($clean['fecha_fin'] < $clean['fecha_inicio']) {
                $errors['fecha_fin'] = 'La fecha fin no puede ser menor a la fecha de inicio.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Los datos de actualización de la incapacidad no son válidos.', $errors);
        }

        return $clean;
    }

    public function validateStatusChange(array $data): string
    {
        $estado = strtolower($this->extractTrimmedString($data, 'estado'));

        if ($estado === '') {
            throw new ValidationException('El nuevo estado es obligatorio.', [
                'estado' => 'Debe indicar el nuevo estado de la incapacidad.',
            ]);
        }

        if (!in_array($estado, self::ALLOWED_STATES, true)) {
            throw new ValidationException('El estado indicado no es válido.', [
                'estado' => 'Valores permitidos: ' . implode(', ', self::ALLOWED_STATES) . '.',
            ]);
        }

        return $estado;
    }

    public function validateFilters(array $queryParams): array
    {
        $filters = [];
        $errors = [];

        if (isset($queryParams['empleado_id']) && trim((string) $queryParams['empleado_id']) !== '') {
            $empleadoId = $this->parsePositiveInt($queryParams['empleado_id']);
            if ($empleadoId === null) {
                $errors['empleado_id'] = 'El filtro empleado_id debe ser un número entero positivo.';
            } else {
                $filters['empleado_id'] = $empleadoId;
            }
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

        $estado = strtolower($this->extractTrimmedString($queryParams, 'estado'));
        if ($estado !== '') {
            if (!in_array($estado, self::ALLOWED_STATES, true)) {
                $errors['estado'] = 'El filtro estado no es válido.';
            } else {
                $filters['estado'] = $estado;
            }
        }

        $tipo = strtolower($this->extractTrimmedString($queryParams, 'tipo'));
        if ($tipo !== '') {
            if (!in_array($tipo, self::ALLOWED_TYPES, true)) {
                $errors['tipo'] = 'El filtro tipo no es válido.';
            } else {
                $filters['tipo'] = $tipo;
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
