<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Time;
use App\Models\Atleta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Elenco de um time interno (antiga tela "Escalação", Fase 10): camisa, posição, titular/reserva e
 * contadores manuais. Aberto pela linha do time em Times. A escalação de cada jogo fica na tela do evento.
 */
class ElencoController extends Controller
{
    public function show($timeId)
    {
        $time = Time::with('categoria')->findOrFail($timeId);

        if ($time->tipo_time === 'EXTERNO') {
            return redirect()->route('admin.times.index')
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

        // Atletas ativos que ainda não estão no elenco, pela categoria atual (select "Adicionar ao elenco")
        $disponiveis = Atleta::with('categoriasAtivas')
            ->where('status_atleta', 'ATIVO')
            ->whereNotIn('id_atleta', DB::table('tbl_atleta_time')->where('id_time', $timeId)->select('id_atleta'))
            ->orderBy('nome_atleta')
            ->get()
            ->groupBy(fn ($atleta) => $atleta->categoriasAtivas->first()?->rotulo ?? 'Sem categoria')
            ->sortKeys();

        return view('admin.times.elenco', compact('time', 'atletas', 'cartoes', 'disponiveis'));
    }

    /**
     * Adiciona um atleta ativo ao elenco (como o modal do atleta faz: titular, camisa opcional). Não inscreve
     * em jogos já criados: use "Preencher pelo elenco" na tela de cada jogo (CLAUDE.md, seção 4, "Jogos").
     */
    public function adicionar(Request $request, $timeId)
    {
        $time = $this->timeInterno($timeId);
        if (! $time) {
            return redirect()->route('admin.times.index')->with('erro', 'Times externos não possuem elenco cadastrado na associação.');
        }

        $request->validate([
            'id_atleta'          => ['required', 'integer', Rule::exists('tbl_atletas', 'id_atleta')->where('status_atleta', 'ATIVO')],
            'camisa_atleta_time' => 'nullable|integer|min:0|max:99',
        ], [
            'id_atleta.required' => 'Escolha o atleta.',
            'id_atleta.exists'   => 'Escolha um atleta ativo.',
        ]);

        $atleta = Atleta::findOrFail($request->id_atleta);
        if ($time->atletas()->where('tbl_atleta_time.id_atleta', $atleta->id_atleta)->exists()) {
            return back()->with('erro', "{$atleta->nome_atleta} já está no elenco.");
        }

        $time->atletas()->attach($atleta->id_atleta, [
            'status_atleta_time'  => 'TITULAR',
            'camisa_atleta_time'  => (int) $request->input('camisa_atleta_time', 0),
            'posicao_atleta_time' => '',
        ]);

        return redirect()->route('admin.times.elenco', $time->id_time)
            ->with('sucesso', "{$atleta->nome_atleta} entrou no elenco. Para os jogos já criados, use \"Preencher pelo elenco\" na tela de cada jogo.");
    }

    /**
     * Tira o atleta do elenco. As inscrições em jogos ficam (a tela do jogo marca "Fora do elenco"); a
     * mensagem diz em quantos jogos futuros do time ele continua inscrito. Contadores do elenco saem juntos.
     */
    public function remover($timeId, $atletaId)
    {
        $time = $this->timeInterno($timeId);
        if (! $time) {
            return redirect()->route('admin.times.index')->with('erro', 'Times externos não possuem elenco cadastrado na associação.');
        }

        $atleta = Atleta::findOrFail($atletaId);
        if (! $time->atletas()->detach($atleta->id_atleta)) {
            return back()->with('erro', "{$atleta->nome_atleta} não está no elenco.");
        }

        $jogos    = $time->jogosFuturosComAtleta($atleta->id_atleta);
        $mensagem = "{$atleta->nome_atleta} saiu do elenco do {$time->nome_time}.";
        if ($jogos) {
            $mensagem .= " Continua inscrito em {$jogos} jogo(s) futuro(s) do time (marcado como \"Fora do elenco\" na tela do jogo); "
                . 'remova a inscrição na tela de cada jogo, se for o caso.';
        }

        return redirect()->route('admin.times.elenco', $time->id_time)->with($jogos ? 'aviso' : 'sucesso', $mensagem);
    }

    // Time interno (externo não tem elenco na associação); null se for externo
    private function timeInterno($timeId): ?Time
    {
        $time = Time::findOrFail($timeId);

        return $time->tipo_time === 'EXTERNO' ? null : $time;
    }

    public function update(Request $request, $timeId, $atletaId)
    {
        $time = Time::findOrFail($timeId);
        if ($time->tipo_time === 'EXTERNO') {
            return redirect()->route('admin.times.index')
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

        return redirect()->route('admin.times.elenco', $timeId)
            ->with('sucesso', 'Dados do atleta atualizados com sucesso.');
    }
}