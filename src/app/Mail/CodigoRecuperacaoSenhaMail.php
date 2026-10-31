<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CodigoRecuperacaoSenhaMail extends Mailable
{
    public function __construct(public string $nome, public string $perfil, public string $codigo)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Código de recuperação de senha - AACJ Futebol');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.codigo-recuperacao-senha');
    }
}
