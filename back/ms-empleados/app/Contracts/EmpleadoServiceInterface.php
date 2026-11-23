<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Collection;

interface EmpleadoServiceInterface
{
    /**
     * @param array<string, string> $filters
     */
    public function list(array $filters = []): Collection;

    public function findById(int $id): Empleado;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Empleado;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Empleado;

    public function changeStatus(int $id, string $estado): Empleado;
}
