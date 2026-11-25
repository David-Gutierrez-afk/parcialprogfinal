<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncapacidadRef extends Model
{
    protected $connection = 'incapacidades';

    protected $table = 'incapacidades';

    public $timestamps = true;

    protected $casts = [
        'empleado_id'      => 'integer',
        'dias_incapacidad' => 'integer',
    ];
}
