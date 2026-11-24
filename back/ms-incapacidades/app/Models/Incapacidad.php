<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incapacidad extends Model
{
    protected $table = 'incapacidades';

    public $timestamps = true;

    protected $fillable = [
        'empleado_id',
        'fecha_inicio',
        'fecha_fin',
        'tipo',
        'diagnostico_general',
        'entidad_medica',
        'observaciones',
        'dias_incapacidad',
        'estado',
    ];

    protected $casts = [
        'empleado_id'      => 'integer',
        'dias_incapacidad' => 'integer',
    ];
}
