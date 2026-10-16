<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GradeTreino extends Model
{
    protected $table      = 'tbl_grade_treino';
    protected $primaryKey = 'id_grade_treino';
    public    $timestamps = false;

    // Valores do ENUM. JOGO não gera eventos (jogo nasce na tela de Jogos) e saiu da escolha (dono do projeto,
    // 16/10/2026): só a linha que já é JOGO pode continuar com ele na edição
    public const TIPOS = ['TREINO', 'JOGO', 'LIVRE'];

    public const TIPOS_PARA_ESCOLHER = ['TREINO', 'LIVRE'];

    // Sem aula no fim de semana: a linha não tem turno e inscreve todos, sem olhar o período escolar
    // (dono do projeto, 16/10/2026: sábado de treino livre ou jogo interno para todos)
    public const DIAS_SEM_TURNO = ['sabado', 'domingo'];

    protected $fillable = [
        'dia_semana_grade_treino',
        'id_categoria',
        'categoria_grade_treino',
        'tipo_grade_treino',
        'horario_inicio_grade_treino',
        'horario_fim_grade_treino',
        'horario_obs_grade_treino',
        'local_grade_treino',
        'ordem_grade_treino',
        'status_grade_treino',
    ];

    // Um dia por linha, na ordem da semana: é a ordem do admin, do site e da ordenação (scopeOrdenada)
    public const DIAS_SEMANA = [
        'segunda' => 'Segunda',
        'terca'   => 'Terça',
        'quarta'  => 'Quarta',
        'quinta'  => 'Quinta',
        'sexta'   => 'Sexta',
        'sabado'  => 'Sábado',
        'domingo' => 'Domingo',
    ];

    // Dia real de cada valor do ENUM (Carbon: 0 = domingo ... 6 = sábado). Cada linha gera um evento por semana.
    public const DIAS_CARBON = [
        'segunda' => Carbon::MONDAY,
        'terca'   => Carbon::TUESDAY,
        'quarta'  => Carbon::WEDNESDAY,
        'quinta'  => Carbon::THURSDAY,
        'sexta'   => Carbon::FRIDAY,
        'sabado'  => Carbon::SATURDAY,
        'domingo' => Carbon::SUNDAY,
    ];

    /**
     * Ordem de exibição (site e admin): dia da semana, depois horário de início, depois o campo "ordem".
     * Sem horário (ex.: "Jogos", que usa a observação) vai para o fim do dia.
     */
    public function scopeOrdenada($query)
    {
        return $query
            ->orderByRaw("FIELD(dia_semana_grade_treino, 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo')")
            ->orderByRaw('horario_inicio_grade_treino IS NULL')
            ->orderBy('horario_inicio_grade_treino')
            ->orderBy('ordem_grade_treino');
    }

    // Categoria da linha da grade; null nos itens gerais (Integrado, Treino Livre, Jogos)
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Eventos gerados a partir desta linha (Fase 7)
    public function eventos()
    {
        return $this->hasMany(EventoCalendario::class, 'id_grade_treino', 'id_grade_treino');
    }

    /**
     * Quantos eventos gerados por esta linha ainda vão acontecer (ativos, de hoje em diante e não
     * concluídos). Mudar ou inativar a linha não altera esses eventos: a tela só avisa (Fase 7).
     */
    public function contarEventosFuturosAtivos(): int
    {
        return $this->eventos()->futurosAtivos()->get()
            ->reject(fn (EventoCalendario $evento) => $evento->estaConcluido())
            ->count();
    }

    // ── Geração de eventos (Fase 7) ─────────────────────────────────────────

    /**
     * Datas do mês em que esta linha acontece, pelo dia da semana. Com $aPartirDe, só datas desse dia
     * em diante (ex.: gerar o mês corrente sem criar treinos que já passaram).
     */
    public function datasNoMes(int $ano, int $mes, ?Carbon $aPartirDe = null): Collection
    {
        $dia    = self::DIAS_CARBON[$this->dia_semana_grade_treino] ?? null;
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $minimo = $aPartirDe?->copy()->startOfDay();

        return collect(range(0, $inicio->daysInMonth - 1))
            ->map(fn (int $i) => $inicio->copy()->addDays($i))
            ->filter(fn (Carbon $data) => $data->dayOfWeek === $dia)
            ->filter(fn (Carbon $data) => $minimo === null || $data->gte($minimo))
            ->values();
    }

    /**
     * Motivo para esta linha não gerar eventos, ou null se gera. Jogo nasce na tela de Jogos; sem
     * horário de início não há como montar o treino nem conferir conflito.
     */
    public function motivoQueNaoGera(): ?string
    {
        return match (true) {
            $this->tipo_grade_treino === 'JOGO'                                => 'Jogos são criados na tela de Jogos.',
            strtoupper($this->status_grade_treino) !== 'ATIVO'                 => 'Horário inativo.',
            $this->categoria && $this->categoria->status_categoria !== 'ATIVO' => "A categoria {$this->categoria->rotulo} está inativa.",
            ! $this->horario_inicio_grade_treino                               => 'Sem horário de início.',
            default                                                            => null,
        };
    }

    public function geraEventos(): bool
    {
        return $this->motivoQueNaoGera() === null;
    }

    // Turno do treino desta linha (MANHA ou TARDE), pelo horário de início; null sem horário e no fim de semana
    // (DIAS_SEM_TURNO: entram todos)
    public function turno(): ?string
    {
        if (in_array($this->dia_semana_grade_treino, self::DIAS_SEM_TURNO, true)) {
            return null;
        }

        return Atleta::turnoDoHorario($this->horario_inicio_grade_treino);
    }

    // O atleta treina nesta linha pelo turno: fim de semana, todos; nos outros dias, quem treina no turno dela
    // (sem período escolar, não). A mesma regra da geração do mês (previaDoMes)
    public static function treinaNoTurno(?string $turnoDaLinha, Atleta $atleta): bool
    {
        if ($turnoDaLinha === null) {
            return true;
        }

        return $atleta->periodo_escolar_atleta && in_array($turnoDaLinha, $atleta->turnosDeTreino(), true);
    }

    // O atleta ATIVO cabe nesta linha (categoria, se houver, e turno): para a volta do afastamento
    public function aceitaAtleta(Atleta $atleta): bool
    {
        if (strtoupper((string) $atleta->status_atleta) !== 'ATIVO') {
            return false;
        }
        if ($this->id_categoria && ! in_array((int) $atleta->id_atleta, array_map('intval', Atleta::idsAtivosNaCategoria($this->id_categoria)), true)) {
            return false;
        }

        return self::treinaNoTurno($this->turno(), $atleta);
    }

    /**
     * Dados do evento desta linha numa data (mesmas chaves do formulário de evento, para EventoCalendario::criarPor).
     * A origem (id_grade_treino e data_grade_evento_calendario) fica de fora: não é fillable e é gravada à parte.
     */
    public function dadosEventoPara(Carbon $data): array
    {
        // "Treino Sub-13 Masculino", "Treino Integrado"; rótulo que já começa com "Treino" fica como está
        $titulo = Str::startsWith(Str::lower($this->rotulo), 'treino') ? $this->rotulo : "Treino {$this->rotulo}";

        return [
            'titulo_evento_calendario'         => $titulo,
            'tipo_evento_calendario'           => 'TREINO', // LIVRE não existe no ENUM do evento: vira TREINO com subtipo
            'subtipo_evento_calendario'        => $this->tipo_grade_treino === 'LIVRE' ? 'Livre' : null,
            'id_categoria'                     => $this->id_categoria,
            'data_evento_calendario'           => $data->toDateString(),
            'horario_inicio_evento_calendario' => $this->horario_inicio_grade_treino,
            'horario_fim_evento_calendario'    => $this->horario_fim_grade_treino,
            'local_evento_calendario'          => $this->local_grade_treino,
            'status_evento_calendario'         => 'ATIVO',
        ];
    }

    // ── Geração do mês (Fase 7, Etapa 2) ────────────────────────────────────

    // Gera do mês atual até MESES_A_FRENTE meses depois (a grade pode mudar; não lotar a agenda)
    public const MESES_A_FRENTE = 2;

    // Meses que podem ser gerados: ['2026-10' => 'Outubro de 2026', ...]
    public static function mesesPermitidos(): array
    {
        $meses = [];
        foreach (range(0, self::MESES_A_FRENTE) as $i) {
            $mes = now()->startOfMonth()->addMonthsNoOverflow($i);
            $meses[$mes->format('Y-m')] = EventoCalendario::rotuloDoMes($mes);
        }

        return $meses;
    }

    // Chave de uma data de uma linha na prévia ("12|2026-11-02"): valor da caixa de marcar data
    public static function chaveDaData(int $idGrade, \Carbon\CarbonInterface $data): string
    {
        return $idGrade . '|' . $data->toDateString();
    }

    /**
     * Prévia da geração de um mês, por linha da grade (na ordem da grade):
     *  - motivo: por que a linha não gera (ou null);
     *  - novas: datas que serão geradas;
     *  - desmarcadas: datas novas que o admin desmarcou (feriado, dia sem aula): não são geradas;
     *  - existentes: datas que já têm evento desta linha (com o status; cancelado e oculto não voltam);
     *  - puladas: hoje, com o treino já concluído (não é gerado);
     *  - atletas: ids que serão inscritos em cada evento: ATIVO da categoria (sem categoria, todos os ATIVO) que
     *    treinam no turno da linha (Atleta::turnosDeTreino: o oposto ao da escola; Fase A, Etapa 2);
     *  - turno: MANHA ou TARDE (turno()); null no fim de semana, quando entram todos os ativos (da categoria);
     *  - semPeriodo: nomes dos atletas da linha sem período escolar (sem turno de treino: não entram);
     *  - origem: CATEGORIA (linha com categoria) ou INDIVIDUAL (linha sem categoria);
     *  - lote: os eventos novos ainda não salvos, com os atletas (para o conflito).
     * Mais os conflitos de horário do lote (EventoCalendario::conflitosEmLote) e os totais.
     * gerarMes() usa esta mesma prévia, recalculada na hora de gravar.
     *
     * $marcadas: as datas que o admin deixou marcadas na prévia, como chaves "idGrade|AAAA-MM-DD" (chaveDaData);
     * null = todas. Data nova fora da lista vai para desmarcadas e fica fora do lote, do conflito e dos totais.
     * Ela não grava nada: numa nova geração do mês, volta como nova (marcada).
     */
    public static function previaDoMes(string $mes, ?array $marcadas = null): array
    {
        $inicio = Carbon::createFromFormat('!Y-m', $mes)->startOfMonth();
        $hoje   = now()->startOfDay();
        // Só de hoje em diante: no mês atual, as datas começam hoje
        $minimo = $inicio->isSameMonth($hoje) ? $hoje : null;

        $grades = self::with('categoria')->ordenada()->get();

        // Eventos já gerados no mês, pela data de origem (o evento pode ter mudado de dia)
        $existentes = EventoCalendario::whereNotNull('id_grade_treino')
            ->whereBetween('data_grade_evento_calendario', [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()])
            ->get(['id_grade_treino', 'data_grade_evento_calendario', 'status_evento_calendario'])
            ->groupBy('id_grade_treino');

        // Atletas: uma consulta por categoria e uma para todos os ativos, com o período escolar (não uma por evento)
        $porCategoria = [];
        $ativos = Atleta::where('status_atleta', 'ATIVO')->orderBy('nome_atleta')
            ->get(['id_atleta', 'nome_atleta', 'periodo_escolar_atleta', 'turno_treino_atleta'])
            ->keyBy('id_atleta');

        $marcadas = $marcadas === null ? null : array_flip($marcadas);

        // Afastamentos que cruzam o mês (16/10/2026): o atleta não entra nos treinos das datas afastadas
        $afastamentos = AtletaAfastamento::porAtletaNoPeriodo($inicio, $inicio->copy()->endOfMonth());

        $linhas = $grades->map(function (self $grade) use ($inicio, $minimo, $existentes, $marcadas, $ativos, $afastamentos, &$porCategoria) {
            $linha = [
                'grade' => $grade, 'motivo' => $grade->motivoQueNaoGera(),
                'novas' => collect(), 'desmarcadas' => collect(), 'existentes' => collect(), 'puladas' => collect(),
                'atletas' => [], 'turno' => $grade->turno(), 'semPeriodo' => [],
                'origem' => $grade->id_categoria ? 'CATEGORIA' : 'INDIVIDUAL', 'lote' => [],
                // Por data nova: quem entra (os da linha menos os afastados naquele dia) e os afastados (nome => "até 10/11")
                'atletasPorData' => [], 'afastados' => [],
            ];

            if ($linha['motivo'] !== null) {
                return $linha;
            }

            $jaGeradas = ($existentes[$grade->id_grade_treino] ?? collect())
                ->keyBy(fn ($e) => $e->data_grade_evento_calendario->toDateString());

            // Da categoria (ou todos os ativos), só quem treina no turno da linha; sem período, fica de fora.
            // Fim de semana (sem turno): entram todos, inclusive quem está sem período escolar
            $candidatos = $grade->id_categoria
                ? ($porCategoria[$grade->id_categoria] ??= Atleta::idsAtivosNaCategoria($grade->id_categoria))
                : $ativos->keys()->all();
            foreach ($candidatos as $idAtleta) {
                $atleta = $ativos[$idAtleta];
                if ($linha['turno'] === null) {
                    $linha['atletas'][] = (int) $idAtleta;
                } elseif (! $atleta->periodo_escolar_atleta) {
                    $linha['semPeriodo'][] = $atleta->nome_atleta;
                } elseif (in_array($linha['turno'], $atleta->turnosDeTreino(), true)) {
                    $linha['atletas'][] = (int) $idAtleta;
                }
            }

            foreach ($grade->datasNoMes((int) $inicio->year, (int) $inicio->month, $minimo) as $data) {
                // Evento ainda não salvo: para a regra de Concluído e para o conflito em lote
                $evento = new EventoCalendario($grade->dadosEventoPara($data));

                if ($existente = $jaGeradas[$data->toDateString()] ?? null) {
                    $linha['existentes']->push(['data' => $data, 'status' => $existente->status_evento_calendario]);
                } elseif ($evento->estaConcluido()) {
                    $linha['puladas']->push($data); // hoje, com o horário já passado (regra de Concluído)
                } elseif ($marcadas !== null && ! isset($marcadas[self::chaveDaData($grade->id_grade_treino, $data)])) {
                    $linha['desmarcadas']->push($data); // o admin desmarcou (feriado, dia sem aula)
                } else {
                    // Afastado naquela data: fica de fora só dela
                    $doDia = array_values(array_filter($linha['atletas'], function (int $idAtleta) use ($afastamentos, $data, $ativos, &$linha) {
                        foreach ($afastamentos[$idAtleta] ?? [] as $afastamento) {
                            if ($afastamento->cobre($data)) {
                                $linha['afastados'][$ativos[$idAtleta]->nome_atleta] = $afastamento->textoAte();
                                return false;
                            }
                        }
                        return true;
                    }));

                    $linha['novas']->push($data);
                    $linha['atletasPorData'][$data->toDateString()] = $doDia;
                    $linha['lote'][] = ['evento' => $evento, 'atletas' => $doDia];
                }
            }

            return $linha;
        });

        // Conflitos de horário do lote (Etapa 3): contra o que já existe e entre os eventos novos
        $conflitos = EventoCalendario::conflitosEmLote($linhas->flatMap(fn ($l) => $l['lote'])->all());

        return [
            'mes'       => $inicio,
            'linhas'    => $linhas,
            'conflitos' => $conflitos,
            'totais'    => [
                'eventos'      => $linhas->sum(fn ($l) => $l['novas']->count()),
                'inscricoes'   => $linhas->sum(fn ($l) => array_sum(array_map('count', $l['atletasPorData']))),
                'existentes'   => $linhas->sum(fn ($l) => $l['existentes']->count()),
                'puladas'      => $linhas->sum(fn ($l) => $l['puladas']->count()),
                'desmarcadas'  => $linhas->sum(fn ($l) => $l['desmarcadas']->count()),
                // Atletas sem período escolar das linhas que geram algo: não entram em nenhum treino
                'sem_periodo'  => $linhas->filter(fn ($l) => $l['novas']->isNotEmpty())->flatMap(fn ($l) => $l['semPeriodo'])->unique()->sort()->values()->all(),
                // Afastados em alguma data nova (nome => "afastado até 10/11"): ficam fora só dessas datas
                'afastados'    => $linhas->flatMap(fn ($l) => $l['afastados'])->sortKeys()->all(),
                'nao_geram'    => $linhas->whereNotNull('motivo')->count(),
                'conflitos_reais'  => $conflitos['totais']['reais'],
                'conflitos_fracos' => $conflitos['totais']['fracos'],
            ],
        ];
    }

    /**
     * Gera os eventos do mês (tudo ou nada, numa transação) e inscreve os atletas de uma vez.
     * Trava as linhas da grade antes de recalcular a prévia: dois admins (ou clique duplo) geram um de
     * cada vez, e o segundo encontra tudo já gerado. O índice único (grade, data de origem) é a última
     * garantia: se escapar, a exceção desfaz o lote inteiro.
     *
     * A inscrição em massa passa por fora de EventoCalendario::inscrever() de propósito (só aqui): cada
     * atleta do lote recebe UMA notificação AGENDA ("agenda do mês disponível", com quantos treinos ganhou),
     * não uma por inscrição, gravada em massa na mesma transação. Devolve também 'notificados'.
     *
     * Conflito de horário (Etapa 3), recalculado aqui: com conflito real e sem $confirmarConflitos,
     * nada é gravado e a prévia volta (com 'gerado' => false). Aviso fraco não bloqueia.
     *
     * $marcadas: só as datas marcadas na prévia (previaDoMes); null = todas.
     */
    public static function gerarMes(string $mes, ?int $idUsuario, bool $confirmarConflitos = false, ?array $marcadas = null): array
    {
        return DB::transaction(function () use ($mes, $idUsuario, $confirmarConflitos, $marcadas) {
            self::query()->lockForUpdate()->get(['id_grade_treino']);

            $previa = self::previaDoMes($mes, $marcadas);

            if ($previa['totais']['conflitos_reais'] > 0 && ! $confirmarConflitos) {
                return ['gerado' => false, 'previa' => $previa, 'notificados' => 0];
            }

            $agora  = now();
            $inscricoes = [];
            $treinosPorAtleta = []; // [id_atleta => treinos novos], para a notificação AGENDA

            foreach ($previa['linhas'] as $linha) {
                foreach ($linha['novas'] as $data) {
                    $evento = EventoCalendario::criarDaGrade($linha['grade'], $data, $idUsuario);

                    foreach ($linha['atletasPorData'][$data->toDateString()] as $idAtleta) {
                        $inscricoes[] = [
                            'id_evento_calendario' => $evento->id_evento_calendario,
                            'id_atleta'            => $idAtleta,
                            'origem_evento_atleta' => $linha['origem'],
                            'id_usuario'           => $idUsuario,
                            'data_evento_atleta'   => $agora,
                        ];
                        $treinosPorAtleta[$idAtleta] = ($treinosPorAtleta[$idAtleta] ?? 0) + 1;
                    }
                }
            }

            foreach (array_chunk($inscricoes, 500) as $bloco) {
                EventoAtleta::insert($bloco);
            }

            $notificados = Notificacao::agendaDoMes($previa['mes'], $treinosPorAtleta, $idUsuario);

            return ['gerado' => true, 'previa' => $previa, 'notificados' => $notificados];
        });
    }

    // Texto exibido: "Sub-13 Masculino" quando há categoria; senão, o rótulo livre do item geral
    public function getRotuloAttribute(): string
    {
        return $this->categoria?->rotulo ?? $this->categoria_grade_treino;
    }

    // Cor do item no site: pelo nome da categoria (cat-sub13), igual para M e F
    public function getCatClassAttribute(): string
    {
        $nome = $this->categoria?->nome_categoria ?? $this->categoria_grade_treino;

        return match($this->tipo_grade_treino) {
            'JOGO'  => 'cat-jogo',
            'LIVRE' => 'cat-livre',
            default => 'cat-' . strtolower(str_replace(['-', ' '], '', $nome)),
        };
    }

    // Horário sem segundos, como na lista de eventos: "08:00 às 09:30"; sem início (ex.: "Jogos"), "—"
    public function getHorarioTextoAttribute(): string
    {
        $formatar = fn ($hora) => substr((string) $hora, 0, 5);

        if (! $this->horario_inicio_grade_treino) {
            return '—';
        }

        return $this->horario_fim_grade_treino
            ? $formatar($this->horario_inicio_grade_treino) . ' às ' . $formatar($this->horario_fim_grade_treino)
            : $formatar($this->horario_inicio_grade_treino);
    }

    public function getDiaLabelAttribute(): string
    {
        return self::DIAS_SEMANA[$this->dia_semana_grade_treino] ?? $this->dia_semana_grade_treino;
    }
}
