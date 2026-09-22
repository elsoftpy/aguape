<?php

namespace App\Livewire\Pages;

use App\Models\Establecimiento;
use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

#[Layout('layouts::app')]
#[Title('Mi cuenta')]
class MiCuenta extends Component
{
    use Interactions;

    /**
     * Activar/confirmar/regenerar códigos siguen siendo formularios planos
     * que postean directo a las rutas de Fortify (mismo criterio que
     * `login.blade.php`) — solo desactivar 2FA pasa por una acción propia,
     * para poder mostrar el diálogo de confirmación de TallStackUI en vez
     * del `confirm()` nativo del navegador.
     */
    #[Computed]
    public function usuario(): User
    {
        return auth()->user()->fresh();
    }

    public function confirmarDesactivacion(): void
    {
        $this->dialog()
            ->question('¿Desactivar la verificación en dos pasos?', 'Vas a poder ingresar solo con tu contraseña.')
            ->confirm('Sí, desactivar', 'desactivar')
            ->cancel('Cancelar')
            ->send();
    }

    public function desactivar(): void
    {
        app(DisableTwoFactorAuthentication::class)($this->usuario);

        $this->toast()->success('Desactivada', 'Verificación en dos pasos desactivada.')->send();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.mi-cuenta');
    }
}
