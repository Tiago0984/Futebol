<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'tbl_categoria';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;

    protected $fillable = [
        'nome_categoria',
        'idade_min_categoria',
        'idade_max_categoria',
        'sexo_categoria',
        'status_categoria',
    ];

    public function campeonatos()
    {
        return $this->hasMany(Campeonato::class, 'id_categoria', 'id_categoria');
    }

    public function times()
    {
        return $this->hasMany(Time::class, 'id_categoria', 'id_categoria');
    }

    public function atletas()
    {
        return $this->belongsToMany(Atleta::class, 'tbl_categoria_atleta', 'id_categoria', 'id_atleta')
                    ->withPivot([
                        'data_inicio_categoria_atleta',
                        'data_fim_categoria_atleta',
                        'data_atualizacao_categoria_atleta',
                        'status_categoria_atleta',
                        'observacao_categoria_atleta',
                    ]);
    }

    public function inscricoes()
    {
        return $this->hasMany(Inscricao::class, 'id_categoria', 'id_categoria');
    }
}
