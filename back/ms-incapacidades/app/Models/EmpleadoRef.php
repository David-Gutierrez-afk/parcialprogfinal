<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpleadoRef extends Model
{
    protected $connection = 'empleados';

    protected $table = 'empleados';

    public $timestamps = true;
}
