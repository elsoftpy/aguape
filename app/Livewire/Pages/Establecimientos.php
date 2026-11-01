<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Livewire\Concerns\AnalizaDecimales;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Establecimientos')]
class Establecimientos extends Component
{
    use AnalizaDecimales, Interactions;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $ubicacion = '';

    public ?string $hectareas = null;

    public bool $mostrarModal = false;

    public function abrirCreacion(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarEstablecimientos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para registrar establecimientos.')->send();

            return;
        }

        $this->resetearFormulario();

        $this->mostrarModal = true;
    }

    public function abrirEdicion(Establecimiento $establecimiento): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarEstablecimientos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para editar establecimientos.')->send();

            return;
        }

        $this->resetValidation();
        $this->editandoId = $establecimiento->id;
        $this->nombre = $establecimiento->nombre;
        $this->ubicacion = $establecimiento->ubicacion ?? '';
        $this->hectareas = $this->formatearDecimal($establecimiento->hectareas);

        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarEstablecimientos->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para guardar establecimientos.')->send();

            return;
        }

        $hectareas = $this->analizarDecimal($this->hectareas);

        $datos = Validator::make([
            'nombre' => trim($this->nombre),
            'ubicacion' => trim($this->ubicacion),
            'hectareas' => $hectareas === false ? $this->hectareas : $hectareas,
        ], [
            'nombre' => ['required', 'string', 'max:100'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'hectareas' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ])->validate();

        $datos['ubicacion'] = $datos['ubicacion'] !== '' ? $datos['ubicacion'] : null;

        if ($this->editandoId) {
            Establecimiento::query()->findOrFail($this->editandoId)->update($datos);
        } else {
            Establecimiento::query()->create($datos);
        }

        $this->mostrarModal = false;
        $this->resetearFormulario();

        $this->toast()->success('Guardado', 'Establecimiento guardado correctamente.')->send();
    }

    private function resetearFormulario(): void
    {
        $this->editandoId = null;
        $this->nombre = '';
        $this->ubicacion = '';
        $this->hectareas = null;
        $this->resetValidation();
    }

    /**
     * @return Collection<int, Establecimiento>
     */
    #[Computed]
    public function establecimientos(): Collection
    {
        return Establecimiento::query()
            ->withCount(['potreros', 'nodos'])
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.establecimientos');
    }
}
