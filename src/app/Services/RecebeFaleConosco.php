<?php

namespace App\Services;

use App\Mail\FaleConoscoMail;
use App\Models\Atleta;
use App\Models\FaleConosco;
use App\Models\Responsavel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        try {
            $destinatario = config('contato.email');
            if (! is_string($destinatario) || ! filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Configure AACJ_CONTATO_EMAIL com um e-mail válido.');
            }
            Mail::to($destinatario)->send(new FaleConoscoMail($mensagem));
        } catch (Throwable $e) {
            $mensagem->status_email_fale_conosco = 'FALHOU';
            $mensagem->save();
            Log::warning('Falha ao enviar e-mail do fale conosco.', [
                'id_fale_conosco' => $mensagem->getKey(),
                'tipo_erro' => get_class($e),
            ]);

            return $mensagem;
        }

        $mensagem->status_email_fale_conosco = 'ENVIADO';
        $mensagem->save();

        return $mensagem;
    }
}
