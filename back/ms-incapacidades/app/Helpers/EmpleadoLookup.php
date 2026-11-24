<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\EmpleadoLookupInterface;
use App\Models\EmpleadoRef;

class EmpleadoLookup implements EmpleadoLookupInterface
{
    public function findById(int $empleadoId): ?EmpleadoRef
    {
        return EmpleadoRef::find($empleadoId);
    }
}
