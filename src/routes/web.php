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
use App\Http\Controllers\Admin\MatriculasController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\CalendarioController as AdminCalendarioController;
use App\Http\Controllers\Admin\VideosController;
use App\Http\Controllers\Admin\EscalacaoController;
use App\Http\Controllers\Admin\ConfiguracoesController;

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
Route::post('/cadastro', [CadastroController::class, 'store'])->middleware('throttle:5,1')->name('cadastro.store');

// Assinatura do responsável (link enviado por WhatsApp)
Route::get('/assinar/{token}',  [AssinaturaController::class, 'show'])->name('assinar.show');
Route::post('/assinar/{token}', [AssinaturaController::class, 'store'])->name('assinar.store');

// Documentação da API (página HTML para leitura humana)
Route::view('/api/documentacao', 'api.documentacao')->name('api.documentacao');


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
    // Jogo = evento JOGO: sem exclusão nem status próprio; cancelar e ocultar são ações do evento
    Route::resource('jogos',       JogosController::class)->only(['index', 'store', 'update']);
    Route::resource('categorias',  CategoriasController::class);
    Route::patch('categorias/{id}/toggle-status', [CategoriasController::class, 'toggleStatus'])->name('categorias.toggleStatus');

    // Calendário (Eventos + Grade de Treinos)
    Route::prefix('calendario')->name('calendario.')->group(function () {
        Route::get('/',                        [AdminCalendarioController::class, 'index'])->name('index');
        Route::post('/eventos',                [AdminCalendarioController::class, 'storeEvento'])->name('eventos.store');
        Route::put('/eventos/{id}',            [AdminCalendarioController::class, 'updateEvento'])->name('eventos.update');
        // Sem exclusão de evento: ocultar (INATIVO) faz esse papel e preserva o registro
        Route::patch('/eventos/{id}/cancelar', [AdminCalendarioController::class, 'cancelarEvento'])->name('eventos.cancelar');
        Route::patch('/eventos/{id}/ocultar',  [AdminCalendarioController::class, 'ocultarEvento'])->name('eventos.ocultar');
        // Tela do evento e inscrições
        Route::get('/eventos/{id}',                          [AdminCalendarioController::class, 'showEvento'])->name('eventos.show');
        Route::post('/eventos/{id}/inscricoes',              [AdminCalendarioController::class, 'inscreverAtleta'])->name('eventos.inscricoes.store');
        Route::post('/eventos/{id}/inscricoes/categoria',    [AdminCalendarioController::class, 'inscreverCategoriaNoEvento'])->name('eventos.inscricoes.categoria');
        Route::post('/eventos/{id}/inscricoes/atualizar',    [AdminCalendarioController::class, 'atualizarInscritosPelaCategoria'])->name('eventos.inscricoes.atualizar');
        Route::delete('/eventos/{id}/inscricoes/{idAtleta}', [AdminCalendarioController::class, 'removerInscricao'])->name('eventos.inscricoes.destroy');
        // Escalação do jogo (tbl_evento_atleta.id_time)
        Route::patch('/eventos/{id}/inscricoes/{idAtleta}/time', [AdminCalendarioController::class, 'escalarAtleta'])->name('eventos.inscricoes.time');
        Route::post('/eventos/{id}/escalacao/elenco',         [AdminCalendarioController::class, 'preencherPeloElenco'])->name('eventos.escalacao.elenco');
        // Geração da agenda do mês pela grade (Fase 7): prévia e gerar
        Route::get('/grade/gerar',             [AdminCalendarioController::class, 'previaGeracao'])->name('grade.previa');
        Route::post('/grade/gerar',            [AdminCalendarioController::class, 'gerarAgenda'])->name('grade.gerar');
        Route::post('/grade',                  [AdminCalendarioController::class, 'storeGrade'])->name('grade.store');
        Route::put('/grade/{id}',              [AdminCalendarioController::class, 'updateGrade'])->name('grade.update');
        Route::patch('/grade/{id}/toggle',     [AdminCalendarioController::class, 'toggleStatusGrade'])->name('grade.toggleStatus');
        Route::delete('/grade/{id}',           [AdminCalendarioController::class, 'destroyGrade'])->name('grade.destroy');
    });

    // Pessoas
    // Pessoas (Corrigido para usar o alias AdminAtletasController)
    // toggle-status significa que o status do atleta será alternado entre ATIVO e INATIVO
    // Sem destroy: na tela de Atletas o atleta só é inativado; exclusão definitiva só em Matrículas Rejeitadas
    Route::resource('atletas', AdminAtletasController::class)->except(['destroy']);
    Route::patch('atletas/{id}/toggle-status', [AdminAtletasController::class, 'toggleStatus'])->name('atletas.toggleStatus');
    Route::post('atletas/{id}/mover-inscricoes', [AdminAtletasController::class, 'moverInscricoes'])->name('atletas.moverInscricoes');

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

    // Configurações do painel
    Route::get('configuracoes', [ConfiguracoesController::class, 'index'])->name('configuracoes.index');

});
