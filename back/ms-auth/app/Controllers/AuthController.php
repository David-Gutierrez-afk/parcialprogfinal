<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\AuthServiceInterface;
use App\Contracts\TokenGeneratorInterface;
use App\Contracts\TokenValidatorInterface;
use App\Exceptions\AuthenticationException;
use App\Models\Usuario;

class AuthController implements AuthServiceInterface, TokenValidatorInterface
{
    private TokenGeneratorInterface $tokenGenerator;

    public function __construct(TokenGeneratorInterface $tokenGenerator)
    {
        $this->tokenGenerator = $tokenGenerator;
    }

    public function login(string $identificador, string $contrasena): array
    {
        $usuario = Usuario::where('usuario', $identificador)
            ->orWhere('correo', $identificador)
            ->first();

        if ($usuario === null || !$this->verifyPassword($contrasena, (string) $usuario->contrasena)) {
            throw new AuthenticationException('Credenciales inválidas. Verifique usuario/correo y contraseña.', 401);
        }

        if ($usuario->estado !== 'activo') {
            throw new AuthenticationException('El usuario se encuentra inactivo.', 403);
        }

        $token = $this->tokenGenerator->generate();
        $usuario->token = $token;
        $usuario->sesion_activa = true;
        $usuario->save();

        return [
            'token'   => $token,
            'usuario' => $usuario,
        ];
    }

    public function logout(Usuario $usuario): void
    {
        $usuario->token = null;
        $usuario->sesion_activa = false;
        $usuario->save();
    }

    public function validateToken(string $token): ?Usuario
    {
        $cleanToken = trim($token);
        if ($cleanToken === '') {
            return null;
        }

        return Usuario::where('token', $cleanToken)
            ->where('sesion_activa', true)
            ->where('estado', 'activo')
            ->first();
    }

    private function verifyPassword(string $plainPassword, string $storedPassword): bool
    {
        $hashInfo = password_get_info($storedPassword);
        if (($hashInfo['algo'] ?? null) !== null) {
            return password_verify($plainPassword, $storedPassword);
        }

        return hash_equals($storedPassword, $plainPassword);
    }
}
