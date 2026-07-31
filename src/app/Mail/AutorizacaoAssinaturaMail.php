<?php

namespace App\Mail;

use App\Models\Atleta;
use App\Models\Responsavel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutorizacaoAssinaturaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Atleta $atleta,
        public Responsavel $responsavel,
        public string $linkAssinatura,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Autorização de cadastro de {$this->atleta->nome_atleta} - AACJ Futebol",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.autorizacao-assinatura',
        );
    }
}
