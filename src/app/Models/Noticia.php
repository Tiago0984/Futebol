<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticia extends Model
{
    // 1. Indica ao Laravel o nome exato da tua tabela no banco
    protected $table = 'tbl_noticias';

    // 2. Define qual é a chave primária
    protected $primaryKey = 'id_noticia';

    // 3. Desativa os timestamps automatizados (created_at/updated_at), 
    // já que controlamos a data diretamente pelo campo 'data_publicacao_noticia'
    public $timestamps = false;

    // 4. Libera os campos para preenchimento em massa 
    // Muito importante para quando fizer o formulário de cadastro no painel!
    protected $fillable = [
        'titulo_noticia',
        'conteudo_noticia',
        'foto_noticia',
        'categoria_noticia',
        'data_publicacao_noticia',
        'autor_noticia',
        'status_noticia', // Adicionado aqui
    ];

    // Defina o cast para converter a string do MySQL em um objeto Carbon automaticamente
    protected $casts = [
        'data_publicacao_noticia' => 'datetime',
    ];
}
