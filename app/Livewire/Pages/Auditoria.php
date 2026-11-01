<?php

namespace App\Livewire\Pages;

use App\Models\DestinatarioAlerta;
use App\Models\Establecimiento;
use App\Models\Nodo;
use App\Models\Potrero;
use App\Models\Umbral;
use App\Models\User;
use App\Models\Variable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Models\Audit;

#[Layout('layouts::app')]
#[Title('Auditoría')]
class Auditoria extends Component
{
    use WithPagination;

    public const ENTIDADES = [
        User::class => 'Usuario',
        Establecimiento::class => 'Establecimiento',
        Potrero::class => 'Potrero',
        Nodo::class => 'Nodo',
        Variable::class => 'Variable',
        Umbral::class => 'Umbral',
        DestinatarioAlerta::class => 'Destinatario de alerta',
    ];

    public const EVENTOS = [
        'created' => 'Creación',
        'updated' => 'Modificación',
        'deleted' => 'Eliminación',
        'rolActualizado' => 'Cambio de rol',
    ];

    #[Url]
    public string $usuarioId = '';

    #[Url]
    public string $evento = '';

    #[Url]
    public string $entidad = '';

    #[Url]
    public ?string $desde = null;

    #[Url]
    public ?string $hasta = null;

    public ?int $auditoriaId = null;

    public bool $mostrarDetalle = false;

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['usuarioId', 'evento', 'entidad', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('usuarioId', 'evento', 'entidad', 'desde', 'hasta');
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        $this->auditoriaId = $id;
        $this->mostrarDetalle = true;
    }

    #[Computed]
    public function rangoDeFechasInvalido(): bool
    {
        return $this->desde !== null
            && $this->hasta !== null
            && CarbonImmutable::parse($this->hasta)->lt(CarbonImmutable::parse($this->desde));
    }

    #[Computed]
    public function hayFiltros(): bool
    {
        return $this->usuarioId !== '' || $this->evento !== '' || $this->entidad !== '' || $this->desde !== null || $this->hasta !== null;
    }

    /**
     * @return LengthAwarePaginator<int, Audit>
     */
    #[Computed]
    public function registros(): LengthAwarePaginator
    {
        return Audit::query()
            ->with('user')
            ->when($this->usuarioId !== '', fn (Builder $query) => $query->where('user_type', User::class)->where('user_id', $this->usuarioId))
            ->when($this->evento !== '', fn (Builder $query) => $query->where('event', $this->evento))
            ->when($this->entidad !== '', fn (Builder $query) => $query->where('auditable_type', $this->entidad))
            ->when($this->desde !== null, fn (Builder $query) => $query->where('created_at', '>=', CarbonImmutable::parse($this->desde)->startOfDay()))
            ->when($this->hasta !== null, fn (Builder $query) => $query->where('created_at', '<=', CarbonImmutable::parse($this->hasta)->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function hayRegistros(): bool
    {
        return Audit::query()->exists();
    }

    #[Computed]
    public function detalle(): ?Audit
    {
        return $this->auditoriaId !== null
            ? Audit::query()->with('user')->find($this->auditoriaId)
            : null;
    }

    /**
     * @return array<string, array{anterior: string, nuevo: string}>
     */
    #[Computed]
    public function cambios(): array
    {
        $detalle = $this->detalle;

        if ($detalle === null) {
            return [];
        }

        $anteriores = $detalle->old_values ?? [];
        $nuevos = $detalle->new_values ?? [];
        $cambios = [];

        foreach (array_unique([...array_keys($anteriores), ...array_keys($nuevos)]) as $campo) {
            $cambios[$campo] = [
                'anterior' => $this->formatearValor($anteriores[$campo] ?? null),
                'nuevo' => $this->formatearValor($nuevos[$campo] ?? null),
            ];
        }

        return $cambios;
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    #[Computed]
    public function usuarioOptions(): array
    {
        return [
            ['label' => 'Todos los usuarios', 'value' => ''],
            ...User::query()->orderBy('name')->get()
                ->map(fn (User $user) => ['label' => $user->name, 'value' => $user->id])
                ->all(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function eventoOptions(): array
    {
        return $this->opciones('Todos los eventos', self::EVENTOS);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function entidadOptions(): array
    {
        return $this->opciones('Todas las entidades', self::ENTIDADES);
    }

    public function nombreEntidad(Audit $registro): string
    {
        return (self::ENTIDADES[$registro->auditable_type] ?? class_basename($registro->auditable_type)).' #'.$registro->auditable_id;
    }

    public function nombreEvento(Audit $registro): string
    {
        return self::EVENTOS[$registro->event] ?? $registro->event;
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.auditoria');
    }

    /**
     * @param  array<string, string>  $etiquetas
     * @return array<int, array{label: string, value: string}>
     */
    private function opciones(string $todas, array $etiquetas): array
    {
        return [
            ['label' => $todas, 'value' => ''],
            ...collect($etiquetas)->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])->values()->all(),
        ];
    }

    private function formatearValor(mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            is_bool($valor) => $valor ? 'sí' : 'no',
            is_array($valor) => json_encode($valor, JSON_UNESCAPED_UNICODE),
            default => (string) $valor,
        };
    }
}
