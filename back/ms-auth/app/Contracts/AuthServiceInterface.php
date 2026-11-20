<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Usuario;

interface AuthServiceInterface
{
    /**
     * @return array{token: string, usuario: Usuario}
     */
    public function login(string $identificador, string $contrasena): array;

    public function logout(Usuario $usuario): void;
}
