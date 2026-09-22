<?php

namespace App\Http\Middleware;

use App\Rules\RecaptchaValidationRule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class VerifyRecaptcha
{
    /**
     * Rutas que exigen reCAPTCHA además del login (RNF05: "Los formularios
     * de login y recuperación de contraseña deben incorporar verificación
     * mediante CAPTCHA"). El login se valida en `Fortify::authenticateUsing()`
     * (`FortifyServiceProvider`) porque ahí sí hay un hook — recuperación de
     * contraseña no tiene un hook equivalente, así que se resuelve con este
     * middleware.
     *
     * Registrado en el grupo `web` global (`bootstrap/app.php`) en vez de
     * atado a las rutas de Fortify por nombre: intentar agregarle middleware
     * a una ruta de Fortify después de que arrancan los providers
     * (`$this->app->booted(...)`) no funciona — Laravel registra las rutas
     * de forma diferida, así que en ese momento la ruta todavía no existe en
     * `Route::getRoutes()`. El middleware global corre en toda request pero
     * se sale al toque si la ruta actual no es una de estas dos.
     *
     * @var list<string>
     */
    private const RUTAS_PROTEGIDAS = ['password.email', 'password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::RUTAS_PROTEGIDAS)) {
            return $next($request);
        }

        Validator::make($request->all(), [
            'g-recaptcha-response' => ['required', new RecaptchaValidationRule],
        ])->validate();

        return $next($request);
    }
}
