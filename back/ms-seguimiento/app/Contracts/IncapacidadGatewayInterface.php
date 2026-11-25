<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\IncapacidadRef;

interface IncapacidadGatewayInterface
{
    public function findById(int $incapacidadId): ?IncapacidadRef;

    public function updateEstado(IncapacidadRef $incapacidad, string $nuevoEstado): IncapacidadRef;
}
