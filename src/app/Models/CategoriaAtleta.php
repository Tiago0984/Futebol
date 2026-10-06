<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Linha de tbl_categoria_atleta lida pelo pivô de Atleta::categorias(). Só para ler: as datas viram Carbon
 * e saem no JSON da API com o fuso (2026-10-01T09:00:00-03:00), como as outras datas (Fase 9). A gravação
 * continua em Atleta::trocarCategoria(), direto na tabela.
 */
class CategoriaAtleta extends Pivot
{
    use SerializaDatasComFuso;

    protected $table = 'tbl_categoria_atleta';

    protected $casts = [
        'data_inicio_categoria_atleta'      => 'datetime',
        'data_fim_categoria_atleta'         => 'datetime',
        'data_atualizacao_categoria_atleta' => 'datetime',
    ];
}
