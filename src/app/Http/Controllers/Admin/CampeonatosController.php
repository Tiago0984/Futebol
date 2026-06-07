<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Time;
use Illuminate\Http\Request;

class CampeonatosController extends Controller
{
    public function index()
    {
        $campeonatos = Campeonato::with('categoria')->get();

        return view('admin.campeonatos.index', compact('campeonatos'));
    }

    public function create()
    {
        $times = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.create', compact('times'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_campeonato'  => 'required|string|max:255',
            'ano_campeonato'   => 'required|integer|min:2000',
            'id_categoria'     => 'nullable|integer',
            'foto_campeonato'  => 'nullable|image|max:2048',
            'times'            => 'nullable|array',
            'times.*'          => 'integer|exists:tbl_time,id_time',
        ]);

        $dados = $request->only([
            'nome_campeonato',
            'ano_campeonato',
            'id_categoria',
        ]);

        if ($request->hasFile('foto_campeonato')) {
            $dados['foto_campeonato'] = $request->file('foto_campeonato')->store('campeonatos', 'public');
        }

        $campeonato = Campeonato::create($dados);

        if ($request->filled('times')) {
            $campeonato->times()->sync($request->times);
        }

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato criado com sucesso.');
    }

    public function edit($id)
    {
        $campeonato = Campeonato::with('times')->findOrFail($id);
        $times      = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.edit', compact('campeonato', 'times'));
    }

    public function update(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);

        $request->validate([
            'nome_campeonato' => 'required|string|max:255',
            'ano_campeonato'  => 'required|integer|min:2000',
            'id_categoria'    => 'nullable|integer',
            'foto_campeonato' => 'nullable|image|max:2048',
            'times'           => 'nullable|array',
            'times.*'         => 'integer|exists:tbl_time,id_time',
        ]);

        $dados = $request->only([
            'nome_campeonato',
            'ano_campeonato',
            'id_categoria',
        ]);

        if ($request->hasFile('foto_campeonato')) {
            $dados['foto_campeonato'] = $request->file('foto_campeonato')->store('campeonatos', 'public');
        }

        $campeonato->update($dados);
        $campeonato->times()->sync($request->times ?? []);

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato atualizado com sucesso.');
    }

    public function destroy($id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $campeonato->times()->detach();
        $campeonato->delete();

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato removido com sucesso.');
    }
}
