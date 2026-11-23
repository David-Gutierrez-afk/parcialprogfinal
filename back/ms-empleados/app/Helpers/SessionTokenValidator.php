<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\TokenValidatorInterface;
use App\Models\AuthSession;

class SessionTokenValidator implements TokenValidatorInterface
{
    public function validateToken(string $token): ?AuthSession
    {
        $cleanToken = trim($token);
        if ($cleanToken === '') {
            return null;
        }

        return AuthSession::where('token', $cleanToken)
            ->where('sesion_activa', true)
            ->where('estado', 'activo')
            ->first();
    }
}
