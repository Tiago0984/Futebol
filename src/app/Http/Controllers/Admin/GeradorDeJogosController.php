<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Support\CentralDoCampeonato;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Services\GeradorDeRodadas;
use App\Services\MontadorDaProximaFase;
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
            // Dentro da aba "Grupos (chaves) e classificação" da central do campeonato (30/10/2026)
            'caminho'    => CentralDoCampeonato::caminho($campeonato, 'grupos', 'Gerar jogos · ' . $categoria->rotulo, (int) $categoria->id_categoria),
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

    // Montar a próxima fase do mata-mata (Fase I, Etapa I3): prévia editável (nada gravado)
    public function proximaFase(Request $request, $id, $categoriaId)
    {
        [$campeonato, $categoria] = $this->carregar($id, $categoriaId);
        $opcoes   = $request->has('data') ? $this->validarOpcoesDaFase($request) : $this->opcoesPadraoDaFase($campeonato, $categoria);
        $montador = new MontadorDaProximaFase($campeonato, $categoria, $opcoes);

        return view('admin.campeonatos.proxima-fase', [
            'campeonato' => $campeonato,
            'categoria'  => $categoria,
            'opcoes'     => $opcoes,
            'montador'   => $montador,
            'origem'     => $montador->origem(),
            'erros'      => $montador->erros(),
            'previa'     => $montador->previa(),
            // Dentro da aba "Mata-mata e campeão" da central do campeonato (30/10/2026)
            'caminho'    => CentralDoCampeonato::caminho($campeonato, 'mata-mata', 'Próxima fase · ' . $categoria->rotulo, (int) $categoria->id_categoria),
        ]);
    }

    public function gerarProximaFase(Request $request, $id, $categoriaId)
    {
        [$campeonato, $categoria] = $this->carregar($id, $categoriaId);
        $opcoes   = $this->validarOpcoesDaFase($request);
        $montador = new MontadorDaProximaFase($campeonato, $categoria, $opcoes);
        $previa   = route('admin.campeonatos.proxima-fase', [$campeonato->id_campeonato, $categoria->id_categoria, ...$opcoes]);

        if ($erros = $montador->erros()) {
            return redirect($previa)->with('erro', implode(' ', $erros));
        }

        $fase = Jogo::FASES[$montador->fase()];
        $resultado = $montador->gerar(auth('admin')->id(), $request->boolean('confirmar_conflitos'));
        if (! $resultado['gravou']) {
            return redirect($previa)->with('erro', 'Nada foi gravado: há conflito de horário dos convocados. Confira abaixo e use "Confirmar mesmo assim e gerar".');
        }

        $partes = array_filter([
            $resultado['rascunhos'] ? "{$resultado['rascunhos']} em rascunho (com time da AACJ: revise e publique cada um para avisar os atletas)" : null,
            $resultado['publicados'] ? "{$resultado['publicados']} publicado(s) (sem time da AACJ)" : null,
        ]);

        return redirect()->route('admin.campeonatos.times', $campeonato->id_campeonato)->with('sucesso',
            "{$fase} do {$categoria->rotulo}: {$resultado['jogos']} jogo(s) em {$resultado['confrontos']} confronto(s): " . implode(' e ', $partes) . '.');
    }

    // Padrões da próxima fase: jogo único, 13:00, de hora em hora, no 1º sábado depois do último jogo da categoria
    // no campeonato (fora os ocultos; nunca antes do próximo sábado), para não cair no mesmo dia da fase anterior
    private function opcoesPadraoDaFase(Campeonato $campeonato, Categoria $categoria): array
    {
        $padrao = $this->opcoesPadrao($campeonato);
        $ultimo = EventoCalendario::whereHas('jogo', fn ($q) => $q->where('id_campeonato', $campeonato->id_campeonato))
            ->where('id_categoria', $categoria->id_categoria)
            ->where('status_evento_calendario', '<>', 'INATIVO')
            ->max('data_evento_calendario');
        if ($ultimo && ($depois = \Illuminate\Support\Carbon::parse($ultimo)->next(\Carbon\CarbonInterface::SATURDAY))->gt($padrao['data'])) {
            $padrao['data'] = $depois->toDateString();
        }

        return [...$padrao, 'ida_e_volta' => 0, 'dias_volta' => 7, 'terceiro' => 0, 'pares' => []];
    }

    private function validarOpcoesDaFase(Request $request): array
    {
        $request->mergeIfMissing(['dias' => 7]);   // "dias entre rodadas" é só do gerador de rodadas
        $comuns = $this->validarOpcoes($request);
        $request->validate([
            'ida_e_volta' => 'nullable|boolean',
            'dias_volta'  => 'nullable|integer|min:1|max:60',
            'terceiro'    => 'nullable|boolean',
            'pares'       => 'nullable|array|max:16',
            'pares.*'     => 'array|size:2',
            'pares.*.*'   => 'integer',
        ], ['dias_volta.*' => 'Dias entre a ida e a volta: de 1 a 60.', 'pares.*' => 'Confira os confrontos da prévia.']);

        unset($comuns['returno'], $comuns['dias']);

        return [
            ...$comuns,
            'ida_e_volta' => (int) $request->boolean('ida_e_volta'),
            'dias_volta'  => (int) ($request->input('dias_volta') ?: 7),
            'terceiro'    => (int) $request->boolean('terceiro'),
            'pares'       => array_values(array_map(fn ($par) => array_map('intval', array_values($par)), (array) $request->input('pares', []))),
        ];
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
            'id_local'  => '',
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
            // Local do cadastro (L2; vazio = o local principal do campeonato). "local" (texto) só nas requisições antigas
            'id_local'  => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('tbl_local', 'id_local')->where('status_local', 'ATIVO')],
            'local'     => 'nullable|string|max:255',
        ], [
            'id_local.*'  => 'Escolha um local ativo da lista (ou deixe vazio para usar o local principal do campeonato).',
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
            'id_local'  => (string) ($dados['id_local'] ?? ''),
            'local'     => trim((string) ($dados['local'] ?? '')),
        ];
    }
}
