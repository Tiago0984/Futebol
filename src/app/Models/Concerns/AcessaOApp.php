<?php

namespace App\Models\Concerns;

use App\Mail\DefinirSenhaMail;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Login no app (Fase 9), igual para os dois perfis, atleta e responsável. O model define:
 * - PERFIL ('atleta' ou 'responsavel'), BROKER (config/auth.php, passwords) e COLUNA_EMAIL;
 * - podeEntrarNoApp(), semSenha(), gravarSenha() e nomeNoApp().
 */
trait AcessaOApp
{
    abstract public function podeEntrarNoApp(): bool;

    abstract public function nomeNoApp(): string;

    abstract public function semSenha(): bool;

    // Grava a senha nova (com hash) na coluna do perfil
    abstract protected function gravarSenha(string $hash): void;

    public static function broker(): PasswordBroker
    {
        return Password::broker(static::BROKER);
    }

    // Login e links pelo e-mail do perfil (a collation ignora maiúsculas; espaços nas pontas saem)
    public static function porEmail(?string $email): ?static
    {
        $email = trim((string) $email);

        return $email === '' ? null : static::where(static::COLUNA_EMAIL, $email)->first();
    }

    public function emailDoApp(): ?string
    {
        return filled($this->{static::COLUNA_EMAIL}) ? $this->{static::COLUNA_EMAIL} : null;
    }

    // Chave da tabela de tokens do broker (o padrão do Laravel lê a coluna "email")
    public function getEmailForPasswordReset()
    {
        return $this->emailDoApp();
    }

    /**
     * Senha definida pelo link: grava e encerra as sessões do app deste perfil (os tokens do Sanctum).
     * O token do link é apagado pelo broker. O outro perfil, mesmo com o mesmo e-mail, não muda.
     */
    public function definirSenha(string $senha): void
    {
        $this->gravarSenha(Hash::make($senha));
        $this->tokens()->delete();
    }

    /**
     * Gera o link de 24 horas e envia por e-mail. Devolve o link quando o envio falha (o admin copia e
     * envia), ou null quando foi enviado. Convite = "Defina sua senha" (aprovação e admin); senão,
     * "Esqueci minha senha".
     */
    public function enviarLinkDeSenha(bool $convite = true): ?string
    {
        $link = route('senha.definir', [
            'perfil' => static::PERFIL,
            'token'  => static::broker()->createToken($this),
            'email'  => $this->emailDoApp(),
        ]);

        try {
            Mail::to($this->emailDoApp())->send(new DefinirSenhaMail($this->nomeNoApp(), static::PERFIL, $link, $convite));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar o link de senha do app', [
                'perfil' => static::PERFIL,
                'id'     => $this->getKey(),
                'erro'   => $e->getMessage(),
            ]);

            return $link;
        }

        return null;
    }
}
