<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use Illuminate\Http\Request;

class NoticiasController extends Controller
{
    public function index()
    {
        // 1. Buscar APENAS as notícias ativas para a listagem principal
        $listaNoticias = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->get();

        // 2. Pegar as 3 últimas notícias RIGOROSAMENTE ATIVAS para o "Posts Recentes"
        $noticiasRecentes = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->limit(3)
            ->get();

        // 3. Montar os filtros laterais considerando APENAS o que está ATIVO
        $todasAsNoticias = Noticia::where('status_noticia', 'ATIVO')->get();
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
        // Garante que ninguém acesse uma notícia inativa digitando a URL direta
        $noticia = Noticia::where('status_noticia', 'ATIVO')->findOrFail($id);

        // Alimenta a barra lateral da página interna apenas com notícias ATIVAS
        $noticiasRecentes = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->take(3)
            ->get();

        $todasAsNoticias = Noticia::where('status_noticia', 'ATIVO')->get();
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
        // Busca filtrando pela categoria e garantindo o status ATIVO
        $listaNoticias = Noticia::where('categoria_noticia', $categoria)
            ->where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->get();

        // Garante que os posts recentes continuem limpos mesmo na página de filtro de categoria
        $noticiasRecentes = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->limit(3)
            ->get();

        $todasAsNoticias = Noticia::where('status_noticia', 'ATIVO')->get();
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
