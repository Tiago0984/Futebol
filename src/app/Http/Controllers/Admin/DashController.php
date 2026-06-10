<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use App\Models\Banner;
use App\Models\Campeonato;
use App\Models\Time;
use App\Models\Galeria;
use App\Models\Jogo;
use App\Models\Atleta;

class DashController extends Controller
{
    public function index()
    {
        try {
            $stats = [
                'noticias'             => Noticia::count(),
                'banners'              => Banner::count(),
                'campeonatos'          => Campeonato::count(),
                'times'                => Time::count(),
                'galerias'             => Galeria::count(),
                'jogos'                => Jogo::count(),
                'matriculas_pendentes' => Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(),
            ];
        } catch (\Exception $e) {
            $stats = [
                'noticias'             => 0,
                'banners'              => 0,
                'campeonatos'          => 0,
                'times'                => 0,
                'galerias'             => 0,
                'jogos'                => 0,
                'matriculas_pendentes' => 0,
            ];
        }

        return view('admin.dash.dashboard', compact('stats'));
    }
}
