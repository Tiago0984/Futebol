<?php

namespace App\View\Composers;

use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dados da barra lateral e do header do admin (Fase 10), montados num lugar só: campeonatos em andamento,
 * matrículas pendentes e o item ativo. Registrado no layout.admin (os partials herdam), então roda uma vez
 * por página: duas consultas, quantos forem os campeonatos (mais uma na tela de um evento, para achar o ramo
 * dele, e uma nos jogadores de um time num jogo).
 */
class MenuAdminComposer
{
    public function __construct(private Request $request)
    {
    }

    public function compose(View $view): void
    {
        $emAndamento = Campeonato::emAndamento()->orderBy('nome_campeonato')->get(['id_campeonato', 'nome_campeonato']);

        $view->with([
            'campeonatosEmAndamento' => $emAndamento,
            'matriculasPendentes'    => Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(),
            'menuAtivo'              => $this->itemAtivo($emAndamento),
        ]);
    }

    /**
     * Chave do item ativo: calendario, ramo:{ramo}, jogos-do-campeonato:{id} (lista de Jogos filtrada só por
     * ele: o "Jogos" dentro de Campeonatos), times-do-campeonato:{id} (os cartões dos times dele e os jogadores de um time: o "Times"),
     * times-dos-amistosos (os times dos amistosos, por jogo: o "Times" de Amistosos), campeonatos (a tela de
     * Campeonatos, com ou sem filtro), jogos, grade, categorias, times ou notificacoes; null fora deles (os
     * outros itens usam routeIs() na própria view).
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

        // Jogos filtrados só por um campeonato em andamento: o "Jogos" dele em Campeonatos; amistosos: o ramo
        // Amistosos (que abre esta lista); o resto (sem filtro ou com outros filtros, como jogo, time ou
        // situação), Jogos
        if ($r->routeIs('admin.jogos.*') && ! $r->routeIs('admin.jogos.times.show')) {
            $campeonato = (string) $r->query('campeonato');

            if ($campeonato === 'amistoso') {
                return 'ramo:amistosos';
            }

            $soCampeonato = $r->query('jogo', '') === '' && $r->query('time', '') === '' && $r->query('situacao', '') === '';

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

        // Times do campeonato (cartões e jogadores de um time): o "Times" dele; campeonato fora do menu: Campeonatos
        if ($r->routeIs('admin.campeonatos.times', 'admin.campeonatos.times.show')) {
            $campeonato = (string) $r->route('id');

            return $emAndamento->contains(fn ($c) => (string) $c->id_campeonato === $campeonato)
                ? "times-do-campeonato:{$campeonato}"
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
