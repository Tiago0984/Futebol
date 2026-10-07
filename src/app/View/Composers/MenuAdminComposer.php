<?php

namespace App\View\Composers;

use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dados da barra lateral do admin (Fase 10), montados num lugar só: campeonatos em andamento, matrículas
 * pendentes e o item ativo. Duas consultas por página (mais uma na tela do evento, para achar o ramo dele).
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
     * Chave do item ativo: calendario, ramo:{ramo}, campeonato:{id}, campeonatos ("Ver todos"), jogos,
     * grade, categorias ou times; null fora deles (os outros itens usam routeIs() na própria view).
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

        // Jogos de um campeonato em andamento: o campeonato no ramo Campeonatos; o resto, Jogos
        if ($r->routeIs('admin.jogos.*')) {
            $campeonato = (string) $r->query('campeonato');

            return $emAndamento->contains(fn ($c) => (string) $c->id_campeonato === $campeonato)
                ? "campeonato:{$campeonato}"
                : 'jogos';
        }

        return match (true) {
            $r->routeIs('admin.campeonatos.*') => 'campeonatos',
            $r->routeIs('admin.times.*')       => 'times',
            $r->routeIs('admin.categorias.*')  => 'categorias',
            default                            => null,
        };
    }
}
