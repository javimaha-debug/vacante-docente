<?php

namespace App\Mail;

use App\Models\TablonAnuncio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TablonContactoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TablonAnuncio $anuncio,
        public string $mensaje,
        public string $replyUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tienes un nuevo mensaje sobre tu anuncio: '.$this->anuncio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tablon-contacto',
            with: [
                'categoria' => $this->anuncio->categoria,
                'titulo' => $this->anuncio->titulo,
                'mensaje' => $this->mensaje,
                'replyUrl' => $this->replyUrl,
            ],
        );
    }
}
