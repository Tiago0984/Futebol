<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecuperacaoSenha extends Model
{
    protected $table = 'tbl_recuperacao_senha';
    protected $primaryKey = 'id_recuperacao_senha';
    public $timestamps = false;

    // Somente o serviço preenche este registro; hashes nunca devem ir para o JSON.
    protected $guarded = ['id_recuperacao_senha'];
    protected $hidden = ['codigo_hash_recuperacao_senha', 'reset_token_hash_recuperacao_senha'];
    protected $casts = [
        'data_codigo_recuperacao_senha' => 'datetime',
        'data_expiracao_codigo_recuperacao_senha' => 'datetime',
        'data_expiracao_token_recuperacao_senha' => 'datetime',
        'tentativas_recuperacao_senha' => 'integer',
    ];
}
