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
        $campeonatos = Campeonato::with(['times.categoria'])->get();
        $videoDestaque = Video::where('status_video', 'ATIVO')
            ->whereIn('secao_video', ['campeonatos', 'ambas'])
            ->orderByDesc('id_video')
            ->first();
        return view('site.campeonatos.campeonatos', compact('campeonatos', 'videoDestaque'));
    }

    public function show($id)
    {
        $campeonato = Campeonato::with(['times.atletas.cartoes.jogo', 'times.categoria'])->findOrFail($id);

        // Jogos visíveis (oculto some; cancelado aparece com o selo), pela data do evento
        $jogos = Jogo::with(['evento', 'timeCasa', 'timeVisitante'])
            ->where('id_campeonato', $id)
            ->visiveis()
            ->ordenadosPelaData()
            ->get();

        // Classificação: só jogos com placar e não cancelados
        $classificacao = Jogo::classificacao($jogos);

        return view('site.campeonatos.show', compact('campeonato', 'classificacao', 'jogos'));
    }
}
