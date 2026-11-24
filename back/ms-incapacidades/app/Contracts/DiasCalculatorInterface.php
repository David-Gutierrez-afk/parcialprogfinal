<?php

declare(strict_types=1);

namespace App\Contracts;

interface DiasCalculatorInterface
{
    public function calculateInclusiveDays(string $fechaInicio, string $fechaFin): int;
}
