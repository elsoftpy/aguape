<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Models\DestinatarioAlerta;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Destinatarios de alertas')]
class Destinatarios extends Component
{
    use Interactions;

    public string $email = '';

    public function agregar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarDestinatarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para configurar destinatarios.')->send();

            return;
        }

        $this->email = Str::lower(trim($this->email));

        $datos = $this->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('destinatarios_alerta', 'email')],
        ], [
            'email.unique' => 'Ese correo ya está en la lista de destinatarios.',
        ]);

        DestinatarioAlerta::query()->create($datos);

        $this->reset('email');

        $this->toast()->success('Agregado', 'El destinatario recibirá las próximas alertas.')->send();
    }

    public function confirmarRetiro(DestinatarioAlerta $destinatario): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarDestinatarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para configurar destinatarios.')->send();

            return;
        }

        $this->dialog()
            ->question('¿Retirar este destinatario?', "{$destinatario->email} dejará de recibir las alertas.")
            ->confirm('Sí, retirar', 'retirar', $destinatario->id)
            ->cancel('Cancelar')
            ->send();
    }

    public function retirar(int $id): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarDestinatarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para configurar destinatarios.')->send();

            return;
        }

        DestinatarioAlerta::query()->findOrFail($id)->delete();

        $this->toast()->success('Retirado', 'El destinatario fue retirado de la lista.')->send();
    }

    /**
     * @return Collection<int, DestinatarioAlerta>
     */
    #[Computed]
    public function destinatarios(): Collection
    {
        return DestinatarioAlerta::query()->orderBy('email')->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function destinatariosPorDefecto(): Collection
    {
        return User::query()
            ->permission(PermissionsEnum::GestionarAlertas->value)
            ->orderBy('email')
            ->get();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::unicoRegistrado();
    }

    public function render()
    {
        return view('livewire.pages.destinatarios');
    }
}
