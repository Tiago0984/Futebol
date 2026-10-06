<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Jogo;
use App\Models\Time;
use App\Models\Galeria;
use App\Models\Banner;
use App\Models\Noticia;
use App\Models\Campeonato;
use App\Models\Video;

class HomeController extends Controller
{
    public function home()
    {
        $banners = Banner::where('status_banner', 'ATIVO')
            ->orderBy('ordem_banner')
            ->get();

        $galerias = Galeria::where('status_galeria', 'ATIVO')
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $noticias = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->take(3)
            ->get();

        $times = Time::with(['atletas.cartoes.jogo', 'categoria'])->get();

        // Próximo jogo em destaque (LIGA PREMIERE): mesma regra da agenda do site (só jogo de campeonato;
        // amistoso não), ativo, de hoje em diante, como o "Próximo Evento" do calendário. Sem nenhum, mostra
        // o último jogo de campeonato visível; sem nenhum jogo de campeonato, a seção mostra o rótulo padrão.
        $comDados = ['evento', 'timeCasa', 'timeVisitante', 'campeonato'];

        $proximoJogo = Jogo::with($comDados)
            ->daAgendaPublica()
            ->ordenadosPelaData()
            ->where('ev_ordem.status_evento_calendario', 'ATIVO')
            ->where('ev_ordem.data_evento_calendario', '>=', now()->toDateString())
            ->first()
            ?? Jogo::with($comDados)->daAgendaPublica()->visiveis()->ordenadosPelaData('desc')->first();

        // Campeonatos com os jogos visíveis (oculto some; cancelado aparece com o selo)
        $campeonatos = Campeonato::with([
                'jogos' => fn ($q) => $q->visiveis()->ordenadosPelaData('desc'),
                'jogos.evento', 'jogos.timeCasa', 'jogos.timeVisitante',
            ])
            ->orderBy('data_inicio_campeonato', 'desc')
            ->get();

        // Classificação por campeonato: só jogos com placar e não cancelados
        $classificacaoPorCampeonato = $campeonatos->mapWithKeys(fn ($camp) => [
            $camp->id_campeonato => Jogo::classificacao($camp->jogos),
        ])->all();

        $videoDestaque = Video::where('status_video', 'ATIVO')->orderByDesc('id_video')->first();

        return view('site.home.home', compact('galerias', 'banners', 'noticias', 'times', 'proximoJogo', 'campeonatos', 'classificacaoPorCampeonato', 'videoDestaque'));
    }
}
