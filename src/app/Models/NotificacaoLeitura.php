<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Leitura de uma notificação do atleta por um responsável (Fase 9): cada responsável tem a própria marca.
 * A leitura do atleta continua em tbl_notificacao.data_leitura_notificacao. Único no par.
 */
class NotificacaoLeitura extends Model
{
    protected $table      = 'tbl_notificacao_leitura';
    protected $primaryKey = 'id_notificacao_leitura';
    public    $timestamps = false;

    protected $fillable = [
        'id_notificacao',
        'id_responsavel',
        'data_notificacao_leitura',
    ];

    protected $casts = [
        'data_notificacao_leitura' => 'datetime',
    ];

    public function notificacao()
    {
        return $this->belongsTo(Notificacao::class, 'id_notificacao', 'id_notificacao');
    }

    public function responsavel()
    {
        return $this->belongsTo(Responsavel::class, 'id_responsavel', 'id_responsavel');
    }
}
