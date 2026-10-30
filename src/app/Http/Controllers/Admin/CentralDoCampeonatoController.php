<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Jogo;
use App\Services\SituacaoDoCampeonato;

/**
 * Central do campeonato (Fase 3 do padrão das telas, 30/10/2026): as abas novas. As outras duas já existiam e
 * continuam nos controllers delas: Jogos (TimesDoCampeonatoController) e Grupos e classificação (GruposController).
 * - Visão geral: o que falta em cada categoria (SituacaoDoCampeonato), as pendências e os próximos jogos;
 * - Participantes e convocação: os times de cada categoria, com o grupo, os jogos e os convocados;
 * - Mata-mata e campeão: o campeão de cada categoria, o encerramento (pontos corridos) e os jogos do mata-mata.
 */
class CentralDoCampeonatoController extends Controller
{
    public function visaoGeral($id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $situacao   = new SituacaoDoCampeonato($campeonato);
        $categorias = $situacao->porCategoria();

        return view('admin.campeonatos.central.visao', [
            'campeonato'    => $campeonato,
            'categorias'    => $categorias,
            'pendencias'    => SituacaoDoCampeonato::pendencias($categorias),
            'proximosJogos' => $situacao->proximosJogos(),
        ]);
    }

    public function participantes($id)
    {
        $campeonato    = Campeonato::with('categorias')->findOrFail($id);
        $participacoes = $campeonato->participacoes()->groupBy(fn ($p) => (int) $p->categoria->id_categoria);
        $grupoDe       = $campeonato->participantesComGrupo()
            ->mapWithKeys(fn ($p) => ["{$p->id_time}|{$p->id_categoria}" => $p->id_campeonato_grupo]);
        $nomesDosGrupos = $campeonato->grupos()->pluck('nome_campeonato_grupo', 'id_campeonato_grupo');

        // Jogos (não ocultos) de cada time em cada categoria; jogo antigo sem categoria fica em "id_time|0" e vale
        // para todas as categorias do time
        $jogosPorTime = [];
        $jogos = $campeonato->jogos()->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', '<>', 'INATIVO'))
            ->with('evento:id_evento_calendario,id_categoria')->get(['id_jogo', 'id_evento', 'id_time_casa', 'id_time_visitante']);
        foreach ($jogos as $jogo) {
            $idCategoria = (int) $jogo->evento->id_categoria;
            foreach ([$jogo->id_time_casa, $jogo->id_time_visitante] as $idTime) {
                $jogosPorTime["{$idTime}|{$idCategoria}"] = ($jogosPorTime["{$idTime}|{$idCategoria}"] ?? 0) + 1;
            }
        }

        return view('admin.campeonatos.central.participantes',
            compact('campeonato', 'participacoes', 'grupoDe', 'nomesDosGrupos', 'jogosPorTime'));
    }

    public function mataMata($id)
    {
        $campeonato     = Campeonato::findOrFail($id);
        $classificacoes = collect($campeonato->classificacoes())->keyBy(fn ($c) => (int) $c['categoria']->id_categoria);
        $campeoes       = $campeonato->campeoes($classificacoes->all());
        $paraEncerrar   = $classificacoes->map(fn ($c) => $campeonato->erroParaEncerrar($c));   // null = pode encerrar

        // Jogos do mata-mata (não ocultos) de cada categoria, na ordem das fases (Jogo::FASES) e da data
        $ordemDasFases = array_flip(array_keys(Jogo::FASES));
        $mataMata = $campeonato->jogos()
            ->whereNotNull('fase_jogo')->where('fase_jogo', '<>', Jogo::FASE_PADRAO)
            ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', '<>', 'INATIVO'))
            ->with(['evento', 'timeCasa', 'timeVisitante', 'jogoIda', 'jogoVolta'])
            ->get()
            ->sortBy(fn ($j) => [$ordemDasFases[$j->fase_jogo] ?? 99, $j->evento->dataHoraDoJogo()])
            ->groupBy(fn ($j) => (int) $j->evento->id_categoria);

        return view('admin.campeonatos.central.mata-mata',
            compact('campeonato', 'classificacoes', 'campeoes', 'paraEncerrar', 'mataMata'));
    }
}
