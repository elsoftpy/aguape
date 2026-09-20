<?php

namespace App\Notifications;

use App\Models\Alerta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaGenerada extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * En cola (RNF09): el backend local no puede quedar atado a la
     * disponibilidad del servidor de correo — la ingesta y la generación de
     * la alerta ya se completaron antes de que esto se encole.
     */
    public function __construct(public readonly Alerta $alerta)
    {
        $this->alerta->loadMissing(['lectura', 'nodo.potrero', 'variable', 'umbral']);
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
        $variable = $this->alerta->variable;
        $nodo = $this->alerta->nodo;
        $potrero = $nodo->potrero;
        $umbral = $this->alerta->umbral;
        $lectura = $this->alerta->lectura;
        $lugarAlerta = $potrero?->nombre ?? $nodo->nombre;

        $mail = (new MailMessage)
            ->subject("Alerta: {$variable->nombre} fuera de rango en {$lugarAlerta}")
            ->greeting('Se generó una nueva alerta');

        if ($potrero !== null) {
            $mail->line("Potrero: {$potrero?->nombre}");
        }

        return $mail
            ->line("Nodo: {$nodo->nombre}")
            ->line("Variable: {$variable->nombre}")
            ->line('Valor medido: '.rtrim(rtrim(number_format((float) $lectura->valor, 2), '0'), '.').' '.$variable->unidad)
            ->line('Umbral incumplido: '.$umbral->tipo->value.' '.rtrim(rtrim(number_format((float) $umbral->valor, 2), '0'), '.').' '.$variable->unidad)
            ->line('Medido el: '.$lectura->medido_en->format('d/m/Y H:i'))
            ->action('Ver panel general', route('dashboard'));
    }
}