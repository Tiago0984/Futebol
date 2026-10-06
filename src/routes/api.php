<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\NoticiaController;
use App\Http\Controllers\Api\V1\CampeonatoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AtletaController;
use App\Http\Controllers\Api\V1\ResponsavelController;
use App\Http\Controllers\Api\V1\AppDoAtletaController;

// Agenda e avisos (Fase 9): as mesmas rotas para o atleta ("") e para o responsável ("/{idAtleta}", sob
// /responsavel/atletas). As regras ficam em App\Services\AppDoAtleta
$rotasDaAgendaEDosAvisos = function (string $prefixo) {
    Route::get("{$prefixo}/agenda", [AppDoAtletaController::class, 'agenda']);
    Route::get("{$prefixo}/notificacoes", [AppDoAtletaController::class, 'notificacoes']);
    Route::get("{$prefixo}/notificacoes/nao-lidas", [AppDoAtletaController::class, 'naoLidas']);
    Route::patch("{$prefixo}/notificacoes/lidas", [AppDoAtletaController::class, 'marcarTodasLidas']);
    Route::patch("{$prefixo}/notificacoes/{idNotificacao}/lida", [AppDoAtletaController::class, 'marcarLida'])->whereNumber('idNotificacao');
};

Route::prefix('v1')->group(function () use ($rotasDaAgendaEDosAvisos) {

    // Status da API
    Route::get('/status', [StatusController::class, 'index']);

    // Banners
    Route::get('/banners', [BannerController::class, 'index']);

    // Categorias
    Route::get('/categorias', [CategoriaController::class, 'index']);
    Route::get('/categorias/{id}/times', [CategoriaController::class, 'times']);

    // Notícias
    Route::get('/noticias', [NoticiaController::class, 'index']);
    Route::get('/noticias/{id}', [NoticiaController::class, 'show']);

    // Campeonatos
    Route::get('/campeonatos', [CampeonatoController::class, 'index']);
    Route::get('/campeonatos/{id}', [CampeonatoController::class, 'show']);

    // LOGIN - rota pública, perfil "atleta" (padrão) ou "responsavel" (limite de 5 tentativas por minuto
    // por IP e 5 por e-mail e perfil: limitador "login-api", no AppServiceProvider)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login-api');

    // ESQUECI MINHA SENHA - rota pública, resposta sempre igual (limitador "esqueci-senha-api")
    Route::post('/auth/esqueci-senha', [AuthController::class, 'esqueciSenha'])->middleware('throttle:esqueci-senha-api');

    // Logout: qualquer perfil
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // ROTAS DO RESPONSÁVEL (token do perfil responsável e algum filho ATIVO)
    Route::middleware(['auth:sanctum', 'perfil:responsavel', 'responsavel.ativo'])->group(function () use ($rotasDaAgendaEDosAvisos) {
        Route::get('/responsavel', [ResponsavelController::class, 'show']);
        Route::put('/responsavel', [ResponsavelController::class, 'update']);
        Route::patch('/responsavel', [ResponsavelController::class, 'update']);
        Route::put('/responsavel/senha', [ResponsavelController::class, 'updateSenha']);

        // Os filhos, em modo leitura: o mesmo que o atleta vê (AppDoAtletaController confere que o atleta
        // é filho do responsável e está ATIVO; senão, 404)
        Route::prefix('/responsavel/atletas')->group(function () use ($rotasDaAgendaEDosAvisos) {
            Route::get('/', [AppDoAtletaController::class, 'filhos']);
            Route::get('/{idAtleta}', [AppDoAtletaController::class, 'dados'])->whereNumber('idAtleta');
            $rotasDaAgendaEDosAvisos('/{idAtleta}');
        });
    });

    // ROTAS DO ATLETA (token do perfil atleta e atleta ATIVO)
    Route::middleware(['auth:sanctum', 'perfil:atleta', 'atleta.ativo'])->group(function () use ($rotasDaAgendaEDosAvisos) {

        // Atleta logado
        Route::get('/atleta', [AtletaController::class, 'show']);

        // Atualizar os dados
        Route::put('/atleta', [AtletaController::class, 'update']);

        // Também permite atualização parcial
        Route::patch('/atleta', [AtletaController::class, 'update']);

        // Atualizar senha
        Route::put('/atleta/senha', [AtletaController::class, 'updateSenha']);

        // Agenda e avisos do próprio atleta
        $rotasDaAgendaEDosAvisos('');
    });

});
