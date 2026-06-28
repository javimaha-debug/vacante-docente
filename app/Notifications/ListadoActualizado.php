<?php

namespace App\Notifications;

use App\Models\Proceso;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a teacher when a listing they follow (vacancies or participants)
 * changes on re-import. Delivered in-app (database), by email, and — when the
 * recipient has a push subscription and VAPID is configured — by web push.
 */
class ListadoActualizado extends Notification
{
    use Queueable;

    /**
     * @param  'vacantes'|'participantes'  $tipo
     * @param  array{nuevas?:int,modificadas?:int,eliminadas?:int,nuevos?:int,modificados?:int,eliminados?:int}  $resumen
     */
    public function __construct(
        public readonly Proceso $proceso,
        public readonly string $tipo,
        public readonly array $resumen,
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

        // Web push is opt-in and only meaningful once the push channel ships,
        // VAPID is configured, and the user has at least one subscription.
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
            ->subject($this->titulo())
            ->greeting('Hola'.($notifiable->name ? ', '.$notifiable->name : '').',')
            ->line($this->descripcion())
            ->action('Ver en la plataforma', $url)
            ->line('Recibes este aviso porque sigues este proceso. Puedes desactivar los correos en tu perfil.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'proceso_id' => $this->proceso->id,
            'proceso_nombre' => $this->proceso->nombre,
            'tipo' => $this->tipo,
            'titulo' => $this->titulo(),
            'descripcion' => $this->descripcion(),
            'resumen' => $this->resumen,
        ];
    }

    /**
     * Payload for the web push channel.
     *
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->titulo(),
            'body' => $this->descripcion(),
            'url' => rtrim((string) config('app.url'), '/').'/dashboard',
        ];
    }

    public function titulo(): string
    {
        return $this->tipo === 'participantes'
            ? "Lista de participantes actualizada — {$this->proceso->nombre}"
            : "Listado de vacantes actualizado — {$this->proceso->nombre}";
    }

    public function descripcion(): string
    {
        if ($this->tipo === 'participantes') {
            $partes = $this->partes([
                'nuevos' => ['nuevo', 'nuevos'],
                'modificados' => ['modificado', 'modificados'],
                'eliminados' => ['eliminado', 'eliminados'],
            ]);

            return $partes === ''
                ? 'La lista de participantes se ha actualizado.'
                : "Cambios en la lista de participantes: {$partes}.";
        }

        $partes = $this->partes([
            'nuevas' => ['nueva', 'nuevas'],
            'modificadas' => ['modificada', 'modificadas'],
            'eliminadas' => ['eliminada', 'eliminadas'],
        ]);

        return $partes === ''
            ? 'El listado de vacantes se ha actualizado.'
            : "Cambios en el listado de vacantes: {$partes}.";
    }

    /**
     * @param  array<string, array{0:string,1:string}>  $labels
     */
    private function partes(array $labels): string
    {
        $out = [];
        foreach ($labels as $key => [$singular, $plural]) {
            $n = (int) ($this->resumen[$key] ?? 0);
            if ($n > 0) {
                $out[] = "{$n} ".($n === 1 ? $singular : $plural);
            }
        }

        return implode(' · ', $out);
    }
}
