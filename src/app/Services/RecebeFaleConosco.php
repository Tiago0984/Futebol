<?php

namespace App\Services;

use App\Jobs\EnviaEmailFaleConosco;
use App\Models\Atleta;
use App\Models\FaleConosco;
use App\Models\Responsavel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RecebeFaleConosco
{
    public function receber(array $dados, Atleta|Responsavel|null $conta = null): FaleConosco
    {
        $perfil = $conta instanceof Atleta ? 'atleta' : 'responsavel';

        $mensagem = FaleConosco::create([
            'id_atleta' => $conta instanceof Atleta ? $conta->getKey() : null,
            'id_responsavel' => $conta instanceof Responsavel ? $conta->getKey() : null,
            'origem_fale_conosco' => $conta ? 'APP' : 'SITE',
            'perfil_fale_conosco' => $conta ? strtoupper($perfil) : 'VISITANTE',
            'nome_fale_conosco' => $conta ? $conta->{'nome_' . $perfil} : $dados['nome'],
            'email_fale_conosco' => $conta ? $conta->{'email_' . $perfil} : $dados['email'],
            'telefone_fale_conosco' => $conta ? null : ($dados['telefone'] ?? null),
            'assunto_fale_conosco' => $dados['assunto'],
            'mensagem_fale_conosco' => $dados['mensagem'],
            'data_fale_conosco' => now(),
        ]);

        // A mensagem já está salva. Uma falha no SMTP não deve provocar outro envio pelo usuário.
        // O e-mail vai para a fila (até 3 tentativas): fica PENDENTE até o job marcar ENVIADO ou FALHOU.
        $destinatario = config('contato.email');
        if (! is_string($destinatario) || ! filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            // Tentar de novo não resolve: falha já, sem passar pela fila.
            $this->registrarFalha($mensagem, new RuntimeException('Configure AACJ_CONTATO_EMAIL com um e-mail válido.'));

            return $mensagem;
        }

        try {
            // Bus::dispatch na hora (não o dispatch() adiado): com a fila "sync" a exceção chega aqui.
            Bus::dispatch(new EnviaEmailFaleConosco($mensagem->getKey()));
        } catch (Throwable $e) {
            // Na fila "sync", o failed() do job já marcou FALHOU e registrou; senão, o job nem foi gravado.
            if ($mensagem->refresh()->status_email_fale_conosco !== 'FALHOU') {
                $this->registrarFalha($mensagem, $e);
            }
        }

        return $mensagem->refresh();
    }

    private function registrarFalha(FaleConosco $mensagem, Throwable $e): void
    {
        $mensagem->status_email_fale_conosco = 'FALHOU';
        $mensagem->save();
        Log::warning('Falha ao enviar e-mail do fale conosco.', [
            'id_fale_conosco' => $mensagem->getKey(),
            'tipo_erro' => get_class($e),
        ]);
    }
}
