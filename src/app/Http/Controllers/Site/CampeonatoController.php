<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CampeonatoController extends Controller
{
    public function campeonato()
    {
        return view('site.campeonatos.campeonatos');
    }
}
