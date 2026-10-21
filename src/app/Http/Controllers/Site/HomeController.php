<?php

namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\Jogo;
use App\Models\Galeria;
use App\Models\Banner;
use App\Models\Noticia;
use App\Models\Campeonato;
use App\Models\Video;

class HomeController extends Controller
{
    public function home()
    {
        $banners = Banner::where('status_banner', 'ATIVO')
            ->orderBy('ordem_banner')
            ->get();

        $galerias = Galeria::where('status_galeria', 'ATIVO')
            ->inRandomOrder()
            ->limit(8)
            ->get();

        $noticias = Noticia::where('status_noticia', 'ATIVO')
            ->orderBy('data_publicacao_noticia', 'desc')
            ->take(3)
            ->get();

        // "Nossas categorias" (Fase G, Etapa 4: o atleta é da AACJ, na categoria dele, não de um time): as
        // categorias ativas com atletas ativos, com quantos são; o cartão abre a página Jogadores filtrada
        $categoriasDaAcj = Categoria::ativas()
            ->withCount(['atletas as total_atletas' => fn ($q) => $q
                ->where('tbl_categoria_atleta.status_categoria_atleta', Atleta::CATEGORIA_ATIVA)
                ->where('tbl_atletas.status_atleta', 'ATIVO')])
            ->get()
            ->where('total_atletas', '>', 0)
            ->values();

        // Próximo jogo em destaque (LIGA PREMIERE): mesma regra da agenda do site (só jogo de campeonato;
        // amistoso não), ativo, de hoje em diante, como o "Próximo Evento" do calendário. Sem nenhum, mostra
        // o último jogo de campeonato visível; sem nenhum jogo de campeonato, a seção mostra o rótulo padrão.
        $comDados = ['evento', 'timeCasa', 'timeVisitante', 'campeonato'];

        $proximoJogo = Jogo::with($comDados)
            ->daAgendaPublica()
            ->ordenadosPelaData()
            ->where('ev_ordem.status_evento_calendario', 'ATIVO')
            ->where('ev_ordem.data_evento_calendario', '>=', now()->toDateString())
            ->first()
            ?? Jogo::with($comDados)->daAgendaPublica()->visiveis()->ordenadosPelaData('desc')->first();

        // Campeonatos com os jogos visíveis (oculto some; cancelado aparece com o selo)
        $campeonatos = Campeonato::with([
                'categorias',
                'jogos' => fn ($q) => $q->visiveis()->ordenadosPelaData('desc'),
                'jogos.evento', 'jogos.timeCasa', 'jogos.timeVisitante',
            ])
            ->ativos()
            ->orderBy('data_inicio_campeonato', 'desc')
            ->get();

        // Prévia da classificação (Fase H, Etapa H5): a primeira tabela com times da primeira categoria do
        // campeonato (só a fase de grupos), com o título ("Sub-15 Masculino · Grupo 01") e os classificados
        $classificacaoPorCampeonato = $campeonatos->mapWithKeys(function ($camp) {
            foreach ($camp->classificacoes() as $categoria) {
                foreach ($categoria['tabelas'] as $tabela) {
                    if ($tabela['tabela']) {
                        return [$camp->id_campeonato => [
                            'titulo'        => collect([$categoria['categoria']->rotulo, $tabela['grupo']])->filter()->implode(' · '),
                            'classificados' => $tabela['classificados'],
                            'linhas'        => $tabela['tabela'],
                        ]];
                    }
                }
            }

            return [$camp->id_campeonato => ['titulo' => null, 'classificados' => null, 'linhas' => []]];
        })->all();

        $videos = Video::doSite('home')->get();

        return view('site.home.home', compact('galerias', 'banners', 'noticias', 'categoriasDaAcj', 'proximoJogo', 'campeonatos', 'classificacaoPorCampeonato', 'videos'));
    }
}
