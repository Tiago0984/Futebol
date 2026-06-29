<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    public function index()
    {
        $eventos = EventoCalendario::orderBy('data_evento_calendario', 'desc')->get();
        $grades  = GradeTreino::orderBy('ordem_grade_treino')->orderBy('dia_semana_grade_treino')->get();

        return view('admin.calendario.index', compact('eventos', 'grades'));
    }

    // ── Eventos ─────────────────────────────────────────────────────────────

    public function storeEvento(Request $request)
    {
        $request->validate([
            'titulo_evento_calendario'          => 'required|string|max:255',
            'tipo_evento_calendario'            => 'required|string|max:50',
            'data_evento_calendario'            => 'required|date',
            'horario_inicio_evento_calendario'  => 'nullable|date_format:H:i',
            'horario_fim_evento_calendario'     => 'nullable|date_format:H:i',
            'local_evento_calendario'           => 'nullable|string|max:255',
            'subtipo_evento_calendario'         => 'nullable|string|max:50',
            'descricao_evento_calendario'       => 'nullable|string',
        ]);

        EventoCalendario::create([
            ...$request->only([
                'titulo_evento_calendario', 'tipo_evento_calendario',
                'data_evento_calendario', 'horario_inicio_evento_calendario',
                'horario_fim_evento_calendario', 'local_evento_calendario',
                'subtipo_evento_calendario', 'descricao_evento_calendario',
            ]),
            'status_evento_calendario' => 'ATIVO',
        ]);

        return redirect()->route('admin.calendario.index')->with('sucesso', 'Evento adicionado ao calendário.');
    }

    public function updateEvento(Request $request, $id)
    {
        $evento = EventoCalendario::findOrFail($id);

        $request->validate([
            'titulo_evento_calendario'          => 'required|string|max:255',
            'tipo_evento_calendario'            => 'required|string|max:50',
            'data_evento_calendario'            => 'required|date',
            'horario_inicio_evento_calendario'  => 'nullable|date_format:H:i',
            'horario_fim_evento_calendario'     => 'nullable|date_format:H:i',
            'local_evento_calendario'           => 'nullable|string|max:255',
            'subtipo_evento_calendario'         => 'nullable|string|max:50',
            'descricao_evento_calendario'       => 'nullable|string',
        ]);

        $evento->update($request->only([
            'titulo_evento_calendario', 'tipo_evento_calendario',
            'data_evento_calendario', 'horario_inicio_evento_calendario',
            'horario_fim_evento_calendario', 'local_evento_calendario',
            'subtipo_evento_calendario', 'descricao_evento_calendario',
        ]));

        return redirect()->route('admin.calendario.index')->with('sucesso', 'Evento atualizado.');
    }

    public function toggleStatusEvento($id)
    {
        $evento = EventoCalendario::findOrFail($id);
        $novo = strtoupper($evento->status_evento_calendario) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $evento->update(['status_evento_calendario' => $novo]);

        return back()->with('sucesso', "Evento {$novo} com sucesso.");
    }

    public function destroyEvento($id)
    {
        EventoCalendario::findOrFail($id)->delete();

        return redirect()->route('admin.calendario.index')->with('sucesso', 'Evento removido.');
    }

    // ── Grade de Treinos ────────────────────────────────────────────────────

    public function storeGrade(Request $request)
    {
        $request->validate([
            'dia_semana_grade_treino'        => 'required|string|max:20',
            'categoria_grade_treino'         => 'nullable|string|max:50',
            'tipo_grade_treino'              => 'nullable|string|max:30',
            'horario_inicio_grade_treino'    => 'required|date_format:H:i',
            'horario_fim_grade_treino'       => 'required|date_format:H:i',
            'horario_obs_grade_treino'       => 'nullable|string|max:100',
            'local_grade_treino'             => 'nullable|string|max:255',
            'ordem_grade_treino'             => 'nullable|integer|min:0',
        ]);

        GradeTreino::create([
            ...$request->only([
                'dia_semana_grade_treino', 'categoria_grade_treino',
                'tipo_grade_treino', 'horario_inicio_grade_treino',
                'horario_fim_grade_treino', 'horario_obs_grade_treino',
                'local_grade_treino',
            ]),
            'ordem_grade_treino'  => $request->input('ordem_grade_treino', 0),
            'status_grade_treino' => 'ATIVO',
        ]);

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário adicionado à grade.');
    }

    public function updateGrade(Request $request, $id)
    {
        $grade = GradeTreino::findOrFail($id);

        $request->validate([
            'dia_semana_grade_treino'        => 'required|string|max:20',
            'categoria_grade_treino'         => 'nullable|string|max:50',
            'tipo_grade_treino'              => 'nullable|string|max:30',
            'horario_inicio_grade_treino'    => 'required|date_format:H:i',
            'horario_fim_grade_treino'       => 'required|date_format:H:i',
            'horario_obs_grade_treino'       => 'nullable|string|max:100',
            'local_grade_treino'             => 'nullable|string|max:255',
            'ordem_grade_treino'             => 'nullable|integer|min:0',
        ]);

        $grade->update([
            ...$request->only([
                'dia_semana_grade_treino', 'categoria_grade_treino',
                'tipo_grade_treino', 'horario_inicio_grade_treino',
                'horario_fim_grade_treino', 'horario_obs_grade_treino',
                'local_grade_treino',
            ]),
            'ordem_grade_treino' => $request->input('ordem_grade_treino', 0),
        ]);

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário atualizado.');
    }

    public function toggleStatusGrade($id)
    {
        $grade = GradeTreino::findOrFail($id);
        $novo = strtoupper($grade->status_grade_treino) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $grade->update(['status_grade_treino' => $novo]);

        return back()->with('sucesso', "Horário {$novo} com sucesso.");
    }

    public function destroyGrade($id)
    {
        GradeTreino::findOrFail($id)->delete();

        return redirect()->route('admin.calendario.index', ['tab' => 'grade'])->with('sucesso', 'Horário removido.');
    }
}
