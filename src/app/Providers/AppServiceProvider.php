<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Login da API: 5 tentativas por minuto por IP e, à parte, 5 por e-mail e perfil (quem tenta muitas
        // senhas de uma conta trocando de IP também para; o mesmo e-mail pode ser de atleta e de responsável,
        // e um perfil não bloqueia o outro). Passou do limite: 429 em JSON
        RateLimiter::for('login-api', function (Request $request) {
            $chave = $this->chaveEmailEPerfil($request);

            return array_filter([
                Limit::perMinute(5)->by('ip:' . $request->ip()),
                $chave ? Limit::perMinute(5)->by('email:' . $chave) : null,
            ]);
        });

        // "Esqueci minha senha": 5 pedidos por minuto por IP e 3 a cada 10 minutos por e-mail e perfil
        // (além do broker, que não gera outro link para a mesma conta em menos de 60 segundos)
        RateLimiter::for('esqueci-senha-api', function (Request $request) {
            $chave = $this->chaveEmailEPerfil($request);

            return array_filter([
                Limit::perMinute(5)->by('esqueci-ip:' . $request->ip()),
                $chave ? Limit::perMinutes(10, 3)->by('esqueci-email:' . $chave) : null,
            ]);
        });
    }

    // "atleta:ana@exemplo.com" (sem perfil, vale atleta, como no login); null sem e-mail
    private function chaveEmailEPerfil(Request $request): ?string
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $perfil = $request->input('perfil') === 'responsavel' ? 'responsavel' : 'atleta';

        return $email === '' ? null : "{$perfil}:{$email}";
    }
}
