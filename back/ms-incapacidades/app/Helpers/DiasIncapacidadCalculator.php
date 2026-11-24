<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\DiasCalculatorInterface;
use App\Exceptions\ValidationException;
use DateTimeImmutable;

class DiasIncapacidadCalculator implements DiasCalculatorInterface
{
    public function calculateInclusiveDays(string $fechaInicio, string $fechaFin): int
    {
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaInicio);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaFin);

        if ($inicio === false || $fin === false) {
            throw new ValidationException('Las fechas proporcionadas no son válidas para calcular días.', [
                'fechas' => 'Verifique el formato YYYY-MM-DD en fecha_inicio y fecha_fin.',
            ]);
        }

        if ($fin < $inicio) {
            throw new ValidationException('La fecha fin no puede ser menor a la fecha inicio.', [
                'fecha_fin' => 'La fecha fin debe ser igual o posterior a la fecha inicio.',
            ]);
        }

        $interval = $inicio->diff($fin);

        return ((int) $interval->days) + 1;
    }
}
