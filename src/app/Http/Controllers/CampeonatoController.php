<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CampeonatoController extends Controller
{
    public function campeonato()
    {
        return view('site.campeonatos.campeonatos');
    }
}
