<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use Illuminate\Http\Request;

class NoticiasController extends Controller
{
    public function index()
    {
        $listaNoticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->get();
        $noticiasRecentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->limit(3)->get();

        $todasAsNoticias = Noticia::all();
        $totalTodasNoticias = $todasAsNoticias->count();

        $filtroCategoria = $todasAsNoticias
            ->groupBy('categoria_noticia')
            ->map(function ($itens, $chave) {
                return (object) [
                    'categoria_noticia' => $chave,
                    'total' => $itens->count()
                ];
            })->sortByDesc('total');

        $categoriaAtiva = 'all';

        return view('site.noticias.noticias', compact(
            'listaNoticias',
            'noticiasRecentes',
            'filtroCategoria',
            'categoriaAtiva',
            'totalTodasNoticias'
        ));
    }

    public function show($id)
    {
        $noticia = Noticia::findOrFail($id);
        $noticiasRecentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->take(3)->get();

        $todasAsNoticias = Noticia::all();
        $totalTodasNoticias = $todasAsNoticias->count();

        $filtroCategoria = $todasAsNoticias
            ->groupBy('categoria_noticia')
            ->map(function ($itens, $chave) {
                return (object) [
                    'categoria_noticia' => $chave,
                    'total' => $itens->count()
                ];
            })->sortByDesc('total');

        return view('site.noticias.show-noticia', compact(
            'noticia',
            'noticiasRecentes',
            'filtroCategoria',
            'totalTodasNoticias'
        ));
    }

    public function filtrarPorCategoria($categoria)
    {
        $listaNoticias = Noticia::where('categoria_noticia', $categoria)
            ->orderBy('data_publicacao_noticia', 'desc')
            ->get();

        $noticiasRecentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->limit(3)->get();

        $todasAsNoticias = Noticia::all();
        $totalTodasNoticias = $todasAsNoticias->count();

        $filtroCategoria = $todasAsNoticias
            ->groupBy('categoria_noticia')
            ->map(function ($itens, $chave) {
                return (object) [
                    'categoria_noticia' => $chave,
                    'total' => $itens->count()
                ];
            })->sortByDesc('total');

        $categoriaAtiva = $categoria;

        return view('site.noticias.noticias', compact(
            'listaNoticias',
            'noticiasRecentes',
            'filtroCategoria',
            'categoriaAtiva',
            'totalTodasNoticias'
        ));
    }
}
