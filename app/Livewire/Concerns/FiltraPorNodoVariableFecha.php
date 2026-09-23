<?php

namespace App\Livewire\Concerns;

use App\Models\Nodo;
use App\Models\Variable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @mixin Component
 *
 * @method void resetPage(string $pageName = 'page')
 */
trait FiltraPorNodoVariableFecha
{
    #[Url]
    public string $nodoId = '';

    #[Url]
    public string $variableId = '';

    #[Url]
    public ?string $desde = null;

    #[Url]
    public ?string $hasta = null;

    public function updatedFiltraPorNodoVariableFecha(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('nodoId', 'variableId', 'desde', 'hasta');
        $this->resetPage();
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
        return $this->nodoId !== '' || $this->variableId !== '' || $this->desde !== null || $this->hasta !== null;
    }

    protected function aplicarFiltros(Builder $query, string $columnaFecha): Builder
    {
        return $query
            ->when($this->nodoId !== '', fn (Builder $query) => $query->where('nodo_id', $this->nodoId))
            ->when($this->variableId !== '', fn (Builder $query) => $query->where('variable_id', $this->variableId))
            ->when($this->desde !== null, fn (Builder $query) => $query->where($columnaFecha, '>=', CarbonImmutable::parse($this->desde)->startOfDay()))
            ->when($this->hasta !== null, fn (Builder $query) => $query->where($columnaFecha, '<=', CarbonImmutable::parse($this->hasta)->endOfDay()));
    }

    #[Computed]
    public function nodoFiltroOptions(): array
    {
        return [
            ['label' => 'Todos los nodos', 'value' => ''],
            ...Nodo::query()->orderBy('nombre')->get()
                ->map(fn (Nodo $nodo) => ['label' => $nodo->nombre, 'value' => $nodo->id])
                ->all(),
        ];
    }

    #[Computed]
    public function variableFiltroOptions(): array
    {
        return [
            ['label' => 'Todas las variables', 'value' => ''],
            ...Variable::query()->orderBy('nombre')->get()
                ->map(fn (Variable $variable) => ['label' => $variable->nombre, 'value' => $variable->id])
                ->all(),
        ];
    }
}
