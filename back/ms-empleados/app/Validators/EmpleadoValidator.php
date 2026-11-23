<?php

declare(strict_types=1);

namespace App\Validators;

use App\Contracts\EmpleadoValidatorInterface;
use App\Exceptions\ValidationException;
use DateTimeImmutable;

class EmpleadoValidator implements EmpleadoValidatorInterface
{
    private const REQUIRED_FIELDS = [
        'nombres'       => 'Los nombres son obligatorios.',
        'apellidos'     => 'Los apellidos son obligatorios.',
        'documento'     => 'El documento de identificación es obligatorio.',
        'correo'        => 'El correo electrónico es obligatorio.',
        'telefono'      => 'El teléfono es obligatorio.',
        'cargo'         => 'El cargo es obligatorio.',
        'area'          => 'El área es obligatoria.',
        'fecha_ingreso' => 'La fecha de ingreso es obligatoria.',
    ];

    private const MAX_LENGTHS = [
        'nombres'   => 100,
        'apellidos' => 100,
        'documento' => 30,
        'correo'    => 150,
        'telefono'  => 30,
        'cargo'     => 100,
        'area'      => 100,
    ];

    private const EDITABLE_FIELDS = [
        'nombres',
        'apellidos',
        'documento',
        'correo',
        'telefono',
        'cargo',
        'area',
        'fecha_ingreso',
        'estado',
    ];

    private const ALLOWED_STATES = ['activo', 'inactivo'];

    private const MIN_HIRE_DATE = '1950-01-01';

    public function validateCreate(array $data): array
    {
        $clean = [];
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field => $requiredMessage) {
            $value = $this->extractTrimmedString($data, $field);
            if ($value === '') {
                $errors[$field] = $requiredMessage;
                continue;
            }

            $clean[$field] = $value;
        }

        $estado = $this->extractTrimmedString($data, 'estado');
        $clean['estado'] = $estado === '' ? 'activo' : strtolower($estado);

        $this->applyBusinessFieldRules($clean, $errors);

        if ($errors !== []) {
            throw new ValidationException('Los datos del empleado no son válidos.', $errors);
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

            $value = $this->extractTrimmedString($data, $field);
            if ($value === '') {
                $errors[$field] = sprintf('El campo %s no puede quedar vacío.', $field);
                continue;
            }

            $clean[$field] = $field === 'estado' ? strtolower($value) : $value;
        }

        if ($clean === [] && $errors === []) {
            throw new ValidationException('Debe enviar al menos un campo para actualizar el empleado.', [
                'payload' => 'No se recibieron campos editables.',
            ]);
        }

        $this->applyBusinessFieldRules($clean, $errors);

        if ($errors !== []) {
            throw new ValidationException('Los datos de actualización del empleado no son válidos.', $errors);
        }

        return $clean;
    }

    public function validateStatusChange(array $data): string
    {
        $estado = strtolower($this->extractTrimmedString($data, 'estado'));

        if ($estado === '') {
            throw new ValidationException('El estado del empleado es obligatorio.', [
                'estado' => 'Debe indicar el nuevo estado (activo o inactivo).',
            ]);
        }

        if (!in_array($estado, self::ALLOWED_STATES, true)) {
            throw new ValidationException('El estado indicado no es válido.', [
                'estado' => 'El estado debe ser activo o inactivo.',
            ]);
        }

        return $estado;
    }

    public function validateFilters(array $queryParams): array
    {
        $filters = [];
        $errors = [];

        $documento = $this->extractTrimmedString($queryParams, 'documento');
        if ($documento !== '') {
            $filters['documento'] = $documento;
        }

        $area = $this->extractTrimmedString($queryParams, 'area');
        if ($area !== '') {
            $filters['area'] = $area;
        }

        $estado = strtolower($this->extractTrimmedString($queryParams, 'estado'));
        if ($estado !== '') {
            if (!in_array($estado, self::ALLOWED_STATES, true)) {
                $errors['estado'] = 'El filtro de estado debe ser activo o inactivo.';
            } else {
                $filters['estado'] = $estado;
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Los filtros de búsqueda no son válidos.', $errors);
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $clean
     * @param array<string, string> $errors
     */
    private function applyBusinessFieldRules(array &$clean, array &$errors): void
    {
        foreach (self::MAX_LENGTHS as $field => $maxLength) {
            if (isset($clean[$field]) && mb_strlen((string) $clean[$field]) > $maxLength) {
                $errors[$field] = sprintf('El campo %s no puede superar %d caracteres.', $field, $maxLength);
            }
        }

        if (isset($clean['documento']) && !isset($errors['documento'])) {
            if (!preg_match('/^[A-Za-z0-9\-]{5,30}$/', (string) $clean['documento'])) {
                $errors['documento'] = 'El documento debe tener entre 5 y 30 caracteres alfanuméricos sin espacios.';
            }
        }

        if (isset($clean['correo']) && !isset($errors['correo'])) {
            $clean['correo'] = strtolower((string) $clean['correo']);
            if (filter_var($clean['correo'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['correo'] = 'El correo electrónico no tiene un formato válido.';
            }
        }

        if (isset($clean['telefono']) && !isset($errors['telefono'])) {
            if (!preg_match('/^[0-9+\-\s()]{7,30}$/', (string) $clean['telefono'])) {
                $errors['telefono'] = 'El teléfono debe contener entre 7 y 30 caracteres numéricos válidos.';
            }
        }

        if (isset($clean['fecha_ingreso']) && !isset($errors['fecha_ingreso'])) {
            $dateValidationError = $this->validateHireDate((string) $clean['fecha_ingreso']);
            if ($dateValidationError !== null) {
                $errors['fecha_ingreso'] = $dateValidationError;
            }
        }

        if (isset($clean['estado']) && !in_array($clean['estado'], self::ALLOWED_STATES, true)) {
            $errors['estado'] = 'El estado debe ser activo o inactivo.';
        }
    }

    private function validateHireDate(string $date): ?string
    {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            return 'La fecha de ingreso debe tener un formato válido (YYYY-MM-DD).';
        }

        $minDate = new DateTimeImmutable(self::MIN_HIRE_DATE);
        $today = new DateTimeImmutable('today');

        if ($parsedDate < $minDate) {
            return 'La fecha de ingreso es anterior al rango permitido.';
        }

        if ($parsedDate > $today) {
            return 'La fecha de ingreso no puede ser una fecha futura.';
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
