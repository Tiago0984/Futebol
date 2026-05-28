<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CalendarioController extends Controller
{
    public function calendario()
    {
        return view('site.calendario.calendario');
    }
}
