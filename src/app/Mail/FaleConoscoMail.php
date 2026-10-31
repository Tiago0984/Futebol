<?php

namespace App\Mail;

use App\Models\FaleConosco;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FaleConoscoMail extends Mailable
{
    public function __construct(public FaleConosco $mensagem)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->mensagem->email_fale_conosco, $this->mensagem->nome_fale_conosco)],
            subject: 'Fale conosco - AACJ: ' . preg_replace('/[\r\n]+/', ' ', $this->mensagem->assunto_fale_conosco),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.fale-conosco');
    }
}
