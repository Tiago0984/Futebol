<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ParceriasController extends Controller
{
    public function parcerias()
    {
        return view('site.parcerias.parcerias');
    }
}
