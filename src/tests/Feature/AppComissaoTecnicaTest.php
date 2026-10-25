<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Perfil do responsável pela atividade no app (Etapa C3, 25/10/2026): GET /comissao-tecnica/{id} e a mesma rota
 * do responsável. Sem lista; foto só autorizada; contato só institucional; 404 para desativado ou inexistente.
 */
class AppComissaoTecnicaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-25 12:00:00');
    }

    public function test_perfil_com_todos_os_campos(): void
    {
        $sub13 = $this->idCategoria('Sub-13', 'M');
        $sub15 = $this->idCategoria('Sub-15', 'M');
        $id = $this->profissional([
            'foto_comissao_tecnica' => 'carlos.png', 'foto_autorizada_comissao_tecnica' => 1, 'id_categoria' => $sub13,
            'data_inicio_comissao_tecnica' => '2024-03-01', 'licenca_comissao_tecnica' => 'Licença C CBF',
            'especialidade_comissao_tecnica' => 'Categorias de base', 'telefone_institucional_comissao_tecnica' => '(11) 3333-4444',
            'email_institucional_comissao_tecnica' => 'carlos@aacj.com.br',
        ], [$sub15, $sub13]);
        $this->atleta('ana@exemplo.com');

        $data = $this->comToken($this->login('ana@exemplo.com'))->getJson("/api/v1/comissao-tecnica/{$id}")
            ->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertSame(['id_comissao_tecnica' => $id, 'nome' => 'Carlos Mendes', 'cargo' => 'TREINADOR_TECNICO', 'cargo_label' => 'Treinador técnico'],
            array_intersect_key($data, array_flip(['id_comissao_tecnica', 'nome', 'cargo', 'cargo_label'])));
        $this->assertStringEndsWith('/futebol/images/comissao/carlos.png', $data['foto_url']);
        $this->assertSame(['id_categoria' => $sub13, 'nome_categoria' => 'Sub-13', 'categoria' => 'Sub-13 Masculino'], $data['categoria_principal']);
        $this->assertSame(['Sub-13 Masculino', 'Sub-15 Masculino'], array_column($data['categorias_atendidas'], 'categoria'));
        $this->assertSame('2024-03-01', $data['data_inicio']);
        $this->assertSame(['anos' => 2, 'meses' => 7, 'texto' => '2 anos e 7 meses'], $data['tempo_na_aacj']);
        $this->assertSame(['Licença C CBF', 'Categorias de base'], [$data['licenca'], $data['especialidade']]);
        $this->assertSame(['telefone' => '(11) 3333-4444', 'email' => 'carlos@aacj.com.br'], $data['contato']);
    }

    public function test_sem_autorizacao_sem_contato_e_sem_data_vem_null(): void
    {
        $id = $this->profissional(['foto_comissao_tecnica' => 'carlos.png', 'foto_autorizada_comissao_tecnica' => 0]);
        $this->atleta('ana@exemplo.com');

        $data = $this->comToken($this->login('ana@exemplo.com'))->getJson("/api/v1/comissao-tecnica/{$id}")->assertOk()->json('data');

        foreach (['foto_url', 'categoria_principal', 'data_inicio', 'tempo_na_aacj', 'licenca', 'especialidade', 'contato'] as $campo) {
            $this->assertNull($data[$campo], $campo);
        }
        $this->assertSame([], $data['categorias_atendidas']);
    }

    public function test_tempo_na_aacj_no_singular_e_menos_de_um_mes(): void
    {
        $this->atleta('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');
        $casos = ['2025-09-25' => [1, 1, '1 ano e 1 mês'], '2026-10-10' => [0, 0, 'Menos de 1 mês'], '2026-07-25' => [0, 3, '3 meses']];

        foreach ($casos as $inicio => [$anos, $meses, $texto]) {
            $id = $this->profissional(['data_inicio_comissao_tecnica' => $inicio]);
            $this->comToken($token)->getJson("/api/v1/comissao-tecnica/{$id}")
                ->assertJsonPath('data.tempo_na_aacj', ['anos' => $anos, 'meses' => $meses, 'texto' => $texto]);
        }

        $futuro = $this->profissional(['data_inicio_comissao_tecnica' => '2026-11-01']);
        $this->comToken($token)->getJson("/api/v1/comissao-tecnica/{$futuro}")->assertJsonPath('data.tempo_na_aacj', null);
    }

    public function test_desativado_e_inexistente_dao_404_e_nao_ha_lista(): void
    {
        $inativo = $this->profissional(['status_comissao_tecnica' => 'INATIVO']);
        $this->atleta('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');

        foreach ([$inativo, 999999] as $id) {
            $this->comToken($token)->getJson("/api/v1/comissao-tecnica/{$id}")
                ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Profissional não encontrado.']);
        }
        $this->comToken($token)->getJson('/api/v1/comissao-tecnica')->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson("/api/v1/comissao-tecnica/{$inativo}")->assertUnauthorized();
    }

    public function test_responsavel_ve_o_mesmo_perfil_so_pelo_proprio_filho(): void
    {
        $id = $this->profissional(['telefone_institucional_comissao_tecnica' => '(11) 3333-4444']);
        $filho = $this->atleta('ana@exemplo.com');
        $deOutraFamilia = $this->atleta('bia@exemplo.com');
        $responsavel = $this->responsavel('mae@exemplo.com');
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $filho, 'id_responsavel' => $responsavel, 'grau_parentesco_responsavel' => 'Mãe']);

        $doAtleta = $this->comToken($this->login('ana@exemplo.com'))->getJson("/api/v1/comissao-tecnica/{$id}")->json();
        $tokenResponsavel = $this->login('mae@exemplo.com', 'responsavel');

        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$filho}/comissao-tecnica/{$id}")
            ->assertOk()->assertExactJson($doAtleta);
        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$deOutraFamilia}/comissao-tecnica/{$id}")
            ->assertNotFound()->assertJsonPath('message', 'Atleta não encontrado.');
        $this->comToken($tokenResponsavel)->getJson("/api/v1/comissao-tecnica/{$id}")->assertForbidden();
    }

    private function profissional(array $campos = [], array $categorias = []): int
    {
        $id = DB::table('tbl_comissao_tecnica')->insertGetId($campos + [
            'nome_comissao_tecnica' => 'Carlos Mendes', 'cargo_comissao_tecnica' => 'TREINADOR_TECNICO', 'status_comissao_tecnica' => 'ATIVO',
        ]);

        foreach ($categorias as $idCategoria) {
            DB::table('tbl_comissao_tecnica_categoria')->insert(['id_comissao_tecnica' => $id, 'id_categoria' => $idCategoria]);
        }

        return $id;
    }

    private function atleta(string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);

        return $id;
    }

    private function responsavel(string $email): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua', 'numero_endereco' => '1', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        return DB::table('tbl_responsavel')->insertGetId([
            'id_endereco' => $idEndereco, 'nome_responsavel' => 'Responsável',
            'cpf_responsavel' => $this->cpfDeTeste(400000001), 'rg_responsavel' => '2', 'whatsapp_responsavel' => '(11) 9',
            'email_responsavel' => $email, 'senha_responsavel' => Hash::make(self::SENHA),
        ]);
    }

    private function login(string $email, string $perfil = 'atleta'): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => self::SENHA, 'perfil' => $perfil])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
