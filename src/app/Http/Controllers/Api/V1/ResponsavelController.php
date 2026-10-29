<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Endereco;
use App\Models\Responsavel;
use App\Rules\CpfValido;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Área do responsável no app (Fase 9). Desde 29/10/2026 o responsável edita os próprios dados: nome, CPF, e-mail
 * (login, único entre responsáveis), telefone, WhatsApp e endereço, com cada mudança no histórico para a secretaria.
 * O RG continua com a secretaria (painel admin).
 */
class ResponsavelController extends Controller
{
    // GET /api/v1/responsavel - dados do responsável autenticado e os filhos ATIVO. endereco.compartilhado_com: os filhos
    // que usam o mesmo registro de endereço (o app pergunta se a mudança vale também para eles)
    public function show(Request $request)
    {
        $responsavel = $request->user()->fresh()->load('endereco');

        return response()->json([
            'success' => true,
            'data'    => self::dadosBasicos($responsavel) + [
                'cpf_responsavel'      => $responsavel->cpf_responsavel,
                'rg_responsavel'       => $responsavel->rg_responsavel,
                'telefone_responsavel' => $responsavel->telefone_responsavel,
                'whatsapp_responsavel' => $responsavel->whatsapp_responsavel,
                'endereco'             => $responsavel->endereco ? $responsavel->endereco->only(Endereco::CAMPOS) + [
                    'compartilhado_com' => $responsavel->filhosNoMesmoEndereco()
                        ->map(fn ($filho) => ['id_atleta' => $filho->id_atleta, 'nome_atleta' => $filho->nome_atleta])->values()->all(),
                ] : null,
                'atletas'              => self::atletasDoResponsavel($responsavel),
            ],
        ]);
    }

    // PUT/PATCH /api/v1/responsavel - nome, CPF, e-mail, telefone, WhatsApp e endereço ("sometimes": envio parcial).
    // O endereço vai inteiro (o formulário do app); endereco_dos_filhos (padrão true) diz se a mudança vale também
    // para os filhos que usam o mesmo endereço (Responsavel::atualizarPeloApp). RG e o resto são ignorados
    public function update(Request $request)
    {
        // Relido do banco: o objeto do token pode ter sido mudado por outra requisição (nos testes, o mesmo objeto)
        $responsavel = $request->user()->fresh();

        // Normaliza antes do unique (o model grava o e-mail em minúsculas e o CPF como 000.000.000-00)
        if ($request->has('email_responsavel')) {
            $request->merge(['email_responsavel' => Responsavel::normalizarEmail($request->input('email_responsavel'))]);
        }
        if ($request->filled('cpf_responsavel')) {
            $request->merge(['cpf_responsavel' => Cpf::formatar($request->input('cpf_responsavel'))]);
        }
        // Número e CEP podem vir como número no JSON: o endereço é validado como texto
        if (is_array($request->input('endereco'))) {
            $request->merge(['endereco' => array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : $v, $request->input('endereco'))]);
        }

        $dados = $request->validate([
            'nome_responsavel'     => 'sometimes|required|string|min:3|max:100',
            'cpf_responsavel'      => ['bail', 'sometimes', 'required', 'string', 'max:14', new CpfValido('Informe um CPF válido.'),
                Rule::unique('tbl_responsavel', 'cpf_responsavel')->ignore($responsavel->id_responsavel, 'id_responsavel')],
            'email_responsavel'    => ['sometimes', 'required', 'email', 'max:150',
                Rule::unique('tbl_responsavel', 'email_responsavel')->ignore($responsavel->id_responsavel, 'id_responsavel')],
            'telefone_responsavel' => 'sometimes|nullable|string|max:20',
            'whatsapp_responsavel' => 'sometimes|required|string|max:20',
            'endereco'                      => 'sometimes|required|array',
            'endereco.cep_endereco'         => ['required_with:endereco', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'endereco.rua_endereco'         => 'required_with:endereco|string|max:100',
            'endereco.numero_endereco'      => 'required_with:endereco|string|max:6',
            'endereco.bairro_endereco'      => 'required_with:endereco|string|max:50',
            'endereco.complemento_endereco' => 'nullable|string|max:100',
            'endereco.cidade_endereco'      => 'required_with:endereco|string|max:50',
            'endereco.estado_endereco'      => ['required_with:endereco', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'endereco_dos_filhos'           => 'sometimes|boolean',
        ], [
            'cpf_responsavel.unique'         => 'Este CPF já está cadastrado para outro responsável.',
            'email_responsavel.unique'       => 'Este e-mail já está cadastrado para outro responsável.',
            'endereco.cep_endereco.regex'    => 'Informe um CEP com 8 dígitos (ex.: 13560-000).',
            'endereco.estado_endereco.regex' => 'Informe a UF com 2 letras (ex.: SP).',
        ], [
            'nome_responsavel'              => 'nome',
            'endereco'                      => 'endereço',
            'endereco.cep_endereco'         => 'CEP',
            'endereco.rua_endereco'         => 'rua',
            'endereco.numero_endereco'      => 'número',
            'endereco.bairro_endereco'      => 'bairro',
            'endereco.complemento_endereco' => 'complemento',
            'endereco.cidade_endereco'      => 'cidade',
            'endereco.estado_endereco'      => 'UF',
            'endereco_dos_filhos'           => 'endereço dos filhos',
        ]);

        $responsavel->atualizarPeloApp(
            Arr::except($dados, ['endereco', 'endereco_dos_filhos']),
            $dados['endereco'] ?? null,
            $request->boolean('endereco_dos_filhos', true),
        );

        return $this->show($request);
    }

    // PUT /api/v1/responsavel/senha - confere a senha atual e grava a nova com Hash::make()
    public function updateSenha(Request $request)
    {
        $responsavel = $request->user();

        $dados = $request->validate([
            'senha_atual' => 'required|string',
            'nova_senha'  => 'required|string|min:8|confirmed|different:senha_atual',
        ]);

        if (!Hash::check($dados['senha_atual'], $responsavel->senha_responsavel)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta.',
            ], 422);
        }

        $responsavel->forceFill(['senha_responsavel' => Hash::make($dados['nova_senha'])])->save();

        // Encerra as sessões de outros aparelhos, mantendo o token atual
        $responsavel->tokens()
            ->where('id', '!=', $responsavel->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso.',
        ]);
    }

    // Também usados na resposta do login
    public static function dadosBasicos(Responsavel $responsavel): array
    {
        return [
            'id_responsavel'    => $responsavel->id_responsavel,
            'nome_responsavel'  => $responsavel->nome_responsavel,
            'email_responsavel' => $responsavel->email_responsavel,
        ];
    }

    public static function atletasDoResponsavel(Responsavel $responsavel): array
    {
        return $responsavel->atletasAtivos()->orderBy('nome_atleta')->get()
            ->map(fn ($atleta) => [
                'id_atleta'                   => $atleta->id_atleta,
                'nome_atleta'                 => $atleta->nome_atleta,
                'numero_matricula_atleta'     => $atleta->numero_matricula_atleta,
                'foto_atleta'                 => $atleta->foto_atleta,
                'foto_url'                    => $atleta->urlFoto(),
                'grau_parentesco_responsavel' => $atleta->pivot->grau_parentesco_responsavel,
            ])->all();
    }
}
