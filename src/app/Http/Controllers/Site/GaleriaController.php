<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Galeria;

class GaleriaController extends Controller
{
    public function index()
    {
        $galerias = Galeria::where('status_galeria', 'ATIVO')
            ->orderBy('ordem_galeria')
            ->get();

        $categorias = $galerias->pluck('categoria_galeria')
            ->unique()
            ->filter(fn($cat) => $cat !== 'GERAL')
            ->values();

        return view('site.galeria.galeria', compact('galerias', 'categorias'));
    }
}
