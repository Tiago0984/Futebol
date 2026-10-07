<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\Campeonato;
use App\Models\EventoCalendario;
use App\Models\Noticia;
use App\View\Composers\MenuAdminComposer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Dados do site só nas views que os usam (com '*', as consultas rodavam em toda view renderizada,
        // inclusive em cada partial do admin). Campeonatos do menu: só o header do site
        View::composer('partials.header', function ($view) {
            $view->with('campeonatosMenu', Campeonato::orderBy('nome_campeonato')->get());
        });

        // As 3 notícias ATIVAS mais recentes: header e footer do site e as páginas de notícias
        View::composer([
            'partials.header', 'partials.footer', 'site.noticias.news-feed', 'site.noticias.show-noticia-content',
        ], function ($view) {
            $noticiasRecentes = Noticia::where('status_noticia', 'ATIVO')
                ->orderBy('data_publicacao_noticia', 'desc')
                ->take(3)
                ->get();

            $view->with('noticiasRecentes', $noticiasRecentes);
        });

        // Layout do admin: campeonatos em andamento, matrículas pendentes e item ativo (os partials da barra
        // e do header herdam do layout; uma vez por página)
        View::composer('layout.admin', MenuAdminComposer::class);

        // Sugestões dos formulários do admin (campo Local)
        View::composer('admin.partials.sugestoes', fn ($view) => $view->with('locaisUsados', EventoCalendario::locaisUsados()));

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
