<?php

namespace App\Models\Concerns;

use App\Mail\DefinirSenhaMail;
use App\Models\RecuperacaoSenha;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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
     * Senha definida por link ou código: grava, encerra as sessões e invalida recuperações do perfil.
     * O outro perfil, mesmo com o mesmo e-mail, não muda. Mesma ordem de bloqueio do serviço: conta, recuperação.
     */
    public function definirSenha(string $senha): void
    {
        DB::transaction(function () use ($senha) {
            static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $this->gravarSenha(Hash::make($senha));
            $this->tokens()->delete();
            $this->invalidarRecuperacoesDeSenha();
        });
    }

    public function invalidarRecuperacoesDeSenha(): void
    {
        // Preserva a data do envio para que consumir o token não contorne o intervalo mínimo de 60 segundos.
        RecuperacaoSenha::where('id_' . static::PERFIL, $this->getKey())->update([
            'codigo_hash_recuperacao_senha' => null,
            'data_expiracao_codigo_recuperacao_senha' => null,
            'reset_token_hash_recuperacao_senha' => null,
            'data_expiracao_token_recuperacao_senha' => null,
        ]);
        static::broker()->deleteToken($this);
    }

    // Alteração com a senha atual: preserva o aparelho em uso e invalida recuperações anteriores.
    public function trocarSenhaPeloApp(string $atual, string $nova, int $tokenAtual): bool
    {
        return DB::transaction(function () use ($atual, $nova, $tokenAtual) {
            $conta = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if (! Hash::check($atual, $conta->getAuthPassword())) {
                return false;
            }
            $conta->gravarSenha(Hash::make($nova));
            $conta->tokens()->where('id', '!=', $tokenAtual)->delete();
            $conta->invalidarRecuperacoesDeSenha();

            return true;
        });
    }

    /**
     * Gera o link de 24 horas e envia por e-mail. Devolve o link quando o envio falha (o admin copia e
     * envia), ou null quando foi enviado. Convite = "Defina sua senha" (aprovação e admin); senão,
     * mensagens antigas de "Esqueci minha senha". A recuperação atual da API usa códigos, fora do broker.
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
