<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;

class CampeonatoController extends Controller
{
    // GET /api/v1/campeonatos - campeonatos ativos
    public function index()
    {
        $campeonatos = Campeonato::with('categoria:id_categoria,nome_categoria')
            ->where('status_campeonato', 'ATIVO')
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
            'data'    => $campeonatos,
        ]);
    }

    // GET /api/v1/campeonatos/{id} - detalhes, times e jogos do campeonato
    public function show($id)
    {
        $campeonato = Campeonato::with([
                'categoria:id_categoria,nome_categoria',
                'times:tbl_time.id_time,nome_time,logo_time',
                // Jogos visíveis, pela data do evento; data_jogo, horario_jogo, local_jogo e status_jogo
                // vêm do evento (Jogo::$appends), com os mesmos nomes de antes para não quebrar o app
                'jogos' => fn ($query) => $query->visiveis()->ordenadosPelaData(),
                'jogos.evento',
                'jogos.timeCasa:id_time,nome_time,logo_time',
                'jogos.timeVisitante:id_time,nome_time,logo_time',
            ])
            ->where('status_campeonato', 'ATIVO')
            ->find($id);

        if (!$campeonato) {
            return response()->json([
                'success' => false,
                'message' => 'Campeonato não encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $campeonato,
        ]);
    }
}
