<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShoppingController extends Controller
{
    public function shopping()
    {
        return view('site.shopping.shopping');
    }
}
