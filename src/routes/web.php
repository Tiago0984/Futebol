<?php

//Site
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\SobreController;
use App\Http\Controllers\Site\CalendarioController;
use App\Http\Controllers\Site\AtletasController;
use App\Http\Controllers\Site\CampeonatoController;
use App\Http\Controllers\Site\NoticiasController;
use App\Http\Controllers\Site\ShoppingController;
use App\Http\Controllers\Site\ParceriasController;
use App\Http\Controllers\Site\ContatoController;
use App\Http\Controllers\Site\CadastroController;
use App\Http\Controllers\Site\AssinaturaController;
use App\Http\Controllers\Site\GaleriaController as SiteGaleriaController;

//Dashboard
use App\Http\Controllers\Admin\DashController;
use App\Http\Controllers\Admin\NoticiasController as AdminNoticiasController;
use App\Http\Controllers\Admin\BannersController;
use App\Http\Controllers\Admin\GaleriaController;
use App\Http\Controllers\Admin\CampeonatosController;
use App\Http\Controllers\Admin\TimesController;
use App\Http\Controllers\Admin\JogosController;
use App\Http\Controllers\Admin\CategoriasController;
use App\Http\Controllers\Admin\AtletasController as AdminAtletasController;
use App\Http\Controllers\Admin\InscricoesController;
use App\Http\Controllers\Admin\MatriculasController;
use App\Http\Controllers\Admin\LoginController;

Route::get('/', [HomeController::class, 'home'])->name('home');

Route::get('/sobre', [SobreController::class, 'sobre'])->name('sobre');

Route::get('/calendario', [CalendarioController::class, 'calendario'])->name('calendario');

Route::get('/jogadores', [AtletasController::class, 'vitrine'])->name('jogadores.vitrine');

Route::get('/campeonato', [CampeonatoController::class, 'campeonato'])->name('campeonato');
Route::get('/campeonato/{id}', [CampeonatoController::class, 'show'])->name('campeonato.show');

Route::get('/noticias', [NoticiasController::class, 'index'])->name('noticias.index');
Route::get('/noticias/categoria/{categoria}', [NoticiasController::class, 'filtrarPorCategoria'])->name('site.noticias.categoria');

Route::get('/noticias/post/{id}', [NoticiasController::class, 'show'])->name('site.noticias.show-noticia');

Route::get('/shopping', [ShoppingController::class, 'shopping'])->name('shopping');

Route::get('/galeria', [SiteGaleriaController::class, 'index'])->name('galeria.index');

Route::get('/parcerias', [ParceriasController::class, 'parcerias'])->name('parcerias');
Route::post('/parcerias', [ParceriasController::class, 'form'])->name('parcerias.form');

Route::get('/contato', [ContatoController::class, 'contato'])->name('contato');

// Cadastro público de atletas
Route::get('/cadastro',  [CadastroController::class, 'index'])->name('cadastro.index');
Route::post('/cadastro', [CadastroController::class, 'store'])->name('cadastro.store');

// Assinatura do responsável (link enviado por WhatsApp)
Route::get('/assinar/{token}',  [AssinaturaController::class, 'show'])->name('assinar.show');
Route::post('/assinar/{token}', [AssinaturaController::class, 'store'])->name('assinar.store');


// Rotas de autenticação admin (sem middleware)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

// Rotas protegidas da área admin
Route::prefix('admin')->name('admin.')->middleware('auth:admin')->group(function () {

    Route::get('/', [DashController::class, 'index'])->name('dash');
    Route::get('/dashboard', [DashController::class, 'index'])->name('dashboard');

    // Conteúdo do site
    Route::resource('noticias', AdminNoticiasController::class);
    Route::resource('banners',   BannersController::class);
    Route::resource('galeria',   GaleriaController::class);

    // Esporte
    Route::resource('campeonatos', CampeonatosController::class);
    Route::resource('times',       TimesController::class);
    Route::resource('jogos',       JogosController::class);
    Route::resource('categorias',  CategoriasController::class);

    // Pessoas
    // Pessoas (Corrigido para usar o alias AdminAtletasController)
    
    Route::resource('atletas', AdminAtletasController::class);
    Route::patch('atletas/{id}/toggle-status', [AdminAtletasController::class, 'toggleStatus'])->name('atletas.toggleStatus');
    Route::get('inscricoes',          [InscricoesController::class, 'index'])->name('inscricoes.index');
    Route::get('inscricoes/{id}',     [InscricoesController::class, 'show'])->name('inscricoes.show');
    Route::delete('inscricoes/{id}',  [InscricoesController::class, 'destroy'])->name('inscricoes.destroy');

    // Matrículas (cadastros vindos do site aguardando aprovação)
    Route::get('matriculas',                      [MatriculasController::class, 'index'])->name('matriculas.index');
    Route::get('matriculas/rejeitadas',           [MatriculasController::class, 'rejeitadas'])->name('matriculas.rejeitadas');
    Route::get('matriculas/{id}',                 [MatriculasController::class, 'show'])->name('matriculas.show');
    Route::patch('matriculas/{id}/aprovar',       [MatriculasController::class, 'aprovar'])->name('matriculas.aprovar');
    Route::patch('matriculas/{id}/rejeitar',      [MatriculasController::class, 'rejeitar'])->name('matriculas.rejeitar');
    Route::patch('matriculas/{id}/reativar',      [MatriculasController::class, 'reativar'])->name('matriculas.reativar');
    Route::delete('matriculas/{id}/deletar',      [MatriculasController::class, 'deletar'])->name('matriculas.deletar');

});
