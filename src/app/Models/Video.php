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
