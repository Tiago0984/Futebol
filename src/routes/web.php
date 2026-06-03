<?php

//Site
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\SobreController;
use App\Http\Controllers\Site\CalendarioController;
use App\Http\Controllers\Site\CampeonatoController;
use App\Http\Controllers\Site\NoticiasController;
use App\Http\Controllers\Site\ShoppingController;
use App\Http\Controllers\Site\ParceriasController;
use App\Http\Controllers\Site\ContatoController;

//Dashboard
use App\Http\Controllers\Admin\DashController;
use App\Http\Controllers\Admin\NoticiasController as AdminNoticiasController;
use App\Http\Controllers\Admin\BannersController;
use App\Http\Controllers\Admin\GaleriaController;
use App\Http\Controllers\Admin\CampeonatosController;
use App\Http\Controllers\Admin\TimesController;
use App\Http\Controllers\Admin\JogosController;
use App\Http\Controllers\Admin\CategoriasController;
use App\Http\Controllers\Admin\AtletasController;
use App\Http\Controllers\Admin\InscricoesController;

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::get('/sobre', [SobreController::class, 'sobre'])->name('sobre');

Route::get('/calendario', [CalendarioController::class, 'calendario'])->name('calendario');

Route::get('/campeonato', [CampeonatoController::class, 'campeonato'])->name('campeonato');


Route::get('/noticias', [NoticiasController::class, 'noticias'])->name('noticias');

    // Filtrar por Assunto/Categoria
    Route::get('/noticias/categoria/{categoria}', [NoticiasController::class, 'filtrarPorCategoria'])->name('site.noticias.categoria');

    // Página Interna da Notícia Completa por ID
    Route::get('/noticias/post/{id}', [NoticiasController::class, 'show'])->name('site.noticias.show-noticia');


Route::get('/shopping', [ShoppingController::class, 'shopping'])->name('shopping');

Route::get('/parcerias', [ParceriasController::class, 'parcerias'])->name('parcerias');
//Formulário de parcerias:
Route::post('/parcerias', [ParceriasController::class, 'form'])->name('parcerias.form'); // ← falta isso

Route::get('/contato', [ContatoController::class, 'contato'])->name('contato');

Route::get('/campeonato', [CampeonatoController::class, 'campeonato'])->name('campeonato');
Route::get('/campeonato/{id}', [CampeonatoController::class, 'show'])->name('campeonato.show');


Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [DashController::class, 'index'])->name('dash');
    Route::get('/dashboard', [DashController::class, 'index'])->name('dashboard');

    // Conteúdo do site
    Route::resource('noticias',  AdminNoticiasController::class);
    Route::resource('banners',   BannersController::class);
    Route::resource('galeria',   GaleriaController::class);

    // Esporte
    Route::resource('campeonatos', CampeonatosController::class);
    Route::resource('times',       TimesController::class);
    Route::resource('jogos',       JogosController::class);
    Route::resource('categorias',  CategoriasController::class);

    // Pessoas
    Route::resource('atletas',    AtletasController::class);
    Route::patch('atletas/{id}/toggle-status', [AtletasController::class, 'toggleStatus'])->name('atletas.toggleStatus');
    Route::get('inscricoes',          [InscricoesController::class, 'index'])->name('inscricoes.index');
    Route::get('inscricoes/{id}',     [InscricoesController::class, 'show'])->name('inscricoes.show');
    Route::delete('inscricoes/{id}',  [InscricoesController::class, 'destroy'])->name('inscricoes.destroy');

});

