<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Jogo;
use App\Models\Time;
use App\Models\Galeria;
use App\Models\Banner;
use App\Models\Noticia;

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

        // NOVA LINHA: Busca todos os times do banco usando Eloquent
        // Busca os times cadastrados já trazendo os atletas vinculados de forma performática
        $times = Time::with('atletas')->get();

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

        // ALTERAÇÃO: Adicionado 'times' ao compact para enviar a variável à view
        return view('site.home.home', compact('jogos', 'classificacao', 'galerias', 'banners', 'noticias', 'times'));
    }
}
