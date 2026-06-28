<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to a teacher when they are adjudicated a post in a weekly ("contínua")
 * adjudication tanda. Delivered in-app, by email and (when configured) web push.
 */
class AdjudicacionContinuaAsignada extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $fecha,       // Y-m-d
        public readonly ?string $centro,
        public readonly ?string $localitat,
        public readonly ?string $especialidad,
        public readonly ?string $jornada,
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

        if (class_exists(\App\Notifications\Channels\WebPushChannel::class)
            && config('webpush.vapid.public_key')
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = \App\Notifications\Channels\WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/').'/dashboard';

        return (new MailMessage)
            ->subject('Adjudicació — '.$this->fechaLarga())
            ->greeting('Hola'.($notifiable->name ? ', '.$notifiable->name : '').',')
            ->line($this->descripcion())
            ->action('Ver en la plataforma', $url)
            ->line('Recibes este aviso porque apareces en la adjudicación semanal con tu nombre GVA.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'adjudicacion_continua',
            'titulo' => '¡Tienes adjudicación! ('.$this->fechaLarga().')',
            'descripcion' => $this->descripcion(),
            'fecha' => $this->fecha,
            'centro' => $this->centro,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => '¡Tienes adjudicación!',
            'body' => $this->descripcion(),
            'url' => rtrim((string) config('app.url'), '/').'/dashboard',
        ];
    }

    private function fechaLarga(): string
    {
        try {
            return Carbon::parse($this->fecha)->translatedFormat('d/m/Y');
        } catch (\Throwable) {
            return $this->fecha;
        }
    }

    private function descripcion(): string
    {
        $centro = $this->centro ?: 'un centro';
        $extra = array_filter([$this->localitat, $this->especialidad, $this->jornada]);

        return "Adjudicación del {$this->fechaLarga()}: {$centro}".
            ($extra ? ' ('.implode(' · ', $extra).')' : '').'.';
    }
}
