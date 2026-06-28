<?php

namespace App\Notifications;

use App\Models\GvaNoticia;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins after the GVA monitor auto-imports a detected listing, so they
 * can review the result (and re-import / fix if the mapping was wrong).
 */
class ListadoImportadoAdmin extends Notification
{
    use Queueable;

    public function __construct(public readonly GvaNoticia $noticia) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $estado = $this->noticia->import_estado;
        $mail = (new MailMessage)
            ->subject('['.strtoupper((string) $estado).'] Listado GVA importado automáticamente')
            ->greeting('Hola,')
            ->line($this->descripcion())
            ->line('Detectado: '.$this->noticia->titulo)
            ->action('Ver el documento original', $this->noticia->url);

        if ($estado !== 'ok') {
            $mail->line('Revisa este listado: puede requerir importación manual.');
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'gva_auto_import',
            'titulo' => $this->titulo(),
            'descripcion' => $this->descripcion(),
            'estado' => $this->noticia->import_estado,
            'url' => $this->noticia->url,
            'noticia_id' => $this->noticia->id,
            'proceso_id' => $this->noticia->proceso_id,
        ];
    }

    private function titulo(): string
    {
        return match ($this->noticia->import_estado) {
            'ok' => 'Listado GVA importado automáticamente',
            'sin_proceso' => 'Listado GVA detectado (requiere importación manual)',
            default => 'Error al importar un listado GVA',
        };
    }

    private function descripcion(): string
    {
        return $this->noticia->import_resumen ?: 'Se ha procesado un listado de la GVA.';
    }
}
