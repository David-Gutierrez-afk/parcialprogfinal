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
    public function list(array $filters = []): Collection
    {
        $query = Empleado::query();

        if (isset($filters['documento'])) {
            $query->where('documento', 'LIKE', '%' . $filters['documento'] . '%');
        }

        if (isset($filters['area'])) {
            $query->where('area', 'LIKE', '%' . $filters['area'] . '%');
        }

        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        return $query->orderByDesc('id')->get();
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

    public function update(int $id, array $data): Empleado
    {
        $empleado = $this->findById($id);

        if (isset($data['documento'])) {
            $this->ensureUniqueDocumento((string) $data['documento'], $id);
        }

        if (isset($data['correo'])) {
            $this->ensureUniqueCorreo((string) $data['correo'], $id);
        }

        $empleado->fill($data);
        $empleado->save();

        return $empleado;
    }

    public function changeStatus(int $id, string $estado): Empleado
    {
        $empleado = $this->findById($id);

        if ($empleado->estado === $estado) {
            throw new ConflictException(sprintf('El empleado ya se encuentra en estado %s.', $estado));
        }

        $empleado->estado = $estado;
        $empleado->save();

        return $empleado;
    }

    private function ensureUniqueDocumento(string $documento, ?int $excludeEmpleadoId = null): void
    {
        $query = Empleado::where('documento', $documento);
        if ($excludeEmpleadoId !== null) {
            $query->where('id', '!=', $excludeEmpleadoId);
        }

        if ($query->exists()) {
            throw new ConflictException('Ya existe un empleado registrado con ese documento de identificación.');
        }
    }

    private function ensureUniqueCorreo(string $correo, ?int $excludeEmpleadoId = null): void
    {
        $query = Empleado::where('correo', $correo);
        if ($excludeEmpleadoId !== null) {
            $query->where('id', '!=', $excludeEmpleadoId);
        }

        if ($query->exists()) {
            throw new ConflictException('Ya existe un empleado registrado con ese correo electrónico.');
        }
    }
}
