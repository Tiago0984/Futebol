<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Atleta;
use App\Models\Responsavel;
use Illuminate\Support\Collection;

/**
 * Convite do app ("Defina sua senha", Fase 9), usado na aprovação da matrícula e na tela de Atletas.
 * Falha no envio: a mensagem pede para copiar o link, e os links vão para session('links_convite'),
 * que a tela mostra pelo partial admin.atletas._links_convite (o link não fica guardado: o broker só
 * grava o hash do token).
 */
trait EnviaConvitesDoApp
{
    /**
     * Envia o convite para cada atleta/responsável e devolve o trecho da mensagem (começa com espaço,
     * vazio sem ninguém). Os links das falhas vão para a sessão.
     */
    private function enviarConvites(Collection $usuarios): string
    {
        $enviados = [];
        $falhas = [];

        foreach ($usuarios as $usuario) {
            $para = $this->rotuloDoConvite($usuario);

            if ($link = $usuario->enviarLinkDeSenha()) {
                $falhas[] = ['para' => $para, 'link' => $link];
            } else {
                $enviados[] = $para;
            }
        }

        if ($falhas) {
            session()->flash('links_convite', $falhas);
        }

        $mensagem = '';
        if ($enviados) {
            $mensagem .= ' Convite do app enviado para: ' . implode('; ', $enviados) . '.';
        }
        if ($falhas) {
            $mensagem .= ' Não foi possível enviar o convite do app para: ' . implode('; ', array_column($falhas, 'para'))
                . '. Copie o link abaixo e envie.';
        }

        return $mensagem;
    }

    // "o atleta Ana Souza (ana@exemplo.com)" / "o responsável João Souza (joao@exemplo.com)"
    private function rotuloDoConvite(Atleta|Responsavel $usuario): string
    {
        $perfil = $usuario instanceof Atleta ? 'o atleta' : 'o responsável';

        return "{$perfil} {$usuario->nomeNoApp()} ({$usuario->emailDoApp()})";
    }

    /**
     * Quem já está no app e ainda não definiu a senha (botão "Enviar convites pendentes"): atleta ATIVO com
     * e-mail e sem senha; responsável com e-mail, sem senha e com algum filho ATIVO.
     */
    private function pendentesDeConvite(): Collection
    {
        $atletas = Atleta::where('status_atleta', 'ATIVO')
            ->whereNotNull('email_atleta')->where('email_atleta', '<>', '')
            ->aindaSemSenha()
            ->orderBy('nome_atleta')->get();

        $responsaveis = Responsavel::whereNull('senha_responsavel')
            ->whereNotNull('email_responsavel')
            ->whereHas('atletasAtivos')
            ->orderBy('nome_responsavel')->get();

        return $atletas->concat($responsaveis);
    }
}
