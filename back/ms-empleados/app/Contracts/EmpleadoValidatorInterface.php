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
}
