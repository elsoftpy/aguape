<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\FiltraPorNodoVariableFecha;
use App\Models\Establecimiento;
use App\Models\Lectura;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Historial de mediciones')]
class Historial extends Component
{
    use FiltraPorNodoVariableFecha, WithPagination;

    /**
     * @return LengthAwarePaginator<int, Lectura>
     */
    #[Computed]
    public function lecturas(): LengthAwarePaginator
    {
        return $this->aplicarFiltros(Lectura::query(), 'medido_en')
            ->with(['nodo', 'variable'])
            ->latest('medido_en')
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function hayLecturas(): bool
    {
        return Lectura::query()->exists();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.historial');
    }
}
