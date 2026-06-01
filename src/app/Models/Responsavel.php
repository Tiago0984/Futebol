<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Responsavel extends Model
{
    protected $table = 'tbl_responsavel';
    protected $primaryKey = 'id_responsavel';
    public $timestamps = false;

    protected $fillable = [
        'nome_responsavel',
        'cpf_responsavel',
        'rg_responsavel',
        'telefone_responsavel',
        'whatsapp_responsavel',
        'assinatura_responsavel',
        'aceite_responsavel',
        'id_endereco',
    ];

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'id_endereco', 'id_endereco');
    }

    public function atletas()
    {
        return $this->belongsToMany(Atleta::class, 'tbl_atleta_responsavel', 'id_responsavel', 'id_atleta')
                    ->withPivot('grau_parentesco_responsavel');
    }

    public function autorizacoes()
    {
        return $this->hasMany(Autorizacao::class, 'id_responsavel', 'id_responsavel');
    }
}
