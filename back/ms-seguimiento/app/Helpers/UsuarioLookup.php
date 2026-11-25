<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\UsuarioLookupInterface;
use App\Models\AuthSession;

class UsuarioLookup implements UsuarioLookupInterface
{
    public function findActiveByIdentifier(string $usuarioResponsable): ?AuthSession
    {
        $cleanIdentifier = trim($usuarioResponsable);
        if ($cleanIdentifier === '') {
            return null;
        }

        return AuthSession::where('estado', 'activo')
            ->where(function ($query) use ($cleanIdentifier): void {
                $query->where('usuario', $cleanIdentifier)
                    ->orWhere('correo', $cleanIdentifier);
            })
            ->first();
    }
}
