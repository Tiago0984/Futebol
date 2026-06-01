<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscricao extends Model
{
    protected $table = 'tbl_inscricao';
    protected $primaryKey = 'id_inscricao';
    public $timestamps = false;

    protected $fillable = [
        'id_atleta',
        'id_categoria',
        'data_inscricao',
        'status_inscricao',
    ];

    protected $casts = [
        'data_inscricao' => 'datetime',
    ];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }
}
