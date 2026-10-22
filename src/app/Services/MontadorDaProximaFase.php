<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\Jogo;
use App\Services\Concerns\ProgramaJogos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Montar a próxima fase do mata-mata de uma categoria do campeonato (Fase I, Etapa I3; dono do projeto,
 * 21/10/2026). Sem mata-mata ainda, entram os classificados da tabela (campo "Classificam por grupo"); com
 * mata-mata, os vencedores da última fase (Jogo::resultadoDoConfronto). A fase sai do número de times (2 Final,
 * 4 Semifinal, 8 Quartas, 16 Oitavas, 32 16 avos); número que não fecha a chave é recusado (decisão do dono do
 * projeto: montar à mão, por exemplo com uma repescagem).
 *
 * Confrontos sugeridos (a prévia deixa trocar): tabela única, 1º × último; com grupos, o cruzamento clássico
 * (1º A × 2º B, 1º B × 2º A; grupos em pares, pela ordem do nome); vencedores, pela ordem dos confrontos (o do
 * jogo 1 × o do jogo 2). O 1º time de cada confronto é o mais bem colocado na fase de grupos: no jogo único, é o
 * mandante; na ida e volta, decide em casa (o outro joga a ida em casa). Ida e volta nascem juntas, com a volta já
 * ligada à ida. Disputa de 3º lugar (os perdedores das semifinais) opcional, desmarcada por padrão.
 *
 * $opcoes: ida_e_volta (bool), data, dias_volta, horario, intervalo, duracao, local, terceiro (bool) e pares
 * (opcional: [[id do 1º, id do 2º], ...] escolhidos na prévia).
 */
class MontadorDaProximaFase
{
    use ProgramaJogos;

    // Fase do mata-mata pelo número de times que entram
    public const FASE_POR_TIMES = [2 => 'FINAL', 4 => 'SEMIFINAL', 8 => 'QUARTAS', 16 => 'OITAVAS', 32 => 'DEZESSEIS_AVOS'];

    // Fases que contam como "a última do mata-mata" (repescagem e disputa de 3º ficam de fora)
    private const CHAVE = ['DEZESSEIS_AVOS', 'OITAVAS', 'QUARTAS', 'SEMIFINAL', 'FINAL'];

    private ?array $origem = null;
    private ?array $previa = null;

    public function __construct(
        private Campeonato $campeonato,
        private Categoria $categoria,
        private array $opcoes,
    ) {
    }

    /**
     * De onde vêm os times: ['tipo' => 'tabela'|'vencedores', 'fase_anterior', 'times' (id_time => linha com
     * id_time, nome_time e 'rotulo': "1º Grupo A", "Venceu Botafogo x Santos"), 'pares' (sugeridos: [[id, id]]),
     * 'perdedores' (das semifinais, para o 3º lugar), 'erros', 'avisos'].
     */
    public function origem(): array
    {
        if ($this->origem !== null) {
            return $this->origem;
        }

        $mataMata = Jogo::with(['evento', 'timeCasa', 'timeVisitante', 'jogoIda', 'jogoVolta'])
            ->where('id_campeonato', $this->campeonato->id_campeonato)
            ->whereIn('fase_jogo', self::CHAVE)
            ->whereHas('evento', fn ($q) => $q->where('id_categoria', $this->categoria->id_categoria)->where('status_evento_calendario', 'ATIVO'))
            ->ordenadosPelaData()
            ->orderBy('tbl_jogos.id_jogo')
            ->get();

        return $this->origem = $mataMata->isEmpty() ? $this->daTabela() : $this->dosVencedores($mataMata);
    }

    // Fase do mata-mata que será montada (null quando o número de times não fecha a chave)
    public function fase(): ?string
    {
        return self::FASE_POR_TIMES[count($this->origem()['times'])] ?? null;
    }

    // Disputa de 3º lugar: só ao montar a final a partir das semifinais
    public function ofereceTerceiro(): bool
    {
        return $this->fase() === 'FINAL' && $this->origem()['fase_anterior'] === 'SEMIFINAL';
    }

    // Confrontos da prévia: os escolhidos (se forem válidos para conferir) ou os sugeridos
    public function pares(): array
    {
        return ! empty($this->opcoes['pares']) ? array_values($this->opcoes['pares']) : $this->origem()['pares'];
    }

    public function erros(): array
    {
        $origem = $this->origem();
        $erros  = $origem['erros'];

        if (! $this->campeonato->estaAtivo()) {
            $erros[] = "O campeonato {$this->campeonato->nome_campeonato} está inativo. Reative-o antes de montar jogos.";
        }
        if ($erros) {
            return $erros;
        }

        $quantos = count($origem['times']);
        if ($quantos < 2 && $origem['tipo'] === 'vencedores') {
            return ['A fase "' . Jogo::FASES[$origem['fase_anterior']] . '" não tem confrontos completos e ativos (um jogo de ida com a volta '
                . 'oculta ou cancelada, por exemplo). Ajuste esses jogos em Jogos.'];
        }
        if (! $this->fase()) {
            return ["{$quantos} time(s) não fecham uma chave (2, 4, 8, 16 ou 32): ajuste os classificados ou monte os jogos à mão "
                . '(por exemplo, com uma repescagem).'];
        }

        foreach (array_filter([$this->fase(), $this->ofereceTerceiro() && ! empty($this->opcoes['terceiro']) ? 'TERCEIRO' : null]) as $fase) {
            if ($jaTem = $this->jogosNaFase($fase)) {
                $erros[] = "O {$this->categoria->rotulo} já tem {$jaTem} jogo(s) na fase \"" . Jogo::FASES[$fase] . '". Ajuste-os em Jogos.';
            }
        }

        // Os confrontos escolhidos na prévia: todos os times, cada um uma vez
        $ids = collect($this->pares())->flatten()->map(fn ($id) => (int) $id);
        if ($ids->count() !== $quantos || $ids->unique()->count() !== $quantos || $ids->diff(array_keys($origem['times']))->isNotEmpty()) {
            $erros[] = 'Cada time que entra precisa estar em um confronto só (confira os confrontos da prévia).';
        }

        return $erros;
    }

    /**
     * Prévia: ['fase', 'confrontos' => [['fase', 'primeiro', 'segundo']], 'jogos' (programados, na ordem de gravação),
     * 'totais' => [times, confrontos, jogos, rascunhos, publicados], 'avisos', 'conflitos'].
     */
    public function previa(): array
    {
        if ($this->previa !== null) {
            return $this->previa;
        }

        $origem = $this->origem();
        $times  = $origem['times'];
        $confrontos = [];
        if (! $this->erros()) {
            if ($this->ofereceTerceiro() && ! empty($this->opcoes['terceiro'])) {
                $confrontos[] = ['fase' => 'TERCEIRO', 'primeiro' => $origem['perdedores'][0], 'segundo' => $origem['perdedores'][1]];
            }
            foreach ($this->pares() as [$primeiro, $segundo]) {
                $confrontos[] = ['fase' => $this->fase(), 'primeiro' => $times[(int) $primeiro], 'segundo' => $times[(int) $segundo]];
            }
        }

        // Ida e volta: o 2º joga a ida em casa e o 1º decide em casa; jogo único: o 1º é o mandante
        $idaEVolta = ! empty($this->opcoes['ida_e_volta']);
        $dataIda   = Carbon::parse($this->opcoes['data']);
        $dataVolta = $dataIda->copy()->addDays((int) ($this->opcoes['dias_volta'] ?? 7));
        $porDia    = [];
        $jogos     = [];
        $programar = function (Carbon $data, object $casa, object $visitante, array $extra) use (&$porDia, &$jogos) {
            $k = $porDia[$data->toDateString()] = ($porDia[$data->toDateString()] ?? -1) + 1;
            $jogos[] = ['data' => $data, 'casa' => $casa, 'visitante' => $visitante, ...$this->horarioDoJogo($k),
                'acj' => $this->temTimeDaAcj($casa, $visitante), ...$extra];
        };
        foreach ($confrontos as $i => $c) {
            if ($idaEVolta) {
                $programar($dataIda, $c['segundo'], $c['primeiro'], ['colunas' => ['fase_jogo' => $c['fase']], 'chave' => "ida{$i}", 'perna' => 'ida']);
                $programar($dataVolta, $c['primeiro'], $c['segundo'], ['colunas' => ['fase_jogo' => $c['fase']], 'volta_de' => "ida{$i}", 'perna' => 'volta']);
            } else {
                $programar($dataIda, $c['primeiro'], $c['segundo'], ['colunas' => ['fase_jogo' => $c['fase']], 'perna' => null]);
            }
        }

        $avisos = $origem['avisos'];
        $ultima = collect($jogos)->max('data');
        if ($ultima && $ultima->gt($this->campeonato->data_fim_campeonato)) {
            $avisos[] = 'O último jogo (' . $ultima->format('d/m/Y') . ') passa da data de fim do campeonato ('
                . $this->campeonato->data_fim_campeonato->format('d/m/Y') . ').';
        }
        if ($jogos && $dataIda->lt(today())) {
            $avisos[] = 'A data (' . $dataIda->format('d/m/Y') . ') já passou: os jogos de datas passadas nascem sem aviso aos atletas.';
        }
        $avisos = [...$avisos, ...$this->avisosDeTimesNoMesmoDia($jogos)];

        $todos = collect($jogos);

        return $this->previa = [
            'fase'       => $this->fase(),
            'confrontos' => $confrontos,
            'jogos'      => $jogos,
            'totais'     => [
                'times'      => count($times),
                'confrontos' => count($confrontos),
                'jogos'      => $todos->count(),
                'rascunhos'  => $todos->where('acj', true)->count(),
                'publicados' => $todos->where('acj', false)->count(),
            ],
            'avisos'     => $avisos,
            'conflitos'  => $this->conflitosDosJogos($jogos),
        ];
    }

    /**
     * Grava tudo numa transação (tudo ou nada). Com conflito real de horário dos convocados e sem $confirmar, não
     * grava nada. Devolve ['gravou' => bool, ...totais].
     */
    public function gerar(?int $idUsuario, bool $confirmar = false): array
    {
        $previa = $this->previa();
        $resultado = ['gravou' => false, ...$previa['totais']];

        if ($this->erros() || ($previa['conflitos']['totais']['reais'] > 0 && ! $confirmar)) {
            return $resultado;
        }

        $this->gravarJogos($previa['jogos'], $idUsuario);

        return [...$resultado, 'gravou' => true];
    }

    // ── Origem: tabela ou vencedores ────────────────────────────────────────

    // Os classificados da tabela: tabela única, 1º × último; com grupos, o cruzamento clássico
    private function daTabela(): array
    {
        $vazio = ['tipo' => 'tabela', 'fase_anterior' => null, 'times' => [], 'pares' => [], 'perdedores' => [], 'erros' => [], 'avisos' => []];
        $categoria = collect($this->campeonato->classificacoes())->first(fn ($c) => (int) $c['categoria']->id_categoria === (int) $this->categoria->id_categoria);
        $passam = $categoria['classificados'] ?? null;

        if (! $passam) {
            return [...$vazio, 'erros' => ["Defina quantos passam por grupo (chave) no {$this->categoria->rotulo}, na tela Grupos e classificação."]];
        }

        $tabelas = collect($categoria['tabelas']);
        if ($tabelas->contains('grupo', 'Sem grupo')) {
            return [...$vazio, 'erros' => ['Há times sem grupo (chave): ponha todos num grupo antes de montar o mata-mata.']];
        }
        if ($curto = $tabelas->first(fn ($t) => count($t['tabela']) < $passam)) {
            return [...$vazio, 'erros' => [($curto['grupo'] ?? 'A tabela') . ' tem ' . count($curto['tabela']) . " time(s) e passam {$passam}."]];
        }

        // Campanha de cada classificado: posição no grupo e, no empate de posição, a tabela (pontos, vitórias…)
        $times = [];
        $porGrupo = [];
        foreach ($tabelas as $tabela) {
            foreach (array_slice($tabela['tabela'], 0, $passam) as $pos => $linha) {
                $time = (object) ['id_time' => $linha['id_time'], 'nome_time' => $linha['nome'],
                    'rotulo' => ($pos + 1) . 'º' . ($tabela['grupo'] ? ' ' . $tabela['grupo'] : ''),
                    'campanha' => [$pos, -$linha['pontos'], -$linha['v'], -($linha['gm'] - $linha['gc']), -$linha['gm']]];
                $times[$time->id_time] = $time;
                $porGrupo[$tabela['grupo'] ?? ''][] = $time;
            }
        }

        $melhorPrimeiro = fn (object $a, object $b) => $a->campanha <= $b->campanha ? [$a->id_time, $b->id_time] : [$b->id_time, $a->id_time];
        $pares = [];
        $grupos = array_values($porGrupo);
        if (count($grupos) === 1) {
            $lista = $grupos[0];
            for ($i = 0, $n = count($lista); $i < intdiv($n, 2); $i++) {
                $pares[] = $melhorPrimeiro($lista[$i], $lista[$n - 1 - $i]);
            }
        } else {
            // Grupos em pares (A e B, C e D…): kº de um × (N+1-k)º do outro, nos dois sentidos
            for ($g = 0; $g + 1 < count($grupos); $g += 2) {
                [$um, $outro] = [$grupos[$g], $grupos[$g + 1]];
                for ($k = 0; $k < intdiv($passam + 1, 2); $k++) {
                    $espelho = $passam - 1 - $k;
                    $pares[] = $melhorPrimeiro($um[$k], $outro[$espelho]);
                    if ($k !== $espelho) {
                        $pares[] = $melhorPrimeiro($outro[$k], $um[$espelho]);
                    }
                }
            }
        }

        $semPlacar = Jogo::where('id_campeonato', $this->campeonato->id_campeonato)
            ->where(fn ($q) => $q->where('fase_jogo', Jogo::FASE_PADRAO)->orWhereNull('fase_jogo'))
            ->where(fn ($q) => $q->whereNull('placar_time_casa_jogos')->orWhereNull('placar_time_visitante_jogos'))
            ->whereHas('evento', fn ($q) => $q->where('id_categoria', $this->categoria->id_categoria)->where('status_evento_calendario', 'ATIVO'))
            ->count();

        return [...$vazio, 'times' => $times, 'pares' => $pares,
            'avisos' => $semPlacar ? ["Ainda há {$semPlacar} jogo(s) da fase de grupos sem placar: a classificação pode mudar."] : []];
    }

    // Os vencedores da última fase do mata-mata, pela ordem dos confrontos (o do jogo 1 × o do jogo 2)
    private function dosVencedores(Collection $mataMata): array
    {
        $ultima = collect(self::CHAVE)->last(fn ($fase) => $mataMata->contains('fase_jogo', $fase));
        $vazio  = ['tipo' => 'vencedores', 'fase_anterior' => $ultima, 'times' => [], 'pares' => [], 'perdedores' => [], 'erros' => [], 'avisos' => []];

        if ($ultima === 'FINAL') {
            return [...$vazio, 'erros' => ["O {$this->categoria->rotulo} já tem a final."]];
        }

        // Confronto = o jogo que decide (jogo único ou volta); a ida decide na volta
        $decisivos = $mataMata->where('fase_jogo', $ultima)->filter(fn ($jogo) => ! $jogo->jogoVolta)->values();
        $sem = $decisivos->filter(fn ($jogo) => ! $jogo->resultadoDoConfronto());
        if ($sem->isNotEmpty()) {
            return [...$vazio, 'erros' => [Jogo::FASES[$ultima] . ": {$sem->count()} confronto(s) sem vencedor ("
                . $sem->map(fn ($j) => $j->evento->titulo_evento_calendario)->implode(', ')
                . '). Lance os placares, a prorrogação ou os pênaltis.']];
        }

        $campanha = $this->campanhaDaFaseDeGrupos();
        $times = [];
        $perdedores = [];
        foreach ($decisivos as $jogo) {
            $vencedor = (int) $jogo->resultadoDoConfronto()['id_time'];
            $perdeu   = $vencedor === (int) $jogo->id_time_casa ? $jogo->timeVisitante : $jogo->timeCasa;
            $venceu   = $vencedor === (int) $jogo->id_time_casa ? $jogo->timeCasa : $jogo->timeVisitante;
            $titulo   = $jogo->id_jogo_ida ? $jogo->jogoIda->evento?->titulo_evento_calendario ?? $jogo->evento->titulo_evento_calendario
                : $jogo->evento->titulo_evento_calendario;
            $times[$vencedor] = (object) ['id_time' => $vencedor, 'nome_time' => $venceu->nome_time,
                'rotulo' => 'Venceu ' . $titulo, 'campanha' => $campanha[$vencedor] ?? [99]];
            $perdedores[] = (object) ['id_time' => (int) $perdeu->id_time, 'nome_time' => $perdeu->nome_time,
                'rotulo' => 'Perdeu ' . $titulo, 'campanha' => $campanha[(int) $perdeu->id_time] ?? [99]];
        }

        $lista = array_values($times);
        $melhorPrimeiro = fn (object $a, object $b) => $a->campanha <= $b->campanha ? [$a->id_time, $b->id_time] : [$b->id_time, $a->id_time];
        $pares = [];
        for ($i = 0; $i + 1 < count($lista); $i += 2) {
            $pares[] = $melhorPrimeiro($lista[$i], $lista[$i + 1]);
        }
        if (count($perdedores) === 2 && $perdedores[1]->campanha < $perdedores[0]->campanha) {
            $perdedores = array_reverse($perdedores);
        }

        return [...$vazio, 'times' => $times, 'pares' => $pares, 'perdedores' => $perdedores];
    }

    // [id_time => campanha] na fase de grupos (posição no grupo, pontos, vitórias, saldo, gols), para o mando
    private function campanhaDaFaseDeGrupos(): array
    {
        $categoria = collect($this->campeonato->classificacoes())->first(fn ($c) => (int) $c['categoria']->id_categoria === (int) $this->categoria->id_categoria);
        $campanha = [];
        foreach ($categoria['tabelas'] ?? [] as $tabela) {
            foreach ($tabela['tabela'] as $pos => $linha) {
                $campanha[(int) $linha['id_time']] = [$pos, -$linha['pontos'], -$linha['v'], -($linha['gm'] - $linha['gc']), -$linha['gm']];
            }
        }

        return $campanha;
    }

    // Jogos da categoria já cadastrados numa fase (fora os ocultos)
    private function jogosNaFase(string $fase): int
    {
        return Jogo::where('id_campeonato', $this->campeonato->id_campeonato)->where('fase_jogo', $fase)
            ->whereHas('evento', fn ($q) => $q->where('id_categoria', $this->categoria->id_categoria)
                ->where('status_evento_calendario', '<>', 'INATIVO'))
            ->count();
    }
}
