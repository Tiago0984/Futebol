<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DispositivoRequest;
use App\Models\Dispositivo;
use Illuminate\Http\JsonResponse;

class DispositivoController extends Controller
{
    public function store(DispositivoRequest $request): JsonResponse
    {
        Dispositivo::registrar($request->user(), $request->validated());

        return response()->json(['success' => true]);
    }

    public function destroy(DispositivoRequest $request): JsonResponse
    {
        Dispositivo::daConta($request->user())->where('token', $request->validated('token'))->delete();

        return response()->json(['success' => true]);
    }
}
