<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Noticia; // O Model cuidando de toda a camada de dados (M)
use Illuminate\Http\Request;

class NoticiasController extends Controller
{
    public function noticias()
    {
        // 1. M - Buscar todas as notícias ativas ordenadas por data
        $listaNoticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->get();

        // 2. M - Pegar as 3 últimas notícias para o "Posts Recentes"
        $noticiasRecentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->limit(3)->get();

        // 3. M - Montar a lista de filtros de categoria e calcular totais (Padrão Eloquent)
        $todasAsNoticias = Noticia::all();
        $totalTodasNoticias = $todasAsNoticias->count(); // Criada a variável que faltava!

        $filtroCategoria = $todasAsNoticias
            ->groupBy('categoria_noticia') 
            ->map(function ($itens, $chave) { // Para cada grupo, retorna um objeto com a categoria e o total
                return (object) [
                    'categoria_noticia' => $chave, // O nome da categoria
                    'total' => $itens->count() // O total de notícias naquela categoria
                ];
            })->sortByDesc('total');  // Ordena as categorias pelo total de notícias, do maior para o menor

        $categoriaAtiva = 'all';

        // 4. V - Retorna a View passando as variáveis (Incluindo a totalTodasNoticias)
        return view('site.noticias.noticias', compact(
            'listaNoticias',
            'noticiasRecentes',
            'filtroCategoria',
            'categoriaAtiva',
            'totalTodasNoticias'
        ));
    }

    // MÉTODO NOVO DA PÁGINA INTERNA (show-noticia) 
    public function show($id)
    {
        // 1. Busca a notícia exata clicada pelo usuário
        $noticia = Noticia::findOrFail($id);

        // 2. Alimenta a barra lateral
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

        // 3. Retorna a View da página interna
        return view('site.noticias.show-noticia', compact(
            'noticia',
            'noticiasRecentes',
            'filtroCategoria',
            'totalTodasNoticias'
        ));
    }

    public function filtrarPorCategoria($categoria)
    {
        // M - Busca filtrando pela string da categoria
        $listaNoticias = Noticia::where('categoria_noticia', $categoria)
            ->orderBy('data_publicacao_noticia', 'desc')
            ->get();

        $noticiasRecentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->limit(3)->get();

        // Alinhando os contadores também na rota de filtro
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
