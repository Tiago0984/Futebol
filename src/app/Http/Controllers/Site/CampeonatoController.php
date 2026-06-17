<?php
namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Jogo;

class CampeonatoController extends Controller
{
    public function campeonato()
    {
        $campeonatos = Campeonato::all();
        return view('site.campeonatos.campeonatos', compact('campeonatos'));
    }

    public function show($id)
    {
        $campeonato = Campeonato::with(['times.atletas.cartoes.jogo'])->findOrFail($id);
        $jogos = Jogo::with(['timeCasa', 'timeVisitante'])
                    ->where('id_campeonato', $id)
                    ->get();

        // Calcula classificação
        $classificacao = [];
        foreach ($jogos as $jogo) {
            $idCasa = $jogo->id_time_casa;
            $idVisitante = $jogo->id_time_visitante;
            $placCasa = $jogo->placar_time_casa_jogos;
            $placVisitante = $jogo->placar_time_visitante_jogos;

            foreach ([$idCasa => $jogo->timeCasa, $idVisitante => $jogo->timeVisitante] as $id => $time) {
                if (!isset($classificacao[$id])) {
                    $classificacao[$id] = [
                        'nome' => $time->nome_time ?? '-',
                        'logo' => $time->logo_time ?? null,
                        'pj' => 0, 'v' => 0, 'e' => 0, 'd' => 0,
                        'gm' => 0, 'gc' => 0, 'pontos' => 0
                    ];
                }
            }

            $classificacao[$idCasa]['pj']++;
            $classificacao[$idVisitante]['pj']++;
            $classificacao[$idCasa]['gm'] += $placCasa;
            $classificacao[$idCasa]['gc'] += $placVisitante;
            $classificacao[$idVisitante]['gm'] += $placVisitante;
            $classificacao[$idVisitante]['gc'] += $placCasa;

            if ($placCasa > $placVisitante) {
                $classificacao[$idCasa]['v']++;
                $classificacao[$idCasa]['pontos'] += 3;
                $classificacao[$idVisitante]['d']++;
            } elseif ($placVisitante > $placCasa) {
                $classificacao[$idVisitante]['v']++;
                $classificacao[$idVisitante]['pontos'] += 3;
                $classificacao[$idCasa]['d']++;
            } else {
                $classificacao[$idCasa]['e']++;
                $classificacao[$idCasa]['pontos']++;
                $classificacao[$idVisitante]['e']++;
                $classificacao[$idVisitante]['pontos']++;
            }
        }

        usort($classificacao, fn($a, $b) => $b['pontos'] - $a['pontos']);

        return view('site.campeonatos.show', compact('campeonato', 'classificacao', 'jogos'));
    }
}
