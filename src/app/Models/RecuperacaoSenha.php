<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class RecuperacaoSenha extends Model
{
    use MassPrunable;

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

    // Limpeza diária (model:prune, routes/console.php). Depois de 1 dia o código (15 min) e o token
    // (10 min) já venceram e a trava de 60 s entre envios também passou: o registro não serve mais.
    // O DELETE bloqueia a linha e relê a versão atual: um reenvio concorrente renova as datas e a tira do filtro.
    public function prunable(): Builder
    {
        return static::where('data_codigo_recuperacao_senha', '<', now()->subDay())
            ->where(fn ($q) => $q->whereNull('data_expiracao_codigo_recuperacao_senha')
                ->orWhere('data_expiracao_codigo_recuperacao_senha', '<', now()))
            ->where(fn ($q) => $q->whereNull('data_expiracao_token_recuperacao_senha')
                ->orWhere('data_expiracao_token_recuperacao_senha', '<', now()));
    }
}
