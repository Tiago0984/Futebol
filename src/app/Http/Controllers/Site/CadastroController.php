<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\AutorizacaoAssinaturaMail;
use App\Models\Atleta;
use App\Models\Endereco;
use App\Models\Responsavel;
use App\Models\Autorizacao;
use App\Rules\CpfValido;
use App\Services\TratadorDeImagem;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CadastroController extends Controller
{
    // Exibe o formulário de cadastro
    public function index()
    {
        return view('site.cadastro.cadastro');
    }

    // Processa o cadastro (o CPF é conferido pela regra App\Rules\CpfValido, a mesma do admin)
    public function store(Request $request)
    {
        $cepRegex = '/^\d{5}-?\d{3}$/';

        // CPF sempre no formato do banco (000.000.000-00) antes de validar: assim o "único" compara igual,
        // venha com ou sem pontos (App\Support\Cpf)
        $request->merge(['cpf_atleta' => Cpf::formatar($request->cpf_atleta), 'cpf_responsavel' => Cpf::formatar($request->cpf_responsavel)]);
        // Peso e altura com vírgula (55,5 e 1,80) e a UF em maiúsculas, como o banco guarda (10/11/2026)
        Atleta::normalizarMedidas($request);
        // RG do atleta e do responsável com a pontuação, de 9 ou de 11 dígitos
        foreach (['rg_atleta', 'rg_responsavel'] as $campo) {
            if (is_string($request->input($campo))) {
                $request->merge([$campo => Atleta::formatarRg($request->input($campo))]);
            }
        }
        foreach (['estado_endereco', 'estado_resp_endereco'] as $campo) {
            if (is_string($request->input($campo))) {
                $request->merge([$campo => strtoupper(trim($request->input($campo)))]);
            }
        }
        $medidas = Atleta::regrasMedidas();
        // As mesmas regras do cadastro pelo painel (10/11/2026)
        $rgRegex   = Atleta::REGEX_RG;
        $foneRegex = Responsavel::REGEX_TELEFONE;

        // Idade pelo ano (9 a 17 anos no ano), mesma regra do cadastro pelo admin
        $nascimento = Atleta::regrasNascimento();
        $periodo    = Atleta::regrasPeriodo(); // obrigatório: o treino é no turno oposto ao da escola

        // Responsável já cadastrado com o mesmo CPF (só os dígitos): é reaproveitado se o e-mail bater
        // (irmãos), e o e-mail único não acusa o próprio cadastro dele
        $responsavelExistente = Responsavel::porCpf($request->cpf_responsavel);

        $request->validate([
            // Atleta
            'nome_atleta'            => 'required|string|max:100',
            'data_nasc_atleta'       => $nascimento['regra'],
            'cpf_atleta'             => ['required', 'string', 'max:14', new CpfValido('Informe um CPF válido para o atleta.'), 'unique:tbl_atletas,cpf_atleta'],
            'rg_atleta'              => ['required', 'string', 'max:15', 'regex:' . $rgRegex],
            'sexo_atleta'            => 'required|in:M,F',
            ...$medidas['regras'],
            // Foto do celular até 10 MB: o servidor recorta o quadrado do centro e reduz a 512 px (TratadorDeImagem)
            'foto_atleta'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'escola_atleta'          => 'required|string|max:100',
            'serie_atleta'           => 'required|string|max:20',
            ...$periodo['regras'],
            'sala_atleta'            => 'nullable|string|max:50',
            'descricao_atleta'       => 'nullable|string|max:1000',
            'email_atleta'           => 'required|email|max:255|unique:tbl_atletas,email_atleta',
            // Endereço atleta (no tamanho das colunas de tbl_endereco: antes o número ia até 10 e bairro e cidade
            // até 100, e o banco recusava acima de 6 e de 50)
            'cep_endereco'           => ['required', 'string', 'max:10', 'regex:' . $cepRegex],
            'rua_endereco'           => 'required|string|max:100',
            'numero_endereco'        => 'required|string|max:6',
            'bairro_endereco'        => 'required|string|max:50',
            'cidade_endereco'        => 'required|string|max:50',
            'estado_endereco'        => ['required', 'regex:/^[A-Z]{2}$/'],
            'complemento_endereco'   => 'nullable|string|max:100',
            // Responsável
            'nome_responsavel'       => 'required|string|max:100',
            'cpf_responsavel'        => ['required', 'string', 'max:14', new CpfValido('Informe um CPF válido para o responsável.')],
            'rg_responsavel'         => ['required', 'string', 'max:15', 'regex:' . $rgRegex],
            'email_responsavel'      => ['required', 'email', 'max:150',
                Rule::unique('tbl_responsavel', 'email_responsavel')->ignore($responsavelExistente?->id_responsavel, 'id_responsavel'),
                // Cada um com o próprio e-mail (12/11/2026, só no Matricule-se): o mesmo nos dois mandaria os dois
                // convites para a mesma caixa. Sem diferenciar maiúsculas e espaços, como o Responsavel grava
                function (string $atributo, mixed $valor, \Closure $falha) use ($request) {
                    $doAtleta = Responsavel::normalizarEmail(is_string($request->email_atleta) ? $request->email_atleta : null);
                    if ($doAtleta !== null && $doAtleta === Responsavel::normalizarEmail(is_string($valor) ? $valor : null)) {
                        $falha('O e-mail do responsável precisa ser diferente do e-mail do atleta.');
                    }
                }],
            'telefone_responsavel'   => ['nullable', 'string', 'max:15', 'regex:' . $foneRegex],
            'whatsapp_responsavel'   => ['required', 'string', 'max:15', 'regex:' . $foneRegex],
            'grau_parentesco'        => ['required', Rule::in(Responsavel::GRAUS_PARENTESCO)],
            // Endereço responsável
            'cep_resp_endereco'      => ['required', 'string', 'max:10', 'regex:' . $cepRegex],
            'rua_resp_endereco'      => 'required|string|max:100',
            'numero_resp_endereco'   => 'required|string|max:6',
            'bairro_resp_endereco'   => 'required|string|max:50',
            'cidade_resp_endereco'   => 'required|string|max:50',
            'estado_resp_endereco'   => ['required', 'regex:/^[A-Z]{2}$/'],
        ], [
            ...$medidas['mensagens'],
            'foto_atleta.image'          => 'A foto precisa ser uma imagem (JPG, PNG ou WebP).',
            'foto_atleta.mimes'          => 'A foto precisa ser uma imagem (JPG, PNG ou WebP).',
            ...TratadorDeImagem::mensagensDoEnvio(['foto_atleta']),
            'numero_endereco.max'        => 'O número pode ter no máximo 6 caracteres (ex.: 123, 1500A ou S/N).',
            'numero_resp_endereco.max'   => 'O número pode ter no máximo 6 caracteres (ex.: 123, 1500A ou S/N).',
            'estado_endereco.regex'      => 'Informe a UF com 2 letras (ex.: SP).',
            'estado_resp_endereco.regex' => 'Informe a UF com 2 letras (ex.: SP).',
            'email_atleta.unique'  => 'Este e-mail já está cadastrado.',
            'cpf_atleta.unique'    => 'Este CPF já está cadastrado.',
            'email_responsavel.unique' => 'Este e-mail já está cadastrado para outro responsável.',
            'rg_atleta.regex'      => 'Informe um RG válido para o atleta.',
            'rg_responsavel.regex' => 'Informe um RG válido para o responsável.',
            'cep_endereco.regex'      => 'Informe um CEP válido.',
            'cep_resp_endereco.regex' => 'Informe um CEP válido.',
            'telefone_responsavel.regex' => 'Informe um telefone válido com DDD.',
            'whatsapp_responsavel.regex' => 'Informe um WhatsApp válido com DDD.',
            ...$nascimento['mensagens'],
            ...$periodo['mensagens'],
        ]);

        // CPF já cadastrado: só reaproveita se o e-mail informado for o do cadastro (o formulário é público:
        // quem só sabe o CPF não consegue se pendurar no responsável de outra família)
        if ($responsavelExistente
            && $responsavelExistente->email_responsavel !== Responsavel::normalizarEmail($request->email_responsavel)) {
            return back()->withErrors(['cpf_responsavel' => 'CPF já cadastrado com outro e-mail; procure a secretaria.'])->withInput();
        }

        // 1. Salva endereço do atleta
        $enderecoAtleta = Endereco::create([
            'cep_endereco'          => $request->cep_endereco,
            'rua_endereco'          => $request->rua_endereco,
            'numero_endereco'       => $request->numero_endereco,
            'bairro_endereco'       => $request->bairro_endereco,
            'cidade_endereco'       => $request->cidade_endereco,
            'estado_endereco'       => $request->estado_endereco,
            'complemento_endereco'  => $request->complemento_endereco,
        ]);

        // 2. Salva foto do atleta (se enviada): quadrado do centro, até 512 px, JPG (antes, a foto do celular ia inteira,
        // com vários MB). Grava em storage/app/public/atletas, como antes (o caminho com "/" é o do site: Atleta::urlFoto)
        $fotoNome = null;
        if ($request->hasFile('foto_atleta')) {
            try {
                $fotoNome = 'atletas/' . TratadorDeImagem::foto($request->file('foto_atleta')->getRealPath(), 'public', 'atletas', 'foto-atleta');
            } catch (\Throwable $e) {
                $enderecoAtleta->delete();

                return back()->withErrors(['foto_atleta' => 'Não foi possível abrir esta foto. Tente outra (JPG ou PNG).'])->withInput();
            }
        }

        // 3. Gera token único para o link de assinatura
        $token = Str::random(60);

        // 4. Salva o atleta com status PENDENTE
        $atleta = Atleta::create([
            'nome_atleta'            => $request->nome_atleta,
            'data_nasc_atleta'       => $request->data_nasc_atleta,
            'cpf_atleta'             => $request->cpf_atleta,
            'rg_atleta'              => $request->rg_atleta,
            'sexo_atleta'            => $request->sexo_atleta,
            'peso_atleta'            => $request->peso_atleta,
            'altura_atleta'          => $request->altura_atleta,
            'escola_atleta'          => $request->escola_atleta,
            'serie_atleta'           => $request->serie_atleta,
            'periodo_escolar_atleta' => $request->periodo_escolar_atleta,
            'turno_treino_atleta'    => $request->turno_treino_atleta,
            'sala_atleta'            => $request->sala_atleta,
            'descricao_atleta'       => $request->descricao_atleta,
            'foto_atleta'            => $fotoNome,
            'email_atleta'           => $request->email_atleta,
            'password'               => Hash::make(Str::random(20)),
            'status_atleta'          => 'PENDENTE',
            'token_cadastro'         => $token,
            'id_endereco'            => $enderecoAtleta->id_endereco,
        ]);

        // 5 e 6. Responsável: o já cadastrado (mesmo CPF e e-mail; os dados dele não mudam por este
        // formulário público), ou um novo, com o próprio endereço
        $responsavel = $responsavelExistente ?? $this->novoResponsavel($request);

        // 7. Vincula atleta e responsável
        $atleta->responsaveis()->attach($responsavel->id_responsavel, [
            'grau_parentesco_responsavel' => $request->grau_parentesco,
        ]);

        // 8. Gera token de assinatura e cria autorização pendente
        $tokenAssinatura = Str::random(60);

        Autorizacao::create([
            'id_atleta'                  => $atleta->id_atleta,
            'id_responsavel'             => $responsavel->id_responsavel,
            'data_assinatura_autorizacao'=> now(),
            'token_assinatura'           => $tokenAssinatura,
            'status_autorizacao'         => 'PENDENTE',
        ]);

        // 9. Monta o link de assinatura
        $linkAssinatura = route('assinar.show', $tokenAssinatura);

        // 10. Envia o link de assinatura por e-mail ao responsável, pela fila (até 3 tentativas;
        // a família não espera o SMTP). O link para copiar só aparece se nem a fila aceitar
        // (ou, com a fila "sync", se o envio falhar na hora). O painel continua enviando na hora.
        $emailEnviado = true;
        try {
            Mail::to($responsavel->email_responsavel)
                ->queue(new AutorizacaoAssinaturaMail($atleta, $responsavel, $linkAssinatura));
        } catch (\Throwable $e) {
            $emailEnviado = false;
            Log::error('Falha ao enviar e-mail de autorização de assinatura', [
                'id_atleta' => $atleta->id_atleta,
                'erro' => $e->getMessage(),
            ]);
        }

        $mensagemSucesso = $emailEnviado
            ? 'Cadastro enviado com sucesso! Um link de assinatura será enviado em instantes para o e-mail do responsável.'
            : 'Cadastro enviado com sucesso, mas não foi possível enviar o e-mail ao responsável. Use o link abaixo para enviar manualmente.';

        return redirect()->route('cadastro.index')
            ->with('sucesso', $mensagemSucesso)
            ->with('link_assinatura', $emailEnviado ? null : $linkAssinatura);
    }

    // Responsável novo, com o endereço informado no formulário
    private function novoResponsavel(Request $request): Responsavel
    {
        $enderecoResp = Endereco::create([
            'cep_endereco'         => $request->cep_resp_endereco,
            'rua_endereco'         => $request->rua_resp_endereco,
            'numero_endereco'      => $request->numero_resp_endereco,
            'bairro_endereco'      => $request->bairro_resp_endereco,
            'cidade_endereco'      => $request->cidade_resp_endereco,
            'estado_endereco'      => $request->estado_resp_endereco,
            'complemento_endereco' => null,
        ]);

        return Responsavel::create([
            'nome_responsavel'     => $request->nome_responsavel,
            'cpf_responsavel'      => $request->cpf_responsavel,
            'rg_responsavel'       => $request->rg_responsavel,
            'telefone_responsavel' => $request->telefone_responsavel,
            'whatsapp_responsavel' => $request->whatsapp_responsavel,
            'email_responsavel'    => $request->email_responsavel,
            'aceite_responsavel'   => 'N',
            'id_endereco'          => $enderecoResp->id_endereco,
        ]);
    }
}
