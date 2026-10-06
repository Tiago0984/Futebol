<?php

namespace App\Http\Middleware;

use App\Models\Atleta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas protegidas da API: recusa atleta que não está ATIVO, mesmo com token válido (ex.: status mudado
 * direto no banco, sem passar pelo model, que já apaga os tokens). Apaga os tokens dele e responde 403
 * com a mesma mensagem do login.
 */
class AtletaAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $atleta = $request->user();

        if ($atleta instanceof Atleta && strtoupper((string) $atleta->status_atleta) !== 'ATIVO') {
            $atleta->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Cadastro não está ativo. Procure a secretaria da escolinha.',
            ], 403);
        }

        return $next($request);
    }
}
