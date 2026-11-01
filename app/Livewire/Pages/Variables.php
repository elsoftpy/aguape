<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Models\Establecimiento;
use App\Models\Variable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Variables monitoreadas')]
class Variables extends Component
{
    use Interactions;

    /**
     * `null` en modo creación, id de la variable en edición en modo edición.
     */
    public ?int $editandoId = null;

    public string $clave = '';

    public string $nombre = '';

    public string $unidad = '';

    public bool $mostrarModal = false;

    public function abrirCreacion(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarVariables->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para registrar variables.')->send();

            return;
        }

        $this->resetearFormulario();

        $this->mostrarModal = true;
    }

    public function abrirEdicion(Variable $variable): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarVariables->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para editar variables.')->send();

            return;
        }

        $this->resetValidation();
        $this->editandoId = $variable->id;
        $this->clave = $variable->clave;
        $this->nombre = $variable->nombre;
        $this->unidad = $variable->unidad ?? '';

        $this->mostrarModal = true;
    }

    /**
     * La clave solo se carga al crear: es el nombre con el que el nodo envía la lectura.
     */
    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarVariables->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para guardar variables.')->send();

            return;
        }

        $reglas = [
            'nombre' => ['required', 'string', 'max:100'],
            'unidad' => ['nullable', 'string', 'max:20'],
        ];

        if ($this->editandoId === null) {
            $this->clave = trim($this->clave);
            $reglas['clave'] = ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('variables', 'clave')];
        }

        $datos = $this->validate($reglas, [
            'clave.regex' => 'La clave solo puede tener minúsculas, números y guion bajo, y debe empezar con una letra.',
            'clave.unique' => 'Esa clave ya está asignada a otra variable.',
        ]);

        $datos['unidad'] = filled($datos['unidad'] ?? null) ? trim($datos['unidad']) : null;

        if ($this->editandoId !== null) {
            Variable::query()->findOrFail($this->editandoId)->update($datos);
        } else {
            Variable::query()->create($datos);
        }

        $this->mostrarModal = false;
        $this->resetearFormulario();

        $this->toast()->success('Guardado', 'Variable guardada correctamente.')->send();
    }

    private function resetearFormulario(): void
    {
        $this->editandoId = null;
        $this->clave = '';
        $this->nombre = '';
        $this->unidad = '';
        $this->resetValidation();
    }

    /**
     * @return Collection<int, Variable>
     */
    #[Computed]
    public function variables(): Collection
    {
        return Variable::query()->orderBy('id')->get();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.variables');
    }
}
