<?php

namespace App\Livewire\Pages;

use App\Enums\EstadoAlerta;
use App\Enums\PermissionsEnum;
use App\Models\Alerta;
use App\Models\Establecimiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Alertas')]
class Alertas extends Component
{
    use Interactions, WithPagination;

    /**
     * Pestaña activa: `pendiente` (Activas) o `atendida` (Revisadas).
     */
    #[Url]
    public string $tab = 'pendiente';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function confirmarRevision(Alerta $alerta): void
    {
        $this->dialog()
            ->question('¿Marcar como revisada?')
            ->confirm('Sí', 'marcarComoRevisada', $alerta->id)
            ->cancel('Cancelar')
            ->send();
    }

    public function marcarComoRevisada(Alerta $alerta): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarAlertas->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para marcar alertas como revisadas')->send();

            return;
        }

        $alerta->update([
            'estado' => EstadoAlerta::Atendida,
            'atendida_por' => auth()->id(),
            'atendida_en' => now(),
        ]);

        unset($this->totalPendientes, $this->totalRevisadas);
    }

    /**
     * @return LengthAwarePaginator<int, Alerta>
     */
    #[Computed]
    public function alertas(): LengthAwarePaginator
    {
        return Alerta::query()
            ->where('estado', $this->tab)
            ->with(['nodo.potrero', 'variable', 'lectura', 'umbral', 'atendidaPor'])
            ->latest('created_at')
            ->paginate(10);
    }

    #[Computed]
    public function totalPendientes(): int
    {
        return Alerta::query()->where('estado', EstadoAlerta::Pendiente)->count();
    }

    #[Computed]
    public function totalRevisadas(): int
    {
        return Alerta::query()->where('estado', EstadoAlerta::Atendida)->count();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.alertas');
    }
}
