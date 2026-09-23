<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Enlace para que un usuario creado por el administrador cree su propia
 * contraseña (CU-03, pasos 7-8).
 *
 * No va en cola, a diferencia de `AlertaGenerada`: CU-03 FA03 pide avisarle
 * al administrador si el envío falla, y eso solo se puede saber si se envía
 * dentro de la misma request.
 */
class InvitacionUsuario extends Notification
{
    use Queueable;

    public const VIGENCIA_HORAS = 48;

    /**
     * Huella de la contraseña actual, incluida en la URL firmada. Cuando el
     * usuario crea su contraseña el hash cambia y la huella deja de coincidir,
     * así que el enlace sirve una sola vez aunque todavía no haya vencido.
     */
    public static function huella(User $user): string
    {
        return sha1($user->getAuthPassword());
    }

    public static function url(User $user): string
    {
        return URL::temporarySignedRoute('password.create', now()->addHours(self::VIGENCIA_HORAS), [
            'user' => $user->id,
            'huella' => self::huella($user),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Creá tu contraseña - Aguape')
            ->greeting("Hola, {$notifiable->name}")
            ->line("Tenés una cuenta en Aguape con el usuario {$notifiable->name}.")
            ->line('Usá el siguiente enlace para crear tu contraseña e ingresar al sistema.')
            ->action('Creá tu contraseña', self::url($notifiable))
            ->line('El enlace vence en '.self::VIGENCIA_HORAS.' horas y se puede usar una sola vez.');
    }
}
