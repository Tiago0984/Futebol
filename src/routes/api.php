<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\NoticiaController;
use App\Http\Controllers\Api\V1\CampeonatoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AtletaController;

Route::prefix('v1')->group(function () {

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

    // LOGIN - rota pública (limite de 5 tentativas por minuto)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // ROTAS COM CREDENCIAL
    Route::middleware('auth:sanctum')->group(function () {

        // Atleta logado
        Route::get('/atleta', [AtletaController::class, 'show']);

        // Atualizar os dados
        Route::put('/atleta', [AtletaController::class, 'update']);

        // Também permite atualização parcial
        Route::patch('/atleta', [AtletaController::class, 'update']);

        // Atualizar senha
        Route::put('/atleta/senha', [AtletaController::class, 'updateSenha']);

        // Logout
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

});
