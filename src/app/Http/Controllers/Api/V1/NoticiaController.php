<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use Illuminate\Http\Request;

class NoticiaController extends Controller
{
    // GET /api/v1/noticias - notícias ativas, mais recentes primeiro
    // Filtro opcional: ?categoria=Campeonato
    public function index(Request $request)
    {
        $noticias = Noticia::where('status_noticia', 'ATIVO')
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $query->where('categoria_noticia', $request->categoria);
            })
            ->orderByDesc('data_publicacao_noticia')
            ->get([
                'id_noticia',
                'titulo_noticia',
                'foto_noticia',
                'categoria_noticia',
                'data_publicacao_noticia',
                'autor_noticia',
            ]);

        return response()->json([
            'success' => true,
            'data'    => $noticias,
        ]);
    }

    // GET /api/v1/noticias/{id} - notícia completa
    public function show($id)
    {
        $noticia = Noticia::where('status_noticia', 'ATIVO')->find($id);

        if (!$noticia) {
            return response()->json([
                'success' => false,
                'message' => 'Notícia não encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $noticia,
        ]);
    }
}
