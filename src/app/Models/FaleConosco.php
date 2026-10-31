<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;

class FaleConosco extends Model
{
    use SerializaDatasComFuso;

    protected $table = 'tbl_fale_conosco';
    protected $primaryKey = 'id_fale_conosco';
    public $timestamps = false;

    public const PERFIS = ['ATLETA' => 'Atleta', 'RESPONSAVEL' => 'Responsável', 'VISITANTE' => 'Visitante'];

    protected $fillable = [
        'id_atleta', 'id_responsavel', 'origem_fale_conosco', 'perfil_fale_conosco',
        'nome_fale_conosco', 'email_fale_conosco', 'telefone_fale_conosco',
        'assunto_fale_conosco', 'mensagem_fale_conosco', 'data_fale_conosco',
    ];

    protected $casts = [
        'data_fale_conosco' => 'datetime',
        'data_leitura_fale_conosco' => 'datetime',
    ];
}
