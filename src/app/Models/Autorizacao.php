<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Autorizacao extends Model
{
    protected $table = 'tbl_autorizacoes';
    protected $primaryKey = 'id_autorizacao';
    public $timestamps = false;

    protected $fillable = [
        'id_atleta',
        'id_responsavel',
        'data_assinatura_autorizacao',
        'token_assinatura',
        'status_autorizacao',
    ];

    protected $casts = [
        'data_assinatura_autorizacao' => 'datetime',
    ];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function responsavel()
    {
        return $this->belongsTo(Responsavel::class, 'id_responsavel', 'id_responsavel');
    }
}
