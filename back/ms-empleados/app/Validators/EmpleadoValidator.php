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

    private const ALLOWED_STATES = ['activo', 'inactivo'];

    public function validateCreate(array $data): array
    {
        $clean = [];
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field => $message) {
            $value = isset($data[$field]) && is_scalar($data[$field])
                ? trim((string) $data[$field])
                : '';

            if ($value === '') {
                $errors[$field] = $message;
            } else {
                $clean[$field] = $value;
            }
        }

        if (isset($clean['correo'])) {
            $clean['correo'] = strtolower($clean['correo']);
            if (filter_var($clean['correo'], FILTER_VALIDATE_EMAIL) === false) {
                $errors['correo'] = 'El correo electrónico no tiene un formato válido.';
            }
        }

        if (isset($clean['fecha_ingreso']) && !$this->isValidDate($clean['fecha_ingreso'])) {
            $errors['fecha_ingreso'] = 'La fecha de ingreso debe tener un formato válido (YYYY-MM-DD).';
        }

        $estado = isset($data['estado']) && is_scalar($data['estado'])
            ? strtolower(trim((string) $data['estado']))
            : 'activo';

        if ($estado === '') {
            $estado = 'activo';
        }

        if (!in_array($estado, self::ALLOWED_STATES, true)) {
            $errors['estado'] = 'El estado debe ser activo o inactivo.';
        } else {
            $clean['estado'] = $estado;
        }

        if ($errors !== []) {
            throw new ValidationException('Los datos del empleado no son válidos.', $errors);
        }

        return $clean;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
