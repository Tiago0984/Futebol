<?php

namespace App\Services;

use App\Mail\CodigoRecuperacaoSenhaMail;
use App\Models\Atleta;
use App\Models\RecuperacaoSenha;
use App\Models\Responsavel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

class RecuperaSenhaPorCodigo
{
    public const MINUTOS_CODIGO = 15;
    public const MINUTOS_TOKEN = 10;
    public const MAX_TENTATIVAS = 5;

    private const MODELS = ['atleta' => Atleta::class, 'responsavel' => Responsavel::class];

    public function solicitar(string $email, string $perfil): void
    {
        // Todas as operações bloqueiam primeiro a conta e depois a recuperação, inclusive no primeiro envio.
        $envio = DB::transaction(function () use ($email, $perfil) {
            $conta = $this->contaPorEmail($email, $perfil);
            if (! $conta || ! $conta->podeEntrarNoApp()) {
                return null;
            }

            $recuperacao = $this->daConta($conta)->lockForUpdate()->first();
            if ($recuperacao && $recuperacao->data_codigo_recuperacao_senha->gt(now()->subMinute())) {
                return null;
            }

            $codigo = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $recuperacao ??= new RecuperacaoSenha;
            $recuperacao->fill([
                'id_atleta' => $conta instanceof Atleta ? $conta->getKey() : null,
                'id_responsavel' => $conta instanceof Responsavel ? $conta->getKey() : null,
                'perfil_recuperacao_senha' => strtoupper($perfil),
                'email_recuperacao_senha' => $this->emailDaConta($conta),
                'codigo_hash_recuperacao_senha' => Hash::make($codigo),
                'data_codigo_recuperacao_senha' => now(),
                'data_expiracao_codigo_recuperacao_senha' => now()->addMinutes(self::MINUTOS_CODIGO),
                'tentativas_recuperacao_senha' => 0,
                'reset_token_hash_recuperacao_senha' => null,
                'data_expiracao_token_recuperacao_senha' => null,
            ])->save();

            return [$conta, $codigo];
        });

        if ($envio) {
            [$conta, $codigo] = $envio;
            // Depois da resposta (como o push): a API responde no mesmo tempo com ou sem conta, sem
            // esperar o SMTP, e o código chega na hora. Sem fila de propósito: o código em texto
            // puro não é gravado na tabela jobs. Sem nova tentativa; a pessoa pede outro após 60 s.
            // always: o código já foi gravado, mesmo se outra etapa da resposta der erro.
            defer(fn () => $this->enviarCodigo($conta, $perfil, $codigo))->always();
        }
    }

    private function enviarCodigo(Atleta|Responsavel $conta, string $perfil, string $codigo): void
    {
        try {
            Mail::to($conta->emailDoApp())->send(new CodigoRecuperacaoSenhaMail($conta->nomeNoApp(), $perfil, $codigo));
        } catch (Throwable $e) {
            // Não devolver código/token na API ou registrar esses segredos no log de falhas.
            Log::warning('Falha ao enviar código de recuperação do app.', [
                'perfil' => $perfil, 'id' => $conta->getKey(), 'tipo_erro' => get_class($e),
            ]);
        }
    }

    public function validar(string $email, string $perfil, string $codigo): ?string
    {
        return DB::transaction(function () use ($email, $perfil, $codigo) {
            $conta = $this->contaPorEmail($email, $perfil);
            if (! $conta || ! $conta->podeEntrarNoApp()) {
                return null;
            }

            $recuperacao = $this->daConta($conta)->lockForUpdate()->first();
            if (! $recuperacao || $recuperacao->email_recuperacao_senha !== $this->emailDaConta($conta)
                || ! $recuperacao->codigo_hash_recuperacao_senha
                || ! $recuperacao->data_expiracao_codigo_recuperacao_senha?->isFuture()
                || $recuperacao->tentativas_recuperacao_senha >= self::MAX_TENTATIVAS) {
                return null;
            }

            if (! Hash::check($codigo, $recuperacao->codigo_hash_recuperacao_senha)) {
                $recuperacao->tentativas_recuperacao_senha++;
                if ($recuperacao->tentativas_recuperacao_senha >= self::MAX_TENTATIVAS) {
                    $recuperacao->codigo_hash_recuperacao_senha = null;
                    $recuperacao->data_expiracao_codigo_recuperacao_senha = null;
                }
                $recuperacao->save();

                // O erro 422 é lançado pelo controller depois do commit: não desfaz o contador de erros.
                return null;
            }

            $token = bin2hex(random_bytes(32));
            $recuperacao->codigo_hash_recuperacao_senha = null;
            $recuperacao->data_expiracao_codigo_recuperacao_senha = null;
            $recuperacao->reset_token_hash_recuperacao_senha = hash('sha256', $token);
            $recuperacao->data_expiracao_token_recuperacao_senha = now()->addMinutes(self::MINUTOS_TOKEN);
            $recuperacao->save();

            return $token;
        });
    }

    public function redefinir(string $token, string $senha): bool
    {
        $hash = hash('sha256', $token);
        // Só localiza a conta aqui; validade e consumo são conferidos novamente sob os bloqueios.
        $referencia = RecuperacaoSenha::where('reset_token_hash_recuperacao_senha', $hash)->first();
        if (! $referencia) {
            return false;
        }

        return DB::transaction(function () use ($referencia, $hash, $senha) {
            $perfil = strtolower($referencia->perfil_recuperacao_senha);
            $conta = self::MODELS[$perfil]::whereKey($referencia->{'id_' . $perfil})->lockForUpdate()->first();
            if (! $conta || ! $conta->podeEntrarNoApp()) {
                return false;
            }

            $recuperacao = $this->daConta($conta)->lockForUpdate()->first();
            if (! $recuperacao || ! hash_equals((string) $recuperacao->reset_token_hash_recuperacao_senha, $hash)
                || $recuperacao->email_recuperacao_senha !== $this->emailDaConta($conta)
                || ! $recuperacao->data_expiracao_token_recuperacao_senha?->isFuture()) {
                return false;
            }

            // Em uma transação: troca a senha e revoga sessões, links e segredos da recuperação (uso único).
            $conta->definirSenha($senha);

            return true;
        });
    }

    private function contaPorEmail(string $email, string $perfil): Atleta|Responsavel|null
    {
        $model = self::MODELS[$perfil];

        return $model::where($model::COLUNA_EMAIL, trim($email))->lockForUpdate()->first();
    }

    private function daConta(Atleta|Responsavel $conta)
    {
        return RecuperacaoSenha::where('id_' . $conta::PERFIL, $conta->getKey());
    }

    private function emailDaConta(Atleta|Responsavel $conta): string
    {
        return mb_strtolower(trim((string) $conta->emailDoApp()));
    }
}
