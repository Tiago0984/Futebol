<?php

namespace App\View\Composers;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dados da barra lateral e do header do admin (Fase 10), montados num lugar só: matrículas pendentes e o item
 * ativo. Registrado no layout.admin (os partials herdam), então roda uma vez por página: uma consulta (mais uma
 * na tela de um evento, para achar o ramo dele, e uma nos jogadores de um time num jogo).
 */
class MenuAdminComposer
{
    public function __construct(private Request $request)
    {
    }

    public function compose(View $view): void
    {
        $view->with([
            'matriculasPendentes' => Atleta::whereIn('status_atleta', ['PENDENTE', 'pendente'])->count(),
            'menuAtivo'           => $this->itemAtivo(),
        ]);
    }

    /**
     * Chave do item ativo: calendario, ramo:{ramo}, jogos-dos-campeonatos (o "Jogos" dentro de Campeonatos: a
     * tela de jogos de todos os campeonatos ou de um, os jogadores de um time, e a lista de Jogos filtrada só por
     * um campeonato), times-dos-amistosos (os jogos dos amistosos em cartões: o item Amistosos), campeonatos (a
     * tela de Campeonatos, com ou sem filtro), jogos, grade, categorias, times ou notificacoes; null fora deles
     * (os outros itens usam routeIs() na própria view).
     */
    private function itemAtivo(): ?string
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

        // Jogos filtrados só por um campeonato: o "Jogos" de Campeonatos; amistosos: o ramo Amistosos (que abre
        // esta lista); o resto (sem filtro ou com outros filtros, como jogo, time ou situação), Jogos
        if ($r->routeIs('admin.jogos.*') && ! $r->routeIs('admin.jogos.times.show')) {
            $campeonato = (string) $r->query('campeonato');

            if ($campeonato === 'amistoso') {
                return 'ramo:amistosos';
            }

            $soCampeonato = $r->query('jogo', '') === '' && $r->query('time', '') === '' && $r->query('situacao', '') === '';

            return $soCampeonato && ctype_digit($campeonato) ? 'jogos-dos-campeonatos' : 'jogos';
        }

        // Jogos dos amistosos (cartões por jogo): o item Amistosos
        if ($r->routeIs('admin.amistosos.times')) {
            return 'times-dos-amistosos';
        }

        // Jogadores de um time num jogo: a tela de onde o cartão veio (Amistosos ou os Jogos dos campeonatos)
        if ($r->routeIs('admin.jogos.times.show')) {
            return Jogo::whereKey($r->route('id'))->value('id_campeonato') ? 'jogos-dos-campeonatos' : 'times-dos-amistosos';
        }

        // Jogos de todos os campeonatos ou de um (e os jogadores de um time dele): o "Jogos" de Campeonatos
        if ($r->routeIs('admin.campeonatos.jogos', 'admin.campeonatos.times', 'admin.campeonatos.times.show')) {
            return 'jogos-dos-campeonatos';
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
