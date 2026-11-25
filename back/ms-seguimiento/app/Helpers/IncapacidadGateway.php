<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\IncapacidadGatewayInterface;
use App\Models\IncapacidadRef;

class IncapacidadGateway implements IncapacidadGatewayInterface
{
    public function findById(int $incapacidadId): ?IncapacidadRef
    {
        return IncapacidadRef::find($incapacidadId);
    }

    public function updateEstado(IncapacidadRef $incapacidad, string $nuevoEstado): IncapacidadRef
    {
        $incapacidad->estado = $nuevoEstado;
        $incapacidad->save();

        return $incapacidad;
    }
}
