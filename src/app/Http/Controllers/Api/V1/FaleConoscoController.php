<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaleConoscoRequest;
use App\Services\RecebeFaleConosco;
use Illuminate\Http\JsonResponse;

class FaleConoscoController extends Controller
{
    public function store(FaleConoscoRequest $request, RecebeFaleConosco $servico): JsonResponse
    {
        $mensagem = $servico->receber($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Mensagem recebida com sucesso.',
            'data' => [
                'id_fale_conosco' => $mensagem->getKey(),
                'data_envio' => $mensagem->data_fale_conosco->toIso8601String(),
            ],
        ], 201);
    }
}
