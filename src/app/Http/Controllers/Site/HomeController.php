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

class HomeController extends Controller
{
    public function home()
    {
        $banners = Banner::where('status_banner', 'ATIVO')
            ->orderBy('ordem_banner')
            ->get();

        $jogos = Jogo::with(['timeCasa', 'timeVisitante'])
            ->get();

        $galerias = Galeria::where('status_galeria', 'ATIVO')
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $noticias = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->take(3)
            ->get();

        $times = Time::with(['atletas.cartoes.jogo'])->get();

        // Próximo jogo em destaque para LIGA PREMIERE
        $proximoJogo = Jogo::with(['timeCasa', 'timeVisitante', 'campeonato']) // busca o próximo jogo com os times e campeonato relacionados
            ->whereNotNull('data_jogo')
            ->where('data_jogo', '>=', now()->subDay())
            ->orderBy('data_jogo')
            ->first();

        if (!$proximoJogo) { // busca o próximo jogo mesmo que a data já tenha passado, para evitar que fique vazio
            $proximoJogo = Jogo::with(['timeCasa', 'timeVisitante', 'campeonato'])
                ->whereNotNull('data_jogo')
                ->orderBy('data_jogo', 'desc')
                ->first();
        }

        // Calcular classificação
        $classificacao = [];

        foreach ($jogos as $jogo) {
            $idCasa = $jogo->id_time_casa;
            $idVisitante = $jogo->id_time_visitante;
            $placCasa = $jogo->placar_time_casa_jogos;
            $placVisitante = $jogo->placar_time_visitante_jogos;

            // Inicializa os times se ainda não existem
            foreach ([$idCasa => $jogo->timeCasa, $idVisitante => $jogo->timeVisitante] as $id => $time) {
                if (!isset($classificacao[$id])) {
                    $classificacao[$id] = [
                        'nome' => $time->nome_time ?? '-',
                        'v' => 0,
                        'd' => 0,
                        'e' => 0,
                        'pontos' => 0
                    ];
                }
            }

            // Vitória casa
            if ($placCasa > $placVisitante) {
                $classificacao[$idCasa]['v']++;
                $classificacao[$idCasa]['pontos'] += 3;
                $classificacao[$idVisitante]['d']++;
            }
            // Vitória visitante
            elseif ($placVisitante > $placCasa) {
                $classificacao[$idVisitante]['v']++;
                $classificacao[$idVisitante]['pontos'] += 3;
                $classificacao[$idCasa]['d']++;
            }
            // Empate
            else {
                $classificacao[$idCasa]['e']++;
                $classificacao[$idCasa]['pontos']++;
                $classificacao[$idVisitante]['e']++;
                $classificacao[$idVisitante]['pontos']++;
            }
        }

        // Ordena por pontos
        usort($classificacao, fn($a, $b) => $b['pontos'] - $a['pontos']);

        // Campeonatos com jogos para a seção dinâmica da home
        $campeonatos = Campeonato::with(['jogos.timeCasa', 'jogos.timeVisitante'])
            ->orderBy('data_inicio_campeonato', 'desc')
            ->get();

        // Classificação por campeonato
        $classificacaoPorCampeonato = [];
        foreach ($campeonatos as $camp) {
            $classif = [];
            foreach ($camp->jogos as $jogo) {
                $idCasa = $jogo->id_time_casa;
                $idVis  = $jogo->id_time_visitante;
                $pCasa  = $jogo->placar_time_casa_jogos;
                $pVis   = $jogo->placar_time_visitante_jogos;

                foreach ([$idCasa => $jogo->timeCasa, $idVis => $jogo->timeVisitante] as $id => $time) {
                    if ($time && !isset($classif[$id])) {
                        $classif[$id] = ['nome' => $time->nome_time ?? '-', 'v' => 0, 'e' => 0, 'd' => 0, 'pontos' => 0];
                    }
                }

                if ($pCasa !== null && $pVis !== null) {
                    if ($pCasa > $pVis) {
                        if (isset($classif[$idCasa])) { $classif[$idCasa]['v']++; $classif[$idCasa]['pontos'] += 3; }
                        if (isset($classif[$idVis]))  { $classif[$idVis]['d']++; }
                    } elseif ($pVis > $pCasa) {
                        if (isset($classif[$idVis]))  { $classif[$idVis]['v']++; $classif[$idVis]['pontos'] += 3; }
                        if (isset($classif[$idCasa])) { $classif[$idCasa]['d']++; }
                    } else {
                        if (isset($classif[$idCasa])) { $classif[$idCasa]['e']++; $classif[$idCasa]['pontos']++; }
                        if (isset($classif[$idVis]))  { $classif[$idVis]['e']++;  $classif[$idVis]['pontos']++; }
                    }
                }
            }
            usort($classif, fn($a, $b) => $b['pontos'] - $a['pontos']);
            $classificacaoPorCampeonato[$camp->id_campeonato] = $classif;
        }

        return view('site.home.home', compact('jogos', 'classificacao', 'galerias', 'banners', 'noticias', 'times', 'proximoJogo', 'campeonatos', 'classificacaoPorCampeonato'));
    }
}
