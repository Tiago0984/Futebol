<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Time;
use App\Models\Atleta;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EscalacaoController extends Controller
{
    public function index()
    {
        $times = Time::with('categoria')
            ->withCount(['atletas as total_atletas'])
            ->orderByRaw("FIELD(tipo_time, 'INTERNO', 'EXTERNO')")
            ->orderBy('nome_time')
            ->get();

        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.escalacao.index', compact('times', 'categorias'));
    }

    public function show($timeId)
    {
        $time = Time::with('categoria')->findOrFail($timeId);

        if ($time->tipo_time === 'EXTERNO') {
            return redirect()->route('admin.escalacao.index')
                ->with('erro', 'Times externos não possuem elenco cadastrado na associação.');
        }

        // Atletas do time com pivot completo + contagem de cartões
        $atletas = Atleta::join('tbl_atleta_time', 'tbl_atletas.id_atleta', '=', 'tbl_atleta_time.id_atleta')
            ->where('tbl_atleta_time.id_time', $timeId)
            ->select(
                'tbl_atletas.id_atleta',
                'tbl_atletas.nome_atleta',
                'tbl_atletas.foto_atleta',
                'tbl_atletas.posicao_atleta',
                'tbl_atleta_time.id_atleta_time',
                'tbl_atleta_time.camisa_atleta_time',
                'tbl_atleta_time.posicao_atleta_time',
                'tbl_atleta_time.status_atleta_time',
                'tbl_atleta_time.jogos_atleta_time',
                'tbl_atleta_time.gols_atleta_time',
                'tbl_atleta_time.defesas_atleta_time',
                'tbl_atleta_time.convocacao_atleta_time'
            )
            ->orderByRaw("FIELD(tbl_atleta_time.status_atleta_time, 'TITULAR', 'RESERVA', 'ATIVO')")
            ->orderBy('tbl_atleta_time.camisa_atleta_time')
            ->get();

        // Cartões por atleta neste time (via jogos do campeonato do time, ou todos)
        $atletaIds = $atletas->pluck('id_atleta');
        $cartoes = DB::table('tbl_cartoes')
            ->whereIn('id_atleta', $atletaIds)
            ->select('id_atleta', 'tipo_cartao', DB::raw('COUNT(*) as total'))
            ->groupBy('id_atleta', 'tipo_cartao')
            ->get()
            ->groupBy('id_atleta');

        return view('admin.escalacao.show', compact('time', 'atletas', 'cartoes'));
    }

    public function update(Request $request, $timeId, $atletaId)
    {
        $time = Time::findOrFail($timeId);
        if ($time->tipo_time === 'EXTERNO') {
            return redirect()->route('admin.escalacao.index')
                ->with('erro', 'Times externos não possuem elenco cadastrado na associação.');
        }

        $request->validate([
            'camisa_atleta_time'       => 'nullable|integer|min:0|max:99',
            'posicao_atleta_time'      => 'nullable|string|max:20',
            'status_atleta_time'       => 'required|in:TITULAR,RESERVA',
            'jogos_atleta_time'        => 'nullable|integer|min:0',
            'gols_atleta_time'         => 'nullable|integer|min:0',
            'defesas_atleta_time'      => 'nullable|integer|min:0',
            'convocacao_atleta_time'   => 'nullable|integer|min:0',
            'cartao_amarelo_manual'    => 'nullable|integer|min:0',
            'cartao_vermelho_manual'   => 'nullable|integer|min:0',
        ]);

        DB::table('tbl_atleta_time')
            ->where('id_time', $timeId)
            ->where('id_atleta', $atletaId)
            ->update([
                'camisa_atleta_time'     => $request->camisa_atleta_time ?? 0,
                'posicao_atleta_time'    => $request->posicao_atleta_time
                                             ? strtoupper($request->posicao_atleta_time)
                                             : '',
                'status_atleta_time'     => $request->status_atleta_time,
                'jogos_atleta_time'      => $request->jogos_atleta_time ?? 0,
                'gols_atleta_time'       => $request->gols_atleta_time ?? 0,
                'defesas_atleta_time'    => $request->defesas_atleta_time ?? 0,
                'convocacao_atleta_time' => $request->convocacao_atleta_time ?? 0,
            ]);

        // Atualiza cartões manuais (id_jogo IS NULL = entrada manual, sem partida vinculada)
        DB::table('tbl_cartoes')
            ->where('id_atleta', $atletaId)
            ->whereNull('id_jogo')
            ->delete();

        $amarelos  = (int) ($request->cartao_amarelo_manual  ?? 0);
        $vermelhos = (int) ($request->cartao_vermelho_manual ?? 0);
        $agora     = now();

        $inserts = [];
        for ($i = 0; $i < $amarelos; $i++) {
            $inserts[] = ['id_atleta' => $atletaId, 'id_campeonato' => null, 'id_jogo' => null, 'tipo_cartao' => 'AMARELO', 'data_cartao' => $agora];
        }
        for ($i = 0; $i < $vermelhos; $i++) {
            $inserts[] = ['id_atleta' => $atletaId, 'id_campeonato' => null, 'id_jogo' => null, 'tipo_cartao' => 'VERMELHO', 'data_cartao' => $agora];
        }
        if ($inserts) {
            DB::table('tbl_cartoes')->insert($inserts);
        }

        return redirect()->route('admin.escalacao.show', $timeId)
            ->with('sucesso', 'Dados do atleta atualizados com sucesso.');
    }
}