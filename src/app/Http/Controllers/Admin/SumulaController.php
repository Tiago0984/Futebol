<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cartao;
use App\Models\Jogo;
use App\Models\JogoGol;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Súmula do jogo (Etapa S1, 22/10/2026), no bloco "Súmula" da tela do jogo: quem fez cada gol dos times da AACJ,
 * as assistências, os gols contra e os cartões. Só no admin, sem aviso ao atleta. Corrigir = remover e lançar de
 * novo. As regras ficam nos models (JogoGol::errosPara, Cartao::erroPara).
 */
class SumulaController extends Controller
{
    public function storeGol(Request $request, $id)
    {
        $jogo = $this->jogo($id);

        $request->validateWithBag('sumula', [
            'id_time'               => 'required|integer',
            'tipo_jogo_gol'         => ['required', Rule::in(array_keys(JogoGol::TIPOS))],
            'periodo_jogo_gol'      => ['required', Rule::in(array_keys(JogoGol::PERIODOS))],
            'id_atleta'             => 'nullable|integer',
            'id_atleta_assistencia' => 'nullable|integer',
            'minuto_jogo_gol'       => 'nullable|integer|min:1|max:' . JogoGol::MINUTO_MAX,
        ], [
            'minuto_jogo_gol.min' => 'O minuto vai de 1 a ' . JogoGol::MINUTO_MAX . '.',
            'minuto_jogo_gol.max' => 'O minuto vai de 1 a ' . JogoGol::MINUTO_MAX . '.',
        ]);

        $numero = fn (string $campo) => $request->filled($campo) ? (int) $request->input($campo) : null;
        $dados  = [
            'id_time'               => (int) $request->input('id_time'),
            'id_atleta'             => $numero('id_atleta'),
            'id_atleta_assistencia' => $numero('id_atleta_assistencia'),
            'tipo_jogo_gol'         => $request->input('tipo_jogo_gol'),
            'periodo_jogo_gol'      => $request->input('periodo_jogo_gol'),
        ];
        if ($erros = JogoGol::errosPara($jogo, $dados)) {
            throw $this->falha($jogo, $erros);
        }

        $gol = new JogoGol([...$dados, 'id_jogo' => $jogo->id_jogo, 'minuto_jogo_gol' => $numero('minuto_jogo_gol')]);
        $gol->id_usuario = auth('admin')->id();
        $gol->save();

        $faltam = $jogo->resumoDaSumula()[$dados['id_time']]['faltam'] ?? 0;

        return $this->tela($jogo)->with('sucesso', 'Gol lançado na súmula.'
            . ($faltam ? " Falta(m) {$faltam} gol(s) do time sem autor lançado." : ''));
    }

    public function destroyGol($id, $idGol)
    {
        $jogo = $this->jogo($id);
        $jogo->gols()->findOrFail($idGol)->delete();

        return $this->tela($jogo)->with('sucesso', 'Gol removido da súmula.');
    }

    public function storeCartao(Request $request, $id)
    {
        $jogo = $this->jogo($id);

        $request->validateWithBag('sumula', [
            'id_atleta'   => 'required|integer',
            'tipo_cartao' => ['required', Rule::in(array_keys(Cartao::TIPOS))],
        ], [
            'id_atleta.required' => 'Escolha o atleta do cartão.',
        ]);

        $idAtleta = (int) $request->input('id_atleta');
        if ($erro = Cartao::erroPara($jogo, $idAtleta, $request->input('tipo_cartao'))) {
            throw $this->falha($jogo, ['id_atleta' => $erro]);
        }

        $cartao = new Cartao([
            'id_atleta'     => $idAtleta,
            'id_jogo'       => $jogo->id_jogo,
            'id_campeonato' => $jogo->id_campeonato,
            'tipo_cartao'   => $request->input('tipo_cartao'),
        ]);
        $cartao->id_usuario = auth('admin')->id();
        $cartao->save();

        return $this->tela($jogo)->with('sucesso', 'Cartão lançado na súmula.');
    }

    public function destroyCartao($id, $idCartao)
    {
        $jogo = $this->jogo($id);
        $jogo->cartoes()->findOrFail($idCartao)->delete();

        return $this->tela($jogo)->with('sucesso', 'Cartão removido da súmula.');
    }

    private function jogo($id): Jogo
    {
        return Jogo::with(['evento', 'timeCasa', 'timeVisitante'])->findOrFail($id);
    }

    // Erro das regras (JogoGol, Cartao): volta para a súmula, com as mensagens no bloco dela
    private function falha(Jogo $jogo, array $erros): ValidationException
    {
        return ValidationException::withMessages($erros)->errorBag('sumula')
            ->redirectTo(route('admin.calendario.eventos.show', $jogo->id_evento) . '#sumula');
    }

    // Volta para a tela do jogo, já no bloco da súmula
    private function tela(Jogo $jogo)
    {
        return redirect()->to(route('admin.calendario.eventos.show', $jogo->id_evento) . '#sumula');
    }
}
