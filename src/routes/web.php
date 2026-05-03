<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SobreController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ParceriasController;
use App\Http\Controllers\ContatoController;

Route::get('/', [HomeController::class, 'home'])->name('home');
Route::get('/sobre', [SobreController::class, 'sobre'])->name('sobre');
Route::get('/agenda', [AgendaController::class, 'agenda'])->name('agenda');
Route::get('/evento', [EventoController::class, 'evento'])->name('evento');
Route::get('/portfolio', [PortfolioController::class, 'portfolio'])->name('portfolio');
Route::get('/parcerias', [ParceriasController::class, 'parcerias'])->name('parcerias');
Route::get('/contato', [ContatoController::class, 'contato'])->name('contato');
