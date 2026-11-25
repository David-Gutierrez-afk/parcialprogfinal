<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\IncapacidadGatewayInterface;
use App\Contracts\SeguimientoServiceInterface;
use App\Contracts\UsuarioLookupInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\IncapacidadRef;
use App\Models\Seguimiento;
use Illuminate\Database\Eloquent\Collection;

class SeguimientosController implements SeguimientoServiceInterface
{
    private IncapacidadGatewayInterface $incapacidadGateway;
    private UsuarioLookupInterface $usuarioLookup;

    public function __construct(
        IncapacidadGatewayInterface $incapacidadGateway,
        UsuarioLookupInterface $usuarioLookup
    ) {
        $this->incapacidadGateway = $incapacidadGateway;
        $this->usuarioLookup = $usuarioLookup;
    }

    public function list(array $filters = []): Collection
    {
        $query = Seguimiento::query();

        if (isset($filters['incapacidad_id'])) {
            $query->where('incapacidad_id', (int) $filters['incapacidad_id']);
        }

        if (isset($filters['estado'])) {
            $query->where('estado', (string) $filters['estado']);
        }

        if (isset($filters['usuario_responsable'])) {
            $query->where('usuario_responsable', 'LIKE', '%' . (string) $filters['usuario_responsable'] . '%');
        }

        if (isset($filters['fecha'])) {
            $query->where('fecha', (string) $filters['fecha']);
        }

        if (isset($filters['fecha_inicio'])) {
            $query->where('fecha', '>=', (string) $filters['fecha_inicio']);
        }

        if (isset($filters['fecha_fin'])) {
            $query->where('fecha', '<=', (string) $filters['fecha_fin']);
        }

        return $query->orderByDesc('fecha')->orderByDesc('id')->get();
    }

    public function findById(int $id): Seguimiento
    {
        $seguimiento = Seguimiento::find($id);
        if ($seguimiento === null) {
            throw new NotFoundException('Registro de seguimiento no encontrado.');
        }

        return $seguimiento;
    }

    public function getHistoryByIncapacidad(int $incapacidadId): array
    {
        $incapacidad = $this->findExistingIncapacidad($incapacidadId);

        $historial = Seguimiento::where('incapacidad_id', $incapacidadId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        return [
            'incapacidad' => $incapacidad->toArray(),
            'historial'   => $historial->toArray(),
            'total'       => $historial->count(),
        ];
    }

    public function register(array $data): array
    {
        $incapacidadId = (int) $data['incapacidad_id'];
        $nuevoEstado = (string) $data['estado'];
        $usuarioResponsable = $this->resolveActiveUsuario((string) $data['usuario_responsable']);
        $incapacidad = $this->findExistingIncapacidad($incapacidadId);

        $this->ensureValidTransition($incapacidad, $nuevoEstado, false);

        $estadoAnterior = (string) $incapacidad->estado;

        $seguimiento = new Seguimiento();
        $seguimiento->incapacidad_id = $incapacidadId;
        $seguimiento->fecha = (string) $data['fecha'];
        $seguimiento->comentario = (string) $data['comentario'];
        $seguimiento->estado = $nuevoEstado;
        $seguimiento->usuario_responsable = $usuarioResponsable;
        $seguimiento->save();

        if ($estadoAnterior !== $nuevoEstado) {
            $incapacidad = $this->incapacidadGateway->updateEstado($incapacidad, $nuevoEstado);
        }

        return [
            'seguimiento'     => $seguimiento->toArray(),
            'incapacidad'     => $incapacidad->toArray(),
            'estado_anterior' => $estadoAnterior,
        ];
    }

    public function updateIncapacidadStatus(int $incapacidadId, array $data): array
    {
        $nuevoEstado = (string) $data['estado'];
        $usuarioResponsable = $this->resolveActiveUsuario((string) $data['usuario_responsable']);
        $incapacidad = $this->findExistingIncapacidad($incapacidadId);

        $this->ensureValidTransition($incapacidad, $nuevoEstado, true);

        $estadoAnterior = (string) $incapacidad->estado;
        $incapacidad = $this->incapacidadGateway->updateEstado($incapacidad, $nuevoEstado);

        $seguimiento = new Seguimiento();
        $seguimiento->incapacidad_id = $incapacidadId;
        $seguimiento->fecha = (string) $data['fecha'];
        $seguimiento->comentario = (string) $data['comentario'];
        $seguimiento->estado = $nuevoEstado;
        $seguimiento->usuario_responsable = $usuarioResponsable;
        $seguimiento->save();

        return [
            'seguimiento'     => $seguimiento->toArray(),
            'incapacidad'     => $incapacidad->toArray(),
            'estado_anterior' => $estadoAnterior,
        ];
    }

    private function findExistingIncapacidad(int $incapacidadId): IncapacidadRef
    {
        $incapacidad = $this->incapacidadGateway->findById($incapacidadId);
        if ($incapacidad === null) {
            throw new NotFoundException('La incapacidad asociada no existe en el sistema.');
        }

        return $incapacidad;
    }

    private function resolveActiveUsuario(string $usuarioResponsable): string
    {
        $usuario = $this->usuarioLookup->findActiveByIdentifier($usuarioResponsable);
        if ($usuario === null) {
            throw new NotFoundException('El usuario responsable especificado no existe o se encuentra inactivo.');
        }

        return (string) $usuario->usuario;
    }

    private function ensureValidTransition(
        IncapacidadRef $incapacidad,
        string $nuevoEstado,
        bool $requireStateChange
    ): void {
        if ($incapacidad->estado === 'finalizada') {
            throw new ConflictException(
                'No es posible registrar movimientos ni cambiar el estado de una incapacidad finalizada.'
            );
        }

        if ($requireStateChange && $incapacidad->estado === $nuevoEstado) {
            throw new ConflictException(
                sprintf('La incapacidad ya se encuentra en estado %s.', $nuevoEstado)
            );
        }

        if ($incapacidad->estado === 'rechazada' && $nuevoEstado === 'finalizada') {
            throw new ConflictException('No es posible finalizar una incapacidad que fue rechazada.');
        }
    }
}
