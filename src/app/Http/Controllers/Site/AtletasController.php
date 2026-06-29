<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Atleta;

class AtletasController extends Controller
{
    public function vitrine()
    {
        // Buscamos todos os atletas ativos — filtragem por nome, posição e clube é feita
        // no client-side via JavaScript para suportar busca em tempo real sem reload de página.
        // 'cartoes' carregado para exibir total de cartões no modal detalhado do atleta.
        $atletas = Atleta::with(['times.categoria', 'categorias', 'cartoes'])
            ->where('status_atleta', 'ATIVO')
            ->orderBy('nome_atleta')
            ->get();

        // atletas.cartoes.jogo necessário para filtrar cartões por time no modal de elenco.
        $times = \App\Models\Time::with(['atletas.cartoes.jogo', 'categoria'])->orderBy('nome_time')->get();

        // Lista de posições fixa ou vinda de outra model
        $posicoes = ['GOLEIRO', 'ZAGUEIRO', 'LATERAL', 'VOLANTE', 'MEIA', 'CENTROAVANTE'];

        return view('site.jogadores.index', compact('atletas', 'times', 'posicoes'));
    }
}
