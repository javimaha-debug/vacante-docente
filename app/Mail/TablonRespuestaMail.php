<?php

namespace App\Mail;

use App\Models\TablonAnuncio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TablonRespuestaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TablonAnuncio $anuncio,
        public string $mensaje,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Respuesta a tu mensaje sobre: '.$this->anuncio->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tablon-respuesta',
            with: [
                'titulo' => $this->anuncio->titulo,
                'mensaje' => $this->mensaje,
            ],
        );
    }
}
