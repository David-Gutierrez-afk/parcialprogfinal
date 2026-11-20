<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Collection;

interface EmpleadoServiceInterface
{
    public function list(): Collection;

    public function findById(int $id): Empleado;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Empleado;
}
