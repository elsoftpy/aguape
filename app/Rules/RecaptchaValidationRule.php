<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use ReCaptcha\ReCaptcha;

class RecaptchaValidationRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Fortify llama a authenticateUsing() dos veces por login (verificación
        // previa al segundo factor y autenticación). Google invalida el token
        // en la primera verificación, así que el resultado se reutiliza
        // dentro de la misma solicitud.
        $verificados = request()->attributes->get('recaptcha_verificados', []);

        if (! array_key_exists($value, $verificados)) {
            $response = (new ReCaptcha(config('services.recaptcha.secretkey')))
                ->verify($value, request()->ip());
            $verificados[$value] = $response->isSuccess();
            request()->attributes->set('recaptcha_verificados', $verificados);
        }

        if (! $verificados[$value]) {
            $fail('Verificá que no sos un robot');
        }
    }
}
