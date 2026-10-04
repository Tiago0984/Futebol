<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    // Tipos que aparecem no site público (provisório até o professor decidir)
    public const TIPOS_PUBLICOS = ['JOGO', 'TREINO', 'CAMPEONATO'];

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

    // id_usuario fica fora de propósito: o responsável é gravado só na criação (criarPor) e nenhum
    // update() com os dados do formulário pode trocá-lo
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
        'data_evento_calendario' => 'date',
    ];

    /**
     * Cria o evento já com o responsável (quem criou), que depois nunca muda. Evento com categoria
     * já nasce com os atletas ativos da categoria inscritos (origem CATEGORIA). Usado também pela
     * geração da grade (Fase 7).
     */
    public static function criarPor(?int $idUsuario, array $dados): self
    {
        return DB::transaction(function () use ($idUsuario, $dados) {
            $evento = new self(self::normalizarHorarios($dados));
            $evento->id_usuario = $idUsuario;
            $evento->save();

            if ($evento->id_categoria) {
                $evento->inscreverCategoria($evento->id_categoria, 'CATEGORIA', $idUsuario);
            }

            return $evento;
        });
    }

    // ── Inscrições ──────────────────────────────────────────────────────────

    public function inscricoes()
    {
        return $this->hasMany(EventoAtleta::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    /**
     * Inscreve um atleta. Já inscrito: não faz nada e devolve false (sem erro), para "adicionar
     * todos de uma categoria" poder ser usado várias vezes. Ponto único de inscrição: a notificação
     * de inscrição (Fase 8) entra aqui.
     */
    public function inscrever(int $idAtleta, string $origem, ?int $idUsuario): bool
    {
        if ($this->inscricoes()->where('id_atleta', $idAtleta)->exists()) {
            return false;
        }

        try {
            $this->inscricoes()->create([
                'id_atleta'            => $idAtleta,
                'origem_evento_atleta' => $origem,
                'id_usuario'           => $idUsuario,
                'data_evento_atleta'   => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return false; // outro admin inscreveu o mesmo atleta ao mesmo tempo
        }

        return true;
    }

    // Inscreve os atletas ativos com categoria ativa nesta categoria; devolve quantos entraram
    public function inscreverCategoria(int $idCategoria, string $origem, ?int $idUsuario): int
    {
        $novos = 0;

        foreach (Atleta::idsAtivosNaCategoria($idCategoria) as $idAtleta) {
            $novos += $this->inscrever($idAtleta, $origem, $idUsuario) ? 1 : 0;
        }

        return $novos;
    }

    public function removerInscricao(int $idAtleta): bool
    {
        return $this->inscricoes()->where('id_atleta', $idAtleta)->delete() > 0;
    }

    /**
     * Evento que mudou de categoria (ou ficou sem): as inscrições AUTOMÁTICAS acompanham a categoria
     * nova (sai quem não é dela, entra quem é); as individuais ficam. Quem chama decide se o evento
     * ainda pode mudar (concluído não muda). Devolve ['entraram' => n, 'sairam' => n].
     */
    public function sincronizarInscricoesPelaCategoria(?int $idUsuario): array
    {
        return DB::transaction(function () use ($idUsuario) {
            $daCategoria = $this->id_categoria ? Atleta::idsAtivosNaCategoria($this->id_categoria) : [];

            $sairam = $this->inscricoes()
                ->where('origem_evento_atleta', 'CATEGORIA')
                ->whereNotIn('id_atleta', $daCategoria)
                ->delete();

            $entraram = $this->id_categoria
                ? $this->inscreverCategoria($this->id_categoria, 'CATEGORIA', $idUsuario)
                : 0;

            return ['entraram' => $entraram, 'sairam' => $sairam];
        });
    }

    // Atletas ativos da categoria do evento que ainda não estão inscritos (quem entrou depois)
    public function idsFaltantesDaCategoria(): array
    {
        if (! $this->id_categoria) {
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
            $outro = $inscricao->evento->intervalo();

            if ($meu === null || $outro === null) {
                return ['atleta' => $inscricao->atleta, 'evento' => $inscricao->evento, 'fraco' => true];
            }

            $sobrepoe = $meu[0]->lt($outro[1]) && $outro[0]->lt($meu[1]);

            return $sobrepoe ? ['atleta' => $inscricao->atleta, 'evento' => $inscricao->evento, 'fraco' => false] : null;
        })->filter()->sortBy(fn ($c) => $c['atleta']->nome_atleta)->values();
    }

    // Texto de um conflito para a tela de confirmação
    public static function descreverConflito(array $conflito): string
    {
        $outro = $conflito['evento'];
        $quando = $outro->data_evento_calendario->format('d/m') . ', ' . $outro->horario_texto;

        return $conflito['fraco']
            ? "{$conflito['atleta']->nome_atleta}: também está em \"{$outro->titulo_evento_calendario}\" no mesmo dia ({$quando}); horário a definir, confira."
            : "{$conflito['atleta']->nome_atleta}: horário sobrepõe \"{$outro->titulo_evento_calendario}\" ({$quando}).";
    }

    // Eventos ativos (não cancelados nem ocultos) que ainda não aconteceram
    public function scopeFuturosAtivos($query)
    {
        return $query->where('status_evento_calendario', 'ATIVO')
            ->whereDate('data_evento_calendario', '>=', now()->toDateString())
            ->orderBy('data_evento_calendario')
            ->orderBy('horario_inicio_evento_calendario');
    }

    // Mesma lista, já sem os que terminaram hoje (regra de "Concluído")
    public static function futurosAtivosDaCategoria(int $idCategoria)
    {
        return self::futurosAtivos()
            ->where('id_categoria', $idCategoria)
            ->get()
            ->reject(fn (self $evento) => $evento->estaConcluido())
            ->values();
    }

    // Dados do jogo (times, campeonato, placar) quando o evento é um jogo cadastrado em Jogos
    public function jogo()
    {
        return $this->hasOne(Jogo::class, 'id_evento', 'id_evento_calendario');
    }

    /**
     * PROVISÓRIO (Fase 6, Etapa 1): o site ainda lê tbl_jogos.data_jogo. Enquanto a coluna existir,
     * ela acompanha a data e o horário do evento, mesmo quando o evento é editado pelo Calendário.
     * Sai na Etapa 2, junto com a coluna.
     */
    protected static function booted(): void
    {
        static::saved(function (self $evento) {
            if ($evento->wasChanged(['data_evento_calendario', 'horario_inicio_evento_calendario'])) {
                Jogo::where('id_evento', $evento->id_evento_calendario)->update(['data_jogo' => $evento->dataHoraDoJogo()]);
            }
        });
    }

    // Data e horário de início juntos ("Y-m-d H:i:s"), para tbl_jogos.data_jogo (provisório, ver booted)
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

    // Usuário do admin que criou o evento; null nos eventos anteriores à Fase 4
    public function responsavel()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
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
     * (valor antigo, valor novo, quem e quando), tudo na mesma transação.
     */
    public function atualizarComHistorico(array $dados, ?int $idUsuario): void
    {
        DB::transaction(function () use ($dados, $idUsuario) {
            $antes = [];
            foreach (array_keys(self::CAMPOS_HISTORICO) as $campo) {
                $antes[$campo] = $this->valorParaHistorico($campo, $this->getRawOriginal($campo));
            }

            $this->fill(self::normalizarHorarios($dados));
            $this->save();

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
                }
            }
        });
    }

    public function mudarStatus(string $novo, ?int $idUsuario): void
    {
        $this->atualizarComHistorico(['status_evento_calendario' => $novo], $idUsuario);
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
