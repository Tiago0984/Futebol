<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Endereco;
use App\Models\Responsavel;
use App\Models\Autorizacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CadastroController extends Controller
{
    // Exibe o formulário de cadastro
    public function index()
    {
        return view('site.cadastro.cadastro');
    }

    // Valida se um CPF é válido (formato e dígitos verificadores)
    private function cpfValido(?string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', (string) $cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    // Processa o cadastro
    public function store(Request $request)
    {
        $cpfRegex = '/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/';
        $cepRegex = '/^\d{5}-?\d{3}$/';
        $rgRegex  = '/^[0-9Xx.\-\s]{5,15}$/';
        $foneRegex = '/^\(?\d{2}\)?\s?\d{4,5}-?\d{4}$/';

        // Data mais antiga permitida (idade máxima 17) e mais recente permitida (idade mínima 9)
        $nascMaisAntiga = now()->subYears(18)->addDay()->format('Y-m-d');
        $nascMaisRecente = now()->subYears(9)->format('Y-m-d');

        $request->validate([
            // Atleta
            'nome_atleta'            => 'required|string|max:100',
            'data_nasc_atleta'       => "required|date|after_or_equal:{$nascMaisAntiga}|before_or_equal:{$nascMaisRecente}",
            'cpf_atleta'             => ['required', 'string', 'max:14', 'regex:' . $cpfRegex, 'unique:tbl_atletas,cpf_atleta'],
            'rg_atleta'              => ['required', 'string', 'max:15', 'regex:' . $rgRegex],
            'sexo_atleta'            => 'required|in:M,F',
            'escola_atleta'          => 'required|string|max:100',
            'serie_atleta'           => 'required|string|max:20',
            'periodo_escolar_atleta' => 'required|string|max:20',
            'email_atleta'           => 'required|email|unique:tbl_atletas,email_atleta',
            // Endereço atleta
            'cep_endereco'           => ['required', 'string', 'max:10', 'regex:' . $cepRegex],
            'rua_endereco'           => 'required|string|max:100',
            'numero_endereco'        => 'required|string|max:10',
            'bairro_endereco'        => 'required|string|max:100',
            'cidade_endereco'        => 'required|string|max:100',
            'estado_endereco'        => 'required|string|max:2',
            // Responsável
            'nome_responsavel'       => 'required|string|max:100',
            'cpf_responsavel'        => ['required', 'string', 'max:14', 'regex:' . $cpfRegex],
            'rg_responsavel'         => ['required', 'string', 'max:15', 'regex:' . $rgRegex],
            'email_responsavel'      => 'required|email|max:150',
            'telefone_responsavel'   => ['nullable', 'string', 'max:15', 'regex:' . $foneRegex],
            'whatsapp_responsavel'   => ['required', 'string', 'max:15', 'regex:' . $foneRegex],
            'grau_parentesco'        => 'required|string|max:50',
            // Endereço responsável
            'cep_resp_endereco'      => ['required', 'string', 'max:10', 'regex:' . $cepRegex],
            'rua_resp_endereco'      => 'required|string|max:100',
            'numero_resp_endereco'   => 'required|string|max:10',
            'bairro_resp_endereco'   => 'required|string|max:100',
            'cidade_resp_endereco'   => 'required|string|max:100',
            'estado_resp_endereco'   => 'required|string|max:2',
        ], [
            'email_atleta.unique'  => 'Este e-mail já está cadastrado.',
            'cpf_atleta.unique'    => 'Este CPF já está cadastrado.',
            'cpf_atleta.regex'     => 'Informe um CPF válido para o atleta.',
            'cpf_responsavel.regex'=> 'Informe um CPF válido para o responsável.',
            'rg_atleta.regex'      => 'Informe um RG válido para o atleta.',
            'rg_responsavel.regex' => 'Informe um RG válido para o responsável.',
            'cep_endereco.regex'      => 'Informe um CEP válido.',
            'cep_resp_endereco.regex' => 'Informe um CEP válido.',
            'data_nasc_atleta.after_or_equal'  => 'A idade do atleta deve estar entre 9 e 17 anos.',
            'data_nasc_atleta.before_or_equal' => 'A idade do atleta deve estar entre 9 e 17 anos.',
            'telefone_responsavel.regex' => 'Informe um telefone válido com DDD.',
            'whatsapp_responsavel.regex' => 'Informe um WhatsApp válido com DDD.',
        ]);

        if (!$this->cpfValido($request->cpf_atleta)) {
            return back()->withErrors(['cpf_atleta' => 'Informe um CPF válido para o atleta.'])->withInput();
        }

        if (!$this->cpfValido($request->cpf_responsavel)) {
            return back()->withErrors(['cpf_responsavel' => 'Informe um CPF válido para o responsável.'])->withInput();
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

        // 2. Salva foto do atleta (se enviada)
        $fotoNome = null;
        if ($request->hasFile('foto_atleta')) {
            $fotoNome = $request->file('foto_atleta')->store('atletas', 'public');
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
            'sala_atleta'            => $request->sala_atleta,
            'descricao_atleta'       => $request->descricao_atleta,
            'foto_atleta'            => $fotoNome,
            'email_atleta'           => $request->email_atleta,
            'password'               => Hash::make(Str::random(20)),
            'status_atleta'          => 'PENDENTE',
            'token_cadastro'         => $token,
            'id_endereco'            => $enderecoAtleta->id_endereco,
        ]);

        // 5. Salva endereço do responsável
        $enderecoResp = Endereco::create([
            'cep_endereco'         => $request->cep_resp_endereco,
            'rua_endereco'         => $request->rua_resp_endereco,
            'numero_endereco'      => $request->numero_resp_endereco,
            'bairro_endereco'      => $request->bairro_resp_endereco,
            'cidade_endereco'      => $request->cidade_resp_endereco,
            'estado_endereco'      => $request->estado_resp_endereco,
            'complemento_endereco' => null,
        ]);

        // 6. Salva o responsável
        $responsavel = Responsavel::create([
            'nome_responsavel'     => $request->nome_responsavel,
            'cpf_responsavel'      => $request->cpf_responsavel,
            'rg_responsavel'       => $request->rg_responsavel,
            'telefone_responsavel' => $request->telefone_responsavel,
            'whatsapp_responsavel' => $request->whatsapp_responsavel,
            'email_responsavel'    => $request->email_responsavel,
            'aceite_responsavel'   => 'N',
            'id_endereco'          => $enderecoResp->id_endereco,
        ]);

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

        // 10. Envia link ao responsável via WhatsApp (link direto)
        // Em produção: integrar com API do WhatsApp (Twilio, Z-API, etc.)
        // Por enquanto armazena o link para exibir na tela
        $whatsapp = preg_replace('/\D/', '', $request->whatsapp_responsavel);
        $mensagem = urlencode("Olá {$request->nome_responsavel}! Clique no link abaixo para assinar a autorização de {$request->nome_atleta} na Escolinha de Futebol: {$linkAssinatura}");
        $linkWhatsApp = "https://wa.me/55{$whatsapp}?text={$mensagem}";

        return redirect()->route('cadastro.index')
            ->with('sucesso', 'Cadastro enviado com sucesso! Envie o link de assinatura ao responsável.')
            ->with('link_whatsapp', $linkWhatsApp)
            ->with('link_assinatura', $linkAssinatura);
    }
}
