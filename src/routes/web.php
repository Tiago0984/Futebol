<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SobreController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\CampeonatoController;
use App\Http\Controllers\NoticiasController;
use App\Http\Controllers\ShoppingController;
use App\Http\Controllers\ParceriasController;
use App\Http\Controllers\ContatoController;

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::get('/sobre', [SobreController::class, 'sobre'])->name('sobre');

Route::get('/agenda', [AgendaController::class, 'agenda'])->name('agenda');

Route::get('/campeonato', [CampeonatoController::class, 'campeonato'])->name('campeonato');

Route::get('/noticias', [NoticiasController::class, 'noticias'])->name('noticias');

Route::get('/shopping', [ShoppingController::class, 'shopping'])->name('shopping');

Route::get('/parcerias', [ParceriasController::class, 'parcerias'])->name('parcerias');

Route::get('/contato', [ContatoController::class, 'contato'])->name('contato');


