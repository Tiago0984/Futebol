<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\AtletaHistorico;
use App\Rules\CpfValido;
use App\Services\AppDoAtleta;
use App\Services\TratadorDeImagem;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\RuntimeException as ImagemException;

class AtletaController extends Controller
{
    // GET /api/v1/atleta - dados do atleta autenticado (identificado pelo token)
    // Os mesmos dados que o responsável vê do filho (App\Services\AppDoAtleta::dados)
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => (new AppDoAtleta($request->user()))->dados(),
        ]);
    }

    // PUT/PATCH /api/v1/atleta - atualiza apenas os dados que o próprio atleta pode alterar.
    // Nome, CPF e data de nascimento (só dia e mês) o atleta corrige pelo app, com registro para a secretaria
    // (tbl_atleta_historico; 22/10/2026). RG, matrícula, status e o ano de nascimento continuam com a secretaria
    // (painel admin): o ano decide a idade no campeonato (Categoria::idadeNoAno), e mudar dia e mês não muda regra nenhuma
    public function update(Request $request)
    {
        $atleta = $request->user();

        // Com ou sem pontos, o CPF é conferido e gravado como 000.000.000-00 (o mesmo do admin e do site)
        if ($request->filled('cpf_atleta')) {
            $request->merge(['cpf_atleta' => Cpf::formatar($request->input('cpf_atleta'))]);
        }

        // "sometimes" permite enviar só alguns campos (atualização parcial). Período: MANHA, TARDE, NOITE ou
        // INTEGRAL; NOITE exige o turno do treino (Atleta::regrasPeriodo)
        $periodo = Atleta::regrasPeriodo(parcial: true);
        $limites = Atleta::limitesNascimento();
        $anoAtual = $atleta->data_nasc_atleta?->format('Y');
        $dados = $request->validate([
            'nome_atleta'            => 'sometimes|required|string|min:3|max:100',
            'cpf_atleta'             => ['bail', 'sometimes', 'required', 'string', 'max:14', new CpfValido('Informe um CPF válido.'),
                Rule::unique('tbl_atletas', 'cpf_atleta')->ignore($atleta->id_atleta, 'id_atleta')],
            'data_nasc_atleta'       => ['bail', 'sometimes', 'required', 'date_format:Y-m-d', 'before:today',
                "after_or_equal:{$limites['min']}", "before_or_equal:{$limites['max']}",
                function ($campo, $valor, $falhar) use ($anoAtual) {
                    if ($anoAtual && substr((string) $valor, 0, 4) !== $anoAtual) {
                        $falhar('Para mudar o ano de nascimento, procure a secretaria da escolinha.');
                    }
                }],
            'email_atleta'           => ['sometimes', 'required', 'email', 'max:255', Rule::unique('tbl_atletas', 'email_atleta')->ignore($atleta->id_atleta, 'id_atleta')],
            'telefone_atleta'        => 'sometimes|nullable|string|max:20',
            'escola_atleta'          => 'sometimes|required|string|max:100',
            'serie_atleta'           => 'sometimes|nullable|string|max:20',
            'sala_atleta'            => 'sometimes|nullable|string|max:255',
            ...$periodo['regras'],
            'peso_atleta'            => 'sometimes|nullable|numeric|min:0|max:999',
            'altura_atleta'          => 'sometimes|nullable|numeric|min:0|max:9.99',
            'descricao_atleta'       => 'sometimes|nullable|string|max:1000',
        ], [
            'cpf_atleta.unique'                => 'Este CPF já está cadastrado para outro atleta.',
            'data_nasc_atleta.date_format'     => 'Informe a data de nascimento no formato AAAA-MM-DD, com um dia que existe.',
            'data_nasc_atleta.before'          => 'A data de nascimento precisa ser anterior a hoje.',
            'data_nasc_atleta.after_or_equal'  => $limites['mensagem'],
            'data_nasc_atleta.before_or_equal' => $limites['mensagem'],
        ]);

        // A edição e o registro do que mudou de verdade (nome, CPF, data), juntos
        DB::transaction(function () use ($atleta, $dados) {
            $antes = collect(array_keys(AtletaHistorico::CAMPOS))
                ->mapWithKeys(fn ($campo) => [$campo => $this->valorParaHistorico($atleta, $campo)]);

            $atleta->update($dados);

            foreach ($antes as $campo => $valorAntigo) {
                $valorNovo = $this->valorParaHistorico($atleta, $campo);
                if ($valorNovo !== $valorAntigo) {
                    $atleta->historico()->create([
                        'campo_atleta_historico'        => $campo,
                        'valor_antigo_atleta_historico' => $valorAntigo,
                        'valor_novo_atleta_historico'   => $valorNovo,
                        'id_usuario'                    => null, // o próprio atleta, pelo app
                    ]);
                }
            }
        });

        // A mesma montagem do GET /atleta
        return response()->json([
            'success' => true,
            'message' => 'Dados atualizados com sucesso.',
            'data'    => (new AppDoAtleta($atleta->fresh()))->dados(),
        ]);
    }

    // Mensagens da foto, as mesmas que o app mostra embaixo da foto
    public const MENSAGEM_FOTO_TIPO    = 'A foto deve ser uma imagem JPG ou PNG.';
    public const MENSAGEM_FOTO_TAMANHO = 'A foto deve ter no máximo 10 MB.';

    // POST /api/v1/atleta/foto (multipart, campo "foto"; 29/10/2026): a foto é tratada (quadrado de até 512×512,
    // JPG) e fica pendente até a secretaria aprovar no painel (dono do projeto: o atleta é menor e a foto vai para o
    // site). foto_url continua com a atual; foto_pendente = true. Mandar outra antes da aprovação troca a pendente
    public function enviarFoto(Request $request)
    {
        $request->validate([
            'foto' => 'bail|required|file|max:' . TratadorDeImagem::ENVIO_MAX_KB . '|mimetypes:' . implode(',', TratadorDeImagem::TIPOS_FOTO),
        ], [
            'foto.required'  => 'Envie a foto no campo "foto".',
            'foto.file'      => self::MENSAGEM_FOTO_TIPO,
            'foto.mimetypes' => self::MENSAGEM_FOTO_TIPO,
            'foto.max'       => self::MENSAGEM_FOTO_TAMANHO,
            'foto.uploaded'  => self::MENSAGEM_FOTO_TAMANHO, // o PHP recusou (acima do upload_max_filesize)
        ]);

        // O tipo do conteúdo passou, mas a imagem pode não abrir (arquivo corrompido, HEIC sem o Imagick)
        try {
            $arquivo = TratadorDeImagem::foto($request->file('foto')->getRealPath(), 'local', Atleta::PASTA_FOTO_PENDENTE, 'foto-atleta');
        } catch (ImagemException) {
            throw ValidationException::withMessages(['foto' => self::MENSAGEM_FOTO_TIPO]);
        }

        // Relido do banco: o objeto do token pode já ter os campos calculados do dados() (foto_url…), que o save() gravaria
        $atleta = $request->user()->fresh();
        $atleta->guardarFotoPendente($arquivo);

        return response()->json([
            'success' => true,
            'message' => 'Foto enviada. Ela aparece no app depois que a secretaria aprovar.',
            'data'    => (new AppDoAtleta($atleta->fresh()))->dados(),
        ]);
    }

    // DELETE /api/v1/atleta/foto: volta para a foto padrão (foto_url null) na hora e descarta a pendente
    public function removerFoto(Request $request)
    {
        $atleta = $request->user()->fresh();
        $atleta->removerFoto();

        return response()->json([
            'success' => true,
            'message' => 'Foto removida.',
            'data'    => (new AppDoAtleta($atleta->fresh()))->dados(),
        ]);
    }

    // Valor do campo como texto, para comparar e gravar no histórico (a data sem a hora)
    private function valorParaHistorico(Atleta $atleta, string $campo): ?string
    {
        $valor = $atleta->{$campo};

        return $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d') : ($valor === null ? null : (string) $valor);
    }

    // PUT /api/v1/atleta/senha - confere a senha atual e grava a nova com Hash::make()
    public function updateSenha(Request $request)
    {
        $atleta = $request->user();

        $dados = $request->validate([
            'senha_atual' => 'required|string',
            'nova_senha'  => 'required|string|min:8|confirmed|different:senha_atual',
        ]);

        if (!Hash::check($dados['senha_atual'], $atleta->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta.',
            ], 422);
        }

        $atleta->update(['password' => Hash::make($dados['nova_senha'])]);

        // Encerra as sessões de outros aparelhos, mantendo o token atual
        $atleta->tokens()
            ->where('id', '!=', $atleta->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso.',
        ]);
    }
}
