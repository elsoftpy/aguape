<?php

namespace App\Livewire\Pages;

use App\Enums\PermissionsEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatus;
use App\Models\Establecimiento;
use App\Models\User;
use App\Notifications\InvitacionUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use OwenIt\Auditing\Events\AuditCustom;
use TallStackUi\Traits\Interactions;
use Throwable;

#[Layout('layouts::app')]
#[Title('Usuarios y roles')]
class Usuarios extends Component
{
    use Interactions, WithPagination;

    /**
     * `null` en modo creación, id del usuario en edición en modo edición.
     */
    public ?int $editandoId = null;

    public string $name = '';

    public string $email = '';

    public string $rol = '';

    public bool $activo = true;

    public bool $mostrarModal = false;

    public function abrirCreacion(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarUsuarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para registrar usuarios.')->send();

            return;
        }

        $this->resetearFormulario();

        $this->mostrarModal = true;
    }

    public function abrirEdicion(User $usuario): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarUsuarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para editar usuarios.')->send();

            return;
        }

        $this->resetearFormulario();

        $this->editandoId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->rol = $usuario->roles->first()?->name ?? '';
        $this->activo = $usuario->status === UserStatus::Activo;

        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarUsuarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para guardar usuarios.')->send();

            return;
        }

        $datos = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')->ignore($this->editandoId)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'rol' => ['required', Rule::enum(RolesEnum::class)],
            'activo' => ['boolean'],
        ]);

        $usuario = $this->editandoId ? User::query()->findOrFail($this->editandoId) : new User;
        $esNuevo = ! $usuario->exists;

        if ($usuario->is(auth()->user()) && (! $datos['activo'] || ! $usuario->hasRole($datos['rol']))) {
            $this->addError('rol', 'No podés cambiar tu propio rol ni desactivar tu propia cuenta.');

            return;
        }

        $status = $datos['activo'] ? UserStatus::Activo : UserStatus::Inactivo;
        $seDesactiva = $usuario->exists && $status === UserStatus::Inactivo && $usuario->status !== UserStatus::Inactivo;

        DB::transaction(function () use ($usuario, $datos, $status, $seDesactiva, $esNuevo) {
            $usuario->name = $datos['name'];
            $usuario->email = $datos['email'];
            $usuario->status = $status;

            if ($esNuevo) {
                $usuario->password = Str::random(64);
            }

            if ($seDesactiva) {
                $usuario->setRememberToken(Str::random(60));
            }

            $usuario->save();

            $this->asignarRol($usuario, $datos['rol']);

            if ($seDesactiva) {
                DB::table('sessions')->where('user_id', $usuario->id)->delete();
            }
        });

        $this->mostrarModal = false;
        $this->resetearFormulario();

        if ($esNuevo) {
            $this->enviarInvitacion($usuario);

            return;
        }

        $this->toast()->success('Guardado', 'Usuario guardado correctamente.')->send();
    }

    /**
     * CU-03 FA03: reintentar el envío del enlace sin volver a crear la cuenta.
     */
    public function reenviarInvitacion(User $usuario): void
    {
        if (! auth()->user()->can(PermissionsEnum::GestionarUsuarios->value)) {
            $this->toast()->error('Sin permiso', 'No tenés permiso para reenviar invitaciones.')->send();

            return;
        }

        $this->enviarInvitacion($usuario);
    }

    /**
     * Si el correo falla, el usuario ya quedó creado: se avisa y se puede
     * reenviar desde la lista (CU-03 FA03).
     */
    private function enviarInvitacion(User $usuario): void
    {
        try {
            $usuario->notify(new InvitacionUsuario);
        } catch (Throwable $e) {
            Log::error('No se pudo enviar la invitación al usuario.', ['user_id' => $usuario->id, 'error' => $e->getMessage()]);

            $this->toast()->warning('Correo no enviado', "No se pudo enviar el enlace a {$usuario->email}. Podés reenviarlo desde la lista.")->send();

            return;
        }

        $this->toast()->success('Invitación enviada', "Se envió el enlace para crear la contraseña a {$usuario->email}.")->send();
    }

    /**
     * `syncRoles()` escribe en `model_has_roles`, que laravel-auditing no
     * observa, así que el cambio de rol se registra a mano como evento
     * propio en `audits` (RF14, postcondición de CU-03).
     */
    private function asignarRol(User $usuario, string $rol): void
    {
        $rolAnterior = $usuario->roles()->value('name');

        $usuario->syncRoles([$rol]);

        if ($rolAnterior === $rol) {
            return;
        }

        $usuario->auditEvent = 'rolActualizado';
        $usuario->isCustomEvent = true;
        $usuario->auditCustomOld = ['rol' => $rolAnterior];
        $usuario->auditCustomNew = ['rol' => $rol];

        Event::dispatch(new AuditCustom($usuario));

        $usuario->isCustomEvent = false;
        $usuario->auditCustomOld = $usuario->auditCustomNew = [];
    }

    private function resetearFormulario(): void
    {
        $this->editandoId = null;
        $this->name = '';
        $this->email = '';
        $this->rol = RolesEnum::Visor->value;
        $this->activo = true;
        $this->resetValidation();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function usuarios(): LengthAwarePaginator
    {
        return User::query()
            ->with(['roles'])
            ->orderBy('name')
            ->paginate(10);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    #[Computed]
    public function rolOptions(): array
    {
        return collect(RolesEnum::cases())
            ->map(fn (RolesEnum $rol) => ['label' => Str::ucfirst($rol->value), 'value' => $rol->value])
            ->all();
    }

    #[Computed]
    public function establecimiento(): ?Establecimiento
    {
        return Establecimiento::query()->first();
    }

    public function render()
    {
        return view('livewire.pages.usuarios');
    }
}
