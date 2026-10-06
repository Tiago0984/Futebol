<?php

namespace App\Http\Middleware;

use App\Models\Atleta;
use App\Models\Responsavel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas da API por perfil (Fase 9): token de atleta só nas rotas do atleta (perfil:atleta), token de
 * responsável só nas do responsável (perfil:responsavel). O Sanctum aceita o token dos dois models;
 * é aqui que um não entra na área do outro.
 */
class PerfilDoApp
{
    private const MODELS = [
        'atleta'      => Atleta::class,
        'responsavel' => Responsavel::class,
    ];

    public function handle(Request $request, Closure $next, string $perfil): Response
    {
        $model = self::MODELS[$perfil] ?? null;

        if (! $model || ! $request->user() instanceof $model) {
            return response()->json([
                'success' => false,
                'message' => $perfil === 'responsavel'
                    ? 'Esta área é do perfil responsável. Entre no app como responsável.'
                    : 'Esta área é do perfil atleta. Entre no app como atleta.',
            ], 403);
        }

        return $next($request);
    }
}
