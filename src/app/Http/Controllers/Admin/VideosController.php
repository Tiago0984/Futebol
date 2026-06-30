<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;

class VideosController extends Controller
{
    public function index()
    {
        $videos = Video::orderByDesc('id_video')->get();
        return view('admin.videos.index', compact('videos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo_video' => 'required|string|max:255',
            'url_video'    => 'required|url|max:500',
            'descricao_video' => 'nullable|string',
        ]);

        Video::create([
            'titulo_video'    => $request->titulo_video,
            'url_video'       => $request->url_video,
            'descricao_video' => $request->descricao_video,
            'status_video'    => 'ATIVO',
            'ao_vivo_video'   => $request->boolean('ao_vivo_video') ? 1 : 0,
        ]);

        return redirect()->route('admin.videos.index')
            ->with('sucesso', 'Vídeo adicionado com sucesso.');
    }

    public function update(Request $request, $id)
    {
        $video = Video::findOrFail($id);

        $request->validate([
            'titulo_video'    => 'required|string|max:255',
            'url_video'       => 'required|url|max:500',
            'descricao_video' => 'nullable|string',
        ]);

        $video->update([
            'titulo_video'    => $request->titulo_video,
            'url_video'       => $request->url_video,
            'descricao_video' => $request->descricao_video,
            'ao_vivo_video'   => $request->boolean('ao_vivo_video') ? 1 : 0,
        ]);

        return redirect()->route('admin.videos.index')
            ->with('sucesso', 'Vídeo atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $video = Video::findOrFail($id);
        $video->update([
            'status_video' => $video->status_video === 'ATIVO' ? 'INATIVO' : 'ATIVO',
        ]);

        return back()->with('sucesso', 'Status do vídeo atualizado.');
    }

    public function destroy($id)
    {
        Video::findOrFail($id)->delete();
        return redirect()->route('admin.videos.index')
            ->with('sucesso', 'Vídeo removido com sucesso.');
    }
}