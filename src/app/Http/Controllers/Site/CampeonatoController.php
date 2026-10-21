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
        $campeonatos = Campeonato::with(['categorias', 'times.categoria'])->get();

        // Local de cada cartão: o principal ou os locais dos jogos visíveis (cada jogo tem o seu), numa consulta só
        $jogosPorCampeonato = Jogo::with('evento')->whereIn('id_campeonato', $campeonatos->pluck('id_campeonato'))
            ->visiveis()->ordenadosPelaData()->get()->groupBy('id_campeonato');
        $locais = $campeonatos->mapWithKeys(fn ($camp) => [
            $camp->id_campeonato => $camp->textoDosLocais($jogosPorCampeonato[$camp->id_campeonato] ?? []),
        ]);

        $videos = Video::doSite('campeonatos')->get();
        return view('site.campeonatos.campeonatos', compact('campeonatos', 'locais', 'videos'));
    }

    /**
     * Página do campeonato: uma aba por categoria (Fase H, Etapa H5), cada uma com os times, os jogos (com a
     * fase) e a classificação de cada grupo (chave), com os classificados destacados (Campeonato::classificacoes:
     * só a fase de grupos). Campeonato de uma categoria só: sem abas. Rascunho e oculto ficam fora; cancelado
     * aparece com o selo.
     */
    public function show($id)
    {
        $campeonato = Campeonato::with(['categorias', 'times.categoria'])->findOrFail($id);

        // Jogos visíveis (oculto e rascunho somem; cancelado aparece com o selo), pela data do evento
        $jogos = Jogo::with(['evento', 'timeCasa', 'timeVisitante'])
            ->where('id_campeonato', $id)
            ->visiveis()
            ->ordenadosPelaData()
            ->get();

        $participantes  = $campeonato->participantesComGrupo()->groupBy('id_categoria');
        $classificacoes = collect($campeonato->classificacoes())->keyBy(fn ($c) => $c['categoria']->id_categoria);

        $abas = $campeonato->categorias->map(fn ($categoria) => [
            'categoria' => $categoria,
            'times'     => $campeonato->times->whereIn('id_time', ($participantes[$categoria->id_categoria] ?? collect())->pluck('id_time'))->values(),
            'jogos'     => $jogos->filter(fn ($jogo) => (int) $jogo->evento->id_categoria === (int) $categoria->id_categoria)->values(),
            'tabelas'   => $classificacoes[$categoria->id_categoria]['tabelas'] ?? [],
        ])->values();

        // Por segurança, campeonato sem a lista de categorias: uma aba só, com tudo
        if ($abas->isEmpty()) {
            $abas = collect([['categoria' => null, 'times' => $campeonato->times, 'jogos' => $jogos,
                'tabelas' => [['grupo' => null, 'classificados' => null, 'tabela' => Jogo::classificacao($jogos)]]]]);
        }

        $local = $campeonato->textoDosLocais($jogos);

        return view('site.campeonatos.show', compact('campeonato', 'abas', 'local'));
    }
}
