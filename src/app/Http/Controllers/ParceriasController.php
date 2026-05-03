<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ParceriasController extends Controller
{
    public function parcerias()
    {
        return view('site.parcerias.parcerias');
    }
}
