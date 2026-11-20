<?php

declare(strict_types=1);

namespace App\Contracts;

interface AuthValidatorInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array{identificador: string, contrasena: string}
     */
    public function validateLogin(array $data): array;
}
