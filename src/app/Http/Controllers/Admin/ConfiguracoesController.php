<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfiguracoesController extends Controller
{
    /**
     * Exibe a página de configurações do painel administrativo.
     */
    public function index()
    {
        return view('admin.configuracoes.index');
    }
}
