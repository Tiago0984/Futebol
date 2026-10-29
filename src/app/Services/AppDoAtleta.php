<?php

namespace App\Services;

use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\EventoAtleta;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\JogoGol;
use App\Models\Notificacao;
use App\Models\NotificacaoLeitura;
use App\Models\Responsavel;
use App\Models\Time;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * O que o app mostra de um atleta (Fase 9): dados, agenda e avisos. Uma classe só para os dois perfis:
 * o atleta vê os dele; o responsável vê os do filho, em modo leitura, com a própria marca de leitura
 * dos avisos (tbl_notificacao_leitura). Quem chama já conferiu o acesso (atleta ATIVO e, no responsável,
 * filho dele: ver AppDoAtletaController).
 */
class AppDoAtleta
{
    public const POR_PAGINA = 20;
    public const PASSADOS = 3;

    // Formato das datas do JSON (o mesmo do trait SerializaDatasComFuso)
    private const FORMATO_DATA = 'Y-m-d\TH:i:sP';

    /**
     * @param ?Responsavel $responsavel quem lê, quando não é o próprio atleta
     */
    public function __construct(
        private Atleta $atleta,
        private ?Responsavel $responsavel = null,
    ) {
    }

    // ── Dados ───────────────────────────────────────────────────────────────

    // GET /atleta (e o mesmo para o responsável): só a categoria atual; as chaves do JSON não mudam.
    // "times" fica sempre vazio (Fase G, Etapa 4: sem elenco fixo; o time de cada jogo vem na agenda, em
    // time_do_atleta), para não quebrar o app. Posições (Fase D): "posicoes" (até 3, a principal primeiro, com
    // código, sigla e nome) e "posicao_atleta" com o código da principal (antes, texto livre), também para o app
    // "foto_url": endereço completo da foto (null sem foto; o banco guarda o caminho do arquivo). "foto_pendente"
    // (29/10/2026): true quando o atleta enviou uma foto pelo app e a secretaria ainda não aprovou (foto_url é a atual)
    public function dados(): Atleta
    {
        $atleta = $this->atleta->load([
            'endereco',
            'categorias' => fn ($q) => $q->select('tbl_categoria.id_categoria', 'nome_categoria')
                ->wherePivot('status_categoria_atleta', Atleta::CATEGORIA_ATIVA),
            'posicoes',
        ])->setRelation('times', collect());

        // Responsáveis (21/10/2026, para o app parar de mostrar um nome fixo): só o nome e o grau de parentesco
        // (decisão do dono do projeto; CPF, e-mail e telefone são dados de outra pessoa e ficam fora)
        $responsaveis = $atleta->responsaveis()->orderBy('nome_responsavel')->get()
            ->map(fn ($r) => ['nome' => $r->nome_responsavel, 'grau_parentesco' => $r->pivot->grau_parentesco_responsavel])
            ->values()->all();

        return $atleta->setAttribute('posicao_atleta', $atleta->posicoes->first()?->posicao)
            ->setAttribute('foto_url', $atleta->urlFoto())
            ->setAttribute('foto_pendente', $atleta->temFotoPendente())
            ->setAttribute('responsaveis', $responsaveis);
    }

    // ── Estatísticas ────────────────────────────────────────────────────────

    /**
     * GET /atleta/estatisticas (22/10/2026): os números do atleta desde que entrou na escolinha, no total e por
     * time, só com o que o sistema registra:
     * - partidas: jogos (campeonato ou amistoso) em que foi ESCALADO num time, publicados, ativos e já realizados
     *   (inscrito sem time não conta: foi chamado, mas não se sabe se jogou);
     * - convocações: uma por campeonato e categoria (inclusive de quando estava inativo: é histórico);
     * - cartões: tbl_cartoes (lançados na súmula do jogo), no time da escalação daquele jogo;
     * - gols e assistências (Etapa S2, 24/10/2026): da súmula, só nos jogos que contam como partida e que têm
     *   súmula (algum gol lançado, de qualquer time). Gol contra não conta para o autor. null, e não 0, quando
     *   nenhum jogo do recorte (total, time ou categoria) tem súmula: o app mostra "—" ("ainda não registrado").
     *
     * "categoria_atual" (22/10/2026, pedido do app): os mesmos números, só os da categoria atual do atleta (linha
     * ATIVO de tbl_categoria_atleta; null sem ela). Conta a categoria em que cada jogo foi jogado (a do jogo, no
     * evento; no amistoso sem categoria, a do time da escalação, se ele tem uma só) e a da convocação: o Sub-13 que
     * sobe para jogar no Sub-15 tem essas partidas no total, não no Sub-13.
     */
    public function estatisticas(): array
    {
        $idAtleta = $this->atleta->id_atleta;

        // Time da escalação em cada jogo do atleta (id_evento => id_time), para as partidas e os cartões
        $escalacoes = EventoAtleta::where('id_atleta', $idAtleta)->whereNotNull('id_time')
            ->whereHas('evento.jogo')
            ->get(['id_evento_calendario', 'id_time'])
            ->pluck('id_time', 'id_evento_calendario');

        $idsJogados = EventoCalendario::whereIn('id_evento_calendario', $escalacoes->keys())
            ->publicados()->where('status_evento_calendario', 'ATIVO')->concluidos()
            ->pluck('id_evento_calendario');
        $partidas = $escalacoes->only($idsJogados->all())->countBy()->all();

        $convocacoes = $this->atleta->convocacoes()->get(['id_time', 'id_categoria']);

        // Cartão no time em que o atleta estava escalado naquele jogo (sem escalação, só no total)
        $eventoDoJogo = Jogo::whereIn('id_jogo', $this->atleta->cartoes()->pluck('id_jogo'))->pluck('id_evento', 'id_jogo');
        $cartoes = $this->atleta->cartoes()->get(['id_jogo', 'tipo_cartao'])
            ->map(fn ($c) => [
                'tipo'      => $c->tipo_cartao,
                'id_evento' => $eventoDoJogo[$c->id_jogo] ?? null,
                'id_time'   => $escalacoes[$eventoDoJogo[$c->id_jogo] ?? 0] ?? null,
            ]);

        $sumula = $this->sumulaDosJogos($escalacoes->only($idsJogados->all()));

        // $sumulaDoRecorte: as linhas de sumulaDosJogos() dos jogos do recorte (vazio = nenhum com súmula: null)
        $numeros = fn (int $partidas, int $convocacoes, $cartoesDoTime, $sumulaDoRecorte) => [
            'partidas'          => $partidas,
            'convocacoes'       => $convocacoes,
            'cartoes_amarelos'  => $cartoesDoTime->where('tipo', 'AMARELO')->count(),
            'cartoes_vermelhos' => $cartoesDoTime->where('tipo', 'VERMELHO')->count(),
            'gols'              => $sumulaDoRecorte->isEmpty() ? null : $sumulaDoRecorte->sum('gols'),
            'assistencias'      => $sumulaDoRecorte->isEmpty() ? null : $sumulaDoRecorte->sum('assistencias'),
        ];

        $convocacoesPorTime = $convocacoes->pluck('id_time')->countBy()->all();
        $idsTimes = collect(array_keys($partidas))->merge(array_keys($convocacoesPorTime))
            ->merge($cartoes->pluck('id_time')->filter())->unique();
        // categoria: as categorias do cadastro do time, num texto ("Sub-13 Masculino, Sub-15 Masculino"; várias desde
        // 27/10/2026, quando a AACJ passou a ter um cadastro só); null sem nenhuma
        $porTime = Time::with('categorias')->whereIn('id_time', $idsTimes)->get()
            ->map(fn (Time $time) => [
                'id_time'   => $time->id_time,
                'nome_time' => $time->nome_time,
                'logo_url'  => $time->logo_url,
                'categoria' => $time->categorias->pluck('rotulo')->implode(', ') ?: null,
            ] + $numeros($partidas[$time->id_time] ?? 0, $convocacoesPorTime[$time->id_time] ?? 0, $cartoes->where('id_time', $time->id_time),
                $sumula->where('id_time', $time->id_time)))
            ->sortBy([['partidas', 'desc'], ['convocacoes', 'desc'], ['nome_time', 'asc']])
            ->values()->all();

        return [
            'total'           => $numeros(array_sum($partidas), $convocacoes->count(), $cartoes, $sumula),
            'por_time'        => $porTime,
            'categoria_atual' => $this->naCategoriaAtual($escalacoes->only($idsJogados->all()), $convocacoes, $cartoes, $sumula, $numeros),
        ];
    }

    /**
     * Súmula dos jogos que contam como partida e têm algum gol lançado (de qualquer time): id_evento =>
     * ['id_time' (da escalação), 'gols' (do atleta, sem os contra), 'assistencias']. Jogo sem súmula fica de fora
     * (o placar dele pode ter gols do atleta que ninguém lançou). Duas consultas, qualquer que seja o número de jogos.
     *
     * @param \Illuminate\Support\Collection $jogados id_evento => id_time
     */
    private function sumulaDosJogos($jogados)
    {
        $idAtleta = $this->atleta->id_atleta;
        $eventoDoJogo = Jogo::whereIn('id_evento', $jogados->keys())->pluck('id_evento', 'id_jogo');
        $gols = JogoGol::whereIn('id_jogo', $eventoDoJogo->keys())->get(['id_jogo', 'id_atleta', 'id_atleta_assistencia', 'tipo_jogo_gol'])
            ->groupBy('id_jogo');

        return $gols->mapWithKeys(function ($doJogo, $idJogo) use ($eventoDoJogo, $jogados, $idAtleta) {
            $validos = $doJogo->reject(fn (JogoGol $gol) => $gol->ehContra());
            $idEvento = $eventoDoJogo[$idJogo];

            return [$idEvento => [
                'id_time'      => $jogados[$idEvento],
                'gols'         => $validos->where('id_atleta', $idAtleta)->count(),
                'assistencias' => $validos->where('id_atleta_assistencia', $idAtleta)->count(),
            ]];
        });
    }

    /**
     * Os números da categoria atual (ver estatisticas()). Uma só: trocarCategoria() encerra a linha ATIVO antes de
     * abrir outra, e a data de fim é conferida como no app (linha ATIVO sem data_fim).
     *
     * @param \Illuminate\Support\Collection $jogados id_evento => id_time, só os jogos que contam como partida
     */
    private function naCategoriaAtual($jogados, $convocacoes, $cartoes, $sumula, callable $numeros): ?array
    {
        $categoria = $this->atleta->categoriasAtivas()->wherePivotNull('data_fim_categoria_atleta')->first();
        if (! $categoria) {
            return null;
        }

        // Categoria em que cada jogo foi jogado: a do jogo (evento) ou, no amistoso sem categoria, a do time quando
        // ele tem uma só no cadastro (com várias, não dá para saber: o jogo fica só no total)
        $categoriaDoEvento = EventoCalendario::whereIn('id_evento_calendario', $jogados->keys()->merge($cartoes->pluck('id_evento')->filter()))
            ->pluck('id_categoria', 'id_evento_calendario');
        $categoriaDoTime = Time::with('categorias')->whereIn('id_time', $jogados->merge($cartoes->pluck('id_time'))->filter()->unique())->get()
            ->mapWithKeys(fn (Time $time) => [$time->id_time => $time->categorias->count() === 1 ? $time->categorias->first()->id_categoria : null]);
        $nela = fn ($idEvento, $idTime) => (int) ($categoriaDoEvento[$idEvento] ?? $categoriaDoTime[$idTime] ?? 0) === (int) $categoria->id_categoria;

        return [
            'id_categoria'   => $categoria->id_categoria,
            'nome_categoria' => $categoria->nome_categoria,
            'categoria'      => $categoria->rotulo,
        ] + $numeros(
            $jogados->filter(fn ($idTime, $idEvento) => $nela($idEvento, $idTime))->count(),
            $convocacoes->where('id_categoria', $categoria->id_categoria)->count(),
            $cartoes->filter(fn ($c) => $nela($c['id_evento'], $c['id_time'])),
            $sumula->filter(fn ($s, $idEvento) => $nela($idEvento, $s['id_time']))
        );
    }

    // ── Campeonatos do atleta ───────────────────────────────────────────────

    /**
     * GET /campeonatos-do-atleta (26/10/2026, carrossel da Home do app): os campeonatos ativos e ainda não
     * terminados (data de fim hoje ou depois) de que o atleta participa (decisão do dono do projeto):
     * - convocado no campeonato, em qualquer categoria (cobre quem joga numa categoria acima); ou
     * - a categoria atual dele está no campeonato com um time da AACJ (INTERNO) participante, antes da convocação.
     * "categorias": só as dele no campeonato (as das convocações e a atual, pela segunda regra); "convocacoes":
     * categoria e camisa de cada convocação, sem o time. Em andamento primeiro (do que termina antes), depois os
     * próximos pela data de início.
     */
    public function campeonatos(): array
    {
        $hoje = now()->toDateString();
        $convocacoes = $this->atleta->convocacoes()->with('categoria')->get()->groupBy('id_campeonato');

        $idCategoriaAtual = $this->atleta->categoriasAtivas()->wherePivotNull('data_fim_categoria_atleta')->value('tbl_categoria.id_categoria');
        // A FK composta do participante já garante que a categoria é do campeonato
        $pelaCategoria = $idCategoriaAtual === null ? collect() : DB::table('tbl_campeonato_time as ct')
            ->join('tbl_time as t', 't.id_time', '=', 'ct.id_time')
            ->where('ct.id_categoria', $idCategoriaAtual)->where('t.tipo_time', 'INTERNO')
            ->distinct()->pluck('ct.id_campeonato')->map(fn ($id) => (int) $id);

        return Campeonato::with('categorias')->ativos()
            ->whereIn('id_campeonato', $convocacoes->keys()->merge($pelaCategoria)->unique()->values())
            ->whereDate('data_fim_campeonato', '>=', $hoje)
            ->get()
            ->sortBy(fn (Campeonato $c) => [
                $c->data_inicio_campeonato->toDateString() <= $hoje ? 0 : 1,
                $c->data_inicio_campeonato->toDateString() <= $hoje ? $c->data_fim_campeonato->toDateString() : $c->data_inicio_campeonato->toDateString(),
                $c->id_campeonato,
            ])
            ->map(function (Campeonato $campeonato) use ($convocacoes, $pelaCategoria, $idCategoriaAtual, $hoje) {
                $doCampeonato = $convocacoes->get($campeonato->id_campeonato, collect());
                $ids = $doCampeonato->pluck('id_categoria')->map(fn ($id) => (int) $id)
                    ->when($pelaCategoria->contains($campeonato->id_campeonato), fn ($ids) => $ids->push((int) $idCategoriaAtual))
                    ->unique()->all();

                return collect($campeonato->toArray())->only([
                    'id_campeonato', 'nome_campeonato', 'organizador_campeonato', 'descricao_campeonato', 'tipo_campeonato', 'tipo_label',
                    'logo_evento', 'logo_url', 'banner_evento', 'banner_url', 'data_inicio_campeonato', 'data_fim_campeonato', 'local_evento',
                ])->all() + [
                    'situacao'     => $campeonato->data_inicio_campeonato->toDateString() <= $hoje ? 'EM_ANDAMENTO' : 'PROXIMO',
                    'categorias'   => $campeonato->categoriasNaApi($ids),
                    'convocada'    => $doCampeonato->isNotEmpty(),
                    'convocacoes'  => $doCampeonato->sortBy(fn ($c) => [$c->categoria?->idade_max_categoria, $c->id_convocacao])
                        ->map(fn ($c) => [
                            'id_categoria' => (int) $c->id_categoria,
                            'rotulo'       => $c->categoria?->rotulo,
                            'camisa'       => $c->camisa_convocacao,
                        ])->values()->all(),
                ];
            })
            ->values()->all();
    }

    // ── Agenda ──────────────────────────────────────────────────────────────

    /**
     * Só eventos em que o atleta está inscrito, ATIVO ou CANCELADO (oculto fica de fora). "proximos": os
     * que ainda não terminaram, paginados, do mais perto ao mais longe (cancelado aparece com a situação
     * CANCELADO). "passados": os 3 últimos concluídos e não cancelados. Nunca "Alterado": a alteração chega
     * pelos avisos.
     *
     * Pedidos do app (24/10/2026): $tipos filtra próximos e passados (vazio = todos); "totais" conta os próximos
     * (todas as páginas) por tipo, sem o filtro de tipo, para os contadores dos filtros do app.
     *
     * @param string[] $tipos chaves de EventoCalendario::TIPOS
     */
    public function agenda(int $pagina = 1, array $tipos = []): array
    {
        $proximos = self::doMaisPerto($this->eventosInscritos($tipos)->naoConcluidos())
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina);

        $passados = $this->eventosInscritos($tipos)
            ->concluidos()
            ->where('status_evento_calendario', 'ATIVO')
            ->orderByDesc('data_evento_calendario')
            ->orderByRaw('horario_inicio_evento_calendario IS NULL DESC')
            ->orderByDesc('horario_inicio_evento_calendario')
            ->orderByDesc('id_evento_calendario')
            ->limit(self::PASSADOS)
            ->get();

        $times = $this->timesDoAtleta($proximos->getCollection()->concat($passados));

        return [
            'proximos'  => $proximos->getCollection()->map(fn ($evento) => $this->evento($evento, $times[$evento->id_evento_calendario] ?? null))->all(),
            'paginacao' => self::paginacao($proximos),
            'passados'  => $passados->map(fn ($evento) => $this->evento($evento, $times[$evento->id_evento_calendario] ?? null))->values()->all(),
            'totais'    => $this->totais($this->eventosInscritos()->naoConcluidos()),
        ];
    }

    /**
     * Agenda de um período (pedido do app, 24/10/2026, para navegar por semana e mês): todos os eventos de $de a
     * $ate (dias de Brasília), já concluídos ou não, ATIVO ou CANCELADO, do mais antigo ao mais novo, sem
     * paginação (o controller limita o tamanho do período). "totais" sem o filtro de tipo, como em agenda().
     */
    public function agendaDoPeriodo(Carbon $de, Carbon $ate, array $tipos = []): array
    {
        $noPeriodo = fn ($query) => $query->whereBetween('data_evento_calendario', [$de->toDateString(), $ate->toDateString()]);

        $eventos = self::doMaisPerto($noPeriodo($this->eventosInscritos($tipos)))->get();
        $times = $this->timesDoAtleta($eventos);

        return [
            'periodo' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
            'eventos' => $eventos->map(fn ($evento) => $this->evento($evento, $times[$evento->id_evento_calendario] ?? null))->values()->all(),
            'totais'  => $this->totais($noPeriodo($this->eventosInscritos())),
        ];
    }

    /**
     * Detalhe de um evento da agenda (GET /agenda/{idEvento}, pedido do app, 24/10/2026, para o "Ver detalhes"): os
     * mesmos campos da lista e, no jogo, também o adversário, a fase, a rodada, a perna, a prorrogação, os pênaltis
     * e o confronto. Só o que já é público ou do próprio atleta: sem os colegas, a escalação, a posição e o titular
     * (decisão do dono do projeto: dados de outros menores, e posição e titular ficam só no admin). null (404) para
     * evento em que o atleta não está inscrito, oculto ou em rascunho: as mesmas regras da lista.
     */
    public function detalhe(int $idEvento): ?array
    {
        $evento = $this->eventosInscritos()->with(['jogo.jogoIda', 'jogo.jogoVolta'])->find($idEvento);

        if (! $evento) {
            return null;
        }

        $idTime = $this->timesDoAtleta(collect([$evento]))[$evento->id_evento_calendario] ?? null;
        $dados = $this->evento($evento, $idTime);

        if ($evento->jogo) {
            $dados['jogo'] += $this->detalheDoJogo($evento->jogo, $idTime);
        }

        return $dados;
    }

    private function eventosInscritos(array $tipos = [])
    {
        return EventoCalendario::query()
            ->with(['categoria', 'comissaoTecnica', 'local', 'jogo.campeonato', 'jogo.timeCasa', 'jogo.timeVisitante'])
            ->publicados() // rascunho (jogo sendo montado) fica fora até publicar
            ->whereIn('status_evento_calendario', ['ATIVO', 'CANCELADO'])
            ->whereHas('inscricoes', fn ($q) => $q->where('id_atleta', $this->atleta->id_atleta))
            ->when($tipos, fn ($q) => $q->whereIn('tipo_evento_calendario', $tipos));
    }

    // Do mais perto ao mais longe; no mesmo dia, sem horário no fim
    private static function doMaisPerto($query)
    {
        return $query->orderBy('data_evento_calendario')
            ->orderByRaw('horario_inicio_evento_calendario IS NULL')
            ->orderBy('horario_inicio_evento_calendario')
            ->orderBy('id_evento_calendario');
    }

    // Time do atleta em cada jogo (escalação), numa consulta só: id_evento => id_time
    private function timesDoAtleta($eventos)
    {
        return EventoAtleta::where('id_atleta', $this->atleta->id_atleta)
            ->whereIn('id_evento_calendario', $eventos->pluck('id_evento_calendario'))
            ->pluck('id_time', 'id_evento_calendario');
    }

    // Quantos eventos de cada tipo (todos os tipos, com 0), numa consulta só
    private function totais($query): array
    {
        $porTipo = $query->toBase()->reorder()
            ->groupBy('tipo_evento_calendario')
            ->selectRaw('tipo_evento_calendario AS tipo, COUNT(*) AS quantos')
            ->pluck('quantos', 'tipo');

        return collect(EventoCalendario::TIPOS)->map(fn ($rotulo, $tipo) => (int) ($porTipo[$tipo] ?? 0))->all();
    }

    private function evento(EventoCalendario $evento, ?int $idTimeDoAtleta): array
    {
        $dia = $evento->data_evento_calendario->format('Y-m-d');
        $momento = fn (?string $hora) => $hora ? Carbon::parse("{$dia} {$hora}")->format(self::FORMATO_DATA) : null;
        $hora = fn (?string $hora) => $hora ? substr($hora, 0, 5) : null;

        return [
            'id_evento'      => $evento->id_evento_calendario,
            'titulo'         => $evento->titulo_evento_calendario,
            'tipo'           => $evento->tipo_evento_calendario,
            'tipo_label'     => $evento->tipo_label,
            'subtipo'        => $evento->subtipo_evento_calendario,
            'descricao'      => $evento->descricao_evento_calendario,
            'categoria'      => $evento->categoria?->rotulo,
            'data'           => Carbon::parse($dia)->format(self::FORMATO_DATA),
            'horario_inicio' => $hora($evento->horario_inicio_evento_calendario),
            'horario_fim'    => $hora($evento->horario_fim_evento_calendario),
            'inicio'         => $momento($evento->horario_inicio_evento_calendario),
            'fim'            => $momento($evento->horario_fim_evento_calendario),
            'local'          => $evento->local_evento_calendario,
            // Endereço e mapa do local cadastrado (Etapa L3; o desativado também, porque o evento continua lá); null com o
            // texto antigo ou sem local: o app mostra só o texto, sem o "Abrir mapa"
            'local_detalhe'  => $evento->local ? [
                'nome'     => $evento->local->nome_local,
                'endereco' => $evento->local->enderecoEmUmaLinha(),
                'mapa_url' => $evento->local->mapaUrl(),
            ] : null,
            // Quem conduz (não quem criou): o nome do profissional da comissão técnica, ou o texto antigo de antes da
            // Etapa C2, ou null; responsavel_id só com o profissional (inativo também: o perfil dele dá 404)
            'responsavel'    => $evento->nomeDoResponsavel(),
            'responsavel_id' => $evento->id_comissao_tecnica,
            'situacao'       => $evento->estaCancelado() ? 'CANCELADO' : 'CONFIRMADO',
            'concluido'      => $evento->estaConcluido(),
            'jogo'         => $evento->jogo ? $this->jogo($evento->jogo, $idTimeDoAtleta, $evento, $momento) : null,
        ];
    }

    // Bloco do jogo: campeonato (null no amistoso), times, placar (null = ainda não jogado), o time em
    // que o atleta está escalado (null = inscrito sem time) e a chegada (Jogo::ANTECEDENCIA_MINUTOS antes
    // do início; null sem horário de início)
    private function jogo(Jogo $jogo, ?int $idTimeDoAtleta, EventoCalendario $evento, callable $momento): array
    {
        $chegada = Jogo::chegadaPara($evento->horario_inicio_evento_calendario);

        return [
            'id_jogo'         => $jogo->id_jogo,
            'amistoso'        => $jogo->ehAmistoso(),
            'campeonato'      => $jogo->campeonato
                ? ['id_campeonato' => $jogo->campeonato->id_campeonato, 'nome_campeonato' => $jogo->campeonato->nome_campeonato]
                : null,
            'time_casa'       => self::time($jogo->timeCasa),
            'time_visitante'  => self::time($jogo->timeVisitante),
            'placar'          => $jogo->temPlacar()
                ? ['casa' => (int) $jogo->placar_time_casa_jogos, 'visitante' => (int) $jogo->placar_time_visitante_jogos]
                : null,
            'time_do_atleta'  => self::time(self::timeDoAtleta($jogo, $idTimeDoAtleta)),
            'horario_chegada' => $chegada,
            'chegada'         => $momento($chegada),
        ];
    }

    /**
     * O que o detalhe acrescenta ao bloco do jogo:
     * - adversario: o outro time do jogo, pelo time em que o atleta está escalado; sem time, o outro do único time
     *   da AACJ do jogo (null quando os dois são da AACJ e o atleta está sem time);
     * - fase_jogo e fase (null no amistoso), fase_completa ("Oitavas de final · volta"), rodada (só na fase de
     *   grupos) e perna (IDA, VOLTA ou null: jogo único);
     * - prorrogacao e penaltis ({casa, visitante} ou null) e confronto (quem passa no mata-mata, o mesmo bloco da
     *   API de campeonatos; null fora do mata-mata, na ida ou sem o resultado).
     */
    private function detalheDoJogo(Jogo $jogo, ?int $idTimeDoAtleta): array
    {
        $doAtleta = self::timeDoAtleta($jogo, $idTimeDoAtleta);
        if (! $doAtleta && ($internos = $jogo->timesEscalaveis())->count() === 1) {
            $doAtleta = $internos->first();
        }
        $adversario = match (true) {
            $doAtleta === null                                    => null,
            (int) $doAtleta->id_time === (int) $jogo->id_time_casa => $jogo->timeVisitante,
            default                                               => $jogo->timeCasa,
        };

        $par = fn ($casa, $visitante) => $casa === null ? null : ['casa' => (int) $casa, 'visitante' => (int) $visitante];

        return [
            'adversario'    => self::time($adversario),
            'fase_jogo'     => $jogo->fase_jogo,
            'fase'          => $jogo->fase_label,
            'fase_completa' => $jogo->fase_completa,
            'rodada'        => $jogo->rodada_jogo === null ? null : (int) $jogo->rodada_jogo,
            'perna'         => match (true) {
                $jogo->id_jogo_ida !== null                      => 'VOLTA',
                $jogo->ehMataMata() && $jogo->jogoVolta !== null => 'IDA',
                default                                          => null,
            },
            'prorrogacao'   => $par($jogo->prorrogacao_time_casa_jogos, $jogo->prorrogacao_time_visitante_jogos),
            'penaltis'      => $par($jogo->penaltis_time_casa_jogos, $jogo->penaltis_time_visitante_jogos),
            'confronto'     => $jogo->resultadoDoConfronto(),
        ];
    }

    private static function time(?Time $time): ?array
    {
        return $time ? ['id_time' => $time->id_time, 'nome_time' => $time->nome_time, 'logo_time' => $time->logo_time, 'logo_url' => $time->logo_url] : null;
    }

    // O time do jogo em que o atleta está escalado (null = inscrito sem time)
    private static function timeDoAtleta(Jogo $jogo, ?int $idTimeDoAtleta): ?Time
    {
        return match ($idTimeDoAtleta) {
            null                     => null,
            $jogo->id_time_casa      => $jogo->timeCasa,
            $jogo->id_time_visitante => $jogo->timeVisitante,
            default                  => null,
        };
    }

    // ── Avisos (notificações) ───────────────────────────────────────────────

    // Avisos do atleta, do mais novo para o mais antigo (no empate da data, o id: relógio do WSL2)
    public function avisos(int $pagina = 1): array
    {
        $avisos = $this->doAtleta()
            ->with('leiturasDosResponsaveis')
            ->orderByDesc('data_notificacao')
            ->orderByDesc('id_notificacao')
            ->paginate(self::POR_PAGINA, ['*'], 'page', $pagina);

        return [
            'notificacoes' => $avisos->getCollection()->map(fn ($aviso) => $this->aviso($aviso))->all(),
            'paginacao'    => self::paginacao($avisos),
        ];
    }

    // Não lidos por quem lê: o atleta pela própria marca; o responsável pela dele
    public function naoLidos(): int
    {
        return $this->naoLidosQuery()->count();
    }

    /**
     * Marca um aviso como lido por quem lê. Aviso de outro atleta: null (o controller responde 404).
     * Já lido: mantém a data da primeira leitura.
     */
    public function marcarLido(int $idNotificacao): ?array
    {
        $aviso = $this->doAtleta()->find($idNotificacao);

        if (! $aviso) {
            return null;
        }

        if ($this->responsavel) {
            NotificacaoLeitura::insertOrIgnore([
                'id_notificacao' => $aviso->id_notificacao, 'id_responsavel' => $this->responsavel->id_responsavel,
                'data_notificacao_leitura' => now(),
            ]);
        } else {
            $aviso->marcarComoLida();
        }

        return $this->aviso($aviso->load('leiturasDosResponsaveis'));
    }

    // Marca todos os avisos não lidos por quem lê; devolve quantos mudaram
    public function marcarTodosLidos(): int
    {
        if (! $this->responsavel) {
            return Notificacao::marcarTodasComoLidas($this->atleta->id_atleta);
        }

        $ids = $this->naoLidosQuery()->pluck('id_notificacao');

        return NotificacaoLeitura::insertOrIgnore($ids->map(fn ($id) => [
            'id_notificacao' => $id, 'id_responsavel' => $this->responsavel->id_responsavel,
            'data_notificacao_leitura' => now(),
        ])->all());
    }

    private function doAtleta()
    {
        return Notificacao::where('id_atleta', $this->atleta->id_atleta);
    }

    private function naoLidosQuery()
    {
        return $this->responsavel
            ? $this->doAtleta()->whereDoesntHave('leiturasDosResponsaveis',
                fn ($q) => $q->where('id_responsavel', $this->responsavel->id_responsavel))
            : $this->doAtleta()->naoLidas();
    }

    private function aviso(Notificacao $aviso): array
    {
        $leitura = $this->responsavel
            ? $aviso->leiturasDosResponsaveis->firstWhere('id_responsavel', $this->responsavel->id_responsavel)?->data_notificacao_leitura
            : $aviso->data_leitura_notificacao;

        return [
            'id_notificacao' => $aviso->id_notificacao,
            'tipo'           => $aviso->tipo_notificacao,
            'tipo_label'     => $aviso->tipo_label,
            'titulo'         => $aviso->titulo_notificacao,
            'mensagem'       => $aviso->mensagem_notificacao,
            'id_evento'      => $aviso->id_evento_calendario,
            'data'           => $aviso->data_notificacao?->copy()->setTimezone(config('app.timezone'))->format(self::FORMATO_DATA),
            'lida'           => $leitura !== null,
            'data_leitura'   => $leitura?->copy()->setTimezone(config('app.timezone'))->format(self::FORMATO_DATA),
        ];
    }

    // ── Comum ───────────────────────────────────────────────────────────────

    private static function paginacao(LengthAwarePaginator $pagina): array
    {
        return [
            'pagina_atual'  => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'por_pagina'    => $pagina->perPage(),
            'total'         => $pagina->total(),
        ];
    }
}
