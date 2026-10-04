<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;
use App\Models\Time;
use App\Models\Jogo;

class Campeonato extends Model
{
    use SerializaDatasComFuso; // datas no JSON da API: 2025-01-01T00:00:00-03:00

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
        'status_campeonato',
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
}
