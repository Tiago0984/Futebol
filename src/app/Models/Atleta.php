<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atleta extends Model
{
    protected $table = 'tbl_atletas';
    protected $primaryKey = 'id_atleta';
    public $timestamps = false;

    protected $fillable = [
        'nome_atleta',
        'data_nasc_atleta',
        'rg_atleta',
        'cpf_atleta',
        'numero_atleta',
        'peso_atleta',
        'altura_atleta',
        'sexo_atleta',
        'escola_atleta',
        'serie_atleta',
        'descricao_atleta',
        'foto_atleta',
        'sala_atleta',
        'periodo_escolar_atleta',
        'status_atleta',
        'id_endereco',
    ];

    protected $casts = [
        'data_nasc_atleta' => 'date',
        'peso_atleta'      => 'decimal:2',
        'altura_atleta'    => 'decimal:2',
    ];

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'id_endereco', 'id_endereco');
    }

    public function responsaveis()
    {
        return $this->belongsToMany(Responsavel::class, 'tbl_atleta_responsavel', 'id_atleta', 'id_responsavel')
                    ->withPivot('grau_parentesco_responsavel');
    }

    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_atleta_time', 'id_atleta', 'id_time')
                    ->withPivot([
                        'camisa_atleta_time',
                        'posicao_atleta_time',
                        'jogos_atleta_time',
                        'convocacao_atleta_time',
                        'gols_atleta_time',
                        'defesas_atleta_time',
                    ]);
    }

    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_categoria_atleta', 'id_atleta', 'id_categoria')
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
        return $this->hasMany(Inscricao::class, 'id_atleta', 'id_atleta');
    }

    public function cartoes()
    {
        return $this->hasMany(Cartao::class, 'id_atleta', 'id_atleta');
    }

    public function autorizacoes()
    {
        return $this->hasMany(Autorizacao::class, 'id_atleta', 'id_atleta');
    }
}
