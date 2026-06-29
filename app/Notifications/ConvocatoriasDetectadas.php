<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells superadmins the convocatorias monitor found new calls to review.
 */
class ConvocatoriasDetectadas extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $titulos
     */
    public function __construct(
        public readonly int $nuevas,
        public readonly array $titulos = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Doccentia · {$this->nuevas} convocatoria(s) detectada(s)")
            ->greeting('Hola,')
            ->line("El monitor ha detectado {$this->nuevas} posible(s) convocatoria(s) que requieren revisión.");

        foreach (array_slice($this->titulos, 0, 10) as $titulo) {
            $mail->line('• '.$titulo);
        }

        return $mail->action('Revisar convocatorias', url('/superadmin/convocatorias'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'convocatorias_detectadas',
            'titulo' => "{$this->nuevas} convocatoria(s) detectada(s)",
            'descripcion' => implode(' · ', array_slice($this->titulos, 0, 5)),
            'nuevas' => $this->nuevas,
        ];
    }
}
