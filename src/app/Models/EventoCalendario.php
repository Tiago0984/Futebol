<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventoCalendario extends Model
{
    protected $table      = 'tbl_evento_calendario';
    protected $primaryKey = 'id_evento_calendario';
    public    $timestamps = false;

    // Valores do ENUM status_evento_calendario => rótulo. INATIVO = oculto (substitui a exclusão).
    public const STATUS = [
        'ATIVO'     => 'Ativo',
        'CANCELADO' => 'Cancelado',
        'INATIVO'   => 'Oculto',
    ];

    // Situação exibida no admin: o status gravado mais os derivados (não gravados) Alterado e Concluído
    public const SITUACOES = [
        'ATIVO'     => 'Ativo',
        'ALTERADO'  => 'Alterado',
        'CONCLUIDO' => 'Concluído',
        'CANCELADO' => 'Cancelado',
        'INATIVO'   => 'Oculto',
    ];

    // Campos registrados no histórico de alterações => rótulo (CLAUDE.md, seção 4)
    public const CAMPOS_HISTORICO = [
        'titulo_evento_calendario'         => 'Título',
        'tipo_evento_calendario'           => 'Tipo',
        'id_categoria'                     => 'Categoria',
        'data_evento_calendario'           => 'Data',
        'horario_inicio_evento_calendario' => 'Horário de início',
        'horario_fim_evento_calendario'    => 'Horário de fim',
        'local_evento_calendario'          => 'Local',
        'status_evento_calendario'         => 'Status',
    ];

    // Só estes contam para o derivado "Alterado"
    public const CAMPOS_ALTERADO = [
        'data_evento_calendario',
        'horario_inicio_evento_calendario',
        'horario_fim_evento_calendario',
        'local_evento_calendario',
    ];

    // Duração usada no alerta de conflito quando o evento não tem horário de fim, em minutos.
    // PROVISÓRIA: confirmar com o professor (CLAUDE.md, seção 8, pergunta 12).
    // DIA_TODO = até o fim do dia; tipos fora da lista usam DURACAO_PADRAO_OUTROS.
    public const DURACAO_PADRAO_MINUTOS = [
        'JOGO'       => 120,
        'TREINO'     => 90,
        'AVALIACAO'  => 60,
        'CAMPEONATO' => 'DIA_TODO',
    ];
    public const DURACAO_PADRAO_OUTROS = 120;

    // Status que aparecem no site público: cancelado continua visível, com o selo
    public const STATUS_VISIVEIS = ['ATIVO', 'CANCELADO'];

    // Valores do ENUM tipo_evento_calendario (sem acento) => texto exibido na tela
    public const TIPOS = [
        'JOGO'             => 'JOGO',
        'TREINO'           => 'TREINO',
        'CAMPEONATO'       => 'CAMPEONATO',
        'EVENTO'           => 'EVENTO',
        'REUNIAO'          => 'REUNIÃO',
        'CONFRATERNIZACAO' => 'CONFRATERNIZAÇÃO',
        'AVALIACAO'        => 'AVALIAÇÃO',
    ];

    // Ramos do menu Eventos e do filtro "Ramo" da lista (Fase 10): chave => rótulo. A regra de cada um fica
    // em scopeDoRamo() e ramo(). Evento JOGO antigo sem tbl_jogos não é de nenhum ramo (só no Calendário)
    public const RAMOS = [
        'campeonatos' => 'Campeonatos',
        'amistosos'   => 'Amistosos',
        'treinos'     => 'Treinos',
        'individuais' => 'Individuais',
        'outros'      => 'Outros',
    ];

    // Tipos do evento individual (o técnico escolhe os atletas; sem categoria): exame, teste ou avaliação,
    // reunião com o atleta ou a família, outro compromisso. Ramo "Individuais" = esses tipos SEM categoria
    public const TIPOS_INDIVIDUAIS = ['AVALIACAO', 'REUNIAO', 'EVENTO'];

    // Tipos do ramo "Outros": a confraternização e os tipos individuais quando o evento tem categoria
    // (ex.: reunião de pais da Sub-13)
    public const TIPOS_OUTROS = ['REUNIAO', 'CONFRATERNIZACAO', 'EVENTO', 'AVALIACAO'];

    // Sugestões do campo Subtipo quando o tipo é AVALIACAO (o campo continua livre)
    public const SUBTIPOS_AVALIACAO = ['Exame médico', 'Avaliação física'];

    // id_usuario fica fora de propósito: o responsável é gravado só na criação (criarPor) e nenhum
    // update() com os dados do formulário pode trocá-lo. O mesmo vale para id_grade_treino e
    // data_grade_evento_calendario: só a geração pela grade grava a origem, e o formulário nunca a muda.
    protected $fillable = [
        'titulo_evento_calendario',
        'descricao_evento_calendario',
        'tipo_evento_calendario',
        'subtipo_evento_calendario',
        'id_categoria',
        'data_evento_calendario',
        'horario_inicio_evento_calendario',
        'horario_fim_evento_calendario',
        'local_evento_calendario',
        'destaque_evento_calendario',
        'status_evento_calendario',
    ];

    protected $casts = [
        'data_evento_calendario'       => 'date',
        'data_grade_evento_calendario' => 'date',
    ];

    // Quantos atletas foram notificados pelas inscrições e remoções feitas nesta instância (não é coluna:
    // serve só para a mensagem de sucesso do admin, ex.: "5 atleta(s) notificado(s)")
    public int $atletasNotificados = 0;

    /**
     * Cria o evento já com o responsável (quem criou), que depois nunca muda. Evento com categoria
     * já nasce com os atletas ativos da categoria inscritos (origem CATEGORIA). Usado pelo formulário
     * de evento e pela tela de Jogos; a geração da grade usa criarDaGrade().
     * $inscreverCategoria = false: o jogo, que inscreve o elenco dos times (Jogo::inscreverElenco).
     */
    public static function criarPor(?int $idUsuario, array $dados, bool $inscreverCategoria = true): self
    {
        return DB::transaction(function () use ($idUsuario, $dados, $inscreverCategoria) {
            $evento = self::novoPor($idUsuario, $dados);
            $evento->save();

            if ($inscreverCategoria && $evento->id_categoria) {
                $evento->inscreverCategoria($evento->id_categoria, 'CATEGORIA', $idUsuario);
            }

            return $evento;
        });
    }

    /**
     * Cria o evento de uma linha da grade numa data (Fase 7), com o responsável e a origem (grade +
     * data de origem, que não são fillable). Sem inscrições: GradeTreino::gerarMes() inscreve o lote
     * de uma vez, dentro da mesma transação.
     */
    public static function criarDaGrade(GradeTreino $grade, Carbon $data, ?int $idUsuario): self
    {
        $evento = self::novoPor($idUsuario, $grade->dadosEventoPara($data));
        $evento->id_grade_treino              = $grade->id_grade_treino;
        $evento->data_grade_evento_calendario = $data->toDateString();
        $evento->save();

        return $evento;
    }

    // Evento ainda não salvo, com os horários normalizados e o responsável (quem criou)
    private static function novoPor(?int $idUsuario, array $dados): self
    {
        $evento = new self(self::normalizarHorarios($dados));
        $evento->id_usuario = $idUsuario;

        return $evento;
    }

    // ── Inscrições ──────────────────────────────────────────────────────────

    public function inscricoes()
    {
        return $this->hasMany(EventoAtleta::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    /**
     * Inscreve um atleta. Já inscrito: não faz nada e devolve false (sem erro), para "adicionar
     * todos de uma categoria" poder ser usado várias vezes. Ponto único de inscrição: inscrição criada
     * gera a notificação INSCRICAO na mesma transação (Notificacao::inscricao decide se avisa).
     * $idTime: escalação num jogo (quem chama valida o time). $notificar = false: "Mover inscrições",
     * que manda um resumo só (AGENDA).
     */
    public function inscrever(int $idAtleta, string $origem, ?int $idUsuario, ?int $idTime = null, bool $notificar = true): bool
    {
        if ($this->inscricoes()->where('id_atleta', $idAtleta)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($idAtleta, $origem, $idUsuario, $idTime, $notificar) {
            try {
                $this->inscricoes()->create([
                    'id_atleta'            => $idAtleta,
                    'id_time'              => $idTime,
                    'origem_evento_atleta' => $origem,
                    'id_usuario'           => $idUsuario,
                    'data_evento_atleta'   => now(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                return false; // outro admin inscreveu o mesmo atleta ao mesmo tempo
            }

            if ($notificar) {
                $this->atletasNotificados += Notificacao::inscricao($this, $idAtleta, $idUsuario);
            }

            return true;
        });
    }

    // Inscreve os atletas ativos com categoria ativa nesta categoria; devolve quantos entraram
    public function inscreverCategoria(int $idCategoria, string $origem, ?int $idUsuario): int
    {
        return DB::transaction(function () use ($idCategoria, $origem, $idUsuario) {
            $novos = 0;

            foreach (Atleta::idsAtivosNaCategoria($idCategoria) as $idAtleta) {
                $novos += $this->inscrever($idAtleta, $origem, $idUsuario) ? 1 : 0;
            }

            return $novos;
        });
    }

    // Remove a inscrição; removida, gera a notificação REMOCAO na mesma transação ($notificar: ver inscrever)
    public function removerInscricao(int $idAtleta, ?int $idUsuario = null, bool $notificar = true): bool
    {
        return DB::transaction(function () use ($idAtleta, $idUsuario, $notificar) {
            $removeu = $this->inscricoes()->where('id_atleta', $idAtleta)->delete() > 0;

            if ($removeu && $notificar) {
                $this->atletasNotificados += Notificacao::remocao($this, [$idAtleta], $idUsuario);
            }

            return $removeu;
        });
    }

    // Avisa os atletas de inscrição e remoção: só evento ATIVO que ainda não aconteceu (concluído,
    // cancelado e oculto não avisam)
    public function avisaAtletas(): bool
    {
        return $this->status_evento_calendario === 'ATIVO' && ! $this->estaConcluido();
    }

    /**
     * Evento que mudou de categoria (ou ficou sem): as inscrições AUTOMÁTICAS acompanham a categoria
     * nova (sai quem não é dela, entra quem é); as individuais ficam. Quem chama decide se o evento
     * ainda pode mudar (concluído não muda). Quem entra recebe INSCRICAO (pelo inscrever()) e quem sai,
     * REMOCAO. Devolve ['entraram' => n, 'sairam' => n, 'ids_entraram' => [...]] (quem entrou não recebe
     * também a ALTERACAO da mesma edição).
     */
    public function sincronizarInscricoesPelaCategoria(?int $idUsuario): array
    {
        return DB::transaction(function () use ($idUsuario) {
            $daCategoria = $this->id_categoria ? Atleta::idsAtivosNaCategoria($this->id_categoria) : [];

            // Quem sai é lido antes do delete em massa, para a notificação
            $saindo = $this->inscricoes()
                ->where('origem_evento_atleta', 'CATEGORIA')
                ->whereNotIn('id_atleta', $daCategoria)
                ->pluck('id_atleta')
                ->map(fn ($id) => (int) $id)
                ->all();

            $sairam = $saindo ? $this->inscricoes()->whereIn('id_atleta', $saindo)->delete() : 0;
            $this->atletasNotificados += Notificacao::remocao($this, $saindo, $idUsuario);

            $inscritos = fn () => $this->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
            $antes     = $inscritos();
            $entraram  = $this->id_categoria
                ? $this->inscreverCategoria($this->id_categoria, 'CATEGORIA', $idUsuario)
                : 0;

            return [
                'entraram'     => $entraram,
                'sairam'       => $sairam,
                'ids_entraram' => $entraram ? array_values(array_diff($inscritos(), $antes)) : [],
            ];
        });
    }

    /**
     * Aviso (não bloqueia) para atletas fora da categoria do evento, que também cobre o sexo, porque
     * cada categoria é M ou F. Ex.: "Fulana é Sub-15 Feminino; o jogo é Sub-11 Masculino."
     * Evento sem categoria: nada a avisar. Se deve bloquear: CLAUDE.md, seção 8, pergunta 14.
     */
    public function avisosForaDaCategoria(array $idsAtletas): array
    {
        if (! $this->categoria || ! $idsAtletas) {
            return [];
        }

        $rotuloEvento = $this->categoria->rotulo;
        $oQue = $this->tipo_evento_calendario === 'JOGO' ? 'o jogo' : 'o evento';

        return Atleta::with('categoriasAtivas')
            ->whereIn('id_atleta', $idsAtletas)
            ->orderBy('nome_atleta')
            ->get()
            ->reject(fn ($atleta) => $atleta->categoriasAtivas->contains('id_categoria', $this->id_categoria))
            ->map(fn ($atleta) => ($categoria = $atleta->categoriasAtivas->first())
                ? "{$atleta->nome_atleta} é {$categoria->rotulo}; {$oQue} é {$rotuloEvento}."
                : "{$atleta->nome_atleta} está sem categoria; {$oQue} é {$rotuloEvento}.")
            ->values()
            ->all();
    }

    /**
     * Evento de um jogo cadastrado em Jogos (tbl_jogos): inscreve o elenco dos times, não a categoria.
     * A categoria fica só para exibição. Evento JOGO sem tbl_jogos continua um evento comum.
     */
    public function ehJogo(): bool
    {
        return $this->id_evento_calendario !== null && $this->jogo()->exists();
    }

    // Atletas ativos da categoria do evento que ainda não estão inscritos (quem entrou depois).
    // No jogo, ninguém falta pela categoria: quem falta é do elenco (Jogo::idsFaltantesDoElenco)
    public function idsFaltantesDaCategoria(): array
    {
        if (! $this->id_categoria || $this->ehJogo()) {
            return [];
        }

        $inscritos = $this->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();

        return array_values(array_diff(Atleta::idsAtivosNaCategoria($this->id_categoria), $inscritos));
    }

    // ── Conflito de horário ─────────────────────────────────────────────────

    /**
     * Início e fim do evento para o alerta de conflito, ou null se não tem horário de início
     * (aí só dá para avisar "mesmo dia, horário a definir"). Sem fim: duração padrão por tipo.
     */
    public function intervalo(): ?array
    {
        if (! $this->horario_inicio_evento_calendario) {
            return null;
        }

        $dia    = $this->data_evento_calendario->toDateString();
        $inicio = Carbon::parse("{$dia} {$this->horario_inicio_evento_calendario}");

        if ($this->horario_fim_evento_calendario) {
            return [$inicio, Carbon::parse("{$dia} {$this->horario_fim_evento_calendario}")];
        }

        $duracao = self::DURACAO_PADRAO_MINUTOS[$this->tipo_evento_calendario] ?? self::DURACAO_PADRAO_OUTROS;

        return [$inicio, $duracao === 'DIA_TODO' ? $inicio->copy()->endOfDay() : $inicio->copy()->addMinutes($duracao)];
    }

    /**
     * Conflitos dos atletas informados com outros eventos ATIVO do mesmo dia em que já estão inscritos
     * (cancelados e ocultos não contam; só atletas ATIVO). Funciona também com um evento ainda não salvo
     * ou com dados novos (simulação antes de criar/editar). Cada item: atleta, outro evento e se é
     * "fraco" (algum dos dois sem horário de início: mesmo dia, horário a definir).
     * Sobreposição: inícioA < fimB e inícioB < fimA (encostar não conta).
     */
    public function conflitosPara(array $idsAtletas): Collection
    {
        if ($this->status_evento_calendario !== 'ATIVO' || empty($idsAtletas)) {
            return collect();
        }

        $meu = $this->intervalo();

        $inscricoes = EventoAtleta::with(['atleta', 'evento'])
            ->whereIn('id_atleta', $idsAtletas)
            ->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))
            ->whereHas('evento', fn ($q) => $q
                ->where('status_evento_calendario', 'ATIVO')
                ->whereDate('data_evento_calendario', $this->data_evento_calendario->toDateString())
                ->when($this->id_evento_calendario, fn ($q) => $q->where('id_evento_calendario', '<>', $this->id_evento_calendario)))
            ->get();

        return $inscricoes->map(function (EventoAtleta $inscricao) use ($meu) {
            $tipo = self::compararIntervalos($meu, $inscricao->evento->intervalo());

            return $tipo === null ? null
                : ['atleta' => $inscricao->atleta, 'evento' => $inscricao->evento, 'fraco' => $tipo === self::CONFLITO_FRACO];
        })->filter()->sortBy(fn ($c) => $c['atleta']->nome_atleta)->values();
    }

    public const CONFLITO_REAL  = 'REAL';
    public const CONFLITO_FRACO = 'FRACO';

    /**
     * Regra única do conflito entre dois eventos do mesmo dia, pelos intervalos de intervalo():
     * REAL = horários sobrepostos (inícioA < fimB e inícioB < fimA; só encostar não conta);
     * FRACO = algum dos dois sem horário de início (mesmo dia, horário a definir); null = sem conflito.
     */
    public static function compararIntervalos(?array $meu, ?array $outro): ?string
    {
        if ($meu === null || $outro === null) {
            return self::CONFLITO_FRACO;
        }

        return $meu[0]->lt($outro[1]) && $outro[0]->lt($meu[1]) ? self::CONFLITO_REAL : null;
    }

    /**
     * Conflitos de um lote de eventos ainda não salvos (geração da grade, Fase 7), contra os eventos ATIVO
     * já existentes em que os atletas estão inscritos e entre os próprios eventos do lote. Poucas consultas,
     * qualquer que seja o tamanho do lote: inscrições existentes nos dias do lote, os eventos delas e os
     * nomes dos atletas; o resto é em memória (só atletas ATIVO, como em conflitosPara()).
     *
     * $lote: lista de ['evento' => EventoCalendario não salvo, 'atletas' => [id_atleta, ...]].
     * Devolve os grupos por par de eventos ([REAL => [...], FRACO => [...]]), cada um com a data, o evento
     * novo, o outro evento, se o outro também é do lote e os nomes dos atletas; e os totais.
     */
    public static function conflitosEmLote(array $lote): array
    {
        $lote       = array_values($lote);
        $idsAtletas = collect($lote)->flatMap(fn ($item) => $item['atletas'])->unique()->values()->all();
        $dias       = collect($lote)->map(fn ($item) => $item['evento']->data_evento_calendario->toDateString())->unique()->values()->all();

        $nomes = $idsAtletas ? Atleta::whereIn('id_atleta', $idsAtletas)->where('status_atleta', 'ATIVO')->pluck('nome_atleta', 'id_atleta') : collect();

        // Existentes: [id_atleta][dia] => eventos
        $existentes = [];
        if ($nomes->isNotEmpty()) {
            EventoAtleta::with('evento')
                ->whereIn('id_atleta', $nomes->keys())
                ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', 'ATIVO')->whereIn('data_evento_calendario', $dias))
                ->get()
                ->each(function (EventoAtleta $inscricao) use (&$existentes) {
                    $existentes[$inscricao->id_atleta][$inscricao->evento->data_evento_calendario->toDateString()][] = $inscricao->evento;
                });
        }

        $intervalos = [];
        $intervalo  = function (self $evento, string $chave) use (&$intervalos) {
            return array_key_exists($chave, $intervalos) ? $intervalos[$chave] : ($intervalos[$chave] = $evento->intervalo());
        };

        $grupos = [self::CONFLITO_REAL => [], self::CONFLITO_FRACO => []];
        $anotar = function (?string $tipo, int $i, self $outro, string $chaveOutro, bool $outroNoLote, int $idAtleta) use (&$grupos, $lote) {
            if ($tipo === null) {
                return;
            }
            $grupos[$tipo]["{$i}|{$chaveOutro}"] ??= [
                'data' => $lote[$i]['evento']->data_evento_calendario, 'novo' => $lote[$i]['evento'],
                'outro' => $outro, 'outro_no_lote' => $outroNoLote, 'atletas' => [],
            ];
            $grupos[$tipo]["{$i}|{$chaveOutro}"]['atletas'][$idAtleta] = true;
        };

        $doLote = []; // [id_atleta][dia] => índices do lote
        foreach ($lote as $i => $item) {
            $dia = $item['evento']->data_evento_calendario->toDateString();
            $meu = $intervalo($item['evento'], "lote{$i}");

            foreach ($item['atletas'] as $idAtleta) {
                if (! $nomes->has($idAtleta)) {
                    continue; // inativo: não conta
                }

                foreach ($existentes[$idAtleta][$dia] ?? [] as $outro) {
                    $chave = "ev{$outro->id_evento_calendario}";
                    $anotar(self::compararIntervalos($meu, $intervalo($outro, $chave)), $i, $outro, $chave, false, $idAtleta);
                }

                // Dentro do lote: cada par uma vez só (o anterior com o atual)
                foreach ($doLote[$idAtleta][$dia] ?? [] as $j) {
                    $anotar(self::compararIntervalos($intervalo($lote[$j]['evento'], "lote{$j}"), $meu), $j, $item['evento'], "lote{$i}", true, $idAtleta);
                }
                $doLote[$idAtleta][$dia][] = $i;
            }
        }

        $organizar = fn (array $lista) => collect($lista)
            ->map(fn ($g) => [...$g, 'atletas' => collect(array_keys($g['atletas']))->map(fn ($id) => $nomes[$id])
                ->sort(fn ($a, $b) => strnatcasecmp($a, $b))->values()->all()])
            ->sortBy(fn ($g) => $g['data']->toDateString() . ' ' . $g['novo']->horario_inicio_evento_calendario)
            ->values();

        $reais  = $organizar($grupos[self::CONFLITO_REAL]);
        $fracos = $organizar($grupos[self::CONFLITO_FRACO]);

        return [
            self::CONFLITO_REAL  => $reais,
            self::CONFLITO_FRACO => $fracos,
            'totais' => [
                'reais'   => $reais->sum(fn ($g) => count($g['atletas'])),
                'fracos'  => $fracos->sum(fn ($g) => count($g['atletas'])),
                'dias'    => $reais->map(fn ($g) => $g['data']->toDateString())->unique()->count(),
                'atletas' => collect($grupos[self::CONFLITO_REAL])->flatMap(fn ($g) => array_keys($g['atletas']))->unique()->count(),
            ],
        ];
    }

    // Texto de um grupo de conflito do lote (um par de eventos), para o aviso depois de gerar
    public static function descreverGrupoDeConflito(array $grupo): string
    {
        $outro = $grupo['outro'];

        return $grupo['data']->format('d/m') . " · {$grupo['novo']->titulo_evento_calendario} ({$grupo['novo']->horario_texto}) × "
            . "{$outro->tipo_evento_calendario} \"{$outro->titulo_evento_calendario}\" ({$outro->horario_texto})"
            . ($grupo['outro_no_lote'] ? ', também gerado' : '')
            . ': ' . implode(', ', $grupo['atletas']);
    }

    // Texto de um conflito para a tela de confirmação
    public static function descreverConflito(array $conflito): string
    {
        $outro = $conflito['evento'];
        // O tipo diferencia dois eventos com o mesmo título (ex.: um jogo e um treino "Sub-13")
        $quando = $outro->tipo_evento_calendario . ', ' . $outro->data_evento_calendario->format('d/m') . ', ' . $outro->horario_texto;

        return $conflito['fraco']
            ? "{$conflito['atleta']->nome_atleta}: também está em \"{$outro->titulo_evento_calendario}\" no mesmo dia ({$quando}); horário a definir, confira."
            : "{$conflito['atleta']->nome_atleta}: horário sobrepõe \"{$outro->titulo_evento_calendario}\" ({$quando}).";
    }

    /**
     * Agenda do site público (decisão de 06/10/2026, CLAUDE.md seção 4, "Site público"): só eventos do tipo
     * CAMPEONATO e jogos de campeonato (evento com tbl_jogos e campeonato preenchido). Ficam de fora
     * amistosos, treinos (à mão ou da grade), eventos JOGO sem tbl_jogos e os outros tipos. O status
     * (cancelado com o selo, oculto fora) é filtrado à parte.
     */
    public function scopeDaAgendaPublica($query)
    {
        return $query->where(fn ($q) => $q
            ->where('tipo_evento_calendario', 'CAMPEONATO')
            ->orWhere(fn ($jogo) => $jogo
                ->where('tipo_evento_calendario', 'JOGO')
                ->whereHas('jogo', fn ($j) => $j->whereNotNull('id_campeonato'))));
    }

    /**
     * Eventos de um ramo do menu (RAMOS): Campeonatos = tipo CAMPEONATO e jogos com campeonato; Amistosos =
     * jogos sem campeonato; Treinos = TREINO (gerado ou à mão); Individuais = TIPOS_INDIVIDUAIS sem
     * categoria (os atletas são escolhidos um a um: exame, teste, reunião); Outros = confraternização e os
     * tipos individuais com categoria. Ramo desconhecido não filtra.
     */
    public function scopeDoRamo($query, ?string $ramo)
    {
        $jogo = fn (bool $comCampeonato) => fn ($q) => $q->where('tipo_evento_calendario', 'JOGO')
            ->whereHas('jogo', fn ($j) => $comCampeonato ? $j->whereNotNull('id_campeonato') : $j->whereNull('id_campeonato'));

        return match ($ramo) {
            'campeonatos' => $query->where(fn ($q) => $q->where('tipo_evento_calendario', 'CAMPEONATO')->orWhere($jogo(true))),
            'amistosos'   => $query->where($jogo(false)),
            'treinos'     => $query->where('tipo_evento_calendario', 'TREINO'),
            'individuais' => $query->whereIn('tipo_evento_calendario', self::TIPOS_INDIVIDUAIS)->whereNull('id_categoria'),
            'outros'      => $query->whereIn('tipo_evento_calendario', self::TIPOS_OUTROS)
                ->where(fn ($q) => $q->where('tipo_evento_calendario', 'CONFRATERNIZACAO')->orWhereNotNull('id_categoria')),
            default       => $query,
        };
    }

    /**
     * Filtro "Situação" das listas do admin (Calendário e Jogos), parte que o banco resolve: vazio = tudo
     * menos os ocultos (as listas abrem assim); INATIVO = só os ocultos; CANCELADO = só os cancelados; ATIVO,
     * ALTERADO e CONCLUIDO = status ATIVO (Alterado e Concluído são derivados: a lista confere depois,
     * pelo atributo situacao).
     */
    public function scopeDaSituacao($query, string $situacao)
    {
        return match ($situacao) {
            ''                     => $query->where('status_evento_calendario', '<>', 'INATIVO'),
            'INATIVO', 'CANCELADO' => $query->where('status_evento_calendario', $situacao),
            default                => $query->where('status_evento_calendario', 'ATIVO'),
        };
    }

    // Página de um ramo (menu e linha de caminho): Amistosos abre a lista de Jogos; os outros, o Calendário filtrado
    public static function urlDoRamo(string $ramo): string
    {
        return $ramo === 'amistosos'
            ? route('admin.jogos.index', ['campeonato' => 'amistoso'])
            : route('admin.calendario.index', ['ramo' => $ramo]);
    }

    // Locais já usados em eventos, grade e campeonatos (sugestões do campo Local), sem repetir, em ordem
    public static function locaisUsados(): array
    {
        $locais = DB::table('tbl_evento_calendario')->select('local_evento_calendario as local')
            ->union(DB::table('tbl_grade_treino')->select('local_grade_treino'))
            ->union(DB::table('tbl_campeonato')->select('local_evento'));

        return DB::query()->fromSub($locais, 'l')
            ->whereNotNull('local')->where('local', '<>', '')
            ->orderBy('local')
            ->pluck('local')
            ->all();
    }

    // Ramo do menu deste evento (a mesma regra de scopeDoRamo), para marcar o item ativo na tela do evento
    public function ramo(): ?string
    {
        return match ($this->tipo_evento_calendario) {
            'CAMPEONATO' => 'campeonatos',
            'JOGO'       => $this->jogo ? ($this->jogo->id_campeonato ? 'campeonatos' : 'amistosos') : null,
            'TREINO'     => 'treinos',
            default      => match (true) {
                in_array($this->tipo_evento_calendario, self::TIPOS_INDIVIDUAIS, true) && ! $this->id_categoria => 'individuais',
                in_array($this->tipo_evento_calendario, self::TIPOS_OUTROS, true)                              => 'outros',
                default                                                                                         => null,
            },
        };
    }

    // Eventos de um mês (lista do admin, por mês)
    public function scopeDoMes($query, Carbon $mes)
    {
        return $query->whereBetween('data_evento_calendario', [
            $mes->copy()->startOfMonth()->toDateString(), $mes->copy()->endOfMonth()->toDateString(),
        ]);
    }

    // "Outubro de 2026" (select de mês da lista e da geração)
    public static function rotuloDoMes(Carbon $mes): string
    {
        return Str::ucfirst($mes->copy()->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
    }

    public function veioDaGrade(): bool
    {
        return $this->id_grade_treino !== null;
    }

    // Eventos ativos (não cancelados nem ocultos) que ainda não aconteceram
    public function scopeFuturosAtivos($query)
    {
        return $query->where('status_evento_calendario', 'ATIVO')
            ->whereDate('data_evento_calendario', '>=', now()->toDateString())
            ->orderBy('data_evento_calendario')
            ->orderBy('horario_inicio_evento_calendario');
    }

    // Mesma lista, já sem os que terminaram hoje (regra de "Concluído"). Sem os jogos: quem joga é o
    // elenco, não a categoria ("Mover inscrições" do atleta não mexe neles)
    public static function futurosAtivosDaCategoria(int $idCategoria)
    {
        return self::futurosAtivos()
            ->where('id_categoria', $idCategoria)
            ->whereDoesntHave('jogo')
            ->get()
            ->reject(fn (self $evento) => $evento->estaConcluido())
            ->values();
    }

    // Dados do jogo (times, campeonato, placar) quando o evento é um jogo cadastrado em Jogos
    public function jogo()
    {
        return $this->hasOne(Jogo::class, 'id_evento', 'id_evento_calendario');
    }

    // Data e horário de início juntos ("Y-m-d H:i:s"; sem horário, meia-noite). Usado por Jogo::data_jogo
    public function dataHoraDoJogo(): string
    {
        return $this->data_evento_calendario->format('Y-m-d') . ' '
            . substr(($this->horario_inicio_evento_calendario ?? '00:00') . ':00', 0, 8);
    }

    // Categoria do evento; null em evento individual (exame, avaliação de um atleta)
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Linha da grade de treino que gerou o evento; null em evento criado à mão
    public function grade()
    {
        return $this->belongsTo(GradeTreino::class, 'id_grade_treino', 'id_grade_treino');
    }

    // Usuário do admin que criou o evento; null nos eventos anteriores à Fase 4
    public function responsavel()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Notificações enviadas aos atletas sobre o evento, da mais nova para a mais antiga (tela do evento)
    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class, 'id_evento_calendario', 'id_evento_calendario')
            ->orderByDesc('data_notificacao')
            ->orderByDesc('id_notificacao');
    }

    public function historico()
    {
        return $this->hasMany(EventoHistorico::class, 'id_evento_calendario', 'id_evento_calendario')
            ->orderByDesc('data_evento_historico')
            ->orderByDesc('id_evento_historico');
    }

    // Para listas: carrega "inscritos_ativos" (só atletas ATIVO contam; CLAUDE.md, seção 4)
    public function scopeComInscritosAtivos($query)
    {
        return $query->withCount([
            'inscricoes as inscritos_ativos' => fn ($q) => $q->whereHas('atleta', fn ($a) => $a->where('status_atleta', 'ATIVO')),
        ]);
    }

    // Para listas: carrega "tem_alteracao" numa consulta só, no lugar de uma por evento
    public function scopeComAlteracao($query)
    {
        return $query->withExists([
            'historico as tem_alteracao' => fn ($q) => $q->whereIn('campo_evento_historico', self::CAMPOS_ALTERADO),
        ]);
    }

    // ── Alterações com histórico ────────────────────────────────────────────

    /**
     * Atualiza o evento e grava no histórico uma linha por campo de CAMPOS_HISTORICO que mudou
     * (valor antigo, valor novo, quem e quando), tudo na mesma transação. Devolve o que mudou,
     * [campo => [antigo, novo]], para a notificação ALTERACAO (Notificacao::alteracao), que quem
     * chama grava depois de sincronizar as inscrições (ver Admin\Concerns\ConfirmaConflitos).
     */
    public function atualizarComHistorico(array $dados, ?int $idUsuario): array
    {
        return DB::transaction(function () use ($dados, $idUsuario) {
            $antes = [];
            foreach (array_keys(self::CAMPOS_HISTORICO) as $campo) {
                $antes[$campo] = $this->valorParaHistorico($campo, $this->getRawOriginal($campo));
            }

            $this->fill(self::normalizarHorarios($dados));
            $this->save();

            $mudancas = [];
            foreach ($antes as $campo => $valorAntigo) {
                $valorNovo = $this->valorParaHistorico($campo, $this->getRawOriginal($campo));

                if ($valorAntigo !== $valorNovo) {
                    $this->historico()->create([
                        'campo_evento_historico'        => $campo,
                        'valor_antigo_evento_historico' => $valorAntigo,
                        'valor_novo_evento_historico'   => $valorNovo,
                        'id_usuario'                    => $idUsuario,
                        'data_evento_historico'         => now(),
                    ]);
                    $mudancas[$campo] = [$valorAntigo, $valorNovo];
                }
            }

            return $mudancas;
        });
    }

    /**
     * Cancelar/reativar e ocultar/mostrar. Na mesma transação, avisa os inscritos quando o evento sai
     * de ATIVO (CANCELAMENTO) ou volta a ATIVO (REATIVACAO); Notificacao::mudancaDeStatus decide.
     */
    public function mudarStatus(string $novo, ?int $idUsuario): void
    {
        DB::transaction(function () use ($novo, $idUsuario) {
            $antes = $this->status_evento_calendario;
            $this->atualizarComHistorico(['status_evento_calendario' => $novo], $idUsuario);
            $this->atletasNotificados += Notificacao::mudancaDeStatus($this, $antes, $idUsuario);
        });
    }

    /**
     * Status que o evento tinha antes de ser ocultado (o "valor antigo" da última vez que virou INATIVO).
     * Assim, "Mostrar" devolve um evento cancelado como cancelado. Sem histórico, volta como ATIVO.
     */
    public function statusAntesDeOcultar(): string
    {
        $anterior = $this->historico()
            ->where('campo_evento_historico', 'status_evento_calendario')
            ->where('valor_novo_evento_historico', 'INATIVO')
            ->value('valor_antigo_evento_historico');

        return in_array($anterior, ['ATIVO', 'CANCELADO'], true) ? $anterior : 'ATIVO';
    }

    // Horários do formulário vêm como "09:00"; no banco ficam "09:00:00". Sem isso, toda edição
    // registraria uma "mudança" de horário que não aconteceu.
    private static function normalizarHorarios(array $dados): array
    {
        foreach (['horario_inicio_evento_calendario', 'horario_fim_evento_calendario'] as $campo) {
            if (isset($dados[$campo]) && preg_match('/^\d{2}:\d{2}$/', $dados[$campo])) {
                $dados[$campo] .= ':00';
            }
        }

        return $dados;
    }

    // Valor comparável e legível para o histórico: data "Y-m-d", horário "H:i", vazio = null
    private function valorParaHistorico(string $campo, $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return match ($campo) {
            'data_evento_calendario'           => Carbon::parse($valor)->format('Y-m-d'),
            'horario_inicio_evento_calendario',
            'horario_fim_evento_calendario'    => substr((string) $valor, 0, 5),
            default                            => (string) $valor,
        };
    }

    // ── Status derivados ────────────────────────────────────────────────────

    /**
     * Concluído: data anterior a hoje, ou hoje com o horário de fim já passado (sem fim, vale o início;
     * sem nenhum dos dois, só no dia seguinte). Decisão (e) da Fase 4, CLAUDE.md seção 4.
     */
    public function estaConcluido(): bool
    {
        $hoje = now()->startOfDay();
        $data = $this->data_evento_calendario->copy()->startOfDay();

        if ($data->lt($hoje)) {
            return true;
        }

        if ($data->gt($hoje)) {
            return false;
        }

        $hora = $this->horario_fim_evento_calendario ?? $this->horario_inicio_evento_calendario;

        return $hora !== null && now()->format('H:i:s') >= substr($hora . ':00', 0, 8);
    }

    // A mesma regra de estaConcluido(), na consulta (agenda do app, paginada no banco)
    public function scopeConcluidos($query)
    {
        [$hoje, $agora] = [now()->toDateString(), now()->format('H:i:s')];

        return $query->where(fn ($q) => $q
            ->whereDate('data_evento_calendario', '<', $hoje)
            ->orWhere(fn ($dia) => $dia
                ->whereDate('data_evento_calendario', $hoje)
                ->whereRaw('COALESCE(horario_fim_evento_calendario, horario_inicio_evento_calendario) <= ?', [$agora])));
    }

    public function scopeNaoConcluidos($query)
    {
        [$hoje, $agora] = [now()->toDateString(), now()->format('H:i:s')];

        return $query->where(fn ($q) => $q
            ->whereDate('data_evento_calendario', '>', $hoje)
            ->orWhere(fn ($dia) => $dia
                ->whereDate('data_evento_calendario', $hoje)
                ->where(fn ($h) => $h
                    ->whereRaw('COALESCE(horario_fim_evento_calendario, horario_inicio_evento_calendario) IS NULL')
                    ->orWhereRaw('COALESCE(horario_fim_evento_calendario, horario_inicio_evento_calendario) > ?', [$agora]))));
    }

    // Teve data, horário ou local alterado (usa o withExists de comAlteracao() quando carregado)
    public function foiAlterado(): bool
    {
        if (array_key_exists('tem_alteracao', $this->attributes)) {
            return (bool) $this->attributes['tem_alteracao'];
        }

        return $this->historico()->whereIn('campo_evento_historico', self::CAMPOS_ALTERADO)->exists();
    }

    /**
     * Situação exibida no admin. Cancelado e oculto prevalecem (cancelado continua cancelado depois
     * da data); depois Concluído; depois Alterado (só enquanto o evento não aconteceu).
     * Nunca usar no site nem no app: lá não existe o selo "Alterado".
     */
    public function getSituacaoAttribute(): string
    {
        return match (true) {
            $this->estaCancelado() => 'CANCELADO',
            $this->estaOculto()    => 'INATIVO',
            $this->estaConcluido() => 'CONCLUIDO',
            $this->foiAlterado()   => 'ALTERADO',
            default                => 'ATIVO',
        };
    }

    public function getSituacaoLabelAttribute(): string
    {
        return self::SITUACOES[$this->situacao];
    }

    // ── Exibição ────────────────────────────────────────────────────────────

    public function getTipoClassAttribute(): string
    {
        return mb_strtolower($this->tipo_evento_calendario);
    }

    /**
     * Etiqueta acima do título no site (calendário e "Próximo Evento"); nada novo é gravado:
     * subtipo preenchido → o subtipo; jogo de campeonato → nome do campeonato; amistoso → "Amistoso";
     * outro evento → o rótulo do tipo. Para listas, carregar 'jogo.campeonato' junto (with).
     */
    public function getEtiquetaAttribute(): string
    {
        if (filled($this->subtipo_evento_calendario)) {
            return $this->subtipo_evento_calendario;
        }

        if ($jogo = $this->jogo) {
            return $jogo->campeonato?->nome_campeonato ?? 'Amistoso';
        }

        return $this->tipo_label;
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_evento_calendario] ?? $this->tipo_evento_calendario;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status_evento_calendario] ?? $this->status_evento_calendario;
    }

    public function estaCancelado(): bool
    {
        return $this->status_evento_calendario === 'CANCELADO';
    }

    public function estaOculto(): bool
    {
        return $this->status_evento_calendario === 'INATIVO';
    }

    // "09:00 às 11:00", "09:00" ou "A definir". Sem horário, Carbon::parse(null) mostraria a hora atual.
    public function getHorarioTextoAttribute(): string
    {
        $formatar = fn ($hora) => substr((string) $hora, 0, 5);

        if (! $this->horario_inicio_evento_calendario) {
            return 'A definir';
        }

        return $this->horario_fim_evento_calendario
            ? $formatar($this->horario_inicio_evento_calendario) . ' às ' . $formatar($this->horario_fim_evento_calendario)
            : $formatar($this->horario_inicio_evento_calendario);
    }
}
