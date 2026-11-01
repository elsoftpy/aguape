<?php

namespace App\Livewire\Pages;

use App\Models\Establecimiento;
use App\Models\IthCorte;
use App\Models\Nodo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('ITH por cortes')]
class IthCortes extends Component
{
    use WithPagination;

    #[Url]
    public string $nodoId = '';

    #[Url]
    public ?string $desde = null;

    #[Url]
    public ?string $hasta = null;

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['nodoId', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('nodoId', 'desde', 'hasta');
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
        return $this->nodoId !== '' || $this->desde !== null || $this->hasta !== null;
    }

    /**
     * @return LengthAwarePaginator<int, IthCorte>
     */
    #[Computed]
    public function cortes(): LengthAwarePaginator
    {
        return IthCorte::query()
            ->with('nodo')
            ->when($this->nodoId !== '', fn (Builder $query) => $query->where('nodo_id', $this->nodoId))
            ->when($this->desde !== null, fn (Builder $query) => $query->where('inicio', '>=', CarbonImmutable::parse($this->desde)->startOfDay()))
            ->when($this->hasta !== null, fn (Builder $query) => $query->where('inicio', '<=', CarbonImmutable::parse($this->hasta)->endOfDay()))
            ->latest('inicio')
            ->orderBy('nodo_id')
            ->paginate(28);
    }

    #[Computed]
    public function hayCortes(): bool
    {
        return IthCorte::query()->exists();
    }

    public function etiquetaCorte(IthCorte $corte): string
    {
        $inicio = $corte->inicio;
        $fin = $corte->fin;

        return $inicio->format('d/m/Y').' '.$inicio->format('H').'–'.($fin->hour === 0 ? '24' : $fin->format('H'));
    }

    /**
     * @return array<int, array{label: string, value: int|string}>
     */
    #[Computed]
    public function nodoOptions(): array
    {
        return [
            ['label' => 'Todos los nodos', 'value' => ''],
            ...Nodo::query()->orderBy('nombre')->get()
                ->map(fn (Nodo $nodo) => ['label' => $nodo->nombre, 'value' => $nodo->id])
                ->all(),
        ];
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.ith-cortes');
    }
}
