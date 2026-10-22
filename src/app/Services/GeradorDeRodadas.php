<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\Jogo;
use App\Services\Concerns\ProgramaJogos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gerar os jogos de todos contra todos de uma categoria do campeonato (Fase I, Etapa I2; dono do projeto,
 * 21/10/2026). O formato é escolhido na hora de gerar: só turno, ou turno e returno (mando invertido). Com grupos
 * (chaves), cada grupo tem os próprios confrontos e as rodadas são numeradas juntas; sem grupos, todos os
 * participantes da categoria. Os jogos ficam na fase de grupos, com a rodada, e são criados pelo mesmo caminho
 * da tela de Jogos (Jogo::criarComEvento): com time da AACJ, rascunho com os convocados; dois externos,
 * publicado. Ninguém é avisado na geração. Tudo ou nada. Categoria que já tem jogos da fase de grupos (fora os
 * ocultos) é recusada.
 *
 * $opcoes: returno (bool), data (AAAA-MM-DD da 1ª rodada), dias (entre rodadas), horario (HH:MM do 1º jogo do
 * dia), intervalo (minutos entre jogos do mesmo dia; 0 = mesmo horário), duracao (minutos, opcional: preenche o
 * fim) e local (opcional; vazio = local principal do campeonato).
 */
class GeradorDeRodadas
{
    use ProgramaJogos;

    private ?array $previa = null;

    public function __construct(
        private Campeonato $campeonato,
        private Categoria $categoria,
        private array $opcoes,
    ) {
    }

    /**
     * Rodadas de todos contra todos pelo método do círculo: um time fica parado e os outros giram; com número
     * ímpar, um "vazio" entra na roda e quem o enfrenta folga. Mandos equilibrados (em n times, cada um com
     * metade dos jogos em casa, arredondada). No returno, os mesmos confrontos com o mando invertido.
     * Devolve [['jogos' => [[casa, visitante], ...], 'folga' => id ou null], ...].
     */
    public static function confrontos(array $idsTimes, bool $returno = false): array
    {
        $times = array_values($idsTimes);
        if (count($times) < 2) {
            return [];
        }
        if (count($times) % 2) {
            $times[] = null;
        }
        $n = count($times);

        $turno = [];
        for ($r = 0; $r < $n - 1; $r++) {
            $jogos = [];
            $folga = null;
            for ($i = 0; $i < $n / 2; $i++) {
                [$a, $b] = [$times[$i], $times[$n - 1 - $i]];
                if ($a === null || $b === null) {
                    $folga = $a ?? $b;
                    continue;
                }
                // Mando: na metade esquerda da roda, em casa; o time parado alterna a cada rodada. Como cada time passa
                // por todas as posições, ninguém fica só em casa nem só fora
                $jogos[] = $i === 0 && $r % 2 === 1 ? [$b, $a] : [$a, $b];
            }
            $turno[] = ['jogos' => $jogos, 'folga' => $folga];

            // Gira: o primeiro fica; o último passa para a segunda posição
            $ultimo = array_pop($times);
            array_splice($times, 1, 0, [$ultimo]);
        }

        if (! $returno) {
            return $turno;
        }

        $volta = array_map(fn ($rodada) => [
            'jogos' => array_map(fn ($jogo) => [$jogo[1], $jogo[0]], $rodada['jogos']),
            'folga' => $rodada['folga'],
        ], $turno);

        return [...$turno, ...$volta];
    }

    // Motivos que impedem gerar (vazio = pode gerar)
    public function erros(): array
    {
        $erros = [];

        if (! $this->campeonato->estaAtivo()) {
            $erros[] = "O campeonato {$this->campeonato->nome_campeonato} está inativo. Reative-o antes de gerar jogos.";
        }

        $jaTem = Jogo::where('id_campeonato', $this->campeonato->id_campeonato)
            ->where(fn ($q) => $q->where('fase_jogo', Jogo::FASE_PADRAO)->orWhereNull('fase_jogo'))
            ->whereHas('evento', fn ($q) => $q->where('id_categoria', $this->categoria->id_categoria)
                ->where('status_evento_calendario', '<>', 'INATIVO'))
            ->count();
        if ($jaTem) {
            $erros[] = "O {$this->categoria->rotulo} já tem {$jaTem} jogo(s) da fase de grupos. Gere só para uma categoria sem jogos, "
                . 'ou cadastre os que faltam em Jogos.';
        }

        $grupos = $this->grupos();
        if ($grupos->has('') && $grupos->count() > 1) {
            $erros[] = 'Ponha todos os times num grupo (chave) antes de gerar: ' . $grupos['']->pluck('nome_time')->implode(', ') . '.';
        }
        if ($grupos->every(fn ($times) => $times->count() < 2)) {
            $erros[] = 'São precisos ao menos 2 times (no grupo ou na categoria) para gerar jogos.';
        }

        return $erros;
    }

    /**
     * Prévia: ['rodadas' => [['numero', 'data' (Carbon), 'jogos' => [['grupo', 'casa' e 'visitante' (id_time e
     * nome_time), 'inicio', 'fim', 'acj' (tem time da AACJ: nasce rascunho)]],'folgas' => [nome do time (e do grupo)]]], 'totais' => [times, rodadas, jogos, rascunhos,
     * publicados], 'avisos' => [...], 'conflitos' => EventoCalendario::conflitosEmLote()].
     */
    public function previa(): array
    {
        if ($this->previa !== null) {
            return $this->previa;
        }

        $grupos  = $this->grupos();
        $porGrupo = $grupos->map(fn ($times) => self::confrontos($times->pluck('id_time')->all(), (bool) $this->opcoes['returno']));
        $total   = $porGrupo->max(fn ($rodadas) => count($rodadas)) ?? 0;
        $times   = $grupos->flatten(1)->keyBy('id_time');
        $internos = array_flip(Jogo::idsInternos($times->keys()->all()));

        $rodadas = [];
        for ($r = 0; $r < $total; $r++) {
            $data  = Carbon::parse($this->opcoes['data'])->addDays($r * (int) $this->opcoes['dias']);
            $jogos = [];
            $folgas = [];
            foreach ($porGrupo as $nomeGrupo => $doGrupo) {
                foreach ($doGrupo[$r]['jogos'] ?? [] as [$casa, $fora]) {
                    $jogos[] = ['grupo' => $nomeGrupo ?: null, 'casa' => $times[$casa], 'visitante' => $times[$fora]];
                }
                if ($folga = $doGrupo[$r]['folga'] ?? null) {
                    $folgas[] = $times[$folga]->nome_time . ($nomeGrupo ? " ({$nomeGrupo})" : '');
                }
            }

            // Jogos do dia escalonados pelo intervalo (0 = todos no mesmo horário)
            foreach ($jogos as $k => &$jogo) {
                $jogo = [...$jogo, ...$this->horarioDoJogo($k), 'data' => $data,
                    'acj' => isset($internos[$jogo['casa']->id_time]) || isset($internos[$jogo['visitante']->id_time]),
                    'colunas' => ['fase_jogo' => Jogo::FASE_PADRAO, 'rodada_jogo' => $r + 1]];
            }
            unset($jogo);

            $rodadas[] = ['numero' => $r + 1, 'data' => $data, 'jogos' => $jogos, 'folgas' => $folgas];
        }

        $todos = collect($rodadas)->flatMap(fn ($rodada) => $rodada['jogos']);
        $avisos = [];
        if ($rodadas && end($rodadas)['data']->gt($this->campeonato->data_fim_campeonato)) {
            $avisos[] = 'A última rodada (' . end($rodadas)['data']->format('d/m/Y') . ') passa da data de fim do campeonato ('
                . $this->campeonato->data_fim_campeonato->format('d/m/Y') . ').';
        }
        if ($rodadas && $rodadas[0]['data']->lt(today())) {
            $avisos[] = 'A 1ª rodada (' . $rodadas[0]['data']->format('d/m/Y') . ') já passou: os jogos de datas passadas nascem sem aviso aos atletas.';
        }
        $avisos = [...$avisos, ...$this->avisosDeTimesNoMesmoDia($todos->all())];

        return $this->previa = [
            'rodadas'   => $rodadas,
            'totais'    => [
                'times'      => $times->count(),
                'rodadas'    => $total,
                'jogos'      => $todos->count(),
                'rascunhos'  => $todos->where('acj', true)->count(),
                'publicados' => $todos->where('acj', false)->count(),
            ],
            'avisos'    => $avisos,
            'conflitos' => $this->conflitosDosJogos($todos->all()),
        ];
    }

    /**
     * Grava tudo numa transação (tudo ou nada). Com conflito real de horário dos convocados e sem $confirmar, não
     * grava nada. Devolve ['gravou' => bool, 'jogos', 'rodadas', 'rascunhos', 'publicados', 'previa'].
     */
    public function gerar(?int $idUsuario, bool $confirmar = false): array
    {
        $previa = $this->previa();
        $resultado = ['gravou' => false, ...$previa['totais'], 'previa' => $previa];

        if ($this->erros() || ($previa['conflitos']['totais']['reais'] > 0 && ! $confirmar)) {
            return $resultado;
        }

        $this->gravarJogos(collect($previa['rodadas'])->flatMap(fn ($rodada) => $rodada['jogos'])->all(), $idUsuario);

        return [...$resultado, 'gravou' => true];
    }

    // Participantes da categoria por grupo (chave): [nome do grupo ou '' => linhas com id_time e nome_time, pelo
    // nome]; sem grupos, um só ('')
    private function grupos(): Collection
    {
        $nomes = $this->campeonato->grupos()->pluck('nome_campeonato_grupo', 'id_campeonato_grupo');

        return $this->campeonato->participantesComGrupo((int) $this->categoria->id_categoria)
            ->groupBy(fn ($p) => $p->id_campeonato_grupo ? (string) $nomes[$p->id_campeonato_grupo] : '')
            ->map(fn ($linhas) => $linhas->values())
            ->sortKeys();
    }
}
