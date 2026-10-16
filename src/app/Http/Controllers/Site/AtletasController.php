<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Categoria;

class AtletasController extends Controller
{
    /**
     * Página Jogadores: os atletas ativos da AACJ, cada um na categoria dele (sem time: desde a Fase G, Etapa 4,
     * o time e a camisa são da convocação de cada campeonato). A filtragem por nome, posição e categoria é feita
     * no navegador (busca em tempo real, sem recarregar); ?categoria=ID chega já filtrado (cartões da home).
     * No modal: as convocações (campeonato, categoria e camisa, sem o nome do time) e os cartões.
     */
    public function vitrine()
    {
        $atletas = Atleta::with([
                'categoriasAtivas',
                'posicoes',
                'cartoes',
                'convocacoes' => fn ($q) => $q->with(['campeonato', 'categoria'])
                    ->join('tbl_campeonato as cp', 'cp.id_campeonato', '=', 'tbl_convocacao.id_campeonato')
                    ->orderByDesc('cp.data_inicio_campeonato')
                    ->select('tbl_convocacao.*'),
            ])
            ->where('status_atleta', 'ATIVO')
            ->orderBy('nome_atleta')
            ->get();

        // Filtro "Categoria": só as que têm atleta na lista
        $idsComAtleta = $atletas->map(fn ($atleta) => $atleta->categoriasAtivas->first()?->id_categoria)->filter()->unique();
        $categorias   = Categoria::ativas()->whereIn('id_categoria', $idsComAtleta)->get();

        // Filtro "Posição": a lista fixa (Fase D); o atleta aparece por qualquer uma das posições dele
        $posicoes = Atleta::POSICOES;

        return view('site.jogadores.index', compact('atletas', 'categorias', 'posicoes'));
    }
}
