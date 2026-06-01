<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campeonato extends Model
{
    protected $table = 'tbl_campeonato';
    protected $primaryKey = 'id_campeonato';
    public $timestamps = false;

    protected $fillable = [
        'logo_evento',
        'banner_evento',
        'nome_campeonato',
        'organizador_campeonato',
        'descricao_campeonato',
        'tipo_campeonato',
        'data_inicio_campeonato',
        'data_fim_campeonato',
        'local_evento',
        'id_categoria',
    ];

    protected $casts = [
        'data_inicio_campeonato' => 'datetime',
        'data_fim_campeonato'    => 'datetime',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_campeonato_time', 'id_campeonato', 'id_time');
    }

    public function jogos()
    {
        return $this->hasMany(Jogo::class, 'id_campeonato', 'id_campeonato');
    }

    public function cartoes()
    {
        return $this->hasMany(Cartao::class, 'id_campeonato', 'id_campeonato');
    }
}
