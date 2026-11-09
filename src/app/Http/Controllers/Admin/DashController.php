<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InicioDoPainel;

/**
 * Página inicial do painel (09/11/2026): o que precisa de atenção, os números do dia, a agenda dos próximos
 * 7 dias, os últimos resultados e o acesso rápido. Os dados ficam em App\Services\InicioDoPainel.
 * Sem try/catch: um erro aparece como erro, e não como um painel zerado e "tudo em dia".
 */
class DashController extends Controller
{
    public function index(InicioDoPainel $inicio)
    {
        $numeros = $inicio->numeros();

        return view('admin.dash.dashboard', [
            'pendencias' => $inicio->pendencias(),
            'numeros'    => $numeros,
            'agenda'     => $inicio->agenda(),
            'resultados' => $inicio->resultados(),
            'atalhos'    => $inicio->atalhos($numeros),
        ]);
    }
}
