<?php
namespace App\Http\Controllers\Site;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Jogo;
use App\Models\Video;

class CampeonatoController extends Controller
{
    public function campeonato()
    {
        $campeonatos = Campeonato::with(['categorias', 'times'])->ativos()->get();

        // Local de cada cartão: o principal ou os locais dos jogos visíveis (cada jogo tem o seu), numa consulta só
        $jogosPorCampeonato = Jogo::with('evento')->whereIn('id_campeonato', $campeonatos->pluck('id_campeonato'))
            ->visiveis()->ordenadosPelaData()->get()->groupBy('id_campeonato');
        $locais = $campeonatos->mapWithKeys(fn ($camp) => [
            $camp->id_campeonato => $camp->textoDosLocais($jogosPorCampeonato[$camp->id_campeonato] ?? []),
        ]);

        // Campeão de cada categoria no cartão (Fase I, Etapa I4): ["Sub-15 Masculino" => "Botafogo da Vila"], só os definidos
        $campeoes = $campeonatos->mapWithKeys(function ($camp) {
            $porCategoria = $camp->campeoes();

            return [$camp->id_campeonato => $camp->categorias
                ->mapWithKeys(fn ($cat) => [$cat->rotulo => Campeonato::textoDoCampeao($porCategoria[(int) $cat->id_categoria] ?? null)])
                ->filter()->all()];
        });

        $videos = Video::doSite('campeonatos')->get();
        return view('site.campeonatos.campeonatos', compact('campeonatos', 'locais', 'campeoes', 'videos'));
    }

    /**
     * Página do campeonato: uma aba por categoria (Fase H, Etapa H5), cada uma com os times, os jogos (com a
     * fase) e a classificação de cada grupo (chave), com os classificados destacados (Campeonato::classificacoes:
     * só a fase de grupos). Campeonato de uma categoria só: sem abas. Rascunho e oculto ficam fora; cancelado
     * aparece com o selo.
     */
    public function show($id)
    {
        $campeonato = Campeonato::with(['categorias', 'times'])->ativos()->findOrFail($id);

        // Jogos visíveis (oculto e rascunho somem; cancelado aparece com o selo), pela data do evento
        $jogos = Jogo::with(['evento', 'timeCasa', 'timeVisitante', 'jogoIda', 'jogoVolta'])
            ->where('id_campeonato', $id)
            ->visiveis()
            ->ordenadosPelaData()
            ->get();

        $participantes  = $campeonato->participantesComGrupo()->groupBy('id_categoria');
        $classificacoes = collect($campeonato->classificacoes())->keyBy(fn ($c) => $c['categoria']->id_categoria);
        $campeoes       = $campeonato->campeoes($classificacoes->all());   // Fase I, Etapa I4

        $abas = $campeonato->categorias->map(fn ($categoria) => [
            'categoria' => $categoria,
            'campeao'   => $campeoes[(int) $categoria->id_categoria] ?? null,
            'times'     => $campeonato->times->whereIn('id_time', ($participantes[$categoria->id_categoria] ?? collect())->pluck('id_time'))->values(),
            'jogos'     => $jogos->filter(fn ($jogo) => (int) $jogo->evento->id_categoria === (int) $categoria->id_categoria)->values(),
            'tabelas'   => $classificacoes[$categoria->id_categoria]['tabelas'] ?? [],
        ])->values();

        // Por segurança, campeonato sem a lista de categorias: uma aba só, com tudo
        if ($abas->isEmpty()) {
            $abas = collect([['categoria' => null, 'campeao' => null, 'times' => $campeonato->times, 'jogos' => $jogos,
                'tabelas' => [['grupo' => null, 'classificados' => null, 'tabela' => Jogo::classificacao($jogos)]]]]);
        }

        $abas = $abas->map(fn ($aba) => [...$aba, 'jogos' => self::emSecoes($aba['jogos'])]);
        $local = $campeonato->textoDosLocais($jogos);

        return view('site.campeonatos.show', compact('campeonato', 'abas', 'local'));
    }

    /**
     * Jogos da aba em seções (Fase I, Etapa I1b): fase de grupos por rodada ("Rodada 1", "Rodada 2"; sem rodada,
     * "Fase de grupos") e o mata-mata pela fase, na ordem de Jogo::FASES; dentro de cada seção, pela data (a
     * ordem que já vem). Cada jogo ganha 'secao_site' (o título da seção).
     */
    private static function emSecoes($jogos)
    {
        $ordemDaFase = array_flip(array_keys(Jogo::FASES));

        return $jogos
            ->each(fn ($jogo) => $jogo->setAttribute('secao_site', $jogo->ehMataMata()
                ? $jogo->fase_label
                : ($jogo->rodada_jogo ? 'Rodada ' . $jogo->rodada_jogo : 'Fase de grupos')))
            // Na fase de grupos, os jogos sem rodada vêm depois das rodadas
            ->sortBy(fn ($jogo) => [$ordemDaFase[$jogo->fase_jogo ?? Jogo::FASE_PADRAO] ?? 99, $jogo->rodada_jogo ?? Jogo::RODADA_MAX + 1])
            ->values();
    }
}
