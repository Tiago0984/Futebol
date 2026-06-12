<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Atleta;

class AtletasController extends Controller
{
    public function vitrine(Request $request)
    {
        // Buscamos atletas com as relações necessárias
        $atletas = Atleta::with(['times', 'categorias'])
            ->when($request->nome, function ($q) use ($request) {
                return $q->where('nome_atleta', 'like', '%' . $request->nome . '%');
            })
            ->when($request->time, function ($q) use ($request) { // Se o usuário não selecionou nenhum time no filtro (o valor está vazio), o Laravel simplesmente pula todo esse bloco e segue em frente. Se o usuário selecionou um time (o valor existe), ele executa a função anônima interna.
                
                return $q->whereHas('times', function ($sq) use ($request) { // Filtra por time usando a relação / sq = subquery td o que escrever dentro dessa função vai rodar olhando diretamente para a tabela de times 
                    $sq->where('tbl_times.id_time', $request->time);
                });
            })
            ->when($request->posicao, function ($q) use ($request) {
                return $q->where('posicao_atleta', $request->posicao);
            })
            ->where('status_atleta', 'ATIVO')
            ->orderBy('nome_atleta')
            ->get();

        // Dados para os selects dos filtros
        $times = \App\Models\Time::orderBy('nome_time')->get();

        // Lista de posições fixa ou vinda de outra model
        $posicoes = ['Goleiro', 'Zagueiro', 'Lateral', 'Volante', 'Meia', 'Atacante'];

        return view('site.jogadores.index', compact('atletas', 'times', 'posicoes'));
    }
}
