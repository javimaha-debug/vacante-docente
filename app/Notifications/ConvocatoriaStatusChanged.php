<?php

namespace App\Notifications;

use App\Models\Convocatoria;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to users who activated an alert on a convocatoria when a superadmin
 * changes its estado. Delivered in-app (database) and by email.
 */
class ConvocatoriaStatusChanged extends Notification
{
    use Queueable;

    private const ESTADO_LABEL = [
        'rumor' => 'Rumor',
        'anunciada' => 'Anunciada',
        'convocada' => 'Convocada',
        'en_proceso' => 'En proceso',
        'resuelta' => 'Resuelta',
    ];

    public function __construct(
        public readonly Convocatoria $convocatoria,
        public readonly string $estadoAnterior,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (($notifiable->notificaciones_email ?? true) && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/').'/dashboard/convocatorias';

        return (new MailMessage)
            ->subject($this->titulo())
            ->greeting('Hola'.($notifiable->name ? ', '.$notifiable->name : '').',')
            ->line($this->descripcion())
            ->action('Ver detalles', $url)
            ->line('Recibes este aviso porque activaste una alerta para esta convocatoria.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'convocatoria_estado',
            'convocatoria_id' => $this->convocatoria->id,
            'titulo' => $this->titulo(),
            'descripcion' => $this->descripcion(),
            'estado' => $this->convocatoria->estado,
            'estado_anterior' => $this->estadoAnterior,
        ];
    }

    public function titulo(): string
    {
        return "Cambio en la convocatoria: {$this->convocatoria->titulo}";
    }

    public function descripcion(): string
    {
        $estado = self::ESTADO_LABEL[$this->convocatoria->estado] ?? $this->convocatoria->estado;
        $fecha = $this->convocatoria->fecha_oficial?->format('d/m/Y');
        $base = "La convocatoria «{$this->convocatoria->titulo}» ha cambiado a {$estado}.";

        return $fecha ? $base." Fecha: {$fecha}." : $base;
    }
}
