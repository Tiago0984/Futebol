<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
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
