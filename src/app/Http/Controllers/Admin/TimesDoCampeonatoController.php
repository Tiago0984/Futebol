<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use App\Models\Jogo;

/**
 * Jogos de um campeonato: a aba "Jogos" da central do campeonato (a rota mantém o nome "times"; desde 30/10/2026
 * a convocação e os participantes sem jogo ficam na aba "Participantes e convocação"), um bloco por jogo
 * (próximos e últimos realizados) com o cartão do mandante e o do visitante. todos() é o "Jogos" do menu: os jogos
 * de todos os campeonatos, com o filtro que abre a central de cada um.
 */
class TimesDoCampeonatoController extends Controller
{
    public function index($id)
    {
        $campeonato = Campeonato::findOrFail($id);

        // Jogos do campeonato (não ocultos): próximos do mais perto ao mais longe, depois os últimos realizados
        [$proximos, $realizados] = Jogo::separadosParaTelaDeTimes($campeonato->jogos()->getQuery());

        return view('admin.campeonatos.times', [
            ...compact('campeonato', 'proximos', 'realizados'),
            'formularioJogo' => JogosController::dadosDoFormulario(),
        ]);
    }

    // Opção "Todos" do filtro: os jogos de todos os campeonatos (sem os amistosos), cada bloco com o nome do
    // campeonato
    public function todos()
    {
        // Jogos dos campeonatos inativos ficam de fora (o filtro ainda abre cada um, no grupo "Inativos")
        [$proximos, $realizados] = Jogo::separadosParaTelaDeTimes(Jogo::whereHas('campeonato', fn ($q) => $q->ativos()));

        $campeonato = null;
        $caminho    = [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
            ['Jogos', null],
        ];

        return view('admin.campeonatos.times', [
            ...compact('campeonato', 'proximos', 'realizados', 'caminho'),
            // Filtro "Campeonato" (cada campeonato, do mais recente para o mais antigo; o encerrado também)
            'campeonatos'    => Campeonato::orderByDesc('data_inicio_campeonato')->orderBy('nome_campeonato')
                ->get(['id_campeonato', 'nome_campeonato', 'status_campeonato']),
            'formularioJogo' => JogosController::dadosDoFormulario(),
        ]);
    }
}
