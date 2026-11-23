<?php

declare(strict_types=1);

namespace App\Contracts;

interface EmpleadoValidatorInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validateCreate(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function validateUpdate(array $data): array;

    /**
     * @param array<string, mixed> $data
     */
    public function validateStatusChange(array $data): string;

    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, string>
     */
    public function validateFilters(array $queryParams): array;
}
