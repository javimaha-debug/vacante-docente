<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells superadmins the document monitor found new documents to review.
 */
class DocumentosDetectados extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $titulos
     */
    public function __construct(
        public readonly int $nuevos,
        public readonly array $titulos = [],
        public readonly int $eventosSugeridos = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Doccentia · {$this->nuevos} documento(s) nuevo(s) detectado(s)")
            ->greeting('Hola,')
            ->line("El monitor ha detectado {$this->nuevos} documento(s) que requieren revisión.");

        foreach (array_slice($this->titulos, 0, 10) as $titulo) {
            $mail->line('• '.$titulo);
        }

        if ($this->eventosSugeridos > 0) {
            $mail->line("Se han sugerido {$this->eventosSugeridos} evento(s) de calendario para confirmar.");
        }

        return $mail->action('Revisar documentos', url('/superadmin/monitor-docs'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'documentos_detectados',
            'titulo' => "{$this->nuevos} documento(s) nuevo(s) detectado(s)",
            'descripcion' => implode(' · ', array_slice($this->titulos, 0, 5)),
            'nuevos' => $this->nuevos,
            'eventos_sugeridos' => $this->eventosSugeridos,
        ];
    }
}
