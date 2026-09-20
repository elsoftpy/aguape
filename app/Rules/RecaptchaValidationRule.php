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
        $recaptcha = new ReCaptcha(config('services.recaptcha.secretkey'));

        $response = $recaptcha->verify($value, request()->ip());

        if (! $response->isSuccess()) {
            $fail('Verificá que no sos un robot');
        }
    }
}
