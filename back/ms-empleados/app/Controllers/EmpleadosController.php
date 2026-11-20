<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\EmpleadoServiceInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\Empleado;
use Illuminate\Database\Eloquent\Collection;

class EmpleadosController implements EmpleadoServiceInterface
{
    public function list(): Collection
    {
        return Empleado::orderByDesc('id')->get();
    }

    public function findById(int $id): Empleado
    {
        $empleado = Empleado::find($id);
        if ($empleado === null) {
            throw new NotFoundException('Empleado no encontrado.');
        }

        return $empleado;
    }

    public function create(array $data): Empleado
    {
        $this->ensureUniqueDocumento((string) $data['documento']);
        $this->ensureUniqueCorreo((string) $data['correo']);

        $empleado = new Empleado();
        $empleado->nombres = (string) $data['nombres'];
        $empleado->apellidos = (string) $data['apellidos'];
        $empleado->documento = (string) $data['documento'];
        $empleado->correo = (string) $data['correo'];
        $empleado->telefono = (string) $data['telefono'];
        $empleado->cargo = (string) $data['cargo'];
        $empleado->area = (string) $data['area'];
        $empleado->fecha_ingreso = (string) $data['fecha_ingreso'];
        $empleado->estado = (string) ($data['estado'] ?? 'activo');
        $empleado->save();

        return $empleado;
    }

    private function ensureUniqueDocumento(string $documento): void
    {
        if (Empleado::where('documento', $documento)->exists()) {
            throw new ConflictException('Ya existe un empleado registrado con ese documento de identificación.');
        }
    }

    private function ensureUniqueCorreo(string $correo): void
    {
        if (Empleado::where('correo', $correo)->exists()) {
            throw new ConflictException('Ya existe un empleado registrado con ese correo electrónico.');
        }
    }
}
