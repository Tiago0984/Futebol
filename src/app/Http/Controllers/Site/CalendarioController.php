<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;

class CalendarioController extends Controller
{
    public function calendario()
    {
        // jogo.campeonato: para a etiqueta (nome do campeonato ou "Amistoso") sem uma consulta por evento
        // Treinos gerados pela grade ficam fora do site (a grade já aparece na tabela da página)
        $eventos = EventoCalendario::with('jogo.campeonato')
            ->whereIn('status_evento_calendario', EventoCalendario::STATUS_VISIVEIS)
            ->whereIn('tipo_evento_calendario', EventoCalendario::TIPOS_PUBLICOS)
            ->foraDaGrade()
            ->orderBy('data_evento_calendario')
            ->get();

        // Destaque com contagem regressiva: só evento ativo (cancelado aparece na lista, com o selo)
        $proximoEvento = EventoCalendario::with('jogo.campeonato')
            ->where('status_evento_calendario', 'ATIVO')
            ->whereIn('tipo_evento_calendario', EventoCalendario::TIPOS_PUBLICOS)
            ->foraDaGrade()
            ->where('data_evento_calendario', '>=', now()->toDateString()) // Filtra eventos futuros ou do dia atual
            ->orderBy('data_evento_calendario')
            ->first();

        $gradeTreinos = GradeTreino::with('categoria')
            ->where('status_grade_treino', 'ATIVO')
            ->ordenada()
            ->get()
            ->groupBy('dia_semana_grade_treino');

        return view('site.calendario.calendario', compact('eventos', 'proximoEvento', 'gradeTreinos'));
    }
}
