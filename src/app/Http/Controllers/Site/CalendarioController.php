<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;

class CalendarioController extends Controller
{
    public function calendario()
    {
        // Só eventos CAMPEONATO e jogos de campeonato (escopo daAgendaPublica); treinos, amistosos e os
        // outros tipos ficam fora (a grade de treinos continua na tabela da página).
        // jogo.campeonato: para a etiqueta (nome do campeonato) sem uma consulta por evento
        $eventos = EventoCalendario::with('jogo.campeonato')
            ->whereIn('status_evento_calendario', EventoCalendario::STATUS_VISIVEIS)
            ->daAgendaPublica()
            ->orderBy('data_evento_calendario')
            ->get();

        // Destaque com contagem regressiva: só evento ativo (cancelado aparece na lista, com o selo).
        // Pode não haver nenhum: a página mostra só a lista (ou o aviso de lista vazia) e a grade
        $proximoEvento = EventoCalendario::with('jogo.campeonato')
            ->where('status_evento_calendario', 'ATIVO')
            ->daAgendaPublica()
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
