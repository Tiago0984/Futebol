<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Galeria extends Model
{
    protected $table = 'tbl_galeria';
    protected $primaryKey = 'id_galeria';
    public $timestamps = false;

    protected $fillable = [
        'titulo_galeria',
        'foto_galeria',
        'categoria_galeria',
        'ordem_galeria',
        'status_galeria',
        'criado_em',
    ];

    protected $casts = [
        'criado_em' => 'datetime',
    ];
}
