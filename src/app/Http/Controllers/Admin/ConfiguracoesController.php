<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

/**
 * Configurações do painel: aparência (modo escuro, guardado no navegador) e minha conta (atalho para o perfil).
 * Sem a situação do sistema nem atalhos técnicos (decisão do dono do projeto, 10/11/2026: quem administra a
 * escolinha não precisa dessas informações).
 */
class ConfiguracoesController extends Controller
{
    public function index()
    {
        return view('admin.configuracoes.index');
    }
}
