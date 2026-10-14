<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\Convocacao;
use App\Models\EventoCalendario;
use App\Models\Time;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

/**
 * Convocados de um time interno num campeonato (Fase G, Etapa 1): a lista com a camisa de cada um (editável),
 * a caixa "Convocar atletas" por categoria (com a camisa de cada escolhido) e a remoção. Um time por atleta em
 * cada campeonato e bloqueio de quem passa da idade do campeonato (ano de início). Nesta etapa a convocação
 * ainda não inscreve nos jogos nem avisa ninguém (Etapa 2).
 */
class ConvocacaoController extends Controller
{
    public function show($id, $timeId)
    {
        [$campeonato, $time] = $this->campeonatoETime($id, $timeId);
        if ($erro = $campeonato->erroDoTimeNaConvocacao($time)) {
            return redirect()->route('admin.campeonatos.times', $campeonato->id_campeonato)->with('erro', $erro);
        }

        // Convocados do time, pela camisa; ativos primeiro (o inativo fica com a linha, mas não conta)
        $convocados = $campeonato->convocacoes()->where('id_time', $time->id_time)
            ->with(['atleta:id_atleta,nome_atleta,status_atleta,sexo_atleta,data_nasc_atleta', 'atleta.categoriasAtivas', 'usuario:id_usuario,nome_usuario'])
            ->get()
            ->sortBy(fn (Convocacao $c) => [$c->atleta->status_atleta === 'ATIVO' ? 0 : 1, $c->camisa_convocacao])
            ->values();
        $acimaDosConvocados = $campeonato->idadesAcimaDaIdade($convocados->pluck('id_atleta')->all());

        // Caixa "Convocar atletas": ativos ainda não convocados por este time; desabilitados os convocados por
        // outro time do campeonato e os que passam da idade
        $idsDoTime = $convocados->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
        $grupos = Atleta::ativosPorCategoria()
            ->map(fn ($grupo) => $grupo->reject(fn ($atleta) => in_array((int) $atleta->id_atleta, $idsDoTime, true))->values())
            ->filter(fn ($grupo) => $grupo->isNotEmpty());
        $bloqueados = $campeonato->convocacoes()->where('id_time', '<>', $time->id_time)->with('time:id_time,nome_time')->get()
            ->mapWithKeys(fn (Convocacao $c) => [(int) $c->id_atleta => "convocado pelo {$c->time->nome_time}"])
            ->all();
        $idsDaCaixa = $grupos->flatten()->pluck('id_atleta')->all();
        foreach ($campeonato->idadesAcimaDaIdade($idsDaCaixa) as $idAtleta => $idade) {
            $bloqueados[$idAtleta] ??= "acima da idade ({$idade} anos)";
        }

        $caminho = [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
            [$campeonato->nome_campeonato, route('admin.campeonatos.times', $campeonato->id_campeonato)],
            ["Convocados · {$time->nome_time}", null],
        ];

        return view('admin.campeonatos.convocados', compact(
            'campeonato', 'time', 'convocados', 'acimaDosConvocados', 'grupos', 'bloqueados', 'caminho'
        ));
    }

    // Convoca os atletas marcados, cada um com a camisa; tudo ou nada (nada é gravado se algum não puder)
    public function convocar(Request $request, $id, $timeId)
    {
        [$campeonato, $time] = $this->campeonatoETime($id, $timeId);
        if ($erro = $campeonato->erroDoTimeNaConvocacao($time)) {
            return redirect()->route('admin.campeonatos.times', $campeonato->id_campeonato)->with('erro', $erro);
        }

        $request->validate([
            'atletas'   => 'required|array|min:1',
            'atletas.*' => 'integer',
        ], [
            'atletas.required' => 'Escolha pelo menos um atleta.',
        ]);

        $ids = array_values(array_unique(array_map('intval', $request->input('atletas'))));
        $request->validate(
            collect($ids)->mapWithKeys(fn ($idAtleta) => ["camisas.{$idAtleta}" => $this->regraDaCamisa()])->all(),
            $this->mensagensDaCamisa('camisas.*')
        );

        $camisas = collect($ids)->mapWithKeys(fn ($idAtleta) => [$idAtleta => (int) $request->input("camisas.{$idAtleta}")])->all();
        if ($erros = $campeonato->errosDaConvocacao($time, $camisas)) {
            return back()->withInput()->withErrors(['convocacao' => $erros]);
        }

        try {
            $total = $campeonato->convocar($time, $camisas, auth('admin')->id());
        } catch (UniqueConstraintViolationException) {
            // Outra tela convocou o mesmo atleta ou usou a mesma camisa entre a conferência e a gravação
            return back()->withInput()->withErrors(['convocacao' => 'A convocação mudou enquanto você escolhia: confira a lista e tente de novo.']);
        }

        // Jogos do time já criados não recebem os novos sozinhos (Fase G, Etapa 2): lembra o "Preencher pela convocação"
        $jogos = $time->jogosFuturosNoCampeonato($campeonato->id_campeonato);
        $redirecionar = redirect()->route('admin.campeonatos.convocados', [$campeonato->id_campeonato, $time->id_time])
            ->with('sucesso', "{$total} atleta(s) convocado(s) pelo {$time->nome_time}."
                . ($jogos ? " O {$time->nome_time} já tem {$jogos} jogo(s) futuro(s) neste campeonato: para inscrever os novos,"
                    . ' use "Preencher pela convocação" na tela de cada jogo.' : ''));

        // Sexo diferente do campeonato: só aviso (CLAUDE.md, seção 8, pergunta 14)
        $avisos = $campeonato->avisosDeSexo($ids);

        return $avisos ? $redirecionar->with('aviso', implode(' ', $avisos)) : $redirecionar;
    }

    // Troca a camisa de um convocado (o número não pode ser de outro convocado do time)
    public function camisa(Request $request, $id, $timeId, $atletaId)
    {
        [$campeonato, $time] = $this->campeonatoETime($id, $timeId);
        $convocacao = $this->convocacao($campeonato, $time, $atletaId);

        $request->validate(['camisa_convocacao' => $this->regraDaCamisa()], $this->mensagensDaCamisa('camisa_convocacao'));
        $camisa = (int) $request->input('camisa_convocacao');

        if ($erro = $campeonato->erroDaCamisa($convocacao, $camisa)) {
            return back()->withErrors(['convocacao' => $erro]);
        }

        try {
            $convocacao->update(['camisa_convocacao' => $camisa]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['convocacao' => 'A convocação mudou enquanto você editava: confira a lista e tente de novo.']);
        }

        return redirect()->route('admin.campeonatos.convocados', [$campeonato->id_campeonato, $time->id_time])
            ->with('sucesso', "Camisa de {$convocacao->atleta->nome_atleta} atualizada para {$camisa}.");
    }

    // Tira o atleta da convocação do time; as inscrições nos jogos ficam (marcadas "Não convocado")
    public function remover($id, $timeId, $atletaId)
    {
        [$campeonato, $time] = $this->campeonatoETime($id, $timeId);
        $convocacao = $this->convocacao($campeonato, $time, $atletaId);
        $nome = $convocacao->atleta->nome_atleta;
        $convocacao->delete();

        // As inscrições nos jogos ficam (a tela do jogo marca "Não convocado"); avisa em quantos jogos futuros
        $jogos    = $time->jogosFuturosComAtleta($convocacao->id_atleta, $campeonato->id_campeonato);
        $mensagem = "{$nome} saiu da convocação do {$time->nome_time}.";
        if ($jogos) {
            $mensagem .= " Continua inscrito em {$jogos} jogo(s) futuro(s) do time neste campeonato (marcado como \"Não convocado\""
                . ' na tela do jogo); remova a inscrição na tela de cada jogo, se for o caso.';
        }

        return redirect()->route('admin.campeonatos.convocados', [$campeonato->id_campeonato, $time->id_time])
            ->with($jogos ? 'aviso' : 'sucesso', $mensagem);
    }

    private function campeonatoETime($id, $timeId): array
    {
        return [Campeonato::with('categoria')->findOrFail($id), Time::findOrFail($timeId)];
    }

    // Convocação do atleta por este time neste campeonato (404 se não houver)
    private function convocacao(Campeonato $campeonato, Time $time, $atletaId): Convocacao
    {
        return $campeonato->convocacoes()->where('id_time', $time->id_time)->where('id_atleta', (int) $atletaId)
            ->with(['atleta:id_atleta,nome_atleta', 'time:id_time,nome_time'])->firstOrFail();
    }

    private function regraDaCamisa(): string
    {
        return 'required|integer|min:' . Convocacao::CAMISA_MIN . '|max:' . Convocacao::CAMISA_MAX;
    }

    private function mensagensDaCamisa(string $campo): array
    {
        $faixa = 'A camisa vai de ' . Convocacao::CAMISA_MIN . ' a ' . Convocacao::CAMISA_MAX . '.';

        return [
            "{$campo}.required" => 'Informe o número da camisa de cada atleta convocado.',
            "{$campo}.integer"  => $faixa,
            "{$campo}.min"      => $faixa,
            "{$campo}.max"      => $faixa,
        ];
    }
}
