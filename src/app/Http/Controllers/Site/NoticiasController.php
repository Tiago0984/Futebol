<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Noticia;

class NoticiasController extends Controller
{
    public function index()
    {
        $noticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->paginate(6);
        $recentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->limit(3)->get();
        return view('site.noticias.noticias', compact('noticias', 'recentes'));
    }

    public function show($id)
    {
        $noticia = Noticia::findOrFail($id);
        $recentes = Noticia::orderBy('data_publicacao_noticia', 'desc')->limit(3)->get();
        return view('site.noticias.detail', compact('noticia', 'recentes'));
    }
}
