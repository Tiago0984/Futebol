<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\Time;
use Illuminate\Http\Request;

class CampeonatosController extends Controller
{
    public function index()
    {
        $campeonatos = Campeonato::with(['categoria', 'times'])->orderBy('data_inicio_campeonato', 'desc')->get();
        $categorias  = Categoria::orderBy('nome_categoria')->get();
        $times       = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.index', compact('campeonatos', 'categorias', 'times'));
    }

    public function create()
    {
        $categorias = Categoria::orderBy('nome_categoria')->get();
        $times      = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.create', compact('categorias', 'times'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_campeonato'        => 'required|string|max:255',
            'tipo_campeonato'        => 'required|string|max:50',
            'data_inicio_campeonato' => 'required|date',
            'data_fim_campeonato'    => 'required|date|after_or_equal:data_inicio_campeonato',
            'local_evento'           => 'nullable|string|max:255',
            'organizador_campeonato' => 'nullable|string|max:255',
            'descricao_campeonato'   => 'nullable|string',
            'id_categoria'           => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'logo_evento'            => 'nullable|image|max:2048',
            'banner_evento'          => 'nullable|image|max:4096',
            'times'                  => 'nullable|array',
            'times.*'                => 'integer|exists:tbl_time,id_time',
        ]);

        $dados = $request->only([
            'nome_campeonato', 'tipo_campeonato', 'data_inicio_campeonato',
            'data_fim_campeonato', 'local_evento', 'organizador_campeonato',
            'descricao_campeonato', 'id_categoria',
        ]);

        if ($request->hasFile('logo_evento')) {
            $dados['logo_evento'] = $request->file('logo_evento')->getClientOriginalName();
            $request->file('logo_evento')->move(public_path('futebol/images/campeonatos'), $dados['logo_evento']);
        }

        if ($request->hasFile('banner_evento')) {
            $dados['banner_evento'] = $request->file('banner_evento')->getClientOriginalName();
            $request->file('banner_evento')->move(public_path('futebol/images/campeonatos'), $dados['banner_evento']);
        }

        $dados['status_campeonato'] = 'ATIVO';
        $campeonato = Campeonato::create($dados);

        if ($request->filled('times')) {
            $campeonato->times()->sync($request->times);
        }

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato criado com sucesso.');
    }

    public function edit($id)
    {
        $campeonato = Campeonato::with('times')->findOrFail($id);
        $categorias = Categoria::orderBy('nome_categoria')->get();
        $times      = Time::orderBy('nome_time')->get();

        return view('admin.campeonatos.edit', compact('campeonato', 'categorias', 'times'));
    }

    public function update(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);

        $request->validate([
            'nome_campeonato'        => 'required|string|max:255',
            'tipo_campeonato'        => 'required|string|max:50',
            'data_inicio_campeonato' => 'required|date',
            'data_fim_campeonato'    => 'required|date|after_or_equal:data_inicio_campeonato',
            'local_evento'           => 'nullable|string|max:255',
            'organizador_campeonato' => 'nullable|string|max:255',
            'descricao_campeonato'   => 'nullable|string',
            'id_categoria'           => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'logo_evento'            => 'nullable|image|max:2048',
            'banner_evento'          => 'nullable|image|max:4096',
            'times'                  => 'nullable|array',
            'times.*'                => 'integer|exists:tbl_time,id_time',
        ]);

        $dados = $request->only([
            'nome_campeonato', 'tipo_campeonato', 'data_inicio_campeonato',
            'data_fim_campeonato', 'local_evento', 'organizador_campeonato',
            'descricao_campeonato', 'id_categoria',
        ]);

        if ($request->hasFile('logo_evento')) {
            $dados['logo_evento'] = $request->file('logo_evento')->getClientOriginalName();
            $request->file('logo_evento')->move(public_path('futebol/images/campeonatos'), $dados['logo_evento']);
        }

        if ($request->hasFile('banner_evento')) {
            $dados['banner_evento'] = $request->file('banner_evento')->getClientOriginalName();
            $request->file('banner_evento')->move(public_path('futebol/images/campeonatos'), $dados['banner_evento']);
        }

        $campeonato->update($dados);
        $campeonato->times()->sync($request->times ?? []);

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $novo = strtoupper($campeonato->status_campeonato) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $campeonato->update(['status_campeonato' => $novo]);

        return back()->with('sucesso', "Campeonato {$novo} com sucesso.");
    }

    public function destroy($id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $campeonato->times()->detach();
        $campeonato->delete();

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato removido com sucesso.');
    }
}
