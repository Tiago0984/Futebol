<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Time;

/**
 * Jogos de um campeonato (o "Jogos" de cada campeonato no menu; a rota mantém o nome "times"), só para ver,
 * como nos Times dos amistosos: um bloco por jogo (próximos e últimos realizados) com o cartão do mandante e o
 * do visitante, e um filtro para trocar de campeonato (ou ver os jogos de todos, em todos());
 * no fim, os participantes que não aparecem em nenhum desses jogos. O time interno abre os convocados dele no
 * campeonato (sem elenco fixo desde a Fase G, Etapa 4); o externo não tem atletas na associação.
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
            // O time interno abre os convocados dele no campeonato (sem elenco fixo, Fase G, Etapa 4)
            ->withCount(['convocacoes as total_convocados' => fn ($q) => $q->where('id_campeonato', $campeonato->id_campeonato)->deAtivos()])
            ->whereNotIn('tbl_time.id_time', $idsNosJogos)
            ->orderByRaw("FIELD(tipo_time, 'INTERNO', 'EXTERNO')")
            ->orderBy('nome_time')
            ->get();

        // Convocação (Fase G): os participantes internos, cada um com quantos atletas ativos convocou e a categoria
        // em que joga no campeonato (Fase H, Etapa H2), na ordem das categorias e pelo nome
        $convocacao = $campeonato->timesConvocaveis()
            ->withCount(['convocacoes as total_convocados' => fn ($q) => $q->where('id_campeonato', $campeonato->id_campeonato)->deAtivos()])
            ->orderBy('nome_time')
            ->get()
            ->each(fn ($time) => $time->setAttribute('categoria_no_campeonato', $campeonato->categoriaDoTime($time)))
            ->sortBy(fn ($time) => [$time->categoria_no_campeonato?->sexo_categoria === 'M' ? 0 : 1,
                (int) $time->categoria_no_campeonato?->idade_min_categoria, $time->nome_time])
            ->values();

        $caminho = $this->caminho($campeonato);

        return view('admin.campeonatos.times', [
            ...compact('campeonato', 'proximos', 'realizados', 'semJogo', 'convocacao', 'caminho'),
            ...$this->dadosDaTela(),
        ]);
    }

    // Opção "Todos" do filtro: os jogos de todos os campeonatos (sem os amistosos), cada bloco com o nome do
    // campeonato; sem a lista de participantes sem jogo (ela é de um campeonato só)
    public function todos()
    {
        // Jogos dos campeonatos inativos ficam de fora (o filtro ainda abre cada um, no grupo "Inativos")
        [$proximos, $realizados] = Jogo::separadosParaTelaDeTimes(Jogo::whereHas('campeonato', fn ($q) => $q->ativos()));

        $campeonato = null;
        $semJogo    = collect();
        $convocacao = collect();
        $caminho    = [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
            ['Jogos', null],
        ];

        return view('admin.campeonatos.times', [
            ...compact('campeonato', 'proximos', 'realizados', 'semJogo', 'convocacao', 'caminho'),
            ...$this->dadosDaTela(),
        ]);
    }

    /**
     * Filtro "Campeonato" da tela ("Todos" e cada campeonato, do mais recente para o mais antigo; o encerrado
     * também pode ser visto) e as listas do modal "Editar jogo" das ações de cada jogo.
     */
    private function dadosDaTela(): array
    {
        return [
            'campeonatos'    => Campeonato::orderByDesc('data_inicio_campeonato')->orderBy('nome_campeonato')
                ->get(['id_campeonato', 'nome_campeonato', 'status_campeonato']),
            'formularioJogo' => JogosController::dadosDoFormulario(),
        ];
    }

    // Eventos › Campeonatos › nome do campeonato › Jogos
    private function caminho(Campeonato $campeonato): array
    {
        return [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
            [$campeonato->nome_campeonato, route('admin.campeonatos.index', ['campeonato' => $campeonato->id_campeonato])],
            ['Jogos', null],
        ];
    }
}
