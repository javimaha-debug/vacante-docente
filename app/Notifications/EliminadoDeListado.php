<?php

namespace App\Notifications;

use App\Models\Proceso;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a teacher whose entry disappeared from a listing on re-import. Phrased
 * neutrally — a removal can mean "adjudicado y fuera de la bolsa activa" or a
 * genuine drop, so it invites the user to check rather than alarming them.
 */
class EliminadoDeListado extends Notification
{
    use Queueable;

    public function __construct(public readonly Proceso $proceso) {}

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
        return (new MailMessage)
            ->subject('Cambio en el listado — '.$this->proceso->nombre)
            ->greeting('Hola'.($notifiable->name ? ', '.$notifiable->name : '').',')
            ->line($this->descripcion())
            ->action('Revisar en la plataforma', rtrim((string) config('app.url'), '/').'/dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'eliminado_listado',
            'proceso_id' => $this->proceso->id,
            'titulo' => 'Ya no apareces en el listado',
            'descripcion' => $this->descripcion(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Cambio en el listado',
            'body' => $this->descripcion(),
            'url' => rtrim((string) config('app.url'), '/').'/dashboard',
        ];
    }

    private function descripcion(): string
    {
        return "Ya no apareces en la última versión del listado «{$this->proceso->nombre}». ".
            'Puede ser por una adjudicación o un cambio en la bolsa: revísalo en la plataforma.';
    }
}
