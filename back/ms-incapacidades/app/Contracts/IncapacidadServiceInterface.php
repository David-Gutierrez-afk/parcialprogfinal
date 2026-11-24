<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Incapacidad;
use Illuminate\Database\Eloquent\Collection;

interface IncapacidadServiceInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = []): Collection;

    public function findById(int $id): Incapacidad;

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Incapacidad;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Incapacidad;

    public function finalize(int $id): Incapacidad;

    public function changeStatus(int $id, string $estado): Incapacidad;
}
