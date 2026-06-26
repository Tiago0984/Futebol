<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\Campeonato;
use App\Models\Noticia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) { // Compartilha os campeonatos ordenados por nome em todas as views do site
            $view->with('campeonatosMenu', Campeonato::orderBy('nome_campeonato')->get());
        });

        // Compartilha APENAS as notícias ativas com as views do site
        View::composer('*', function ($view) { // Compartilha as notícias ativas em todas as views do site
            // Adicionado o where para blindar o status ATIVO globalmente
            $noticiasRecentes = Noticia::where('status_noticia', 'ATIVO') // Me dê as 3 notícias mais recentes, mas apenas se a coluna status_noticia for exatamente igual a 'ATIVO'
                ->orderBy('data_publicacao_noticia', 'desc')
                ->take(3)
                ->get();

            $view->with('noticiasRecentes', $noticiasRecentes);
        });

        URL::forceRootUrl(config('app.url'));
    }
}
