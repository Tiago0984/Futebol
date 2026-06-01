<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannersController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('ordem_banner')->get();

        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo_banner'    => 'required|string|max:255',
            'subtitulo_banner' => 'nullable|string|max:255',
            'ordem_banner'     => 'required|integer|min:1',
            'status_banner'    => 'required|in:ativo,inativo',
            'foto_banner'      => 'required|image|max:2048',
        ]);

        $dados = $request->only([
            'titulo_banner',
            'subtitulo_banner',
            'ordem_banner',
            'status_banner',
        ]);

        $dados['foto_banner'] = $request->file('foto_banner')->store('banners', 'public');

        Banner::create($dados);

        return redirect()->route('admin.banners.index')->with('sucesso', 'Banner criado com sucesso.');
    }

    public function edit($id)
    {
        $banner = Banner::findOrFail($id);

        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'titulo_banner'    => 'required|string|max:255',
            'subtitulo_banner' => 'nullable|string|max:255',
            'ordem_banner'     => 'required|integer|min:1',
            'status_banner'    => 'required|in:ativo,inativo',
            'foto_banner'      => 'nullable|image|max:2048',
        ]);

        $dados = $request->only([
            'titulo_banner',
            'subtitulo_banner',
            'ordem_banner',
            'status_banner',
        ]);

        if ($request->hasFile('foto_banner')) {
            $dados['foto_banner'] = $request->file('foto_banner')->store('banners', 'public');
        }

        $banner->update($dados);

        return redirect()->route('admin.banners.index')->with('sucesso', 'Banner atualizado com sucesso.');
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('sucesso', 'Banner removido com sucesso.');
    }
}
