<?php

declare(strict_types=1);

namespace App\Contracts;

interface SeguimientoValidatorInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validateCreate(array $data, ?string $defaultUsuario = null): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validateStatusUpdate(array $data, ?string $defaultUsuario = null): array;

    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    public function validateFilters(array $queryParams): array;
}
