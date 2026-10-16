<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Posição do atleta (Fase D, Etapa D1): até 3 por atleta, da lista fixa Atleta::POSICOES, em ordem
 * (1 = a principal). Gravadas só por Atleta::definirPosicoes.
 */
class AtletaPosicao extends Model
{
    protected $table      = 'tbl_atleta_posicao';
    protected $primaryKey = 'id_atleta_posicao';
    public    $timestamps = false;

    protected $fillable = [
        'id_atleta',
        'posicao_atleta_posicao',
        'ordem_atleta_posicao',
    ];

    protected $casts = [
        'ordem_atleta_posicao' => 'integer',
    ];

    // No JSON da API: o código, a sigla e o nome ({"posicao": "VOLANTE", "sigla": "VOL", "nome": "Volante"})
    protected $visible = ['posicao', 'sigla', 'nome', 'ordem_atleta_posicao'];
    protected $appends = ['posicao', 'sigla', 'nome'];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function getPosicaoAttribute(): string
    {
        return $this->posicao_atleta_posicao;
    }

    public function getSiglaAttribute(): string
    {
        return Atleta::POSICOES[$this->posicao_atleta_posicao]['sigla'] ?? $this->posicao_atleta_posicao;
    }

    public function getNomeAttribute(): string
    {
        return Atleta::POSICOES[$this->posicao_atleta_posicao]['nome'] ?? $this->posicao_atleta_posicao;
    }
}
