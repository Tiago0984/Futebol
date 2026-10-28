<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Time;
use App\Services\TratadorDeImagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Times (27/10/2026, dono do projeto): um cadastro só para a AACJ e para cada adversário, com as categorias de que
 * ele participa marcadas (tbl_time_categoria), no lugar de um time por categoria. O nome não se repete. Masculino e
 * feminino em cadastros separados ("AACJ" e "AACJ Feminino", 28/10/2026); outro time interno ("AACJ B") é permitido.
 */
class TimesController extends Controller
{
    // Pasta das logos no disco "imagens" (public/futebol/images), a mesma lida por Time::getLogoUrlAttribute
    public const PASTA_IMAGENS = 'team';

    public function index()
    {
        $times = Time::with('categorias')->orderBy('nome_time')->get();

        // Todas, inclusive as inativas: a edição mostra a inativa só no time que já a tem marcada
        $categorias = Categoria::orderByDesc('sexo_categoria')->orderBy('idade_min_categoria')->get();

        return view('admin.times.index', compact('times', 'categorias'));
    }

    public function store(Request $request)
    {
        $this->validar($request);

        // Sem logo, texto vazio (a coluna não aceita NULL; a lista mostra o escudo genérico): antes dava erro 500
        $dados = [...$request->only(['nome_time', 'tipo_time']), 'logo_time' => ''];
        [$logo, $aviso] = $this->tratarLogo($request);
        if ($logo) {
            $dados['logo_time'] = $logo;
        }

        $dados['status_time'] = 'ATIVO';
        DB::transaction(fn () => Time::create($dados)->definirCategorias($request->input('categorias')));

        return redirect()->route('admin.times.index')->with('sucesso', 'Time criado com sucesso.')
            ->with('avisos_imagem', array_filter([$aviso]));
    }

    public function update(Request $request, $id)
    {
        $time = Time::findOrFail($id);

        $this->validar($request, $time);

        if ($erros = $time->errosDasCategoriasQueSaem($request->input('categorias'))) {
            return back()->withErrors(['categorias' => $erros])->withInput();
        }

        $dados = $request->only(['nome_time', 'tipo_time']);
        [$logo, $aviso] = $this->tratarLogo($request);
        if ($logo) {
            $dados['logo_time'] = $logo;
        }
        $antiga = $time->logo_time;

        DB::transaction(function () use ($time, $dados, $request) {
            $time->update($dados);
            $time->definirCategorias($request->input('categorias'));
        });

        // A logo antiga sai depois de gravar, se tem nome gerado e nenhum outro time a usa (as do Git ficam)
        if ($logo) {
            TratadorDeImagem::apagarSeNinguemUsa(self::PASTA_IMAGENS, $antiga, fn ($arquivo) => Time::where('logo_time', $arquivo)->exists());
        }

        return redirect()->route('admin.times.index')->with('sucesso', 'Time atualizado com sucesso.')
            ->with('avisos_imagem', array_filter([$aviso]));
    }

    /**
     * Logo enviada, tratada como a do campeonato (28/10/2026): inteira num quadrado de até 512 px, PNG com fundo
     * transparente e nome gerado ("logo-time-20261028-101500-a1b2c3.png"). Antes ia com o nome original do arquivo:
     * dois times com "logo.png" (ou o envio de "time-preto.jpg", já usado pelo Botafogo) trocavam a logo um do outro.
     *
     * @return array{0: ?string, 1: ?string} [arquivo gravado (null sem envio), aviso de logo pequena]
     */
    private function tratarLogo(Request $request): array
    {
        if (! $request->hasFile('logo_time')) {
            return [null, null];
        }

        $logo = TratadorDeImagem::logo($request->file('logo_time')->getRealPath(), self::PASTA_IMAGENS, 'logo-time');

        return [$logo['arquivo'], $logo['aviso']];
    }

    // Nome único (a collation ignora maiúsculas e acentos), ao menos uma categoria e, entre as que o time ainda não
    // tem, só categorias ativas (a inativa que ele já tem pode continuar marcada)
    private function validar(Request $request, ?Time $time = null): void
    {
        $request->validate([
            'nome_time'    => ['required', 'string', 'max:50', Rule::unique('tbl_time', 'nome_time')->ignore($time?->id_time, 'id_time')],
            'tipo_time'    => 'required|in:INTERNO,EXTERNO',
            'categorias'   => 'required|array|min:1',
            'categorias.*' => 'integer|exists:tbl_categoria,id_categoria',
            'logo_time'    => 'nullable|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
        ], [
            'nome_time.unique'    => 'Já existe um time com este nome. Marque as categorias nele, em vez de cadastrar outro.',
            'nome_time.max'       => 'O nome do time pode ter até 50 caracteres.',
            'categorias.required' => 'Marque ao menos uma categoria de que o time participa.',
            'categorias.min'      => 'Marque ao menos uma categoria de que o time participa.',
            ...TratadorDeImagem::mensagensDoEnvio(['logo_time']),
        ]);

        $marcadas = Categoria::whereIn('id_categoria', array_map('intval', $request->input('categorias')))->get();

        // Masculino e feminino em cadastros separados (dono do projeto, 28/10/2026): "AACJ" e "AACJ Feminino",
        // "Botafogo" e "Botafogo Feminino", para todos os times
        if ($marcadas->pluck('sexo_categoria')->unique()->count() > 1) {
            $exemplo = preg_replace('/\s+Masculino$/iu', '', trim((string) $request->input('nome_time'))) . ' Feminino';
            throw ValidationException::withMessages(['categorias' =>
                "Um time não mistura categorias masculinas e femininas. Para o feminino, cadastre outro time (ex.: \"{$exemplo}\")."]);
        }

        $jaTem = $time ? $time->categorias()->pluck('tbl_categoria.id_categoria')->map(fn ($id) => (int) $id)->all() : [];
        $inativas = $marcadas->whereNotIn('id_categoria', $jaTem)->where('status_categoria', '!=', 'ATIVO');
        if ($inativas->isNotEmpty()) {
            throw ValidationException::withMessages(['categorias' =>
                'Categoria inativa não pode ser marcada: ' . $inativas->pluck('rotulo')->implode(', ') . '.']);
        }
    }

    public function toggleStatus($id)
    {
        $time = Time::findOrFail($id);
        $novo = strtoupper($time->status_time) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $time->update(['status_time' => $novo]);

        return back()->with('sucesso', "Time {$novo} com sucesso.");
    }

    // Time com jogos ou convocados não é excluído (mensagem em vez do erro de FK); a participação nos
    // campeonatos (tbl_campeonato_time) e as categorias do cadastro (tbl_time_categoria) saem junto
    public function destroy($id)
    {
        $time = Time::findOrFail($id);

        if ($motivo = $time->motivoParaNaoExcluir()) {
            return redirect()->route('admin.times.index')->with('erro', $motivo);
        }

        DB::transaction(function () use ($time) {
            $time->campeonatos()->detach();
            $time->categorias()->detach();
            $time->delete();
        });

        return redirect()->route('admin.times.index')->with('sucesso', 'Time removido com sucesso.');
    }
}
