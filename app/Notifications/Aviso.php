<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso del sistema: siempre a la campana; por correo si el usuario tiene correo
 * y no apagó "Recibir avisos por correo" en su perfil.
 * Va en cola: en producción la procesa el cron (ver docs/DESPLIEGUE.md).
 */
class Aviso extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $titulo,
        public string $mensaje,
        public ?string $enlace = null,
        public string $accion = 'Ver en el sistema',
    ) {}

    public function via(object $notifiable): array
    {
        $canales = ['database'];
        if (! empty($notifiable->email) && ($notifiable->notif_email ?? false) && ($notifiable->activo ?? true)) {
            $canales[] = 'mail';
        }

        return $canales;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->titulo.' · Tuboformas')
            ->greeting('Hola, '.strtok($notifiable->name, ' '))
            ->line('**'.$this->titulo.'**')
            ->line($this->mensaje);
        if ($this->enlace) {
            $mail->action($this->accion, $this->enlace);
        }

        return $mail->salutation('Sistema Tuboformas')
            ->line('Recibes este correo porque tienes activados los avisos por correo. Puedes desactivarlos en Mi perfil.');
    }

    public function toArray(object $notifiable): array
    {
        return ['titulo' => $this->titulo, 'mensaje' => $this->mensaje, 'enlace' => $this->enlace];
    }
}
