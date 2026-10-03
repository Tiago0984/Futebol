<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoCalendario extends Model
{
    protected $table      = 'tbl_evento_calendario';
    protected $primaryKey = 'id_evento_calendario';
    public    $timestamps = false;

    // Valores do ENUM status_evento_calendario
    public const STATUS = ['CONFIRMADO', 'ALTERADO', 'CANCELADO'];

    // Status que aparecem no site público
    public const STATUS_VISIVEIS = ['CONFIRMADO', 'ALTERADO'];

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

    protected $fillable = [
        'titulo_evento_calendario',
        'descricao_evento_calendario',
        'tipo_evento_calendario',
        'subtipo_evento_calendario',
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

    public function getTipoClassAttribute(): string
    {
        return mb_strtolower($this->tipo_evento_calendario);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_evento_calendario] ?? $this->tipo_evento_calendario;
    }
}
