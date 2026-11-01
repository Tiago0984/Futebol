<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    use SerializaDatasComFuso;

    protected $table = 'tbl_dispositivo';
    protected $primaryKey = 'id_dispositivo';
    public const CREATED_AT = 'data_criacao_dispositivo';
    public const UPDATED_AT = 'data_atualizacao_dispositivo';

    protected $hidden = ['token'];

    protected $casts = [
        'ativo_dispositivo' => 'boolean',
        'data_ultimo_uso' => 'datetime',
    ];

    public function scopeDaConta(Builder $query, Atleta|Responsavel $conta): Builder
    {
        return $query->where($conta instanceof Atleta ? 'id_atleta' : 'id_responsavel', $conta->getKey());
    }

    public static function registrar(Atleta|Responsavel $conta, array $dados): void
    {
        // UPSERT atômico pelo índice único: limpa o dono anterior, inclusive ao trocar de perfil.
        self::upsert([[
            'token' => $dados['token'],
            'id_atleta' => $conta instanceof Atleta ? $conta->getKey() : null,
            'id_responsavel' => $conta instanceof Responsavel ? $conta->getKey() : null,
            'plataforma' => $dados['plataforma'],
            'ativo_dispositivo' => $dados['notificacoes_ativas'],
            'data_ultimo_uso' => now(),
        ]], ['token'], ['id_atleta', 'id_responsavel', 'plataforma', 'ativo_dispositivo', 'data_ultimo_uso']);
    }
}
