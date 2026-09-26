<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;

class BannerController extends Controller
{
    // GET /api/v1/banners - banners ativos na ordem de exibição
    public function index()
    {
        $banners = Banner::where('status_banner', 'ATIVO')
            ->orderBy('ordem_banner')
            ->get([
                'id_banner',
                'titulo_banner',
                'subtitulo_banner',
                'foto_banner',
                'ordem_banner',
                'status_banner',
            ]);

        return response()->json([
            'success' => true,
            'data'    => $banners,
        ]);
    }
}
