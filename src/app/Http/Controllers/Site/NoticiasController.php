<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Noticia; // 💡 Importamos a sua model aqui!

class NoticiasController extends Controller
{
    public function noticias()
    {
        // Puxa as notícias da tbl_noticias ordenando pela data mais recente
        $noticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->paginate(3);

        // Retorna a view enviando a lista de notícias dentro da variável $noticias
        return view('site.noticias.noticias', compact('noticias'));
    }
}
