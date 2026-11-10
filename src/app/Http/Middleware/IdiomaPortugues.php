<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas da API (api/*) em português: as mensagens de validação (422) saem de lang/pt_BR. O admin e o site
 * continuam com o idioma do config/app.php.
 */
class IdiomaDaApi
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('pt_BR');

        return $next($request);
    }
}
