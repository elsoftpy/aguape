<?php

namespace App\Livewire\Pages;

use App\Enums\EstadoAlerta;
use App\Enums\PermissionsEnum;
use App\Livewire\Concerns\FiltraPorNodoVariableFecha;
use App\Models\Alerta;
use App\Models\Establecimiento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
    use FiltraPorNodoVariableFecha, Interactions, WithPagination;

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
        if ($alerta->estado === EstadoAlerta::Atendida) {
            $this->avisarYaRevisada($alerta);

            return;
        }

        $this->dialog()
            ->question('¿Marcar como revisada?')
            ->confirm('Sí, marcar', 'marcarComoRevisada', $alerta->id)
            ->cancel('Cancelar')
            ->send();
    }

    public function marcarComoRevisada(Alerta $alerta): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarAlertas->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para marcar alertas como revisadas')->send();

            return;
        }

        $marcadas = Alerta::query()
            ->whereKey($alerta->id)
            ->where('estado', EstadoAlerta::Pendiente)
            ->update([
                'estado' => EstadoAlerta::Atendida,
                'atendida_por' => auth()->id(),
                'atendida_en' => now(),
            ]);

        unset($this->totalPendientes, $this->totalRevisadas);

        if ($marcadas === 0) {
            $this->avisarYaRevisada($alerta->fresh());

            return;
        }

        $this->toast()->success('Alerta revisada', 'La alerta se marcó como revisada.')->send();
    }

    private function avisarYaRevisada(Alerta $alerta): void
    {
        $responsable = $alerta->atendidaPor?->name ?? 'otro usuario';
        $momento = $alerta->atendida_en?->format('d/m/Y H:i') ?? '—';

        $this->toast()->info('Alerta ya revisada', "La revisó {$responsable} el {$momento}.")->send();
    }

    /**
     * @return LengthAwarePaginator<int, Alerta>
     */
    #[Computed]
    public function alertas(): LengthAwarePaginator
    {
        return $this->alertasFiltradas()
            ->where('estado', $this->tab)
            ->with(['nodo.potrero', 'variable', 'lectura', 'umbral', 'atendidaPor'])
            ->latest('created_at')
            ->paginate(10);
    }

    #[Computed]
    public function totalPendientes(): int
    {
        return $this->alertasFiltradas()->where('estado', EstadoAlerta::Pendiente)->count();
    }

    #[Computed]
    public function totalRevisadas(): int
    {
        return $this->alertasFiltradas()->where('estado', EstadoAlerta::Atendida)->count();
    }

    private function alertasFiltradas(): Builder
    {
        return $this->aplicarFiltros(Alerta::query(), 'created_at');
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.alertas');
    }
}
