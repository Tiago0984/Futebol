<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\AtletaAfastamento;
use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Afastamentos do atleta (16/10/2026): a tela do atleta com o afastamento atual (ou o próximo) e os anteriores, e as
 * ações registrar, mudar (prorrogar ou encurtar o fim), encerrar (o atleta volta hoje) e excluir. O atleta continua
 * ATIVO e com o app. As regras do período ficam em AtletaAfastamento::errosDoPeriodo.
 */
class AfastamentosController extends Controller
{
    public function index($id)
    {
        $atleta = Atleta::with(['categoriasAtivas', 'afastamentos.usuario:id_usuario,nome_usuario'])->findOrFail($id);

        [$vigentes, $anteriores] = $atleta->afastamentos->partition(fn (AtletaAfastamento $a) => ! $a->jaTerminou());
        $vigentes = $vigentes->sortBy('data_inicio_atleta_afastamento')->values(); // o atual (ou o próximo) primeiro

        $caminho = [
            ['Atletas', route('admin.atletas.index')],
            [$atleta->nome_atleta, null],
            ['Afastamentos', null],
        ];

        return view('admin.atletas.afastamentos', compact('atleta', 'vigentes', 'anteriores', 'caminho'));
    }

    public function store(Request $request, $id)
    {
        $atleta = Atleta::findOrFail($id);
        if (strtoupper((string) $atleta->status_atleta) !== 'ATIVO') {
            return back()->with('erro', 'Só atleta ativo pode ser afastado (o inativo já não treina).');
        }

        [$inicio, $fim] = $this->validarDatas($request);
        if ($erros = AtletaAfastamento::errosDoPeriodo($atleta->id_atleta, $inicio, $fim)) {
            return back()->withInput()->withErrors($erros);
        }

        $afastamento = new AtletaAfastamento([
            'id_atleta'                      => $atleta->id_atleta,
            'data_inicio_atleta_afastamento' => $inicio->toDateString(),
            'data_fim_atleta_afastamento'    => $fim->toDateString(),
            'motivo_atleta_afastamento'      => $request->input('motivo_atleta_afastamento'),
        ]);
        $agenda = DB::transaction(function () use ($afastamento, $atleta) {
            $afastamento->id_usuario = auth('admin')->id();
            $afastamento->save();

            return AtletaAfastamento::aplicarNaAgenda($atleta, null, $afastamento->periodo(), 'registrado', auth('admin')->id());
        });

        return $this->voltar($afastamento, "Afastamento de {$afastamento->periodoTexto()} registrado.", $agenda);
    }

    // Mudar as datas (prorrogar ou encurtar o fim; o início só antes de começar) e o motivo
    public function update(Request $request, $id, $idAfastamento)
    {
        $afastamento = $this->afastamento($id, $idAfastamento);
        if ($afastamento->jaTerminou()) {
            return back()->with('erro', 'Este afastamento já terminou e não muda mais.');
        }

        [$inicio, $fim] = $this->validarDatas($request);
        if ($erros = AtletaAfastamento::errosDoPeriodo($afastamento->id_atleta, $inicio, $fim, $afastamento)) {
            return back()->withInput()->withErrors($erros);
        }

        $antes = $afastamento->periodo();
        $agenda = DB::transaction(function () use ($afastamento, $request, $inicio, $fim, $antes) {
            $afastamento->update([
                'data_inicio_atleta_afastamento' => $inicio->toDateString(),
                'data_fim_atleta_afastamento'    => $fim->toDateString(),
                'motivo_atleta_afastamento'      => $request->input('motivo_atleta_afastamento'),
            ]);

            // Só o motivo mudou: a agenda e o atleta não mudam (o motivo não vai para ele)
            if ($inicio->isSameDay($antes[0]) && $fim->isSameDay($antes[1])) {
                return null;
            }

            return AtletaAfastamento::aplicarNaAgenda($afastamento->atleta, $antes, $afastamento->periodo(), 'alterado', auth('admin')->id());
        });

        return $this->voltar($afastamento, "Afastamento alterado: {$afastamento->periodoTexto()}.", $agenda);
    }

    // O atleta volta hoje: o fim vira ontem (só para o afastamento que começou antes de hoje e ainda vale)
    public function encerrar($id, $idAfastamento)
    {
        $afastamento = $this->afastamento($id, $idAfastamento);
        if (! $afastamento->podeEncerrar()) {
            return back()->with('erro', 'Só dá para encerrar um afastamento que começou antes de hoje e ainda vale. '
                . 'Se ele ainda não começou (ou começa hoje), exclua.');
        }

        $antes = $afastamento->periodo();
        $agenda = DB::transaction(function () use ($afastamento, $antes) {
            $afastamento->update(['data_fim_atleta_afastamento' => today()->subDay()->toDateString()]);

            return AtletaAfastamento::aplicarNaAgenda($afastamento->atleta, $antes, $afastamento->periodo(), 'encerrado', auth('admin')->id());
        });

        return $this->voltar($afastamento, "Afastamento encerrado: o atleta volta às atividades hoje ({$afastamento->periodoTexto()}).", $agenda);
    }

    public function destroy($id, $idAfastamento)
    {
        $afastamento = $this->afastamento($id, $idAfastamento);
        $periodo = $afastamento->periodoTexto();
        $antes = $afastamento->periodo();

        // O que já terminou não mexe na agenda nem avisa (é só histórico)
        $agenda = DB::transaction(function () use ($afastamento, $antes) {
            $terminou = $afastamento->jaTerminou();
            $afastamento->delete();

            return $terminou ? null : AtletaAfastamento::aplicarNaAgenda($afastamento->atleta, $antes, null, 'excluido', auth('admin')->id());
        });

        return $this->voltar($afastamento, "Afastamento de {$periodo} excluído.", $agenda);
    }

    // Volta à tela do atleta com o resumo do que mudou na agenda (AtletaAfastamento::aplicarNaAgenda)
    private function voltar(AtletaAfastamento $afastamento, string $mensagem, ?array $agenda)
    {
        $redirect = redirect()->route('admin.atletas.afastamentos', $afastamento->id_atleta);

        if ($agenda) {
            $mensagem .= match (count($agenda['saiu'])) {
                0       => '',
                1       => ' Saiu de 1 atividade: ' . $agenda['saiu'][0] . '.',
                default => ' Saiu de ' . count($agenda['saiu']) . ' atividades: ' . implode('; ', $agenda['saiu']) . '.',
            }
                . ($agenda['voltou'] ? " Voltou para {$agenda['voltou']} treino(s) da grade." : '')
                . Notificacao::textoNotificados($agenda['notificados']);

            if ($agenda['jogos_para_conferir']) {
                $redirect->with('aviso', 'Jogos no período liberado em que ele é convocado (use "Preencher pela convocação" na tela do jogo, se ele for jogar): '
                    . implode('; ', $agenda['jogos_para_conferir']) . '.');
            }
        }

        return $redirect->with('sucesso', $mensagem);
    }

    // Início e fim obrigatórios (o fim pode ser prorrogado depois) e motivo opcional
    private function validarDatas(Request $request): array
    {
        $request->validate([
            'data_inicio_atleta_afastamento' => 'required|date_format:Y-m-d',
            'data_fim_atleta_afastamento'    => 'required|date_format:Y-m-d',
            'motivo_atleta_afastamento'      => 'nullable|string|max:' . AtletaAfastamento::MOTIVO_MAX,
        ], [
            'data_inicio_atleta_afastamento.required' => 'Informe a data de início do afastamento.',
            'data_fim_atleta_afastamento.required'    => 'Informe a data de fim do afastamento (dá para prorrogar depois).',
        ]);

        return [
            Carbon::createFromFormat('!Y-m-d', $request->data_inicio_atleta_afastamento),
            Carbon::createFromFormat('!Y-m-d', $request->data_fim_atleta_afastamento),
        ];
    }

    private function afastamento($id, $idAfastamento): AtletaAfastamento
    {
        return AtletaAfastamento::where('id_atleta', (int) $id)->findOrFail($idAfastamento);
    }
}
