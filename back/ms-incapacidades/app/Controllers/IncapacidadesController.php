<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\DiasCalculatorInterface;
use App\Contracts\EmpleadoLookupInterface;
use App\Contracts\IncapacidadServiceInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Incapacidad;
use Illuminate\Database\Eloquent\Collection;

class IncapacidadesController implements IncapacidadServiceInterface
{
    private EmpleadoLookupInterface $empleadoLookup;
    private DiasCalculatorInterface $diasCalculator;

    public function __construct(
        EmpleadoLookupInterface $empleadoLookup,
        DiasCalculatorInterface $diasCalculator
    ) {
        $this->empleadoLookup = $empleadoLookup;
        $this->diasCalculator = $diasCalculator;
    }

    public function list(array $filters = []): Collection
    {
        $query = Incapacidad::query();

        if (isset($filters['empleado_id'])) {
            $query->where('empleado_id', (int) $filters['empleado_id']);
        }

        if (isset($filters['estado'])) {
            $query->where('estado', (string) $filters['estado']);
        }

        if (isset($filters['tipo'])) {
            $query->where('tipo', (string) $filters['tipo']);
        }

        if (isset($filters['fecha'])) {
            $targetDate = (string) $filters['fecha'];
            $query->where('fecha_inicio', '<=', $targetDate)
                ->where('fecha_fin', '>=', $targetDate);
        }

        if (isset($filters['fecha_inicio'])) {
            $query->where('fecha_fin', '>=', (string) $filters['fecha_inicio']);
        }

        if (isset($filters['fecha_fin'])) {
            $query->where('fecha_inicio', '<=', (string) $filters['fecha_fin']);
        }

        return $query->orderByDesc('id')->get();
    }

    public function findById(int $id): Incapacidad
    {
        $incapacidad = Incapacidad::find($id);
        if ($incapacidad === null) {
            throw new NotFoundException('Incapacidad médica no encontrada.');
        }

        return $incapacidad;
    }

    public function create(array $data): Incapacidad
    {
        $empleadoId = (int) $data['empleado_id'];
        $fechaInicio = (string) $data['fecha_inicio'];
        $fechaFin = (string) $data['fecha_fin'];

        $this->ensureActiveEmpleadoExists($empleadoId);
        $this->ensureNoOverlappingDates($empleadoId, $fechaInicio, $fechaFin);

        $diasIncapacidad = $this->diasCalculator->calculateInclusiveDays($fechaInicio, $fechaFin);

        $incapacidad = new Incapacidad();
        $incapacidad->empleado_id = $empleadoId;
        $incapacidad->fecha_inicio = $fechaInicio;
        $incapacidad->fecha_fin = $fechaFin;
        $incapacidad->tipo = (string) $data['tipo'];
        $incapacidad->diagnostico_general = (string) $data['diagnostico_general'];
        $incapacidad->entidad_medica = (string) $data['entidad_medica'];
        $incapacidad->observaciones = $data['observaciones'] ?? null;
        $incapacidad->dias_incapacidad = $diasIncapacidad;
        $incapacidad->estado = (string) ($data['estado'] ?? 'registrada');
        $incapacidad->save();

        return $incapacidad;
    }

    public function update(int $id, array $data): Incapacidad
    {
        $incapacidad = $this->findById($id);

        if ($incapacidad->estado === 'finalizada') {
            throw new ConflictException('No es posible editar una incapacidad que ya se encuentra finalizada.');
        }

        if (isset($data['estado']) && $incapacidad->estado === 'rechazada' && $data['estado'] === 'finalizada') {
            throw new ConflictException('No es posible finalizar una incapacidad rechazada.');
        }

        $effectiveFechaInicio = (string) ($data['fecha_inicio'] ?? $incapacidad->fecha_inicio);
        $effectiveFechaFin = (string) ($data['fecha_fin'] ?? $incapacidad->fecha_fin);

        if ($effectiveFechaFin < $effectiveFechaInicio) {
            throw new ValidationException('La fecha fin no puede ser menor a la fecha de inicio.', [
                'fecha_fin' => 'La fecha fin debe ser igual o posterior a la fecha de inicio.',
            ]);
        }

        $effectiveEstado = (string) ($data['estado'] ?? $incapacidad->estado);
        if ($effectiveEstado !== 'rechazada') {
            $this->ensureNoOverlappingDates(
                (int) $incapacidad->empleado_id,
                $effectiveFechaInicio,
                $effectiveFechaFin,
                $id
            );
        }

        $diasIncapacidad = $this->diasCalculator->calculateInclusiveDays($effectiveFechaInicio, $effectiveFechaFin);

        $incapacidad->fill($data);
        $incapacidad->fecha_inicio = $effectiveFechaInicio;
        $incapacidad->fecha_fin = $effectiveFechaFin;
        $incapacidad->dias_incapacidad = $diasIncapacidad;
        $incapacidad->save();

        return $incapacidad;
    }

    public function finalize(int $id): Incapacidad
    {
        $incapacidad = $this->findById($id);

        if ($incapacidad->estado === 'finalizada') {
            throw new ConflictException('La incapacidad ya se encuentra finalizada.');
        }

        if ($incapacidad->estado === 'rechazada') {
            throw new ConflictException('No es posible finalizar una incapacidad que fue rechazada.');
        }

        $incapacidad->estado = 'finalizada';
        $incapacidad->save();

        return $incapacidad;
    }

    public function changeStatus(int $id, string $estado): Incapacidad
    {
        $incapacidad = $this->findById($id);

        if ($incapacidad->estado === 'finalizada') {
            throw new ConflictException('Una incapacidad finalizada no puede cambiar de estado.');
        }

        if ($incapacidad->estado === $estado) {
            throw new ConflictException(sprintf('La incapacidad ya se encuentra en estado %s.', $estado));
        }

        if ($incapacidad->estado === 'rechazada' && $estado === 'finalizada') {
            throw new ConflictException('No es posible finalizar una incapacidad rechazada.');
        }

        if ($incapacidad->estado === 'rechazada' && $estado !== 'rechazada') {
            $this->ensureNoOverlappingDates(
                (int) $incapacidad->empleado_id,
                (string) $incapacidad->fecha_inicio,
                (string) $incapacidad->fecha_fin,
                $id
            );
        }

        $incapacidad->estado = $estado;
        $incapacidad->save();

        return $incapacidad;
    }

    private function ensureActiveEmpleadoExists(int $empleadoId): void
    {
        $empleado = $this->empleadoLookup->findById($empleadoId);

        if ($empleado === null) {
            throw new NotFoundException('El empleado asociado no existe en el sistema.');
        }

        if ($empleado->estado !== 'activo') {
            throw new ConflictException('No se puede registrar una incapacidad para un empleado en estado inactivo.');
        }
    }

    private function ensureNoOverlappingDates(
        int $empleadoId,
        string $fechaInicio,
        string $fechaFin,
        ?int $excludeIncapacidadId = null
    ): void {
        $query = Incapacidad::where('empleado_id', $empleadoId)
            ->where('estado', '!=', 'rechazada')
            ->where('fecha_inicio', '<=', $fechaFin)
            ->where('fecha_fin', '>=', $fechaInicio);

        if ($excludeIncapacidadId !== null) {
            $query->where('id', '!=', $excludeIncapacidadId);
        }

        if ($query->exists()) {
            throw new ConflictException(
                'El empleado ya cuenta con una incapacidad registrada que se cruza con el rango de fechas indicado.'
            );
        }
    }
}
