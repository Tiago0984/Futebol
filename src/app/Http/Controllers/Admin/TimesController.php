<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Time;
use Illuminate\Http\Request;

class TimesController extends Controller
{
    public function index()
    {
        $times      = Time::with('categoria')->orderBy('nome_time')->get();
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.times.index', compact('times', 'categorias'));
    }

    public function create()
    {
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.times.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_time'    => 'required|string|max:255',
            'tipo_time'    => 'required|in:INTERNO,EXTERNO',
            'id_categoria' => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'logo_time'    => 'nullable|image|max:1024',
        ]);

        $dados = $request->only(['nome_time', 'tipo_time', 'id_categoria']);

        if ($request->hasFile('logo_time')) {
            $dados['logo_time'] = $request->file('logo_time')->getClientOriginalName();
            $request->file('logo_time')->move(public_path('futebol/images/team'), $dados['logo_time']);
        }

        $dados['status_time'] = 'ATIVO';
        Time::create($dados);

        return redirect()->route('admin.times.index')->with('sucesso', 'Time criado com sucesso.');
    }

    public function edit($id)
    {
        $time       = Time::findOrFail($id);
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.times.edit', compact('time', 'categorias'));
    }

    public function update(Request $request, $id)
    {
        $time = Time::findOrFail($id);

        $request->validate([
            'nome_time'    => 'required|string|max:255',
            'tipo_time'    => 'required|in:INTERNO,EXTERNO',
            'id_categoria' => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'logo_time'    => 'nullable|image|max:1024',
        ]);

        $dados = $request->only(['nome_time', 'tipo_time', 'id_categoria']);

        if ($request->hasFile('logo_time')) {
            $dados['logo_time'] = $request->file('logo_time')->getClientOriginalName();
            $request->file('logo_time')->move(public_path('futebol/images/team'), $dados['logo_time']);
        }

        $time->update($dados);

        return redirect()->route('admin.times.index')->with('sucesso', 'Time atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $time = Time::findOrFail($id);
        $novo = strtoupper($time->status_time) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $time->update(['status_time' => $novo]);

        return back()->with('sucesso', "Time {$novo} com sucesso.");
    }

    public function destroy($id)
    {
        $time = Time::findOrFail($id);
        $time->delete();

        return redirect()->route('admin.times.index')->with('sucesso', 'Time removido com sucesso.');
    }
}
