<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mensagens em português (lang/pt_BR): nas rotas da API (api/*) e, desde 10/11/2026, nas do painel (admin/*), onde
 * as regras sem mensagem própria apareciam em inglês ("The nome atleta field is required."), e no envio do
 * Matricule-se do site (cadastro.store). O resto do site continua com o idioma do config/app.php. Antes se chamava
 * IdiomaDaApi.
 */
class IdiomaPortugues
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('pt_BR');

        return $next($request);
    }
}
