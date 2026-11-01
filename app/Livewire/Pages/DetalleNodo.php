<?php

namespace App\Livewire\Pages;

use App\Enums\EstadoAlerta;
use App\Models\Alerta;
use App\Models\Establecimiento;
use App\Models\Lectura;
use App\Models\Nodo;
use App\Models\Variable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('Detalle de nodo')]
class DetalleNodo extends Component
{
    public const HORAS_HISTORIAL = 6;

    public Nodo $nodo;

    public string $variableHistorial = '';

    public function mount(Nodo $nodo): void
    {
        $this->nodo = $nodo->load('potrero');

        $this->variableHistorial = (string) ($this->historial->keys()->first() ?? '');
    }

    #[Computed]
    public function ultimasLecturas(): Collection
    {
        $variableBateria = $this->variableBateria();

        $lecturas = Lectura::query()
            ->whereIn('id', function ($query) use ($variableBateria) {
                $query->selectRaw('MAX(id)')
                    ->from('lecturas')
                    ->where('nodo_id', $this->nodo->id)
                    ->when($variableBateria, fn ($q) => $q->where('variable_id', '!=', $variableBateria->id))
                    ->groupBy('variable_id');
            })
            ->with('variable')
            ->get();

        $lecturaIdsEnAlerta = Alerta::query()
            ->where('estado', EstadoAlerta::Pendiente)
            ->whereIn('lectura_id', $lecturas->pluck('id'))
            ->pluck('lectura_id')
            ->flip();

        return $lecturas
            ->map(fn (Lectura $lectura) => [
                'lectura' => $lectura,
                'icono' => $lectura->variable->icono(),
                'enAlerta' => $lecturaIdsEnAlerta->has($lectura->id),
            ])
            ->sortBy(fn (array $item) => $item['lectura']->variable->nombre)
            ->values();
    }

    #[Computed]
    public function ultimaLectura(): ?Lectura
    {
        return $this->nodo->lecturas()->latest('medido_en')->first();
    }

    #[Computed]
    public function bateria(): ?float
    {
        $variableBateria = $this->variableBateria();

        if (! $variableBateria) {
            return null;
        }

        $valor = $this->nodo->lecturas()
            ->where('variable_id', $variableBateria->id)
            ->latest('medido_en')
            ->value('valor');

        return $valor !== null ? (float) $valor : null;
    }

    #[Computed]
    public function historial(): Collection
    {
        return $this->nodo->lecturas()
            ->where('medido_en', '>=', now()->subHours(self::HORAS_HISTORIAL))
            ->with('variable')
            ->orderBy('medido_en')
            ->get()
            ->groupBy('variable_id')
            ->sortBy(fn (Collection $lecturas) => $lecturas->first()->variable->nombre);
    }

    #[Computed]
    public function variableHistorialOptions(): array
    {
        return $this->historial
            ->map(fn (Collection $lecturas, int $variableId) => [
                'label' => $lecturas->first()->variable->nombre,
                'value' => (string) $variableId,
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function serieHistorial(): Collection
    {
        return $this->historial->get((int) $this->variableHistorial, collect());
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return $this->nodo->establecimiento;
    }

    private function variableBateria(): ?Variable
    {
        return Variable::query()->where('clave', 'nivel_bateria')->first();
    }

    public function render()
    {
        return view('livewire.pages.detalle-nodo');
    }
}
