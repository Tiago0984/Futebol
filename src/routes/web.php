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
use App\Http\Controllers\Admin\CalendarioController as AdminCalendarioController;
use App\Http\Controllers\Admin\VideosController;
use App\Http\Controllers\Admin\EscalacaoController;

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
    Route::patch('banners/{id}/toggle-status', [BannersController::class, 'toggleStatus'])->name('banners.toggleStatus');
    Route::resource('galeria',   GaleriaController::class);

    // Esporte
    Route::resource('campeonatos', CampeonatosController::class);
    Route::patch('campeonatos/{id}/toggle-status', [CampeonatosController::class, 'toggleStatus'])->name('campeonatos.toggleStatus');
    Route::resource('times',       TimesController::class);
    Route::patch('times/{id}/toggle-status', [TimesController::class, 'toggleStatus'])->name('times.toggleStatus');
    Route::resource('jogos',       JogosController::class);
    Route::patch('jogos/{id}/toggle-status', [JogosController::class, 'toggleStatus'])->name('jogos.toggleStatus');
    Route::resource('categorias',  CategoriasController::class);
    Route::patch('categorias/{id}/toggle-status', [CategoriasController::class, 'toggleStatus'])->name('categorias.toggleStatus');

    // Calendário (Eventos + Grade de Treinos)
    Route::prefix('calendario')->name('calendario.')->group(function () {
        Route::get('/',                        [AdminCalendarioController::class, 'index'])->name('index');
        Route::post('/eventos',                [AdminCalendarioController::class, 'storeEvento'])->name('eventos.store');
        Route::put('/eventos/{id}',            [AdminCalendarioController::class, 'updateEvento'])->name('eventos.update');
        Route::patch('/eventos/{id}/toggle',   [AdminCalendarioController::class, 'toggleStatusEvento'])->name('eventos.toggleStatus');
        Route::delete('/eventos/{id}',         [AdminCalendarioController::class, 'destroyEvento'])->name('eventos.destroy');
        Route::post('/grade',                  [AdminCalendarioController::class, 'storeGrade'])->name('grade.store');
        Route::put('/grade/{id}',              [AdminCalendarioController::class, 'updateGrade'])->name('grade.update');
        Route::patch('/grade/{id}/toggle',     [AdminCalendarioController::class, 'toggleStatusGrade'])->name('grade.toggleStatus');
        Route::delete('/grade/{id}',           [AdminCalendarioController::class, 'destroyGrade'])->name('grade.destroy');
    });

    // Pessoas
    // Pessoas (Corrigido para usar o alias AdminAtletasController)
    // toggle-status significa que o status do atleta será alternado entre ATIVO e INATIVO
    Route::resource('atletas', AdminAtletasController::class);
    Route::patch('atletas/{id}/toggle-status', [AdminAtletasController::class, 'toggleStatus'])->name('atletas.toggleStatus');
    Route::get('inscricoes',          [InscricoesController::class, 'index'])->name('inscricoes.index');
    Route::get('inscricoes/{id}',     [InscricoesController::class, 'show'])->name('inscricoes.show');
    Route::delete('inscricoes/{id}',  [InscricoesController::class, 'destroy'])->name('inscricoes.destroy');

    // Escalação
    Route::get('escalacao',                                  [EscalacaoController::class, 'index'])->name('escalacao.index');
    Route::get('escalacao/{timeId}',                         [EscalacaoController::class, 'show'])->name('escalacao.show');
    Route::patch('escalacao/{timeId}/atleta/{atletaId}',     [EscalacaoController::class, 'update'])->name('escalacao.update');

    // Vídeos
    Route::get('videos',                         [VideosController::class, 'index'])->name('videos.index');
    Route::post('videos',                        [VideosController::class, 'store'])->name('videos.store');
    Route::put('videos/{id}',                    [VideosController::class, 'update'])->name('videos.update');
    Route::patch('videos/{id}/toggle-status',    [VideosController::class, 'toggleStatus'])->name('videos.toggleStatus');
    Route::delete('videos/{id}',                 [VideosController::class, 'destroy'])->name('videos.destroy');

    // Matrículas (cadastros vindos do site aguardando aprovação)
    Route::get('matriculas',                      [MatriculasController::class, 'index'])->name('matriculas.index');
    Route::get('matriculas/rejeitadas',           [MatriculasController::class, 'rejeitadas'])->name('matriculas.rejeitadas');
    Route::get('matriculas/{id}',                 [MatriculasController::class, 'show'])->name('matriculas.show');
    Route::patch('matriculas/{id}/aprovar',       [MatriculasController::class, 'aprovar'])->name('matriculas.aprovar');
    Route::patch('matriculas/{id}/rejeitar',      [MatriculasController::class, 'rejeitar'])->name('matriculas.rejeitar');
    Route::patch('matriculas/{id}/reativar',      [MatriculasController::class, 'reativar'])->name('matriculas.reativar');
    Route::delete('matriculas/{id}/deletar',      [MatriculasController::class, 'deletar'])->name('matriculas.deletar');

});
