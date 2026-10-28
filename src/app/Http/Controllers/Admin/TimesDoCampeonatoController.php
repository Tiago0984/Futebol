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

        // Participações (time e categoria, 28/10/2026) que não aparecem em nenhum desses jogos daquela categoria: a
        // AACJ Masculino com jogo no Sub-13 e sem jogo no Sub-15 aparece só no Sub-15. Cada cartão com os convocados
        // daquela categoria (o interno abre os convocados dela; sem elenco fixo, Fase G, Etapa 4)
        // (jogo antigo sem categoria: vale para todas as categorias do time, chave "id_time|")
        $nosJogos = $proximos->concat($realizados)
            ->flatMap(fn ($jogo) => [
                "{$jogo->id_time_casa}|{$jogo->categoriaDoJogo()?->id_categoria}",
                "{$jogo->id_time_visitante}|{$jogo->categoriaDoJogo()?->id_categoria}",
            ])->flip();
        $participacoes = $campeonato->participacoes();
        $semJogo = $participacoes->reject(fn ($p) => $nosJogos->has("{$p->time->id_time}|{$p->categoria->id_categoria}")
            || $nosJogos->has("{$p->time->id_time}|"))->values();

        // Convocação (Fase G): uma linha por time interno e categoria em que ele joga (a AACJ pode estar em várias,
        // desde 27/10/2026), com quantos atletas ativos convocou nela, na ordem das categorias e pelo nome
        $convocacao = $participacoes->where(fn ($p) => $p->time->tipo_time === 'INTERNO')->values();

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
