<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $table      = 'tbl_videos';
    protected $primaryKey = 'id_video';

    const CREATED_AT = 'criado_em_video';
    const UPDATED_AT = 'atualizado_em_video';

    protected $fillable = [
        'titulo_video',
        'url_video',
        'descricao_video',
        'status_video',
        'ao_vivo_video',
        'secao_video',
    ];

    /** Quantos vídeos a seção do site mostra: o do player e os outros nas miniaturas. */
    public const MAX_NO_SITE = 7;

    /**
     * Vídeos ativos de uma seção do site ('home' ou 'campeonatos'), mais os marcados para as duas ('ambas'):
     * o ao vivo primeiro (vai para o player), depois do mais novo para o mais antigo.
     */
    public function scopeDoSite($query, string $secao)
    {
        return $query->where('status_video', 'ATIVO')
            ->whereIn('secao_video', [$secao, 'ambas'])
            ->orderByDesc('ao_vivo_video')
            ->orderByDesc('id_video')
            ->limit(self::MAX_NO_SITE);
    }

    /** Título mostrado no site: "ASSISTA AO VIVO" no ao vivo; senão, o título em maiúsculas. */
    public function getTituloNoSiteAttribute(): string
    {
        return $this->ao_vivo_video ? 'ASSISTA AO VIVO' : mb_strtoupper($this->titulo_video);
    }

    /** Descrição mostrada no site (o ao vivo não mostra). */
    public function getDescricaoNoSiteAttribute(): string
    {
        return $this->ao_vivo_video ? '' : (string) $this->descricao_video;
    }

    public function getEmbedUrlAttribute(): string
    {
        $url = $this->url_video;

        // YouTube: watch?v=ID ou youtu.be/ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo: vimeo.com/ID
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $url;
    }

    public function getThumbnailAttribute(): string
    {
        $url = $this->url_video;

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
        }

        return '';
    }
}
