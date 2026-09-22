<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | Traducción al español de las claves que usa el password broker de
    | Laravel (`Illuminate\Contracts\Auth\PasswordBroker`) — `trans($status)`
    | con `$status` literalmente `'passwords.reset'`/`'passwords.sent'`/etc.,
    | no un string plano, así que `lang/es.json` no aplica acá (ver
    | docs/dev-guide.md, mismo caso que `lang/es/validation.php`).
    |
    */

    'reset' => 'Tu contraseña fue restablecida.',
    'sent' => 'Te enviamos por correo el enlace para restablecer tu contraseña.',
    'throttled' => 'Esperá un momento antes de volver a intentar.',
    'token' => 'Este token de restablecimiento de contraseña no es válido.',
    'user' => 'No encontramos ningún usuario con ese correo electrónico.',

];
