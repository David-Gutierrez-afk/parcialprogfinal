<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Usuario;

interface TokenValidatorInterface
{
    public function validateToken(string $token): ?Usuario;
}
