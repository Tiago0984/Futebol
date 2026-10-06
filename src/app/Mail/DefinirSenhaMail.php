<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link para definir a senha do app (Fase 9): convite ("Defina sua senha", na aprovação e pelo admin) ou
 * "Esqueci minha senha". Vale 24 horas; vencido, basta pedir outro pelo "Esqueci minha senha" do app.
 */
class DefinirSenhaMail extends Mailable
{
    use Queueable, SerializesModels;

    public const HORAS_DE_VALIDADE = 24;

    public function __construct(
        public string $nome,
        public string $perfil,
        public string $link,
        public bool $convite,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->convite
                ? 'Defina sua senha do app - AACJ Futebol'
                : 'Nova senha do app - AACJ Futebol',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.definir-senha',
            with: [
                'rotuloPerfil' => $this->perfil === 'responsavel' ? 'responsável' : 'atleta',
                'horas'        => self::HORAS_DE_VALIDADE,
            ],
        );
    }
}
