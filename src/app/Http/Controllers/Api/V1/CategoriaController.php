<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;

class CategoriaController extends Controller
{
    // GET /api/v1/categorias - categorias ativas (Sub-9, Sub-11...)
    public function index()
    {
        $categorias = Categoria::where('status_categoria', 'ATIVO')
            ->orderBy('idade_min_categoria')
            ->get([
                'id_categoria',
                'nome_categoria',
                'idade_min_categoria',
                'idade_max_categoria',
                'sexo_categoria',
                'status_categoria',
            ]);

        return response()->json([
            'success' => true,
            'data'    => $categorias,
        ]);
    }

    // GET /api/v1/categorias/{id}/times - times ativos de uma categoria
    public function times($id)
    {
        $categoria = Categoria::where('status_categoria', 'ATIVO')->find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria não encontrada.',
            ], 404);
        }

        $times = $categoria->times()
            ->where('status_time', 'ATIVO')
            ->orderBy('nome_time')
            ->get(['id_time', 'nome_time', 'logo_time', 'tipo_time', 'id_categoria']);

        return response()->json([
            'success' => true,
            'data'    => [
                'categoria' => $categoria->only(['id_categoria', 'nome_categoria']),
                'times'     => $times,
            ],
        ]);
    }
}
