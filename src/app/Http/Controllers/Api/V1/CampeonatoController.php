<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Time;

class CampeonatoController extends Controller
{
    // GET /api/v1/campeonatos - campeonatos ativos, com as categorias (Fase H, Etapa H5; "categoria" continua sendo
    // a de referência, para não quebrar o app)
    public function index()
    {
        $campeonatos = Campeonato::with(['categoria:id_categoria,nome_categoria', 'categorias'])
            ->ativos()
            ->orderByDesc('data_inicio_campeonato')
            ->get([
                'id_campeonato',
                'nome_campeonato',
                'organizador_campeonato',
                'tipo_campeonato',
                'logo_evento',
                'banner_evento',
                'data_inicio_campeonato',
                'data_fim_campeonato',
                'local_evento',
                'id_categoria',
            ]);

        return response()->json([
            'success' => true,
            'data'    => $campeonatos->map(fn ($camp) => [...$camp->toArray(), 'categorias' => $this->categorias($camp)]),
        ]);
    }

    // GET /api/v1/campeonatos/{id} - detalhes, categorias, times, jogos (com fase e categoria) e a classificação
    // por categoria e grupo (chave), só a fase de grupos (Fase H, Etapa H5)
    public function show($id)
    {
        $campeonato = Campeonato::with([
                'categoria:id_categoria,nome_categoria',
                'categorias',
                'times:tbl_time.id_time,nome_time,logo_time',
                // Jogos visíveis, pela data do evento; data_jogo, horario_jogo, local_jogo e status_jogo
                // vêm do evento (Jogo::$appends), com os mesmos nomes de antes para não quebrar o app
                'jogos' => fn ($query) => $query->visiveis()->ordenadosPelaData(),
                'jogos.evento', 'jogos.jogoIda', 'jogos.jogoVolta',   // para o "confronto" sem uma consulta por jogo
                'jogos.timeCasa:id_time,nome_time,logo_time',
                'jogos.timeVisitante:id_time,nome_time,logo_time',
            ])
            ->ativos()
            ->find($id);

        if (!$campeonato) {
            return response()->json([
                'success' => false,
                'message' => 'Campeonato não encontrado.',
            ], 404);
        }

        // A tabela de ligação (pivot) é interna: fica fora do JSON
        $campeonato->times->each->makeHidden('pivot');

        $classificacoes = $campeonato->classificacoes();
        $campeoes       = $campeonato->campeoes($classificacoes);   // Fase I, Etapa I4
        $time = fn (?array $t) => $t ? [...$t, 'logo_url' => Time::urlDaImagem($t['logo'], 'team')] : null;

        $classificacao = collect($classificacoes)->map(fn ($c) => [
            'id_categoria'  => (int) $c['categoria']->id_categoria,
            'categoria'     => $c['categoria']->rotulo,
            'campeao'       => ($campeao = $campeoes[(int) $c['categoria']->id_categoria] ?? null) ? [
                'situacao' => $campeao['situacao'], 'modo' => $campeao['modo'], 'como' => $campeao['como'],
                'campeao'  => $time($campeao['campeao']), 'vice' => $time($campeao['vice']), 'terceiro' => $time($campeao['terceiro']),
                'empatados' => $campeao['empatados'],
            ] : null,
            'classificados' => $c['classificados'],
            'grupos'        => array_map(fn ($tabela) => [
                'grupo'         => $tabela['grupo'],
                'classificados' => $tabela['classificados'],
                'tabela'        => array_map(fn ($linha, $pos) => [
                    'posicao' => $pos + 1, 'id_time' => $linha['id_time'], 'time' => $linha['nome'], 'logo' => $linha['logo'],
                    'logo_url' => Time::urlDaImagem($linha['logo'], 'team'),
                    'pontos' => $linha['pontos'], 'jogos' => $linha['pj'], 'vitorias' => $linha['v'],
                    'empates' => $linha['e'], 'derrotas' => $linha['d'], 'gols_marcados' => $linha['gm'],
                    'gols_sofridos' => $linha['gc'], 'saldo' => $linha['gm'] - $linha['gc'],
                    'classificado' => $tabela['classificados'] !== null && $pos < $tabela['classificados'],
                    'empate_sorteio' => $linha['sorteio'],
                ], $tabela['tabela'], array_keys($tabela['tabela'])),
            ], $c['tabelas']),
        ])->all();

        return response()->json([
            'success' => true,
            'data'    => [...$campeonato->toArray(), 'categorias' => $this->categorias($campeonato), 'classificacao' => $classificacao],
        ]);
    }

    // Categorias do campeonato no JSON: [{id_categoria, nome_categoria, sexo_categoria, rotulo}]
    private function categorias(Campeonato $campeonato): array
    {
        return $campeonato->categorias->map(fn ($categoria) => [
            'id_categoria'   => (int) $categoria->id_categoria,
            'nome_categoria' => $categoria->nome_categoria,
            'sexo_categoria' => $categoria->sexo_categoria,
            'rotulo'         => $categoria->rotulo,
        ])->all();
    }
}
