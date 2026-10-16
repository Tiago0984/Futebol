<?php

namespace App\Http\Middleware;

use App\Models\EventoCalendario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Afastamento (16/10/2026): o EventoCalendario::inscrever() deixa de fora quem está afastado na data do evento,
 * em qualquer caminho (criar evento ou jogo, preencher pela convocação, trocar time, adicionar categoria, mover
 * inscrições). Este middleware, nas rotas do admin, transforma a lista dessa requisição no aviso
 * "avisos_afastamento" da próxima tela (partial admin.partials.avisos-afastamento).
 */
class AvisaAfastadosIgnorados
{
    public function handle(Request $request, Closure $next): Response
    {
        EventoCalendario::$afastadosIgnorados = [];

        $resposta = $next($request);

        if (EventoCalendario::$afastadosIgnorados && $request->hasSession()) {
            $request->session()->flash('avisos_afastamento', EventoCalendario::textosDosAfastadosIgnorados());
        }
        EventoCalendario::$afastadosIgnorados = [];

        return $resposta;
    }
}
