<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\EscolheLocal;
use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\Categoria;
use App\Models\Time;
use App\Services\TratadorDeImagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampeonatosController extends Controller
{
    use EscolheLocal;

    // Pasta da logo e do banner no disco "imagens" (public/futebol/images)
    public const PASTA_IMAGENS = 'campeonatos';

    /**
     * Lista de campeonatos. ?campeonato=ID (o nome do campeonato no menu) mostra só aquele, com o aviso e
     * o "Ver todos"; ID inexistente ou inválido é ignorado (lista todos).
     */
    public function index(Request $request)
    {
        $idFiltro = (string) $request->query('campeonato', '');
        $campeonatoFiltrado = ctype_digit($idFiltro) ? Campeonato::find((int) $idFiltro, ['id_campeonato', 'nome_campeonato']) : null;

        $campeonatos = Campeonato::with(['categoria', 'categorias', 'times'])
            ->when($campeonatoFiltrado, fn ($q) => $q->whereKey($campeonatoFiltrado->id_campeonato))
            ->orderBy('data_inicio_campeonato', 'desc')
            ->get();
        $categorias  = $this->categorias();
        $times       = Time::with('categorias')->orderBy('nome_time')->get();

        // Participantes de cada categoria, para o modal de edição
        $participantes = $campeonatos->mapWithKeys(fn ($camp) => [$camp->id_campeonato => $camp->participantesPorCategoria()]);

        return view('admin.campeonatos.index', compact('campeonatos', 'categorias', 'times', 'campeonatoFiltrado', 'participantes'));
    }

    public function create()
    {
        $categorias = $this->categorias();
        $times      = Time::with('categorias')->orderBy('nome_time')->get();

        return view('admin.campeonatos.create', compact('categorias', 'times'));
    }

    public function store(Request $request)
    {
        $this->validar($request);

        $idsCategorias = $this->categoriasDoPedido($request);
        $participantes = $this->participantesDoPedido($request, $idsCategorias);
        if ($erro = $this->erroDosParticipantes($participantes)) {
            return back()->withInput()->withErrors(['participantes' => $erro]);
        }

        $dados = $request->only([
            'nome_campeonato', 'tipo_campeonato', 'data_inicio_campeonato',
            'data_fim_campeonato', 'organizador_campeonato',
            'descricao_campeonato',
        ]);
        [$dados['id_local'], $dados['local_evento']] = $this->localEscolhido($request, 'local_evento', null, null);

        [$imagens, $avisosImagem] = $this->tratarImagens($request);
        $dados += $imagens;

        $dados['status_campeonato'] = 'ATIVO';
        $dados['id_categoria'] = Campeonato::categoriaDeReferencia($idsCategorias);

        DB::transaction(function () use ($dados, $idsCategorias, $participantes) {
            $campeonato = Campeonato::create($dados);
            $campeonato->gravarCategoriasEParticipantes($idsCategorias, $participantes);
        });

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato criado com sucesso.')
            ->with('avisos_imagem', $avisosImagem);
    }

    public function edit($id)
    {
        $campeonato = Campeonato::with('categorias')->findOrFail($id);
        $categorias = $this->categorias();
        $times      = Time::with('categorias')->orderBy('nome_time')->get();

        return view('admin.campeonatos.edit', compact('campeonato', 'categorias', 'times'));
    }

    public function update(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);

        $this->validar($request, $campeonato);

        $idsCategorias = $this->categoriasDoPedido($request);
        $participantes = $this->participantesDoPedido($request, $idsCategorias);

        if ($erro = $this->erroDosParticipantes($participantes)) {
            return back()->withInput()->withErrors(['participantes' => $erro]);
        }

        // Time com convocados não sai da categoria em que convocou (a convocação aponta para o participante)
        if ($saem = $campeonato->timesQueSaemComConvocados($participantes)) {
            return back()->withInput()->withErrors(['participantes' => 'Não é possível tirar da categoria time com atletas convocados: '
                . implode(', ', $saem) . '. Remova a convocação do time antes.']);
        }

        // Categoria com jogos não sai do campeonato (cada jogo tem a categoria dele: Fase H, Etapa H3)
        if ($comJogos = $campeonato->categoriasQueSaemComJogos($idsCategorias)) {
            return back()->withInput()->withErrors(['categorias' => 'Não é possível tirar do campeonato categoria com jogos: '
                . implode(', ', $comJogos) . '. Mude a categoria desses jogos antes (ou mantenha a categoria).']);
        }

        $dados = $request->only([
            'nome_campeonato', 'tipo_campeonato', 'data_inicio_campeonato',
            'data_fim_campeonato', 'organizador_campeonato',
            'descricao_campeonato',
        ]);
        [$dados['id_local'], $dados['local_evento']] = $this->localEscolhido($request, 'local_evento', $campeonato->id_local, $campeonato->local_evento);

        [$imagens, $avisosImagem] = $this->tratarImagens($request);
        $dados += $imagens;
        $antigas = $campeonato->only(array_keys($imagens));

        $dados['id_categoria'] = Campeonato::categoriaDeReferencia($idsCategorias, (int) $campeonato->id_categoria);

        // Cada jogo tem a categoria dele (Fase H, Etapa H3): trocar as categorias do campeonato não mexe nos jogos
        DB::transaction(function () use ($campeonato, $dados, $idsCategorias, $participantes) {
            $campeonato->update($dados);
            $campeonato->gravarCategoriasEParticipantes($idsCategorias, $participantes);
        });

        // A logo e o banner antigos saem depois de gravar, se nenhum outro campeonato usa o mesmo arquivo
        foreach ($antigas as $antiga) {
            TratadorDeImagem::apagarSeNinguemUsa(self::PASTA_IMAGENS, $antiga, fn ($arquivo) => Campeonato::where('logo_evento', $arquivo)
                ->orWhere('banner_evento', $arquivo)->exists());
        }

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato atualizado com sucesso.')
            ->with('avisos_imagem', $avisosImagem);
    }

    public function toggleStatus($id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $novo = strtoupper($campeonato->status_campeonato) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $campeonato->update(['status_campeonato' => $novo]);

        return back()->with('sucesso', "Campeonato {$novo} com sucesso.");
    }

    public function destroy($id)
    {
        $campeonato = Campeonato::findOrFail($id);

        // Campeonato com jogos não é excluído (mensagem em vez do erro de FK)
        if ($motivo = $campeonato->motivoParaNaoExcluir()) {
            return redirect()->route('admin.campeonatos.index')->with('erro', $motivo);
        }

        DB::transaction(function () use ($campeonato) {
            $campeonato->apagarCategoriasEParticipantes();
            $campeonato->delete();
        });

        return redirect()->route('admin.campeonatos.index')->with('sucesso', 'Campeonato removido com sucesso.');
    }

    /**
     * Tipo pela lista Campeonato::TIPOS (o valor chega em maiúsculas; na edição, o tipo antigo fora da
     * lista continua aceito, para editar outros campos sem perder o tipo).
     */
    private function validar(Request $request, ?Campeonato $campeonato = null): void
    {
        $request->merge(['tipo_campeonato' => mb_strtoupper(trim((string) $request->input('tipo_campeonato')))]);
        $tipos = array_keys(Campeonato::TIPOS);
        if ($campeonato?->tipo_campeonato) {
            $tipos[] = $campeonato->tipo_campeonato;
        }

        $request->validate([
            // Tamanho das colunas (VARCHAR(100)): acima disso, o banco recusaria com erro 500
            'nome_campeonato'        => 'required|string|max:100',
            'tipo_campeonato'        => ['required', Rule::in($tipos)],
            'data_inicio_campeonato' => 'required|date',
            'data_fim_campeonato'    => 'required|date|after_or_equal:data_inicio_campeonato',
            'local_evento'           => 'nullable|string|max:100',
            'organizador_campeonato' => 'nullable|string|max:100',
            'descricao_campeonato'   => 'nullable|string',
            'categorias'             => 'required|array|min:1',
            'categorias.*'           => 'integer|exists:tbl_categoria,id_categoria',
            // A logo e o banner são reduzidos no servidor (TratadorDeImagem): a foto pode vir direto do celular
            'logo_evento'            => 'nullable|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'banner_evento'          => 'nullable|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'recorte_banner_evento'  => 'nullable|string|max:60',
            'participantes'          => 'nullable|array',
            'participantes.*'        => 'array',
            'participantes.*.*'      => 'integer|exists:tbl_time,id_time',
        ], [
            'categorias.required' => 'Marque ao menos uma categoria do campeonato.',
            'categorias.min'      => 'Marque ao menos uma categoria do campeonato.',
            'nome_campeonato.max'        => 'O nome do campeonato pode ter no máximo 100 caracteres.',
            'local_evento.max'           => 'O local principal pode ter no máximo 100 caracteres.',
            'organizador_campeonato.max' => 'O organizador pode ter no máximo 100 caracteres.',
            ...TratadorDeImagem::mensagensDoEnvio(['logo_evento', 'banner_evento']),
        ]);
    }

    /**
     * Logo (512×512 PNG, inteira, fundo transparente) e banner (1920×960 JPG, pelo recorte do formulário ou pelo
     * centro), tratados pelo TratadorDeImagem: [colunas => arquivo, avisos de imagem pequena].
     */
    private function tratarImagens(Request $request): array
    {
        $imagens = [];
        $avisos = [];

        if ($request->hasFile('logo_evento')) {
            $logo = TratadorDeImagem::logo($request->file('logo_evento')->getRealPath(), self::PASTA_IMAGENS, 'logo-campeonato');
            $imagens['logo_evento'] = $logo['arquivo'];
            $avisos[] = $logo['aviso'];
        }

        if ($request->hasFile('banner_evento')) {
            [$largura, $altura] = TratadorDeImagem::BANNER_CAMPEONATO;
            $banner = TratadorDeImagem::capa($request->file('banner_evento')->getRealPath(), self::PASTA_IMAGENS, 'banner-campeonato',
                $largura, $altura, $request->input('recorte_banner_evento'));
            $imagens['banner_evento'] = $banner['arquivo'];
            $avisos[] = $banner['aviso'];
        }

        return [$imagens, array_values(array_filter($avisos))];
    }

    // Categorias marcadas (todas valem igual na tela; a de referência sai delas: Campeonato::categoriaDeReferencia)
    private function categoriasDoPedido(Request $request): array
    {
        return array_values(array_unique(array_map('intval', (array) $request->input('categorias', []))));
    }

    // Participante numa categoria que não está marcada no cadastro do time (ou null; 27/10/2026)
    private function erroDosParticipantes(array $participantes): ?string
    {
        $foraDasCategorias = Campeonato::timesForaDasCategorias($participantes);

        return $foraDasCategorias ? implode(' ', $foraDasCategorias) : null;
    }

    // Participantes de cada categoria marcada: [id_categoria => [id_time]]; o bloco de categoria desmarcada é ignorado
    private function participantesDoPedido(Request $request, array $idsCategorias): array
    {
        $pedido = (array) $request->input('participantes', []);

        return collect($idsCategorias)
            ->mapWithKeys(fn ($idCategoria) => [$idCategoria => array_values(array_unique(array_map('intval', (array) ($pedido[$idCategoria] ?? []))))])
            ->all();
    }

    // Categorias dos selects (com o sexo no rótulo), na ordem dos outros formulários; inclui as inativas,
    // para a categoria atual de um campeonato antigo continuar aparecendo
    private function categorias()
    {
        return Categoria::orderBy('sexo_categoria', 'desc')->orderBy('idade_min_categoria')->get();
    }
}
