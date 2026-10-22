<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\AtletaHistorico;
use App\Models\Responsavel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Nome, CPF e data de nascimento corrigidos pelo próprio atleta no app (PATCH /api/v1/atleta; 22/10/2026):
 * a data só muda dia e mês (o ano decide a idade no campeonato), cada mudança real fica em tbl_atleta_historico
 * para a secretaria e a resposta é a mesma do GET /atleta. RG, matrícula e status continuam com a secretaria.
 */
class AtletaDadosPeloAppTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $id;
    private string $ano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ano = (string) (now()->year - 12);
        $this->id = $this->criarAtleta("{$this->ano}-05-14", 'F', 'ATIVO');
        $this->colocarNaCategoria($this->id, $this->idCategoria('Sub-13', 'F'));
        Sanctum::actingAs(Atleta::find($this->id));
    }

    public function test_muda_so_o_nome_registra_e_devolve_o_mesmo_formato_do_get(): void
    {
        $resposta = $this->patchJson('/api/v1/atleta', ['nome_atleta' => '  Joana Exemplo da Silva  '])
            ->assertOk()
            ->assertJsonPath('message', 'Dados atualizados com sucesso.')
            ->assertJsonPath('data.nome_atleta', 'Joana Exemplo da Silva');

        // A mesma montagem do GET (categorias, posições, responsáveis e foto_url)
        $this->assertSame(
            array_keys($this->getJson('/api/v1/atleta')->json('data')),
            array_keys($resposta->json('data'))
        );

        $historico = AtletaHistorico::where('id_atleta', $this->id)->get();
        $this->assertCount(1, $historico);
        $this->assertSame('nome_atleta', $historico[0]->campo_atleta_historico);
        $this->assertSame('Atleta de Teste', $historico[0]->valor_antigo_atleta_historico);
        $this->assertSame('Joana Exemplo da Silva', $historico[0]->valor_novo_atleta_historico);
        $this->assertNull($historico[0]->id_usuario);
        $this->assertSame('Pelo app', $historico[0]->autor);
    }

    public function test_cpf_com_ou_sem_pontuacao_e_gravado_com_mascara(): void
    {
        $cpf = $this->cpfDeTeste(345678901);
        $digitos = preg_replace('/\D/', '', $cpf);

        $this->patchJson('/api/v1/atleta', ['cpf_atleta' => $digitos])->assertOk()->assertJsonPath('data.cpf_atleta', $cpf);
        $this->assertSame($cpf, Atleta::find($this->id)->cpf_atleta);

        // O mesmo CPF com pontos não é mudança: nada novo no histórico
        $this->patchJson('/api/v1/atleta', ['cpf_atleta' => $cpf])->assertOk();
        $this->assertSame(1, AtletaHistorico::where('id_atleta', $this->id)->where('campo_atleta_historico', 'cpf_atleta')->count());
    }

    public function test_cpf_invalido_ou_de_outro_atleta_da_422_e_nao_muda_nada(): void
    {
        $antes = Atleta::find($this->id)->cpf_atleta;
        $outro = Atleta::find($this->criarAtleta("{$this->ano}-01-01", 'F', 'ATIVO'));

        $this->patchJson('/api/v1/atleta', ['cpf_atleta' => '123.456.789-00'])
            ->assertStatus(422)->assertJsonPath('errors.cpf_atleta.0', 'Informe um CPF válido.');
        $this->patchJson('/api/v1/atleta', ['cpf_atleta' => '111.111.111-11'])->assertStatus(422);
        $this->patchJson('/api/v1/atleta', ['cpf_atleta' => preg_replace('/\D/', '', $outro->cpf_atleta)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este CPF já está cadastrado para outro atleta.')
            ->assertJsonPath('success', false);

        $this->assertSame($antes, Atleta::find($this->id)->cpf_atleta);
        $this->assertSame(0, AtletaHistorico::count());
    }

    public function test_data_muda_dia_e_mes_mas_nao_o_ano(): void
    {
        $this->patchJson('/api/v1/atleta', ['data_nasc_atleta' => "{$this->ano}-05-20"])->assertOk();
        $this->assertSame("{$this->ano}-05-20", Atleta::find($this->id)->data_nasc_atleta->format('Y-m-d'));
        $this->assertSame("Data de nascimento: 14/05/{$this->ano} → 20/05/{$this->ano}", AtletaHistorico::sole()->resumo);

        $outroAno = (string) ($this->ano + 1);
        $this->patchJson('/api/v1/atleta', ['data_nasc_atleta' => "{$outroAno}-05-20"])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Para mudar o ano de nascimento, procure a secretaria da escolinha.');
        $this->assertSame("{$this->ano}-05-20", Atleta::find($this->id)->data_nasc_atleta->format('Y-m-d'));
    }

    public function test_data_inexistente_em_outro_formato_ou_futura_da_422(): void
    {
        $this->patchJson('/api/v1/atleta', ['data_nasc_atleta' => "{$this->ano}-02-31"])
            ->assertStatus(422)
            ->assertJsonPath('errors.data_nasc_atleta', ['Informe a data de nascimento no formato AAAA-MM-DD, com um dia que existe.']);
        $this->patchJson('/api/v1/atleta', ['data_nasc_atleta' => "14/05/{$this->ano}"])->assertStatus(422);
        $this->patchJson('/api/v1/atleta', ['data_nasc_atleta' => now()->addDay()->format('Y-m-d')])
            ->assertStatus(422)->assertJsonValidationErrors('data_nasc_atleta');

        $this->assertSame("{$this->ano}-05-14", Atleta::find($this->id)->data_nasc_atleta->format('Y-m-d'));
    }

    public function test_nome_curto_ou_vazio_da_422(): void
    {
        $this->patchJson('/api/v1/atleta', ['nome_atleta' => 'Jo'])->assertStatus(422)
            ->assertJsonPath('errors.nome_atleta.0', 'O campo nome deve ter pelo menos 3 caracteres.');
        $this->patchJson('/api/v1/atleta', ['nome_atleta' => ''])->assertStatus(422)->assertJsonValidationErrors('nome_atleta');
        $this->patchJson('/api/v1/atleta', ['nome_atleta' => str_repeat('a', 101)])->assertStatus(422);
    }

    public function test_rg_matricula_e_status_continuam_ignorados(): void
    {
        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['numero_matricula_atleta' => 'A001']);

        $this->patchJson('/api/v1/atleta', [
            'rg_atleta' => '99.999.999-9', 'numero_matricula_atleta' => 'A999', 'status_atleta' => 'INATIVO', 'escola_atleta' => 'Escola Nova',
        ])->assertOk();

        $atleta = Atleta::find($this->id);
        $this->assertSame('00.000.000-0', $atleta->rg_atleta);
        $this->assertSame('A001', $atleta->numero_matricula_atleta);
        $this->assertSame('ATIVO', $atleta->status_atleta);
        $this->assertSame('Escola Nova', $atleta->escola_atleta);
        $this->assertSame(0, AtletaHistorico::count()); // escola não entra no histórico
    }

    public function test_responsavel_continua_sem_editar_o_filho(): void
    {
        $idResponsavel = $this->criarResponsavel($this->id);
        Sanctum::actingAs(Responsavel::find($idResponsavel));

        $this->patchJson("/api/v1/responsavel/atletas/{$this->id}", ['nome_atleta' => 'Outro Nome'])->assertStatus(405);
        $this->assertSame('Atleta de Teste', Atleta::find($this->id)->nome_atleta);
    }

    public function test_admin_ve_as_alteracoes_no_editar_e_a_exclusao_apaga_o_historico(): void
    {
        $this->patchJson('/api/v1/atleta', ['nome_atleta' => 'Joana Exemplo'])->assertOk();

        $this->comoAdmin()->get(route('admin.atletas.index'))->assertOk()
            ->assertSee('Alterações feitas pelo app')
            ->assertSee('Pelo app · Nome: Atleta de Teste → Joana Exemplo', false);

        DB::table('tbl_atletas')->where('id_atleta', $this->id)->update(['status_atleta' => 'REJEITADO']);
        $this->assertTrue(Atleta::find($this->id)->excluirComDependencias());
        $this->assertSame(0, AtletaHistorico::count());
    }
}
