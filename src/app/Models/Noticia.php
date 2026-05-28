<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Noticia extends Model
{
    // Informa o nome exato da tabela no banco
    protected $table = 'tbl_noticias';

    // Informa qual é a chave primária da tabela
    protected $primaryKey = 'id_noticia';

    // Se a sua tabela não tiver as colunas created_at e updated_at, deixe como false
    public $timestamps = false; 

    // Quais campos podem ser preenchidos em massa
    protected $fillable = [
        'titulo_noticia',
        'conteudo_noticia',
        'data_publicacao_noticia',
        'autor_noticia'
    ];
}
