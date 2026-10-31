<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContatoSiteRequest;
use App\Services\RecebeFaleConosco;

class ContatoController extends Controller
{
    public function contato()
    {
        return view('site.contato.contato');
    }

    public function enviar(ContatoSiteRequest $request, RecebeFaleConosco $servico)
    {
        $dados = $request->validated();
        $dados['assunto'] = ContatoSiteRequest::ASSUNTOS[$dados['assunto']];
        $servico->receber($dados);

        return redirect()->route('contato', ['#enviar-mensagem'])
            ->with('sucesso', 'Mensagem recebida com sucesso. Obrigado pelo contato!');
    }
}
