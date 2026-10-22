<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Services\GeradorDeRodadas;
use Illuminate\Http\Request;

/**
 * Gerar os jogos de todos contra todos de uma categoria do campeonato (Fase I, Etapa I2), pelo botão da tela
 * "Grupos e classificação": formulário com o formato e as datas, prévia (nada gravado) e "Gerar" (tudo ou nada;
 * conflito real de horário dos convocados pede confirmação, como na geração da grade). E o "Publicar a Rodada N"
 * da tela de jogos do campeonato: publica os rascunhos daquela rodada e avisa os atletas (o "Publicar" de cada
 * jogo continua existindo).
 */
class GeradorDeJogosController extends Controller
{
    public function show(Request $request, $id, $categoriaId)
    {
        [$campeonato, $categoria] = $this->carregar($id, $categoriaId);
        $opcoes  = $request->has('data') ? $this->validarOpcoes($request) : $this->opcoesPadrao($campeonato);
        $gerador = new GeradorDeRodadas($campeonato, $categoria, $opcoes);

        return view('admin.campeonatos.gerar-jogos', [
            'campeonato' => $campeonato,
            'categoria'  => $categoria,
            'opcoes'     => $opcoes,
            'erros'      => $gerador->erros(),
            'previa'     => $gerador->previa(),
            'caminho'    => [
                ['Eventos', route('admin.calendario.index')],
                ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
                [$campeonato->nome_campeonato, route('admin.campeonatos.times', $campeonato->id_campeonato)],
                ['Grupos (chaves) e classificação', route('admin.campeonatos.grupos', $campeonato->id_campeonato)],
                ['Gerar jogos · ' . $categoria->rotulo, null],
            ],
        ]);
    }

    public function gerar(Request $request, $id, $categoriaId)
    {
        [$campeonato, $categoria] = $this->carregar($id, $categoriaId);
        $opcoes  = $this->validarOpcoes($request);
        $gerador = new GeradorDeRodadas($campeonato, $categoria, $opcoes);
        $previa  = route('admin.campeonatos.gerar-jogos', [$campeonato->id_campeonato, $categoria->id_categoria, ...$opcoes]);

        if ($erros = $gerador->erros()) {
            return redirect($previa)->with('erro', implode(' ', $erros));
        }

        $resultado = $gerador->gerar(auth('admin')->id(), $request->boolean('confirmar_conflitos'));
        if (! $resultado['gravou']) {
            return redirect($previa)->with('erro', 'Nada foi gravado: há conflito de horário dos convocados. Confira abaixo e use "Confirmar mesmo assim e gerar".');
        }

        $partes = array_filter([
            $resultado['rascunhos'] ? "{$resultado['rascunhos']} em rascunho (com time da AACJ: publique a rodada ou cada jogo para avisar os atletas)" : null,
            $resultado['publicados'] ? "{$resultado['publicados']} publicado(s) (sem time da AACJ)" : null,
        ]);

        return redirect()->route('admin.campeonatos.times', $campeonato->id_campeonato)->with('sucesso',
            "{$resultado['jogos']} jogo(s) do {$categoria->rotulo} criado(s) em {$resultado['rodadas']} rodada(s): " . implode(' e ', $partes) . '.');
    }

    // "Publicar a Rodada N": os rascunhos da rodada (campeonato e categoria), com o aviso aos atletas de cada jogo
    public function publicarRodada(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $dados = $request->validate([
            'id_categoria' => 'required|integer',
            'rodada_jogo'  => 'required|integer|min:1|max:' . Jogo::RODADA_MAX,
        ]);

        $jogos = Jogo::with(['evento', 'campeonato', 'timeCasa', 'timeVisitante'])
            ->where('id_campeonato', $campeonato->id_campeonato)
            ->where('rodada_jogo', $dados['rodada_jogo'])
            ->whereHas('evento', fn ($q) => $q->where('id_categoria', $dados['id_categoria'])->whereNull('data_publicacao_evento_calendario'))
            ->get();

        $publicados = 0;
        $avisados   = 0;
        $recusados  = [];
        foreach ($jogos as $jogo) {
            if ($erro = $jogo->erroParaPublicar()) {
                $recusados[] = "{$jogo->evento->titulo_evento_calendario}: {$erro}";
                continue;
            }
            if ($jogo->evento->publicar(auth('admin')->id())) {
                $publicados++;
                $avisados += $jogo->evento->avisaAtletas() ? $jogo->evento->atletasNotificados : 0;
            }
        }

        $voltar = str_starts_with((string) $request->input('voltar'), url('/')) ? $request->input('voltar')
            : route('admin.campeonatos.times', $campeonato->id_campeonato);
        $resposta = redirect($voltar);
        if ($recusados) {
            $resposta->with('erro', 'Não publicado(s): ' . implode(' ', $recusados));
        }

        return $resposta->with($publicados ? 'sucesso' : 'aviso', $publicados
            ? "Rodada {$dados['rodada_jogo']}: {$publicados} jogo(s) publicado(s)." . Notificacao::textoNotificados($avisados)
            : "Rodada {$dados['rodada_jogo']}: nenhum jogo em rascunho para publicar.");
    }

    // Campeonato e uma categoria dele (404 se a categoria não é do campeonato)
    private function carregar($id, $categoriaId): array
    {
        $campeonato = Campeonato::with('categorias')->findOrFail($id);
        $categoria  = $campeonato->categorias->firstWhere('id_categoria', (int) $categoriaId);
        abort_unless($categoria instanceof Categoria, 404);

        return [$campeonato, $categoria];
    }

    // Padrões: só turno, 1ª rodada no próximo sábado (ou no início do campeonato, se for depois), um jogo por
    // semana, 13:00, jogos do mesmo dia de hora em hora (como na tabela que o dono da AACJ mandou)
    private function opcoesPadrao(Campeonato $campeonato): array
    {
        $sabado = today()->next(\Carbon\CarbonInterface::SATURDAY);
        $inicio = $campeonato->data_inicio_campeonato?->copy()->startOfDay();

        return [
            'returno'   => 0,
            'data'      => ($inicio && $inicio->gt($sabado) ? $inicio : $sabado)->toDateString(),
            'dias'      => 7,
            'horario'   => '13:00',
            'intervalo' => 60,
            'duracao'   => '',
            'local'     => '',
        ];
    }

    private function validarOpcoes(Request $request): array
    {
        $dados = $request->validate([
            'returno'   => 'nullable|boolean',
            'data'      => 'required|date_format:Y-m-d',
            'dias'      => 'required|integer|min:1|max:60',
            'horario'   => 'required|date_format:H:i',
            'intervalo' => 'required|integer|min:0|max:600',
            'duracao'   => 'nullable|integer|min:10|max:600',
            'local'     => 'nullable|string|max:255',
        ], [
            'data.*'      => 'Informe a data da 1ª rodada.',
            'dias.*'      => 'Dias entre rodadas: de 1 a 60.',
            'horario.*'   => 'Informe o horário do 1º jogo (HH:MM).',
            'intervalo.*' => 'Minutos entre jogos do mesmo dia: de 0 a 600.',
            'duracao.*'   => 'Duração do jogo: de 10 a 600 minutos (ou vazia).',
        ]);

        return [
            'returno'   => (int) $request->boolean('returno'),
            'data'      => $dados['data'],
            'dias'      => (int) $dados['dias'],
            'horario'   => $dados['horario'],
            'intervalo' => (int) $dados['intervalo'],
            'duracao'   => $dados['duracao'] ?? '',
            'local'     => trim((string) ($dados['local'] ?? '')),
        ];
    }
}
