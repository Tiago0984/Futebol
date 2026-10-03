<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoCalendario extends Model
{
    protected $table      = 'tbl_evento_calendario';
    protected $primaryKey = 'id_evento_calendario';
    public    $timestamps = false;

    // Valores do ENUM status_evento_calendario => rótulo. "Alterado" e "Concluído" não são gravados:
    // são derivados (Fase 4, Etapa 3). INATIVO = oculto (substitui a exclusão).
    public const STATUS = [
        'ATIVO'     => 'Ativo',
        'CANCELADO' => 'Cancelado',
        'INATIVO'   => 'Oculto',
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

    // Cria o evento já com o responsável (quem criou), que depois nunca muda
    public static function criarPor(?int $idUsuario, array $dados): self
    {
        $evento = new self($dados);
        $evento->id_usuario = $idUsuario;
        $evento->save();

        return $evento;
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
