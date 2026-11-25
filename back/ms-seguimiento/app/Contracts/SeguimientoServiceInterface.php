<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Seguimiento;
use Illuminate\Database\Eloquent\Collection;

interface SeguimientoServiceInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = []): Collection;

    public function findById(int $id): Seguimiento;

    /**
     * @return array<string, mixed>
     */
    public function getHistoryByIncapacidad(int $incapacidadId): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function register(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateIncapacidadStatus(int $incapacidadId, array $data): array;
}
