<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\EmpleadoRef;

interface EmpleadoLookupInterface
{
    public function findById(int $empleadoId): ?EmpleadoRef;
}
