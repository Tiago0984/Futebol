<?php

namespace App\Services;

use App\Models\Campeonato;
use App\Support\CentralDoCampeonato;
use Illuminate\Support\Collection;

/**
 * Visão geral da central do campeonato (Fase 3 do padrão das telas, 30/10/2026): em que pé está cada categoria,
 * em seis passos na ordem do trabalho (times, convocação, grupos, jogos, publicação e placares, campeão), cada um
 * com a situação, um texto curto e o link para a tela onde se resolve. O "próximo passo" de uma categoria é o
 * primeiro pendente. Só lê: as regras de verdade continuam nas telas (o gerador recusa, o encerramento confere).
 *
 * Situações: feito, pendente (falta algo do admin), andamento (depende dos jogos acontecerem), opcional
 * (pode ficar como está) e bloqueado (depende de um passo anterior).
 */
class SituacaoDoCampeonato
{
    public const PROXIMOS_JOGOS = 5;

    private Collection $jogos;

    public function __construct(private Campeonato $campeonato)
    {
        // Jogos não ocultos, com o evento (data, status, publicação e categoria) e os times
        $this->jogos = $campeonato->jogos()
            ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', '<>', 'INATIVO'))
            ->with(['evento.categoria', 'timeCasa', 'timeVisitante', 'jogoIda', 'jogoVolta'])
            ->get();
    }

    /**
     * Uma entrada por categoria do campeonato: ['categoria', 'etapas' => [chave => etapa], 'proximo' => etapa|null].
     * Etapa: ['titulo', 'situacao', 'detalhe', 'url', 'acao'] (acao = texto do botão).
     */
    public function porCategoria(): array
    {
        $c = $this->campeonato;
        $participacoes  = $c->participacoes()->groupBy(fn ($p) => (int) $p->categoria->id_categoria);
        $grupos         = $c->grupos()->get()->groupBy(fn ($g) => (int) $g->id_categoria);
        $semGrupo       = $c->participantesComGrupo()->whereNull('id_campeonato_grupo')->groupBy(fn ($p) => (int) $p->id_categoria);
        $classificacoes = collect($c->classificacoes())->keyBy(fn ($x) => (int) $x['categoria']->id_categoria);
        $campeoes       = $c->campeoes($classificacoes->all());
        $porCategoria   = $this->jogos->filter(fn ($j) => $j->evento->status_evento_calendario === 'ATIVO')
            ->groupBy(fn ($j) => (int) $j->evento->id_categoria);

        $resultado = [];
        foreach ($classificacoes as $id => $classificacao) {
            $etapas = [
                'participantes' => $this->participantes($participacoes[$id] ?? collect(), $id),
                'convocacao'    => $this->convocacao($participacoes[$id] ?? collect(), $id),
                'grupos'        => $this->grupos($grupos[$id] ?? collect(), $semGrupo[$id] ?? collect(), $classificacao, $id),
            ];
            $jogos = $porCategoria[$id] ?? collect();
            $etapas['jogos']      = $this->jogosDaFaseDeGrupos($jogos, ($participacoes[$id] ?? collect())->count(), $id);
            $etapas['resultados'] = $this->resultados($jogos);
            $etapas['campeao']    = $this->campeao($jogos, $classificacao, $campeoes[$id] ?? null, $id);

            $resultado[] = [
                'categoria' => $classificacao['categoria'],
                'etapas'    => $etapas,
                'proximo'   => collect($etapas)->first(fn ($e) => $e['situacao'] === 'pendente'),
            ];
        }

        return $resultado;
    }

    // "Precisa da sua atenção": os passos pendentes de todas as categorias, cada um com o nome da categoria
    public static function pendencias(array $porCategoria): array
    {
        $pendencias = [];
        foreach ($porCategoria as $linha) {
            foreach ($linha['etapas'] as $etapa) {
                if ($etapa['situacao'] === 'pendente') {
                    $pendencias[] = [...$etapa, 'categoria' => $linha['categoria']->rotulo];
                }
            }
        }

        return $pendencias;
    }

    // Próximos jogos (ativos e ainda não concluídos), do mais perto ao mais longe
    public function proximosJogos(): Collection
    {
        return $this->jogos
            ->filter(fn ($j) => $j->evento->status_evento_calendario === 'ATIVO' && ! $j->evento->estaConcluido())
            ->sortBy(fn ($j) => $j->evento->dataHoraDoJogo())
            ->take(self::PROXIMOS_JOGOS)
            ->values();
    }

    // ── Passos ─────────────────────────────────────────────────────────────

    private function participantes(Collection $participacoes, int $id): array
    {
        $total = $participacoes->count();

        return $total >= 2
            ? $this->etapa('Times participantes', 'feito', "{$total} times", $this->url('participantes', $id), 'Ver')
            : $this->etapa('Times participantes', 'pendente',
                ($total ? 'Só 1 time' : 'Nenhum time') . ': são precisos 2 ou mais para ter jogos',
                route('admin.campeonatos.edit', [$this->campeonato->id_campeonato, 'voltar' => 'central']), 'Incluir times');
    }

    private function convocacao(Collection $participacoes, int $id): array
    {
        $internos = $participacoes->filter(fn ($p) => $p->time->tipo_time === 'INTERNO');
        if ($internos->isEmpty()) {
            return $this->etapa('Convocação da AACJ', 'opcional', 'Nenhum time da AACJ nesta categoria', $this->url('participantes', $id), 'Ver');
        }

        $semConvocados = $internos->where('total', 0);
        if ($semConvocados->isNotEmpty()) {
            $primeiro = $semConvocados->first();

            return $this->etapa('Convocação da AACJ', 'pendente', 'Sem convocados: ' . $semConvocados->pluck('time.nome_time')->implode(', '),
                route('admin.campeonatos.convocados', [$this->campeonato->id_campeonato, $primeiro->time->id_time, 'categoria' => $id]), 'Convocar');
        }

        return $this->etapa('Convocação da AACJ', 'feito', $internos->sum('total') . ' atleta(s) convocado(s)', $this->url('participantes', $id), 'Ver');
    }

    private function grupos(Collection $grupos, Collection $semGrupo, array $classificacao, int $id): array
    {
        $url = $this->url('grupos', $id);
        if ($grupos->isEmpty()) {
            return $this->etapa('Grupos (chaves)', 'opcional', 'Tabela única, sem grupos', $url, 'Ver');
        }
        if ($semGrupo->isNotEmpty()) {
            return $this->etapa('Grupos (chaves)', 'pendente', $semGrupo->count() . ' time(s) ainda sem grupo (chave)', $url, 'Definir grupos');
        }

        $passam = $classificacao['classificados'] ? " · passam {$classificacao['classificados']} de cada" : '';

        return $this->etapa('Grupos (chaves)', 'feito', $grupos->count() . ' grupo(s)' . $passam, $url, 'Ver');
    }

    private function jogosDaFaseDeGrupos(Collection $jogos, int $participantes, int $id): array
    {
        $daFase = $jogos->filter->contaNaClassificacao();
        if ($daFase->isNotEmpty()) {
            return $this->etapa('Jogos da fase de grupos', 'feito', $daFase->count() . ' jogo(s) marcado(s)', $this->url('jogos'), 'Ver');
        }
        if ($participantes < 2) {
            return $this->etapa('Jogos da fase de grupos', 'bloqueado', 'Depois de incluir os times', null, null);
        }
        if ($jogos->isNotEmpty()) {
            return $this->etapa('Jogos da fase de grupos', 'opcional', 'Sem fase de grupos: só mata-mata', $this->url('mata-mata', $id), 'Ver');
        }

        return $this->etapa('Jogos da fase de grupos', 'pendente', 'Nenhum jogo marcado',
            route('admin.campeonatos.gerar-jogos', [$this->campeonato->id_campeonato, $id]), 'Gerar jogos');
    }

    private function resultados(Collection $jogos): array
    {
        if ($jogos->isEmpty()) {
            return $this->etapa('Publicação e placares', 'bloqueado', 'Depois de marcar os jogos', null, null);
        }

        $rascunhos = $jogos->reject(fn ($j) => $j->evento->estaPublicado())->count();
        $semPlacar = $jogos->filter(fn ($j) => $j->evento->estaConcluido() && ! $j->temPlacar())->count();
        if ($rascunhos || $semPlacar) {
            $partes = array_filter([
                $rascunhos ? "{$rascunhos} em rascunho (atletas ainda não avisados)" : null,
                $semPlacar ? "{$semPlacar} já aconteceu(ram) sem placar" : null,
            ]);

            return $this->etapa('Publicação e placares', 'pendente', implode(' · ', $partes), $this->url('jogos'),
                $rascunhos ? 'Publicar' : 'Lançar placares');
        }

        $comPlacar = $jogos->filter->temPlacar()->count();

        return $comPlacar === $jogos->count()
            ? $this->etapa('Publicação e placares', 'feito', "Todos os {$comPlacar} jogo(s) com placar", $this->url('jogos'), 'Ver')
            : $this->etapa('Publicação e placares', 'andamento', "{$comPlacar} de {$jogos->count()} jogo(s) com placar", $this->url('jogos'), 'Ver');
    }

    private function campeao(Collection $jogos, array $classificacao, ?array $campeao, int $id): array
    {
        $url = $this->url('mata-mata', $id);
        if ($campeao && $campeao['situacao'] === 'campeao') {
            return $this->etapa('Campeão', 'feito', 'Campeão: ' . $campeao['campeao']['nome'], $url, 'Ver');
        }
        if ($campeao) {
            return $this->etapa('Campeão', 'pendente', 'Sorteio entre ' . collect($campeao['empatados'])->join(', ', ' e '), $url, 'Ver');
        }
        if ($jogos->isEmpty()) {
            return $this->etapa('Campeão', 'bloqueado', 'Depois dos jogos', null, null);
        }

        // Com mata-mata (jogos de outra fase ou "classificam N"): a fase em andamento terminou e ainda não há final?
        $doMataMata = $jogos->filter->ehMataMata();
        if ($doMataMata->isNotEmpty() || $classificacao['classificados'] !== null) {
            $faseAtual = $doMataMata->isNotEmpty() ? $doMataMata : $jogos->filter->contaNaClassificacao();
            $terminou  = $faseAtual->isNotEmpty() && $faseAtual->every(fn ($j) => $j->temPlacar() && $j->evento->estaPublicado());

            // Confronto do mata-mata jogado e sem vencedor (empate sem prorrogação e pênaltis): o montador recusaria
            $semVencedor = $doMataMata->filter(fn ($j) => $j->temPlacar() && ! $j->jogoVolta && ! $j->resultadoDoConfronto())->count();
            if ($semVencedor) {
                return $this->etapa('Campeão', 'pendente', "{$semVencedor} confronto(s) do mata-mata sem vencedor: lance a prorrogação e os pênaltis",
                    $url, 'Ver confrontos');
            }
            if ($terminou && ! $doMataMata->contains('fase_jogo', 'FINAL')) {
                return $this->etapa('Campeão', 'pendente', 'A fase terminou: monte a próxima do mata-mata',
                    route('admin.campeonatos.proxima-fase', [$this->campeonato->id_campeonato, $id]), 'Montar próxima fase');
            }

            return $this->etapa('Campeão', 'andamento',
                $doMataMata->contains('fase_jogo', 'FINAL') ? 'Final marcada: o campeão sai dela' : 'O campeão sai da final do mata-mata', $url, 'Ver');
        }

        // Pontos corridos: o 1º da tabela, quando o admin encerra a categoria
        if (! $this->campeonato->erroParaEncerrar($classificacao)) {
            return $this->etapa('Campeão', 'pendente', 'Todos os jogos com placar: encerre a categoria para declarar o campeão', $url, 'Encerrar categoria');
        }

        return $this->etapa('Campeão', 'andamento', 'Pontos corridos: o 1º da tabela, ao encerrar a categoria', $url, 'Ver');
    }

    private function etapa(string $titulo, string $situacao, string $detalhe, ?string $url, ?string $acao): array
    {
        return compact('titulo', 'situacao', 'detalhe', 'url', 'acao');
    }

    private function url(string $aba, ?int $idCategoria = null): string
    {
        return CentralDoCampeonato::url($this->campeonato, $aba, $idCategoria);
    }
}
