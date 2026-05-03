<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function evento()
    {
        return view('site.evento.evento');
    }
}
