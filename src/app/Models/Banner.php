<?php

namespace App\Models;

use App\Models\Concerns\UrlDeImagem;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use UrlDeImagem;

    // Endereço completo da imagem no JSON da API (o banco guarda só o nome do arquivo)
    protected $appends = ['foto_url'];

    protected $table = 'tbl_banner';
    protected $primaryKey = 'id_banner';
    public $timestamps = false;

    protected $fillable = [
        'titulo_banner',
        'subtitulo_banner',
        'foto_banner',
        'ordem_banner',
        'status_banner',
        'criado_em',
    ];

    protected $casts = [
        'criado_em' => 'datetime',
    ];

    public function getFotoUrlAttribute(): ?string
    {
        return static::urlDaImagem($this->attributes['foto_banner'] ?? null, 'banner');
    }
}
