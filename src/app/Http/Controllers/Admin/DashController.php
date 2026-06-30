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
use App\Models\Video;

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
                'atletas_ativos'       => Atleta::where('status_atleta', 'ATIVO')->count(),
                'matriculas_pendentes' => Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(),
                'videos'               => Video::count(),
            ];

            $ultimasMatriculas = Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])
                ->orderByDesc('id_atleta')
                ->limit(5)
                ->get();

            $ultimosJogos = Jogo::with(['timeCasa', 'timeVisitante', 'campeonato'])
                ->orderByDesc('id_jogo')
                ->limit(5)
                ->get();

        } catch (\Exception $e) {
            $stats = [
                'noticias'             => 0,
                'banners'              => 0,
                'campeonatos'          => 0,
                'times'                => 0,
                'galerias'             => 0,
                'jogos'                => 0,
                'atletas_ativos'       => 0,
                'matriculas_pendentes' => 0,
                'videos'               => 0,
            ];
            $ultimasMatriculas = collect();
            $ultimosJogos      = collect();
        }

        return view('admin.dash.dashboard', compact('stats', 'ultimasMatriculas', 'ultimosJogos'));
    }
}