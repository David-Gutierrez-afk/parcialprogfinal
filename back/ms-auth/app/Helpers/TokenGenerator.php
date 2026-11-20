<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Contracts\TokenGeneratorInterface;

class TokenGenerator implements TokenGeneratorInterface
{
    private int $bytes;

    public function __construct(int $bytes = 32)
    {
        $this->bytes = max(16, $bytes);
    }

    public function generate(): string
    {
        return bin2hex(random_bytes($this->bytes));
    }
}
