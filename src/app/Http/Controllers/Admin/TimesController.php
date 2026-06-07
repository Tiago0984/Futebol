<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Time;
use Illuminate\Http\Request;

class TimesController extends Controller
{
    public function index()
    {
        $times = Time::orderBy('nome_time')->get();

        return view('admin.times.index', compact('times'));
    }

    public function create()
    {
        return view('admin.times.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_time' => 'required|string|max:255',
            'logo_time' => 'nullable|image|max:1024',
        ]);

        $dados = $request->only(['nome_time']);

        if ($request->hasFile('logo_time')) {
            $dados['logo_time'] = $request->file('logo_time')->store('times', 'public');
        }

        Time::create($dados);

        return redirect()->route('admin.times.index')->with('sucesso', 'Time criado com sucesso.');
    }

    public function edit($id)
    {
        $time = Time::findOrFail($id);

        return view('admin.times.edit', compact('time'));
    }

    public function update(Request $request, $id)
    {
        $time = Time::findOrFail($id);

        $request->validate([
            'nome_time' => 'required|string|max:255',
            'logo_time' => 'nullable|image|max:1024',
        ]);

        $dados = $request->only(['nome_time']);

        if ($request->hasFile('logo_time')) {
            $dados['logo_time'] = $request->file('logo_time')->store('times', 'public');
        }

        $time->update($dados);

        return redirect()->route('admin.times.index')->with('sucesso', 'Time atualizado com sucesso.');
    }

    public function destroy($id)
    {
        $time = Time::findOrFail($id);
        $time->delete();

        return redirect()->route('admin.times.index')->with('sucesso', 'Time removido com sucesso.');
    }
}
