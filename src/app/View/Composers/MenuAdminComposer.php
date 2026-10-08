<?php

namespace App\View\Composers;

use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dados da barra lateral e do header do admin (Fase 10), montados num lugar só: campeonatos em andamento
 * (com o jogo que cada um mostra), matrículas pendentes e o item ativo. Registrado no layout.admin (os
 * partials herdam), então roda uma vez por página: três consultas, quantos forem os campeonatos (mais uma
 * na tela de um evento, para achar o ramo dele).
 */
class MenuAdminComposer
{
    public function __construct(private Request $request)
    {
    }

    public function compose(View $view): void
    {
        $emAndamento = Campeonato::emAndamento()->orderBy('nome_campeonato')->get(['id_campeonato', 'nome_campeonato']);
        $this->escolherJogoDoMenu($emAndamento);

        $view->with([
            'campeonatosEmAndamento' => $emAndamento,
            'matriculasPendentes'    => Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(),
            'menuAtivo'              => $this->itemAtivo($emAndamento),
        ]);
    }

    /**
     * Jogo que cada campeonato mostra na barra (atributo jogo_do_menu, ou null): o próximo (evento ATIVO e não
     * concluído, o mais perto de hoje); sem jogo futuro, o último realizado (ATIVO e concluído). Cancelados e
     * ocultos nunca entram. Uma consulta para todos os campeonatos, com os nomes dos times ("Casa x Visitante");
     * "concluído" pela mesma regra do evento (EventoCalendario::estaConcluido).
     */
    private function escolherJogoDoMenu(Collection $campeonatos): void
    {
        $jogos = $campeonatos->isEmpty() ? collect() : DB::table('tbl_jogos as j')
            ->join('tbl_evento_calendario as ev', 'ev.id_evento_calendario', '=', 'j.id_evento')
            ->join('tbl_time as casa', 'casa.id_time', '=', 'j.id_time_casa')
            ->join('tbl_time as fora', 'fora.id_time', '=', 'j.id_time_visitante')
            ->whereIn('j.id_campeonato', $campeonatos->pluck('id_campeonato'))
            ->where('ev.status_evento_calendario', 'ATIVO')
            ->orderBy('ev.data_evento_calendario')
            ->orderBy('ev.horario_inicio_evento_calendario')
            ->orderBy('j.id_jogo')
            ->get([
                'j.id_campeonato', 'j.id_jogo', 'j.id_evento', 'casa.nome_time as nome_casa', 'fora.nome_time as nome_visitante',
                'ev.data_evento_calendario', 'ev.horario_inicio_evento_calendario', 'ev.horario_fim_evento_calendario',
            ])
            ->groupBy('id_campeonato');

        foreach ($campeonatos as $campeonato) {
            [$realizados, $futuros] = ($jogos[$campeonato->id_campeonato] ?? collect())
                ->partition(fn ($jogo) => (new EventoCalendario)->forceFill([
                    'data_evento_calendario'           => $jogo->data_evento_calendario,
                    'horario_inicio_evento_calendario' => $jogo->horario_inicio_evento_calendario,
                    'horario_fim_evento_calendario'    => $jogo->horario_fim_evento_calendario,
                ])->estaConcluido());

            $campeonato->setAttribute('jogo_do_menu', $futuros->first() ?? $realizados->last());
        }
    }

    /**
     * Chave do item ativo: calendario, ramo:{ramo}, campeonato:{id} (tela de Campeonatos filtrada por ele:
     * o nome no menu), jogo:{id_jogo} (lista de Jogos filtrada pelo jogo que o menu mostra),
     * jogos-do-campeonato:{id} (lista de Jogos filtrada só por ele: o "Ver todos os jogos"),
     * times-do-campeonato:{id} (os cartões dos times dele e os jogadores de um time: o "Times"),
     * times-dos-amistosos (os times dos amistosos, por jogo: o "Times" de Amistosos), campeonatos
     * ("Ver todos"), jogos, grade, categorias, times ou notificacoes; null fora deles (os outros itens usam
     * routeIs() na própria view).
     */
    private function itemAtivo(Collection $emAndamento): ?string
    {
        $r = $this->request;

        if ($r->routeIs('admin.calendario.grade.*') || ($r->routeIs('admin.calendario.index') && $r->query('tab') === 'grade')) {
            return 'grade';
        }

        if ($r->routeIs('admin.calendario.index')) {
            $ramo = $r->query('ramo');

            return array_key_exists((string) $ramo, EventoCalendario::RAMOS) ? "ramo:{$ramo}" : 'calendario';
        }

        // Tela do evento: o ramo dele (jogo de campeonato em Campeonatos, amistoso em Amistosos...)
        if ($r->routeIs('admin.calendario.eventos.show')) {
            $ramo = EventoCalendario::with('jogo')->find($r->route('id'))?->ramo();

            return $ramo ? "ramo:{$ramo}" : 'calendario';
        }

        // Jogos filtrados pelo jogo que o menu mostra: esse jogo; só por um campeonato em andamento: o "Ver
        // todos os jogos" dele; amistosos: o ramo Amistosos (que abre esta lista); o resto (inclusive com
        // filtro de time ou situação), Jogos
        if ($r->routeIs('admin.jogos.*') && ! $r->routeIs('admin.jogos.times.show')) {
            $campeonato = (string) $r->query('campeonato');
            $jogo       = (string) $r->query('jogo', '');

            if ($jogo !== '' && $emAndamento->contains(fn ($c) => (string) $c->jogo_do_menu?->id_jogo === $jogo)) {
                return "jogo:{$jogo}";
            }

            if ($campeonato === 'amistoso') {
                return 'ramo:amistosos';
            }

            $soCampeonato = $jogo === '' && $r->query('time', '') === '' && $r->query('situacao', '') === '';

            return $soCampeonato && $emAndamento->contains(fn ($c) => (string) $c->id_campeonato === $campeonato)
                ? "jogos-do-campeonato:{$campeonato}"
                : 'jogos';
        }

        // Times dos amistosos (cartões por jogo): o "Times" de Amistosos
        if ($r->routeIs('admin.amistosos.times')) {
            return 'times-dos-amistosos';
        }

        // Jogadores de um time num jogo: o "Times" de onde o cartão veio (Amistosos ou o campeonato do menu)
        if ($r->routeIs('admin.jogos.times.show')) {
            $idCampeonato = Jogo::whereKey($r->route('id'))->value('id_campeonato');
            if (! $idCampeonato) {
                return 'times-dos-amistosos';
            }

            return $emAndamento->contains(fn ($c) => (int) $c->id_campeonato === (int) $idCampeonato)
                ? "times-do-campeonato:{$idCampeonato}"
                : 'campeonatos';
        }

        // Times do campeonato (cartões e jogadores de um time): o "Times" dele; campeonato fora do menu: "Ver todos"
        if ($r->routeIs('admin.campeonatos.times', 'admin.campeonatos.times.show')) {
            $campeonato = (string) $r->route('id');

            return $emAndamento->contains(fn ($c) => (string) $c->id_campeonato === $campeonato)
                ? "times-do-campeonato:{$campeonato}"
                : 'campeonatos';
        }

        // Tela de Campeonatos filtrada por um campeonato do menu: o nome dele; sem filtro (ou outro): "Ver todos"
        if ($r->routeIs('admin.campeonatos.index')) {
            $campeonato = (string) $r->query('campeonato');

            return $emAndamento->contains(fn ($c) => (string) $c->id_campeonato === $campeonato)
                ? "campeonato:{$campeonato}"
                : 'campeonatos';
        }

        return match (true) {
            $r->routeIs('admin.campeonatos.*')  => 'campeonatos',
            $r->routeIs('admin.times.*')        => 'times',
            $r->routeIs('admin.categorias.*')   => 'categorias',
            $r->routeIs('admin.notificacoes.*') => 'notificacoes',
            default                             => null,
        };
    }
}
