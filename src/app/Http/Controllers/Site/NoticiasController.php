<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NoticiasController extends Controller
{
    public function noticias()
    {
        return view('site.noticias.noticias');
    }
}
