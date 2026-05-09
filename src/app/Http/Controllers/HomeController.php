<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Jogo;
use App\Models\Time;

class HomeController extends Controller
{
    public function home()
    {
        $jogos = Jogo::with(['timeCasa', 'timeVisitante'])->get();

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

        return view('site.home.home', compact('jogos', 'classificacao'));
    }
}
