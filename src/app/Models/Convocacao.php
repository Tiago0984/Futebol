<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Convocação por campeonato (Fase G, Etapa 1): o atleta convocado por um time interno (a "camisa" da AACJ
 * naquele campeonato), com o número da camisa, quem convocou e quando. Um time por atleta em cada campeonato
 * e camisa sem repetir no mesmo time do campeonato (os dois únicos da tabela). Atleta inativado continua com
 * a linha, mas não conta (como nas inscrições). As regras ficam em Campeonato (convocar, trocarCamisa).
 */
class Convocacao extends Model
{
    protected $table      = 'tbl_convocacao';
    protected $primaryKey = 'id_convocacao';
    public    $timestamps = false;

    // Faixa do número da camisa
    public const CAMISA_MIN = 1;
    public const CAMISA_MAX = 99;

    // id_usuario (quem convocou) fica fora: gravado só por Campeonato::convocar
    protected $fillable = [
        'id_campeonato',
        'id_time',
        'id_atleta',
        'camisa_convocacao',
    ];

    protected $casts = [
        'camisa_convocacao' => 'integer',
        'data_convocacao'   => 'datetime',
    ];

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class, 'id_campeonato', 'id_campeonato');
    }

    public function time()
    {
        return $this->belongsTo(Time::class, 'id_time', 'id_time');
    }

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Só atletas ATIVO contam (inativado ou rejeitado fica com a linha)
    public function scopeDeAtivos($query)
    {
        return $query->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'));
    }
}
