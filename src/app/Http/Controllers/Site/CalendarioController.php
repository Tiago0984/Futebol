<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;

class CalendarioController extends Controller
{
    public function calendario()
    {
        $eventos = EventoCalendario::where('status_evento_calendario', 'ATIVO')
            ->orderBy('data_evento_calendario')
            ->get();

        $proximoEvento = EventoCalendario::where('status_evento_calendario', 'ATIVO')
            ->where('data_evento_calendario', '>=', now()->toDateString()) // Filtra eventos futuros ou do dia atual
            ->orderBy('data_evento_calendario')
            ->first();

        $gradeTreinos = GradeTreino::where('status_grade_treino', 'ATIVO')
            ->orderByRaw("FIELD(dia_semana_grade_treino, 'segunda_quarta', 'terca_quinta', 'sexta', 'sabado')")
            ->orderBy('ordem_grade_treino')
            ->get()
            ->groupBy('dia_semana_grade_treino');

        return view('site.calendario.calendario', compact('eventos', 'proximoEvento', 'gradeTreinos'));
    }
}
