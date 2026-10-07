<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\EnviaConvitesDoApp;
use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Responsavel;
use App\Models\Endereco;
use App\Models\Categoria;
use App\Models\EventoCalendario;
use App\Models\Notificacao;
use App\Models\Time;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AtletasController extends Controller
{
    use EnviaConvitesDoApp;

    // E-mails opcionais no admin. Os dois são login do app (Fase 9), por isso únicos: o do atleta entre
    // atletas e o do responsável entre responsáveis (um responsável com dois filhos é um cadastro só)
    private const MENSAGENS_EMAIL = [
        'email_atleta.email'       => 'Informe um e-mail válido para o atleta.',
        'email_atleta.unique'      => 'Este e-mail já está cadastrado para outro atleta.',
        'email_responsavel.email'  => 'Informe um e-mail válido para o responsável.',
        'email_responsavel.unique' => 'Este e-mail já está cadastrado para outro responsável.',
    ];

    // Atleta ATIVO sempre tem categoria: é por ela que entra nos eventos (inscrição automática)
    private const MENSAGEM_SEM_CATEGORIA = 'Escolha a categoria do atleta: todo atleta ativo precisa estar numa categoria.';

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

        // Categorias inativadas em que algum atleta ainda está: aparecem no select da edição como
        // "(inativa)", para editar outro campo não tirar o atleta da categoria (como no evento)
        $categoriasInativasEmUso = Categoria::where('status_categoria', '<>', 'ATIVO')
            ->whereIn('id_categoria', DB::table('tbl_categoria_atleta')
                ->where('status_categoria_atleta', Atleta::CATEGORIA_ATIVA)->select('id_categoria'))
            ->get();

        // Botão "Enviar convites pendentes" (Fase 9)
        $convitesPendentes = $this->pendentesDeConvite()->count();

        return view('admin.atletas.index', compact('atletas', 'categorias', 'times', 'categoriasInativasEmUso', 'convitesPendentes'));
    }

    public function store(Request $request)
    {
        $nascimento = Atleta::regrasNascimento();

        // Responsável já cadastrado com o CPF (só os dígitos): o atleta é vinculado a ele
        $responsavelExistente = Responsavel::porCpf($request->cpf_responsavel);

        $request->validate([
            'nome_atleta'                 => 'required|string|max:255',
            'data_nasc_atleta'            => $nascimento['regra'],
            'cpf_atleta'                  => 'required|string|max:14|unique:tbl_atletas,cpf_atleta',
            'rg_atleta'                   => 'required|string|max:20',
            'numero_matricula_atleta'     => 'nullable|string|max:20|unique:tbl_atletas,numero_matricula_atleta',
            'email_atleta'                => 'nullable|email|max:255|unique:tbl_atletas,email_atleta',
            'escola_atleta'               => 'required|string|max:255',
            'sexo_atleta'                 => 'required|in:M,F',
            'id_categoria'                => 'required|integer|exists:tbl_categoria,id_categoria',
            'motivo_categoria'            => 'nullable|string|max:500',
            'foto_atleta'                 => 'nullable|image|max:2048',
            'nome_responsavel'            => 'required|string|max:255',
            'cpf_responsavel'             => 'required|string|max:14',
            'email_responsavel'           => ['nullable', 'email', 'max:150', $this->emailResponsavelUnico($responsavelExistente)],
            'whatsapp_responsavel'        => 'required|string|max:20',
            'grau_parentesco_responsavel' => ['required', Rule::in(Responsavel::GRAUS_PARENTESCO)],
            'cep_endereco'                => 'required|string|max:9',
            'rua_endereco'                => 'required|string|max:255',
            'numero_endereco'             => 'required|string|max:10',
            'bairro_endereco'             => 'required|string|max:100',
            'complemento_endereco'        => 'nullable|string|max:100',
            'cidade_endereco'             => 'required|string|max:100',
            'estado_endereco'             => 'required|string|max:2',
        ], [...$nascimento['mensagens'], ...self::MENSAGENS_EMAIL, 'id_categoria.required' => self::MENSAGEM_SEM_CATEGORIA]);

        $this->validarCategoria($request);

        try {
            $numero = DB::transaction(fn () => $this->cadastrarAtleta($request, $responsavelExistente));
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput()
                ->with('erro', 'Não foi possível gerar o número de matrícula agora. Tente salvar de novo.');
        }

        $mensagem = "Atleta cadastrado com sucesso. Matrícula: {$numero}";
        if ($responsavelExistente) {
            $mensagem .= " O responsável {$responsavelExistente->nome_responsavel} já estava cadastrado com este CPF:"
                . ' o atleta foi vinculado a ele e os dados do responsável não foram alterados'
                . ($responsavelExistente->wasChanged('email_responsavel') ? ' (só o e-mail, que estava vazio, foi preenchido).' : '.');
        }

        return redirect()->route('admin.atletas.index')->with('sucesso', $mensagem);
    }

    // E-mail do responsável único entre responsáveis, sem acusar o próprio cadastro dele
    private function emailResponsavelUnico(?Responsavel $responsavel)
    {
        return Rule::unique('tbl_responsavel', 'email_responsavel')->ignore($responsavel?->id_responsavel, 'id_responsavel');
    }

    /**
     * Grava endereço, responsável, atleta, categoria e número de matrícula; devolve o número.
     * Responsável já cadastrado com o CPF: o atleta é vinculado a ele, sem mudar os dados dele (o cadastro
     * é de outro atleta, da mesma família); só o e-mail é preenchido se estava vazio (é o login do app).
     */
    private function cadastrarAtleta(Request $request, ?Responsavel $responsavelExistente): string
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

        // 2. Responsável: o já cadastrado (completa só o e-mail vazio) ou um novo
        if ($responsavelExistente) {
            $responsavel = $responsavelExistente;
            if (blank($responsavel->email_responsavel) && filled($request->email_responsavel)) {
                $responsavel->update(['email_responsavel' => $request->email_responsavel]);
            }
        } else {
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
        }

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

        // 5. Categoria (obrigatória: o atleta já nasce ATIVO; validada em validarCategoria)
        $atleta->trocarCategoria((int) $request->id_categoria, $request->motivo_categoria);

        // 6. Número de matrícula: o informado ou o próximo (A001, A002...)
        return $atleta->atribuirNumeroMatricula();
    }

    public function update(Request $request, $id)
    {
        $atleta     = Atleta::with(['endereco', 'responsaveis', 'categoriasAtivas', 'times'])->findOrFail($id);
        $nascimento = Atleta::regrasNascimento();

        // Responsável que esta edição altera: o atual do atleta, ou (sem nenhum) o já cadastrado com o CPF
        $responsavelAtual     = $atleta->responsaveis->first();
        $responsavelExistente = $responsavelAtual ? null : Responsavel::porCpf($request->cpf_responsavel);

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
            'email_responsavel'           => ['nullable', 'email', 'max:150', $this->emailResponsavelUnico($responsavelAtual ?? $responsavelExistente)],
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

        // CPF do responsável trocado para o de OUTRO responsável já cadastrado: recusa (viraria um cadastro
        // repetido). Trocar o responsável do atleta por um já cadastrado ainda não é possível pela edição
        if ($responsavelAtual
            && Responsavel::digitosCpf($request->cpf_responsavel) !== Responsavel::digitosCpf($responsavelAtual->cpf_responsavel)
            && ($outro = Responsavel::porCpf($request->cpf_responsavel, exceto: $responsavelAtual->id_responsavel))) {
            throw ValidationException::withMessages([
                'cpf_responsavel' => "Este CPF já é do responsável {$outro->nome_responsavel}, de outro cadastro. Confira o CPF.",
            ])->errorBag('edicao');
        }

        // Só valida a categoria quando ela muda: editar outro campo de um atleta que já está
        // numa categoria (por exemplo, acima da idade, com motivo) não pode ser bloqueado
        $idAtual = $atleta->categoriasAtivas->first()?->id_categoria;
        if ($request->filled('id_categoria') && (int) $request->id_categoria !== $idAtual) {
            $this->validarCategoria($request, 'edicao');
        }

        // Atleta que fica ATIVO depois da edição sempre sai com categoria: o campo vazio encerraria a
        // linha atual (trocarCategoria(null)). INATIVO segue como antes; pendente e rejeitado não passam
        // por aqui (o status deles não muda na edição)
        if (strtoupper($this->statusAposEdicao($request, $atleta)) === 'ATIVO' && ! $request->filled('id_categoria')) {
            throw ValidationException::withMessages(['id_categoria' => self::MENSAGEM_SEM_CATEGORIA])->errorBag('edicao');
        }

        DB::transaction(function () use ($request, $atleta, $responsavelExistente) {

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
                'status_atleta'           => $this->statusAposEdicao($request, $atleta),
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

            // 3. Atualiza responsável (ou, se o atleta não tem, vincula o já cadastrado com o CPF ou cria)
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
            } elseif ($responsavelExistente) {
                // Mesmo CPF de um responsável já cadastrado: vincula sem mudar os dados dele
                $atleta->responsaveis()->attach($responsavelExistente->id_responsavel, [
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
                    // Conflitos que entrar nos eventos da categoria nova criaria (o clique em "Mover" confirma):
                    // reais (horário sobreposto) e de mesmo dia (algum evento sem horário), separados
                    ...$this->conflitosAoMover($movimento['entrar'], $atleta->id_atleta),
                ]);
            }
        }

        return $resposta;
    }

    // ['conflitos' => [...], 'mesmo_dia' => [...]] dos eventos em que o atleta entraria
    private function conflitosAoMover($eventos, int $idAtleta): array
    {
        $todos = $eventos->flatMap(fn ($e) => $e->conflitosPara([$idAtleta])
            ->map(fn ($c) => [
                'fraco' => $c['fraco'],
                'texto' => "{$e->titulo_evento_calendario} ({$e->data_evento_calendario->format('d/m')}): "
                    . EventoCalendario::descreverConflito($c),
            ]));

        [$fracos, $fortes] = $todos->partition(fn ($c) => $c['fraco']);

        return [
            'conflitos' => $fortes->pluck('texto')->values()->all(),
            'mesmo_dia' => $fracos->pluck('texto')->values()->all(),
        ];
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

        ['sairam' => $sairam, 'entraram' => $entraram, 'notificados' => $notificados]
            = $atleta->moverInscricoes((int) $request->de, (int) $request->para, auth('admin')->id());

        // Uma notificação de resumo (AGENDA) para o atleta, não uma por evento
        return back()->with('sucesso', "Inscrições de {$atleta->nome_atleta} movidas: saiu de {$sairam} evento(s) e entrou em {$entraram}."
            . ($sairam + $entraram > 0 ? Notificacao::textoNotificados($notificados) : ''));
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

        // Ativar exige categoria ativa (é por ela que o atleta entra nos eventos)
        if ($novoStatus === 'ATIVO' && ! $atleta->categoriasAtivas()->exists()) {
            return back()->with('erro', "{$atleta->nome_atleta} está sem categoria. Edite o atleta, escolha a categoria e marque o status Ativo.");
        }

        $atleta->update(['status_atleta' => $novoStatus]);

        return back()->with('sucesso', "Atleta {$novoStatus} com sucesso.");
    }

    /**
     * "Reenviar convite" do app (Fase 9): link "Defina sua senha" para o atleta ou para um responsável dele.
     * Só com o atleta ATIVO (é quando os dois perfis podem entrar). Vale também para quem já tem senha
     * (ajuda quem esqueceu): a senha atual só muda se o link for usado.
     */
    public function convite(Request $request, $id)
    {
        $atleta = Atleta::findOrFail($id);

        $request->validate([
            'perfil'         => 'required|in:atleta,responsavel',
            'id_responsavel' => 'required_if:perfil,responsavel|nullable|integer',
        ]);

        if (! $atleta->podeEntrarNoApp()) {
            return back()->with('erro', 'O convite do app é só para atleta ativo.');
        }

        $usuario = $request->perfil === 'atleta'
            ? $atleta
            : $atleta->responsaveis()->where('tbl_responsavel.id_responsavel', $request->id_responsavel)->first();

        if (! $usuario) {
            return back()->with('erro', 'Este responsável não é do atleta.');
        }
        if (blank($usuario->emailDoApp())) {
            return back()->with('erro', 'Sem e-mail cadastrado: edite o atleta e informe o e-mail antes de enviar o convite.');
        }

        return back()->with('sucesso', ltrim($this->enviarConvites(collect([$usuario]))));
    }

    // "Enviar convites pendentes": todos os atletas ATIVO e responsáveis que ainda não definiram a senha
    public function convitesPendentes()
    {
        $pendentes = $this->pendentesDeConvite();

        if ($pendentes->isEmpty()) {
            return back()->with('sucesso', 'Nenhum convite pendente: todos os atletas ativos e responsáveis com e-mail já definiram a senha.');
        }

        return back()->with('sucesso', "{$pendentes->count()} convite(s) do app processado(s)." . $this->enviarConvites($pendentes));
    }

    // Status que a edição grava. PENDENTE e REJEITADO só mudam pela tela de Matrículas: a edição mantém o atual
    private function statusAposEdicao(Request $request, Atleta $atleta): string
    {
        return $atleta->foiAprovado() && $request->filled('status_atleta')
            ? strtoupper($request->status_atleta)
            : $atleta->status_atleta;
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
