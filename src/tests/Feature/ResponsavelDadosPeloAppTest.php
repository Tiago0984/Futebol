<?php

namespace Tests\Feature;

use App\Models\AtletaHistorico;
use App\Models\Responsavel;
use App\Models\ResponsavelHistorico;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * O responsável muda pelo app nome, CPF e endereço, além dos contatos (PATCH /api/v1/responsavel; 29/10/2026). Cada
 * mudança real fica em tbl_responsavel_historico. O endereço que o painel grava junto com o do filho: com
 * endereco_dos_filhos (padrão true) muda o dos dois; com false, o responsável ganha um endereço próprio.
 */
class ResponsavelDadosPeloAppTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idAtleta;
    private int $idResponsavel;

    private const ENDERECO = [
        'cep_endereco' => '13560000', 'rua_endereco' => 'Rua das Flores', 'numero_endereco' => 120,
        'bairro_endereco' => 'Centro', 'complemento_endereco' => '', 'cidade_endereco' => 'São Carlos', 'estado_endereco' => 'sp',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->idAtleta = $this->criarAtleta((now()->year - 12) . '-05-14', 'F', 'ATIVO');
        $this->colocarNaCategoria($this->idAtleta, $this->idCategoria('Sub-13', 'F'));
        DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->update(['nome_atleta' => 'Joana Exemplo']);
        $this->idResponsavel = $this->criarResponsavel($this->idAtleta);
        DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->update(['nome_responsavel' => 'Carla Exemplo']);
        $this->entrar();
    }

    private function entrar(): void
    {
        Sanctum::actingAs(Responsavel::find($this->idResponsavel));
    }

    // Como o cadastro pelo painel: o responsável no mesmo registro de endereço do filho
    private function compartilharEnderecoComOFilho(): int
    {
        $idEndereco = DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco');
        DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->update(['id_endereco' => $idEndereco]);
        $this->entrar();

        return $idEndereco;
    }

    private function historico(): array
    {
        return ResponsavelHistorico::where('id_responsavel', $this->idResponsavel)->orderBy('id_responsavel_historico')->get()
            ->map(fn ($h) => $h->resumo)->all();
    }

    public function test_nome_e_cpf_com_ou_sem_mascara_mudam_e_ficam_no_historico(): void
    {
        $cpfAntes = DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('cpf_responsavel');
        $cpf = $this->cpfDeTeste(456789012);

        $this->patchJson('/api/v1/responsavel', [
            'nome_responsavel' => '  Carla Exemplo da Silva ', 'cpf_responsavel' => preg_replace('/\D/', '', $cpf), 'rg_responsavel' => '99',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nome_responsavel', 'Carla Exemplo da Silva')
            ->assertJsonPath('data.cpf_responsavel', $cpf)
            ->assertJsonPath('data.rg_responsavel', '11.111.111-1'); // o RG fica com a secretaria

        $this->assertSame(["Nome: Carla Exemplo → Carla Exemplo da Silva", "CPF: {$cpfAntes} → {$cpf}"], $this->historico());
        $this->assertSame('Pelo app', ResponsavelHistorico::first()->autor);

        // O mesmo CPF com máscara não é mudança: nada novo no histórico
        $this->patchJson('/api/v1/responsavel', ['cpf_responsavel' => $cpf, 'nome_responsavel' => 'Carla Exemplo da Silva'])->assertOk();
        $this->assertCount(2, $this->historico());
    }

    public function test_nome_curto_cpf_invalido_ou_repetido_dao_422_e_nao_mudam_nada(): void
    {
        $outro = $this->criarResponsavel($this->criarAtleta((now()->year - 12) . '-01-01', 'M', 'ATIVO'));
        $cpfDoOutro = DB::table('tbl_responsavel')->where('id_responsavel', $outro)->value('cpf_responsavel');

        $this->patchJson('/api/v1/responsavel', ['nome_responsavel' => 'Ca'])
            ->assertStatus(422)->assertJsonValidationErrors('nome_responsavel');
        $this->patchJson('/api/v1/responsavel', ['cpf_responsavel' => '123.456.789-00'])
            ->assertStatus(422)->assertJsonPath('errors.cpf_responsavel.0', 'Informe um CPF válido.');
        $this->patchJson('/api/v1/responsavel', ['cpf_responsavel' => '111.111.111-11'])
            ->assertStatus(422)->assertJsonPath('errors.cpf_responsavel.0', 'Informe um CPF válido.');
        $this->patchJson('/api/v1/responsavel', ['cpf_responsavel' => preg_replace('/\D/', '', $cpfDoOutro)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este CPF já está cadastrado para outro responsável.')
            ->assertJsonPath('errors.cpf_responsavel.0', 'Este CPF já está cadastrado para outro responsável.');

        $this->assertDatabaseHas('tbl_responsavel', ['id_responsavel' => $this->idResponsavel, 'nome_responsavel' => 'Carla Exemplo']);
        $this->assertSame([], $this->historico());
    }

    public function test_endereco_so_dele_muda_normalizado_e_registra_uma_linha(): void
    {
        $idEndereco = DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('id_endereco');
        $this->getJson('/api/v1/responsavel')->assertOk()->assertJsonPath('data.endereco.compartilhado_com', []);

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO])
            ->assertOk()
            ->assertJsonPath('data.endereco.cep_endereco', '13560-000')
            ->assertJsonPath('data.endereco.numero_endereco', '120')
            ->assertJsonPath('data.endereco.estado_endereco', 'SP')
            ->assertJsonPath('data.endereco.complemento_endereco', null);

        $this->assertSame($idEndereco, DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('id_endereco'));
        $this->assertSame(['Endereço do responsável: Rua do Responsável, 200 – Centro, São Paulo/SP, 01000-000 → Rua das Flores, 120 – Centro, São Carlos/SP, 13560-000'], $this->historico());

        // Enviar de novo igual (CEP com hífen, UF em maiúsculas) não é mudança
        $this->patchJson('/api/v1/responsavel', ['endereco' => [...self::ENDERECO, 'cep_endereco' => '13560-000', 'estado_endereco' => 'SP']])->assertOk();
        $this->assertCount(1, $this->historico());
    }

    public function test_endereco_compartilhado_muda_o_dos_filhos_por_padrao_e_registra_no_historico_deles(): void
    {
        $idEndereco = $this->compartilharEnderecoComOFilho();

        // Um irmão no mesmo registro também aparece
        $irmao = $this->criarAtleta((now()->year - 10) . '-01-01', 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $irmao)->update(['nome_atleta' => 'Igor Exemplo', 'id_endereco' => $idEndereco]);
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $irmao, 'id_responsavel' => $this->idResponsavel, 'grau_parentesco_responsavel' => 'Mãe']);

        $this->getJson('/api/v1/responsavel')->assertJsonPath('data.endereco.compartilhado_com', [
            ['id_atleta' => $irmao, 'nome_atleta' => 'Igor Exemplo'],
            ['id_atleta' => $this->idAtleta, 'nome_atleta' => 'Joana Exemplo'],
        ]);

        // Sem endereco_dos_filhos: vale true
        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO])->assertOk()
            ->assertJsonPath('data.endereco.rua_endereco', 'Rua das Flores')
            ->assertJsonPath('data.endereco.compartilhado_com.1.id_atleta', $this->idAtleta);

        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idEndereco, 'rua_endereco' => 'Rua das Flores', 'cep_endereco' => '13560-000']);
        $this->assertSame($idEndereco, DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco'));
        $this->assertSame(1, AtletaHistorico::where('id_atleta', $this->idAtleta)->count());
        $this->assertSame(1, AtletaHistorico::where('id_atleta', $irmao)->count());
        $this->assertStringStartsWith('Endereço (pelo responsável): Rua de Teste, 100', AtletaHistorico::where('id_atleta', $this->idAtleta)->sole()->resumo);
    }

    public function test_endereco_compartilhado_com_false_da_um_endereco_proprio_e_o_do_filho_fica(): void
    {
        $idEndereco = $this->compartilharEnderecoComOFilho();

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO, 'endereco_dos_filhos' => false])->assertOk()
            ->assertJsonPath('data.endereco.rua_endereco', 'Rua das Flores')
            ->assertJsonPath('data.endereco.compartilhado_com', []);

        $novo = DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('id_endereco');
        $this->assertNotSame($idEndereco, $novo);
        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idEndereco, 'rua_endereco' => 'Rua de Teste']);
        $this->assertSame($idEndereco, DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco'));
        $this->assertSame(0, AtletaHistorico::where('id_atleta', $this->idAtleta)->count());
        $this->assertCount(1, $this->historico());
    }

    public function test_registro_usado_por_quem_nao_esta_na_mudanca_nao_muda_e_os_da_familia_vao_juntos_para_um_novo(): void
    {
        $idEndereco = $this->compartilharEnderecoComOFilho();
        $estranho = $this->criarAtleta((now()->year - 12) . '-01-01', 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $estranho)->update(['id_endereco' => $idEndereco]);

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO, 'endereco_dos_filhos' => true])->assertOk()
            ->assertJsonPath('data.endereco.compartilhado_com.0.id_atleta', $this->idAtleta);

        // O de fora fica no registro antigo; o responsável e a filha vão juntos para um novo
        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idEndereco, 'rua_endereco' => 'Rua de Teste']);
        $this->assertSame($idEndereco, DB::table('tbl_atletas')->where('id_atleta', $estranho)->value('id_endereco'));
        $novo = DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('id_endereco');
        $this->assertNotSame($idEndereco, $novo);
        $this->assertSame($novo, DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco'));
        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $novo, 'rua_endereco' => 'Rua das Flores']);
        $this->assertSame(1, AtletaHistorico::where('id_atleta', $this->idAtleta)->count());
    }

    // Cadastro pelo site (30/10/2026, o caso da Carla e da Joana em produção): registros diferentes com o mesmo endereço
    private function mesmoEnderecoEmOutroRegistro(): int
    {
        $idDoFilho = DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco');
        $enderecoDoFilho = (array) DB::table('tbl_endereco')->where('id_endereco', $idDoFilho)->first(\App\Models\Endereco::CAMPOS);
        DB::table('tbl_endereco')
            ->where('id_endereco', DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('id_endereco'))
            ->update($enderecoDoFilho);
        $this->entrar();

        return $idDoFilho;
    }

    public function test_filho_com_o_mesmo_endereco_em_outro_registro_aparece_e_com_true_o_registro_dele_muda(): void
    {
        $idDoFilho = $this->mesmoEnderecoEmOutroRegistro();
        $idDoResponsavel = DB::table('tbl_responsavel')->where('id_responsavel', $this->idResponsavel)->value('id_endereco');
        $this->assertNotSame($idDoFilho, $idDoResponsavel);

        $this->getJson('/api/v1/responsavel')
            ->assertJsonPath('data.endereco.compartilhado_com', [['id_atleta' => $this->idAtleta, 'nome_atleta' => 'Joana Exemplo']]);

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO])->assertOk()
            ->assertJsonPath('data.endereco.compartilhado_com.0.id_atleta', $this->idAtleta);

        // Cada um continua no seu registro, os dois com o endereço novo
        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idDoResponsavel, 'rua_endereco' => 'Rua das Flores', 'cep_endereco' => '13560-000']);
        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idDoFilho, 'rua_endereco' => 'Rua das Flores', 'cep_endereco' => '13560-000']);
        $this->assertSame($idDoFilho, DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco'));
        $this->assertStringStartsWith('Endereço (pelo responsável): Rua de Teste, 100', AtletaHistorico::where('id_atleta', $this->idAtleta)->sole()->resumo);
    }

    public function test_filho_com_o_mesmo_endereco_em_outro_registro_com_false_fica_e_sai_da_lista(): void
    {
        $idDoFilho = $this->mesmoEnderecoEmOutroRegistro();

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO, 'endereco_dos_filhos' => false])->assertOk()
            ->assertJsonPath('data.endereco.compartilhado_com', []);

        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idDoFilho, 'rua_endereco' => 'Rua de Teste']);
        $this->assertSame(0, AtletaHistorico::where('id_atleta', $this->idAtleta)->count());
    }

    public function test_registro_do_filho_dividido_com_outro_responsavel_o_filho_ganha_um_novo_e_o_outro_fica(): void
    {
        $idDoFilho = $this->mesmoEnderecoEmOutroRegistro();
        // O pai, cadastrado pelo painel no mesmo registro do filho (criado por outro atleta só para ter outro e-mail)
        $pai = $this->criarResponsavel($this->criarAtleta((now()->year - 12) . '-01-01', 'M', 'INATIVO'));
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $this->idAtleta, 'id_responsavel' => $pai, 'grau_parentesco_responsavel' => 'Pai']);
        DB::table('tbl_responsavel')->where('id_responsavel', $pai)->update(['id_endereco' => $idDoFilho]);

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO])->assertOk();

        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $idDoFilho, 'rua_endereco' => 'Rua de Teste']);
        $this->assertSame($idDoFilho, DB::table('tbl_responsavel')->where('id_responsavel', $pai)->value('id_endereco'));
        $doFilhoAgora = DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->value('id_endereco');
        $this->assertNotSame($idDoFilho, $doFilhoAgora);
        $this->assertDatabaseHas('tbl_endereco', ['id_endereco' => $doFilhoAgora, 'rua_endereco' => 'Rua das Flores']);
    }

    public function test_cep_uf_e_campos_do_endereco_dao_422_com_a_chave_de_cada_campo(): void
    {
        $this->patchJson('/api/v1/responsavel', ['endereco' => [...self::ENDERECO, 'cep_endereco' => '1356-000']])
            ->assertStatus(422)->assertJsonPath('errors', ['endereco.cep_endereco' => ['Informe um CEP com 8 dígitos (ex.: 13560-000).']]);
        $this->patchJson('/api/v1/responsavel', ['endereco' => [...self::ENDERECO, 'estado_endereco' => 'São Paulo']])
            ->assertStatus(422)->assertJsonPath('errors', ['endereco.estado_endereco' => ['Informe a UF com 2 letras (ex.: SP).']]);
        $this->patchJson('/api/v1/responsavel', ['endereco' => [...self::ENDERECO, 'rua_endereco' => '', 'numero_endereco' => '1234567']])
            ->assertStatus(422)->assertJsonValidationErrors(['endereco.rua_endereco', 'endereco.numero_endereco']);
        $this->patchJson('/api/v1/responsavel', ['endereco' => 'Rua A'])
            ->assertStatus(422)->assertJsonValidationErrors('endereco');
        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO, 'endereco_dos_filhos' => 'talvez'])
            ->assertStatus(422)->assertJsonValidationErrors('endereco_dos_filhos');

        $this->assertDatabaseMissing('tbl_endereco', ['rua_endereco' => 'Rua das Flores']);
        $this->assertSame([], $this->historico());
    }

    public function test_envio_sem_mudanca_responde_igual_ao_get_e_nao_registra_nada(): void
    {
        $get = $this->getJson('/api/v1/responsavel')->json('data');

        $patch = $this->patchJson('/api/v1/responsavel', [
            'nome_responsavel' => $get['nome_responsavel'], 'cpf_responsavel' => $get['cpf_responsavel'],
            'whatsapp_responsavel' => $get['whatsapp_responsavel'],
            'endereco' => array_diff_key($get['endereco'], ['compartilhado_com' => true]),
        ])->assertOk()->json('data');

        $this->assertSame($get, $patch);
        $this->assertSame([], $this->historico());
    }

    public function test_painel_mostra_as_alteracoes_do_responsavel_no_editar_do_atleta(): void
    {
        $this->patchJson('/api/v1/responsavel', ['nome_responsavel' => 'Carla Exemplo da Silva'])->assertOk();

        $this->comoAdmin()->get(route('admin.atletas.index'))
            ->assertOk()
            ->assertSee('Pelo app · Responsável: Nome: Carla Exemplo → Carla Exemplo da Silva');
    }

    // Correção 1 (30/10/2026): o Editar do atleta mostra o endereço do responsável quando é outro, e o histórico diz de quem é
    public function test_painel_avisa_o_endereco_do_responsavel_quando_e_diferente_do_do_atleta(): void
    {
        $this->mesmoEnderecoEmOutroRegistro();
        $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()->assertDontSee('diferente do do atleta');

        $this->patchJson('/api/v1/responsavel', ['endereco' => self::ENDERECO, 'endereco_dos_filhos' => false])->assertOk();

        $this->comoAdmin()->get(route('admin.atletas.index'))
            ->assertOk()
            ->assertSee('Endereço do responsável (Carla Exemplo), diferente do do atleta: Rua das Flores, 120 – Centro, São Carlos/SP, 13560-000')
            ->assertSee('Pelo app · Responsável: Endereço do responsável: Rua de Teste, 100');
    }
}
