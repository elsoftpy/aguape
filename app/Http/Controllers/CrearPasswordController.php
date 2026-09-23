<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Notifications\InvitacionUsuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrearPasswordController extends Controller
{
    public function show(Request $request, User $user): View
    {
        return view('auth.create-password', [
            'user' => $user,
            'enlaceValido' => $this->enlaceValido($request, $user),
        ]);
    }

    public function store(Request $request, User $user, ResetUserPassword $resetUserPassword): RedirectResponse|View
    {
        if (! $this->enlaceValido($request, $user)) {
            return view('auth.create-password', ['user' => $user, 'enlaceValido' => false]);
        }

        $resetUserPassword->reset($user, $request->only('password', 'password_confirmation'));

        return redirect()->route('login')->with('status', 'Tu contraseña fue creada. Ya podés iniciar sesión.');
    }

    private function enlaceValido(Request $request, User $user): bool
    {
        return $request->hasValidSignature()
            && hash_equals(InvitacionUsuario::huella($user), (string) $request->query('huella'));
    }
}
