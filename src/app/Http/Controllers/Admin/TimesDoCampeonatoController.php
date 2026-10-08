<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Time;

/**
 * Times de um campeonato (subitem "Times" de cada campeonato no menu), só para ver, separados por jogo como
 * nos amistosos: um bloco por jogo (próximos e últimos realizados) com o cartão do mandante e o do visitante;
 * no fim, os participantes que não aparecem em nenhum desses jogos. O time interno abre os jogadores do
 * elenco, sem edição (a edição continua em Times > Elenco). Externo não tem elenco na associação.
 */
class TimesDoCampeonatoController extends Controller
{
    public function index($id)
    {
        $campeonato = Campeonato::findOrFail($id);

        // Jogos do campeonato (não ocultos): próximos do mais perto ao mais longe, depois os últimos realizados
        [$proximos, $realizados] = Jogo::separadosParaTelaDeTimes($campeonato->jogos()->getQuery());

        // Participantes que não aparecem em nenhum desses jogos (internos primeiro, depois por nome)
        $idsNosJogos = $proximos->concat($realizados)
            ->flatMap(fn ($jogo) => [(int) $jogo->id_time_casa, (int) $jogo->id_time_visitante])
            ->unique()->all();
        $semJogo = $campeonato->times()
            ->with('categoria')
            ->withCount(['atletas as total_atletas'])
            ->whereNotIn('tbl_time.id_time', $idsNosJogos)
            ->orderByRaw("FIELD(tipo_time, 'INTERNO', 'EXTERNO')")
            ->orderBy('nome_time')
            ->get();

        $caminho = $this->caminho($campeonato);

        return view('admin.campeonatos.times', compact('campeonato', 'proximos', 'realizados', 'semJogo', 'caminho'));
    }

    // Jogadores do time no campeonato (o elenco), só leitura, na mesma tela do Elenco
    public function show($id, $timeId)
    {
        $campeonato = Campeonato::findOrFail($id);
        $time = Time::with('categoria')->findOrFail($timeId);

        // Time do campeonato: participante ou de algum jogo dele (o jogo pode ter time fora dos participantes)
        $doCampeonato = $campeonato->times()->where('tbl_time.id_time', $time->id_time)->exists()
            || $campeonato->jogos()
                ->where(fn ($q) => $q->where('id_time_casa', $time->id_time)->orWhere('id_time_visitante', $time->id_time))
                ->exists();
        abort_unless($doCampeonato, 404);

        if ($time->tipo_time === 'EXTERNO') {
            return redirect()->route('admin.campeonatos.times', $campeonato->id_campeonato)
                ->with('erro', 'Times externos não possuem elenco cadastrado na associação.');
        }

        [$atletas, $cartoes] = $time->elencoParaTela();
        $somenteLeitura = true;
        $voltar  = route('admin.campeonatos.times', $campeonato->id_campeonato);
        $caminho = [...$this->caminho($campeonato, comLink: true), [$time->nome_time, null]];

        return view('admin.times.elenco', compact('time', 'atletas', 'cartoes', 'somenteLeitura', 'voltar', 'caminho'));
    }

    // Eventos › Campeonatos › nome do campeonato › Times (a última sem link, a não ser nos jogadores)
    private function caminho(Campeonato $campeonato, bool $comLink = false): array
    {
        return [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
            [$campeonato->nome_campeonato, route('admin.campeonatos.index', ['campeonato' => $campeonato->id_campeonato])],
            ['Times', $comLink ? route('admin.campeonatos.times', $campeonato->id_campeonato) : null],
        ];
    }
}
