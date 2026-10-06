<?php

namespace App\Http\Middleware;

use App\Models\Responsavel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas do responsável: recusa quem não tem mais nenhum filho ATIVO, mesmo com token válido (o filho foi
 * inativado ou rejeitado depois do login). Apaga os tokens dele e responde 403 com a mensagem do login,
 * como o atleta.ativo faz com o atleta.
 */
class ResponsavelComAtletaAtivo
{
    public const MENSAGEM = 'Nenhum atleta ativo vinculado a este responsável. Procure a secretaria da escolinha.';

    public function handle(Request $request, Closure $next): Response
    {
        $responsavel = $request->user();

        if ($responsavel instanceof Responsavel && ! $responsavel->podeEntrarNoApp()) {
            $responsavel->tokens()->delete();

            return response()->json(['success' => false, 'message' => self::MENSAGEM], 403);
        }

        return $next($request);
    }
}
