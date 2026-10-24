<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\ComissaoTecnica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Cadastro da comissão técnica (Etapa C1, 25/10/2026): criar, editar, ativar e desativar. Sem exclusão: o
 * profissional fica nos eventos e na grade em que já está (desativado, não aparece para novas escolhas).
 */
class ComissaoTecnicaController extends Controller
{
    public function index()
    {
        $profissionais = ComissaoTecnica::with(['categoriaPrincipal', 'categorias'])
            ->orderByRaw("status_comissao_tecnica = 'ATIVO' DESC")
            ->orderBy('nome_comissao_tecnica')
            ->get();

        return view('admin.comissao.index', compact('profissionais'));
    }

    public function create()
    {
        return view('admin.comissao.form', ['profissional' => new ComissaoTecnica(), 'categorias' => $this->categoriasDoFormulario()]);
    }

    public function store(Request $request)
    {
        $dados = $this->dados($request);

        $profissional = DB::transaction(function () use ($request, $dados) {
            $profissional = new ComissaoTecnica($dados);
            $profissional->status_comissao_tecnica = 'ATIVO';
            $this->gravarFoto($request, $profissional);
            $profissional->save();
            $profissional->definirCategorias($request->input('categorias', []));

            return $profissional;
        });

        return redirect()->route('admin.comissao.index')
            ->with('sucesso', "{$profissional->nome_comissao_tecnica} cadastrado(a) na comissão técnica.");
    }

    public function edit($id)
    {
        $profissional = ComissaoTecnica::with('categorias')->findOrFail($id);

        return view('admin.comissao.form', ['profissional' => $profissional, 'categorias' => $this->categoriasDoFormulario($profissional)]);
    }

    public function update(Request $request, $id)
    {
        $profissional = ComissaoTecnica::findOrFail($id);
        $dados = $this->dados($request, $profissional);

        DB::transaction(function () use ($request, $dados, $profissional) {
            $profissional->fill($dados);
            $this->gravarFoto($request, $profissional);
            $profissional->save();
            $profissional->definirCategorias($request->input('categorias', []));
        });

        return redirect()->route('admin.comissao.index')
            ->with('sucesso', "Dados de {$profissional->nome_comissao_tecnica} atualizados.");
    }

    // Desativado: some das novas escolhas e do perfil do app, mas continua nos eventos em que já está
    public function toggleStatus($id)
    {
        $profissional = ComissaoTecnica::findOrFail($id);
        $profissional->status_comissao_tecnica = $profissional->estaAtivo() ? 'INATIVO' : 'ATIVO';
        $profissional->save();

        return redirect()->route('admin.comissao.index')->with('sucesso', $profissional->estaAtivo()
            ? "{$profissional->nome_comissao_tecnica} foi reativado(a)."
            : "{$profissional->nome_comissao_tecnica} foi desativado(a): não aparece mais para novas escolhas nem no app.");
    }

    // Ativas e, na edição, as inativas que o profissional já atende (para não sumirem do formulário)
    private function categoriasDoFormulario(?ComissaoTecnica $profissional = null)
    {
        $atuais = $profissional?->categorias->pluck('id_categoria')->push($profissional->id_categoria)->filter()->all() ?? [];

        return Categoria::where(fn ($q) => $q->where('status_categoria', 'ATIVO')->orWhereIn('id_categoria', $atuais))
            ->orderBy('sexo_categoria')->orderBy('idade_max_categoria')
            ->get();
    }

    private function dados(Request $request, ?ComissaoTecnica $atual = null): array
    {
        $atuais = $atual ? $atual->categorias()->pluck('tbl_categoria.id_categoria')->push($atual->id_categoria)->filter()->all() : [];
        $categoriaValida = Rule::exists('tbl_categoria', 'id_categoria')
            ->where(fn ($q) => $q->where('status_categoria', 'ATIVO')->orWhereIn('id_categoria', $atuais));

        $request->validate([
            'nome_comissao_tecnica'                   => 'required|string|max:100',
            'cargo_comissao_tecnica'                  => ['required', Rule::in(array_keys(ComissaoTecnica::CARGOS))],
            'id_categoria'                            => ['nullable', 'integer', $categoriaValida],
            'categorias'                              => 'nullable|array',
            'categorias.*'                            => ['integer', $categoriaValida],
            'data_inicio_comissao_tecnica'            => 'nullable|date|before_or_equal:today',
            'licenca_comissao_tecnica'                => 'nullable|string|max:100',
            'especialidade_comissao_tecnica'          => 'nullable|string|max:100',
            'telefone_institucional_comissao_tecnica' => ['nullable', 'string', 'max:20', 'regex:/^[0-9()+\-\s]+$/'],
            'email_institucional_comissao_tecnica'    => 'nullable|email|max:255',
            'foto_comissao_tecnica'                   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            // O admin usa o idioma do config/app.php (as mensagens em português estão ligadas só na API): todas aqui
            'nome_comissao_tecnica.required'                => 'Informe o nome.',
            'nome_comissao_tecnica.max'                     => 'O nome pode ter no máximo 100 caracteres.',
            'cargo_comissao_tecnica.required'               => 'Escolha um cargo da lista.',
            'cargo_comissao_tecnica.in'                     => 'Escolha um cargo da lista.',
            'licenca_comissao_tecnica.max'                  => 'A licença/certificação pode ter no máximo 100 caracteres.',
            'especialidade_comissao_tecnica.max'            => 'A especialidade pode ter no máximo 100 caracteres.',
            'telefone_institucional_comissao_tecnica.max'   => 'O telefone pode ter no máximo 20 caracteres.',
            'email_institucional_comissao_tecnica.max'      => 'O e-mail pode ter no máximo 255 caracteres.',
            'data_inicio_comissao_tecnica.date'             => 'Informe uma data de início válida.',
            'id_categoria.exists'                           => 'Escolha uma categoria ativa.',
            'categorias.*.exists'                           => 'Escolha só categorias ativas.',
            'data_inicio_comissao_tecnica.before_or_equal'  => 'A data de início na AACJ não pode ser no futuro.',
            'telefone_institucional_comissao_tecnica.regex' => 'O telefone só pode ter números, espaços, parênteses, + e -.',
            'email_institucional_comissao_tecnica.email'    => 'Informe um e-mail válido.',
            'foto_comissao_tecnica.image'                   => 'A foto precisa ser uma imagem (JPG, PNG ou WEBP).',
            'foto_comissao_tecnica.mimes'                   => 'A foto precisa ser uma imagem (JPG, PNG ou WEBP).',
            'foto_comissao_tecnica.max'                     => 'A foto pode ter no máximo 2 MB.',
        ]);

        return [
            ...$request->only([
                'nome_comissao_tecnica', 'cargo_comissao_tecnica', 'id_categoria', 'data_inicio_comissao_tecnica',
                'licenca_comissao_tecnica', 'especialidade_comissao_tecnica',
                'telefone_institucional_comissao_tecnica', 'email_institucional_comissao_tecnica',
            ]),
            'foto_autorizada_comissao_tecnica' => $request->boolean('foto_autorizada_comissao_tecnica'),
        ];
    }

    /**
     * Foto nova (nome gerado, sem repetir) substitui a antiga; "Remover foto" tira. Disco "imagens": public/futebol/images.
     * O arquivo antigo só é apagado depois que o banco gravou (DB::afterCommit).
     */
    private function gravarFoto(Request $request, ComissaoTecnica $profissional): void
    {
        $antiga = $profissional->getOriginal('foto_comissao_tecnica');

        if ($request->hasFile('foto_comissao_tecnica')) {
            $caminho = $request->file('foto_comissao_tecnica')->store(ComissaoTecnica::PASTA_FOTO, 'imagens');
            $profissional->foto_comissao_tecnica = basename($caminho);
        } elseif ($request->boolean('remover_foto')) {
            $profissional->foto_comissao_tecnica = null;
        }

        if ($antiga && $antiga !== $profissional->foto_comissao_tecnica) {
            DB::afterCommit(fn () => Storage::disk('imagens')->delete(ComissaoTecnica::PASTA_FOTO . '/' . $antiga));
        }
    }
}
