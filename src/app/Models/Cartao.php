<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cartao extends Model
{
    protected $table = 'tbl_cartoes';
    protected $primaryKey = 'id_cartao';
    public $timestamps = false;

    protected $fillable = [
        'id_campeonato',
        'id_atleta',
        'id_jogo',
        'tipo_cartao',
        'data_cartao',
    ];

    protected $casts = [
        'data_cartao' => 'datetime',
    ];

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class, 'id_campeonato', 'id_campeonato');
    }

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function jogo()
    {
        return $this->belongsTo(Jogo::class, 'id_jogo', 'id_jogo');
    }
}
