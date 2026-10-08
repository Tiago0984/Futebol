<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventoCalendario;
use App\Models\Jogo;

/**
 * Times dos amistosos (subitem "Times" de Amistosos no menu), só para ver: os amistosos separados por jogo
 * (cada um com data, horário, local e situação) e, em cada jogo, o cartão do mandante e o do visitante, que
 * abre os jogadores escalados por aquele time naquele jogo (JogosController::escaladosDoTime).
 */
class AmistososController extends Controller
{
    public function times()
    {
        // Amistosos não ocultos: próximos do mais perto ao mais longe, depois os últimos realizados
        [$proximos, $realizados] = Jogo::separadosParaTelaDeTimes(Jogo::whereNull('id_campeonato'));

        $caminho = [
            ['Eventos', route('admin.calendario.index')],
            ['Amistosos', EventoCalendario::urlDoRamo('amistosos')],
            ['Times', null],
        ];

        return view('admin.jogos.amistosos-times', compact('proximos', 'realizados', 'caminho'));
    }
}
