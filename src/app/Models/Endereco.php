<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Endereco extends Model
{
    protected $table = 'tbl_endereco';
    protected $primaryKey = 'id_endereco';
    public $timestamps = false;

    protected $fillable = [
        'rua_endereco',
        'numero_endereco',
        'bairro_endereco',
        'complemento_endereco',
        'cep_endereco',
        'cidade_endereco',
        'estado_endereco',
    ];

    public function atleta()
    {
        return $this->hasOne(Atleta::class, 'id_endereco', 'id_endereco');
    }

    public function responsavel()
    {
        return $this->hasOne(Responsavel::class, 'id_endereco', 'id_endereco');
    }
}
