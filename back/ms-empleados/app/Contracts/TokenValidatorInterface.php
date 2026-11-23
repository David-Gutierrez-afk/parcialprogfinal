<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\AuthSession;

interface TokenValidatorInterface
{
    public function validateToken(string $token): ?AuthSession;
}
