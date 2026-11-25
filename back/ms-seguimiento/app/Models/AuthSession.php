<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthSession extends Model
{
    protected $connection = 'auth';

    protected $table = 'usuarios';

    public $timestamps = true;

    protected $hidden = [
        'contrasena',
    ];

    protected $casts = [
        'sesion_activa' => 'boolean',
    ];
}
