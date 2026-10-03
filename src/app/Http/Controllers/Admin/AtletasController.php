<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Responsavel;
use App\Models\Endereco;
use App\Models\Categoria;
use App\Models\Time;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AtletasController extends Controller
{
    // E-mails opcionais (CLAUDE.md, seção 8, pergunta 7). O do atleta é o login do app, por isso único;
    // o do responsável não: um responsável pode ter mais de um atleta
    private const MENSAGENS_EMAIL = [
        'email_atleta.email'      => 'Informe um e-mail válido para o atleta.',
        'email_atleta.unique'     => 'Este e-mail já está cadastrado para outro atleta.',
        'email_responsavel.email' => 'Informe um e-mail válido para o responsável.',
    ];

    public function index()
    {
        $atletas = Atleta::with([
            'categoriasAtivas',
            'responsaveis',
            'times',
            'endereco',
        ])->orderBy('nome_atleta')->get();

        $categorias = Categoria::ativas()->get();
        $times      = Time::where('tipo_time', 'INTERNO')->orderBy('nome_time')->get();

        return view('admin.atletas.index', compact('atletas', 'categorias', 'times'));
    }

    public function create()
    {
        $categorias = Categoria::ativas()->get();
        return view('admin.atletas.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $nascimento = Atleta::regrasNascimento();

        $request->validate([
            'nome_atleta'                 => 'required|string|max:255',
            'data_nasc_atleta'            => $nascimento['regra'],
            'cpf_atleta'                  => 'required|string|max:14|unique:tbl_atletas,cpf_atleta',
            'rg_atleta'                   => 'required|string|max:20',
            'numero_matricula_atleta'     => 'nullable|string|max:20|unique:tbl_atletas,numero_matricula_atleta',
            'email_atleta'                => 'nullable|email|max:255|unique:tbl_atletas,email_atleta',
            'escola_atleta'               => 'required|string|max:255',
            'sexo_atleta'                 => 'required|in:M,F',
            'id_categoria'                => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'motivo_categoria'            => 'nullable|string|max:500',
            'foto_atleta'                 => 'nullable|image|max:2048',
            'nome_responsavel'            => 'required|string|max:255',
            'cpf_responsavel'             => 'required|string|max:14',
            'email_responsavel'           => 'nullable|email|max:150',
            'whatsapp_responsavel'        => 'required|string|max:20',
            'grau_parentesco_responsavel' => ['required', Rule::in(Responsavel::GRAUS_PARENTESCO)],
            'cep_endereco'                => 'required|string|max:9',
            'rua_endereco'                => 'required|string|max:255',
            'numero_endereco'             => 'required|string|max:10',
            'bairro_endereco'             => 'required|string|max:100',
            'complemento_endereco'        => 'nullable|string|max:100',
            'cidade_endereco'             => 'required|string|max:100',
            'estado_endereco'             => 'required|string|max:2',
        ], [...$nascimento['mensagens'], ...self::MENSAGENS_EMAIL]);

        $this->validarCategoria($request);

        try {
            $numero = DB::transaction(fn () => $this->cadastrarAtleta($request));
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput()
                ->with('erro', 'Não foi possível gerar o número de matrícula agora. Tente salvar de novo.');
        }

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', "Atleta cadastrado com sucesso. Matrícula: {$numero}");
    }

    // Grava endereço, responsável, atleta, categoria e número de matrícula; devolve o número
    private function cadastrarAtleta(Request $request): string
    {
        // 1. Endereço do atleta
        $endereco = Endereco::create([
            'rua_endereco'         => $request->rua_endereco,
            'numero_endereco'      => $request->numero_endereco,
            'bairro_endereco'      => $request->bairro_endereco,
            'complemento_endereco' => $request->complemento_endereco,
            'cep_endereco'         => $request->cep_endereco,
            'cidade_endereco'      => $request->cidade_endereco,
            'estado_endereco'      => strtoupper($request->estado_endereco),
        ]);

        // 2. Responsável
        $responsavel = Responsavel::create([
            'nome_responsavel'       => $request->nome_responsavel,
            'cpf_responsavel'        => $request->cpf_responsavel,
            'email_responsavel'      => $request->email_responsavel,
            'rg_responsavel'         => '',
            'telefone_responsavel'   => $request->whatsapp_responsavel,
            'whatsapp_responsavel'   => $request->whatsapp_responsavel,
            'assinatura_responsavel' => '',
            'aceite_responsavel'     => 'N',
            'id_endereco'            => $endereco->id_endereco,
        ]);

        // 3. Atleta
        $fotoPath = 'default-player.jpg';
        if ($request->hasFile('foto_atleta')) {
            $ext      = $request->file('foto_atleta')->getClientOriginalExtension();
            $filename = 'atleta_' . uniqid() . '.' . $ext;
            $request->file('foto_atleta')->move(public_path('futebol/images/our-teams'), $filename);
            $fotoPath = $filename;
        }

        $atleta = Atleta::create([
            'nome_atleta'            => $request->nome_atleta,
            'data_nasc_atleta'       => $request->data_nasc_atleta,
            'cpf_atleta'             => $request->cpf_atleta,
            'rg_atleta'              => $request->rg_atleta,
            'email_atleta'           => $request->email_atleta, // vazio vira null (ConvertEmptyStringsToNull)
            'numero_matricula_atleta' => $request->numero_matricula_atleta,
            'escola_atleta'          => $request->escola_atleta,
            'foto_atleta'            => $fotoPath,
            'status_atleta'          => 'ATIVO', // Padronizado para MAIÚSCULO
            'id_endereco'            => $endereco->id_endereco,
            'posicao_atleta'         => $request->posicao_atleta ? strtoupper($request->posicao_atleta) : null,
            'sexo_atleta'            => $request->sexo_atleta,
            'peso_atleta'            => $request->peso_atleta ?? 0,
            'altura_atleta'          => $request->altura_atleta ?? 0,
            'serie_atleta'           => $request->serie_atleta ?? '',
            'periodo_escolar_atleta' => $request->periodo_escolar_atleta ?? '',
            'descricao_atleta'       => $request->descricao_atleta ?? '',
            'sala_atleta'            => $request->sala_atleta,
        ]);

        // 4. Pivot atleta <-> responsável
        $atleta->responsaveis()->attach($responsavel->id_responsavel, [
            'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
        ]);

        // 5. Categoria (se selecionada; já validada em validarCategoria)
        if ($request->filled('id_categoria')) {
            $atleta->trocarCategoria((int) $request->id_categoria, $request->motivo_categoria);
        }

        // 6. Número de matrícula: o informado ou o próximo (A001, A002...)
        return $atleta->atribuirNumeroMatricula();
    }

    public function edit($id)
    {
        $atleta = Atleta::with(['endereco', 'responsaveis', 'categoriasAtivas', 'times'])
            ->findOrFail($id);

        $categorias = Categoria::ativas()->get();

        return view('admin.atletas.edit', compact('atleta', 'categorias'));
    }

    public function update(Request $request, $id)
    {
        $atleta     = Atleta::with(['endereco', 'responsaveis', 'categoriasAtivas', 'times'])->findOrFail($id);
        $nascimento = Atleta::regrasNascimento();

        // Bag "edicao": os erros da edição não podem abrir o modal de cadastro (que usa a bag padrão)
        $request->validateWithBag('edicao', [
            'nome_atleta'                 => 'required|string|max:255',
            'data_nasc_atleta'            => $nascimento['regra'],
            'cpf_atleta'                  => 'required|string|max:14',
            'rg_atleta'                   => 'required|string|max:20',
            'email_atleta'                => ['nullable', 'email', 'max:255', Rule::unique('tbl_atletas', 'email_atleta')->ignore($atleta->id_atleta, 'id_atleta')],
            'escola_atleta'               => 'required|string|max:255',
            'sexo_atleta'                 => 'required|in:M,F',
            'id_categoria'                => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'motivo_categoria'            => 'nullable|string|max:500',
            'status_atleta'               => 'nullable|in:ATIVO,INATIVO',
            'camisa_atleta_time'          => 'nullable|string|max:10',
            'nome_responsavel'            => 'required|string|max:255',
            'cpf_responsavel'             => 'required|string|max:14',
            'email_responsavel'           => 'nullable|email|max:150',
            'whatsapp_responsavel'        => 'required|string|max:20',
            'grau_parentesco_responsavel' => ['required', Rule::in(Responsavel::GRAUS_PARENTESCO)],
            'cep_endereco'                => 'required|string|max:9',
            'rua_endereco'                => 'required|string|max:255',
            'numero_endereco'             => 'required|string|max:10',
            'bairro_endereco'             => 'required|string|max:100',
            'complemento_endereco'        => 'nullable|string|max:100',
            'cidade_endereco'             => 'required|string|max:100',
            'estado_endereco'             => 'required|string|max:2',
        ], [...$nascimento['mensagens'], ...self::MENSAGENS_EMAIL]);

        // Só valida a categoria quando ela muda: editar outro campo de um atleta que já está
        // numa categoria (por exemplo, acima da idade, com motivo) não pode ser bloqueado
        $idAtual = $atleta->categoriasAtivas->first()?->id_categoria;
        if ($request->filled('id_categoria') && (int) $request->id_categoria !== $idAtual) {
            $this->validarCategoria($request, 'edicao');
        }

        DB::transaction(function () use ($request, $atleta) {

            // 1. Atualiza atleta
            $fotoPath = $atleta->foto_atleta;
            if ($request->hasFile('foto_atleta')) {
                $ext      = $request->file('foto_atleta')->getClientOriginalExtension();
                $filename = 'atleta_' . uniqid() . '.' . $ext;
                $request->file('foto_atleta')->move(public_path('futebol/images/our-teams'), $filename);
                $fotoPath = $filename;
            }

            $atleta->update([
                'nome_atleta'             => $request->nome_atleta,
                'data_nasc_atleta'        => $request->data_nasc_atleta,
                'cpf_atleta'              => $request->cpf_atleta,
                'rg_atleta'               => $request->rg_atleta,
                'email_atleta'            => $request->email_atleta,
                'escola_atleta'           => $request->escola_atleta,
                'serie_atleta'            => $request->serie_atleta,
                'periodo_escolar_atleta'  => $request->periodo_escolar_atleta,
                'sexo_atleta'             => $request->sexo_atleta,
                'peso_atleta'             => $request->peso_atleta,
                'altura_atleta'           => $request->altura_atleta,
                'posicao_atleta'          => $request->posicao_atleta ? strtoupper($request->posicao_atleta) : null,
                'descricao_atleta'        => $request->descricao_atleta,
                'sala_atleta'             => $request->sala_atleta,
                // PENDENTE e REJEITADO só mudam pela tela de Matrículas: a edição mantém o status atual
                'status_atleta'           => $atleta->foiAprovado() && $request->filled('status_atleta')
                                                ? strtoupper($request->status_atleta)
                                                : $atleta->status_atleta,
                'foto_atleta'             => $fotoPath,
            ]);

            // 2. Atualiza endereço
            if ($atleta->endereco) {
                $atleta->endereco->update([
                    'rua_endereco'         => $request->rua_endereco,
                    'numero_endereco'      => $request->numero_endereco,
                    'bairro_endereco'      => $request->bairro_endereco,
                    'complemento_endereco' => $request->complemento_endereco,
                    'cep_endereco'         => $request->cep_endereco,
                    'cidade_endereco'      => $request->cidade_endereco,
                    'estado_endereco'      => strtoupper($request->estado_endereco),
                ]);
            }

            // 3. Atualiza responsável (ou cria se ainda não existe)
            $responsavel = $atleta->responsaveis->first();
            if ($responsavel) {
                $responsavel->update([
                    'nome_responsavel'     => $request->nome_responsavel,
                    'cpf_responsavel'      => $request->cpf_responsavel,
                    'email_responsavel'    => $request->email_responsavel,
                    'whatsapp_responsavel' => $request->whatsapp_responsavel,
                    'telefone_responsavel' => $request->whatsapp_responsavel,
                ]);

                $atleta->responsaveis()->updateExistingPivot($responsavel->id_responsavel, [
                    'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
                ]);
            } else {
                $novoResponsavel = Responsavel::create([
                    'nome_responsavel'       => $request->nome_responsavel,
                    'cpf_responsavel'        => $request->cpf_responsavel,
                    'email_responsavel'      => $request->email_responsavel,
                    'rg_responsavel'         => '',
                    'telefone_responsavel'   => $request->whatsapp_responsavel,
                    'whatsapp_responsavel'   => $request->whatsapp_responsavel,
                    'assinatura_responsavel' => '',
                    'aceite_responsavel'     => 'N',
                    'id_endereco'            => $atleta->id_endereco,
                ]);

                $atleta->responsaveis()->attach($novoResponsavel->id_responsavel, [
                    'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
                ]);
            }

            // 4. Categoria: encerra a linha atual e abre uma nova (mesma categoria não muda nada;
            //    vazio só encerra a atual). Nunca sobrescreve nem apaga o histórico.
            $atleta->trocarCategoria(
                $request->filled('id_categoria') ? (int) $request->id_categoria : null,
                $request->motivo_categoria,
            );

            // 5. Sincroniza times — apenas INTERNOS podem ser associados
            $internosIds     = Time::where('tipo_time', 'INTERNO')->pluck('id_time')->map(fn($v) => (int) $v);
            $novosTimeIds    = collect($request->input('times', []))
                                   ->map(fn($v) => (int) $v)
                                   ->filter(fn($id) => $internosIds->contains($id))
                                   ->values();
            $timesExistentes = $atleta->times->unique('id_time')->pluck('id_time')->map(fn($v) => (int) $v);
            $camisaValue     = ($request->filled('camisa_atleta_time') && (int) $request->camisa_atleta_time > 0)
                                ? (int) $request->camisa_atleta_time
                                : 0;
            $posicaoValue    = $request->posicao_atleta ? strtoupper($request->posicao_atleta) : '';

            // Remove times desmarcados
            $remover = $timesExistentes->diff($novosTimeIds);
            if ($remover->isNotEmpty()) {
                $atleta->times()->detach($remover->toArray());
            }

            // Adiciona times novos
            $adicionar = $novosTimeIds->diff($timesExistentes);
            foreach ($adicionar as $timeId) {
                $atleta->times()->attach($timeId, [
                    'status_atleta_time'  => 'TITULAR',
                    'camisa_atleta_time'  => $camisaValue,
                    'posicao_atleta_time' => $posicaoValue,
                ]);
            }

            // Atualiza posição e camisa em TODOS os times selecionados via SQL direto
            if ($novosTimeIds->isNotEmpty()) {
                $pivotUpdate = ['posicao_atleta_time' => $posicaoValue];
                if ($camisaValue > 0) {
                    $pivotUpdate['camisa_atleta_time'] = $camisaValue;
                }
                DB::table('tbl_atleta_time')
                    ->where('id_atleta', $atleta->id_atleta)
                    ->whereIn('id_time', $novosTimeIds->toArray())
                    ->update($pivotUpdate);
            }
        });

        $resposta = redirect()->route('admin.atletas.index')->with('sucesso', 'Atleta atualizado com sucesso.');

        // Trocou de categoria: avisa dos eventos futuros a mover (nada muda sem o admin confirmar)
        $idNova = $request->filled('id_categoria') ? (int) $request->id_categoria : null;
        if ($idAtual && $idNova && $idAtual !== $idNova) {
            $movimento = $atleta->eventosParaMoverInscricoes($idAtual, $idNova);

            if ($movimento['sair']->isNotEmpty() || $movimento['entrar']->isNotEmpty()) {
                $resposta->with('mover_inscricoes', [
                    'id_atleta' => $atleta->id_atleta,
                    'nome'      => $atleta->nome_atleta,
                    'de'        => $idAtual,
                    'para'      => $idNova,
                    'de_rotulo'   => Categoria::find($idAtual)?->rotulo,
                    'para_rotulo' => Categoria::find($idNova)?->rotulo,
                    'sair'      => $movimento['sair']->map(fn ($e) => $e->data_evento_calendario->format('d/m') . ' ' . $e->titulo_evento_calendario)->all(),
                    'entrar'    => $movimento['entrar']->map(fn ($e) => $e->data_evento_calendario->format('d/m') . ' ' . $e->titulo_evento_calendario)->all(),
                ]);
            }
        }

        return $resposta;
    }

    /**
     * "Mover inscrições" depois da troca de categoria (confirmado pelo admin): sai dos eventos futuros
     * e não cancelados da categoria antiga (só das inscrições automáticas) e entra nos da nova.
     */
    public function moverInscricoes(Request $request, $id)
    {
        $atleta = Atleta::with('categoriasAtivas')->findOrFail($id);

        $request->validate([
            'de'   => 'required|integer|exists:tbl_categoria,id_categoria',
            'para' => 'required|integer|exists:tbl_categoria,id_categoria',
        ]);

        // Só move para a categoria em que o atleta está agora (o aviso pode ter ficado velho)
        if ((int) $request->para !== $atleta->categoriasAtivas->first()?->id_categoria) {
            return back()->with('erro', 'A categoria do atleta mudou de novo. Edite o atleta e confira os eventos.');
        }

        ['sairam' => $sairam, 'entraram' => $entraram] = $atleta->moverInscricoes((int) $request->de, (int) $request->para, auth('admin')->id());

        return back()->with('sucesso', "Inscrições de {$atleta->nome_atleta} movidas: saiu de {$sairam} evento(s) e entrou em {$entraram}.");
    }

    public function toggleStatus($id)
    {
        $atleta = Atleta::findOrFail($id);

        // Só alterna ATIVO <-> INATIVO. Pendente ou rejeitado ainda não foi aprovado: ativar por aqui
        // pularia a assinatura da autorização, a categoria e o número de matrícula.
        if (! $atleta->foiAprovado()) {
            return back()->with('erro', 'Este atleta ainda não foi aprovado. Use a tela de Matrículas.');
        }

        $novoStatus = strtoupper($atleta->status_atleta) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $atleta->update(['status_atleta' => $novoStatus]);

        return back()->with('sucesso', "Atleta {$novoStatus} com sucesso.");
    }

    // Categoria escolhida: mesmo sexo, não abaixo da idade e, se acima, com motivo (Categoria::erroParaAtleta)
    private function validarCategoria(Request $request, string $bag = 'default'): void
    {
        if (! $request->filled('id_categoria')) {
            return;
        }

        $erro = Categoria::findOrFail($request->id_categoria)
            ->erroParaAtleta($request->data_nasc_atleta, $request->sexo_atleta, $request->motivo_categoria);

        if ($erro) {
            throw ValidationException::withMessages(['id_categoria' => $erro])->errorBag($bag);
        }
    }
}
