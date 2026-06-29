<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Jogo;
use App\Models\Time;
use Illuminate\Http\Request;

class JogosController extends Controller
{
    public function index()
    {
        $jogos       = Jogo::with(['timeCasa', 'timeVisitante', 'campeonato'])->orderBy('data_jogo', 'desc')->get();
        $campeonatos = Campeonato::orderBy('nome_campeonato')->get();
        $times       = Time::orderBy('nome_time')->get();

        return view('admin.jogos.index', compact('jogos', 'campeonatos', 'times'));
    }

    public function create()
    {
        $campeonatos = Campeonato::orderBy('nome_campeonato')->get();
        $times       = Time::orderBy('nome_time')->get();

        return view('admin.jogos.create', compact('campeonatos', 'times'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_campeonato'               => 'required|integer|exists:tbl_campeonato,id_campeonato',
            'id_time_casa'                => 'required|integer|exists:tbl_time,id_time',
            'id_time_visitante'           => 'required|integer|exists:tbl_time,id_time|different:id_time_casa',
            'data_jogo'                   => 'required|date',
            'placar_time_casa_jogos'      => 'nullable|integer|min:0',
            'placar_time_visitante_jogos' => 'nullable|integer|min:0',
        ]);

        Jogo::create([
            ...$request->only(['id_campeonato', 'id_time_casa', 'id_time_visitante',
                'data_jogo', 'placar_time_casa_jogos', 'placar_time_visitante_jogos']),
            'status_jogo' => 'ATIVO',
        ]);

        return redirect()->route('admin.jogos.index')->with('sucesso', 'Jogo registrado com sucesso.');
    }

    public function edit($id)
    {
        $jogo        = Jogo::findOrFail($id);
        $campeonatos = Campeonato::orderBy('nome_campeonato')->get();
        $times       = Time::orderBy('nome_time')->get();

        return view('admin.jogos.edit', compact('jogo', 'campeonatos', 'times'));
    }

    public function update(Request $request, $id)
    {
        $jogo = Jogo::findOrFail($id);

        $request->validate([
            'id_campeonato'               => 'required|integer|exists:tbl_campeonato,id_campeonato',
            'id_time_casa'                => 'required|integer|exists:tbl_time,id_time',
            'id_time_visitante'           => 'required|integer|exists:tbl_time,id_time|different:id_time_casa',
            'data_jogo'                   => 'required|date',
            'placar_time_casa_jogos'      => 'nullable|integer|min:0',
            'placar_time_visitante_jogos' => 'nullable|integer|min:0',
        ]);

        $jogo->update($request->only([
            'id_campeonato', 'id_time_casa', 'id_time_visitante',
            'data_jogo', 'placar_time_casa_jogos', 'placar_time_visitante_jogos',
        ]));

        return redirect()->route('admin.jogos.index')->with('sucesso', 'Jogo atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $jogo = Jogo::findOrFail($id);
        $novo = strtoupper($jogo->status_jogo) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $jogo->update(['status_jogo' => $novo]);

        return back()->with('sucesso', "Jogo {$novo} com sucesso.");
    }

    public function destroy($id)
    {
        $jogo = Jogo::findOrFail($id);
        $jogo->delete();

        return redirect()->route('admin.jogos.index')->with('sucesso', 'Jogo removido com sucesso.');
    }
}
