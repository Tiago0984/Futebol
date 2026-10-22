<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Período escolar e turno do treino (Fase A, Etapa 1; professor, 13/10/2026): o atleta treina no turno oposto
 * ao da escola, integral treina à tarde e quem estuda à noite escolhe (manhã, tarde ou os dois). O período é
 * obrigatório no site, no admin e no app.
 */
class AtletaPeriodoEscolarTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_turnos_de_treino_e_texto_pelo_periodo(): void
    {
        $casos = [
            ['MANHA', null, ['TARDE'], 'Manhã'],
            ['TARDE', null, ['MANHA'], 'Tarde'],
            ['INTEGRAL', null, ['TARDE'], 'Integral'],
            ['NOITE', 'MANHA', ['MANHA'], 'Noite · treina de manhã'],
            ['NOITE', 'TARDE', ['TARDE'], 'Noite · treina à tarde'],
            ['NOITE', 'AMBOS', ['MANHA', 'TARDE'], 'Noite · treina de manhã e à tarde'],
            ['NOITE', null, [], 'Noite'],
            [null, null, [], null],
        ];

        foreach ($casos as [$periodo, $turno, $turnos, $texto]) {
            $atleta = new Atleta(['periodo_escolar_atleta' => $periodo, 'turno_treino_atleta' => $turno]);
            $this->assertSame($turnos, $atleta->turnosDeTreino(), "{$periodo}/{$turno}");
            $this->assertSame($texto, $atleta->periodo_escolar_texto, "{$periodo}/{$turno}");
        }
    }

    public function test_turno_do_treino_so_fica_gravado_para_quem_estuda_a_noite(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $atleta = Atleta::find($id);

        $atleta->update(['periodo_escolar_atleta' => 'NOITE', 'turno_treino_atleta' => 'AMBOS']);
        $this->assertSame('AMBOS', $atleta->fresh()->turno_treino_atleta);

        $atleta->update(['periodo_escolar_atleta' => 'TARDE']);
        $this->assertNull($atleta->fresh()->turno_treino_atleta);
    }

    public function test_admin_exige_o_periodo_e_o_turno_de_quem_estuda_a_noite(): void
    {
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['periodo_escolar_atleta' => '']))
            ->assertSessionHasErrors(['periodo_escolar_atleta' => 'Informe o período em que o atleta estuda: o treino é no turno oposto ao da escola.']);
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['periodo_escolar_atleta' => 'MANHÃ']))
            ->assertSessionHasErrors('periodo_escolar_atleta');
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['periodo_escolar_atleta' => 'NOITE']))
            ->assertSessionHasErrors('turno_treino_atleta');

        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro(['periodo_escolar_atleta' => 'NOITE', 'turno_treino_atleta' => 'AMBOS']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_atletas', ['cpf_atleta' => $this->cpfDeTeste(300000001), 'periodo_escolar_atleta' => 'NOITE', 'turno_treino_atleta' => 'AMBOS']);
    }

    public function test_edicao_trava_atleta_sem_periodo_ate_preencher(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));
        $this->criarResponsavel($id);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['periodo_escolar_atleta' => null]);

        // Lista: selo para a secretaria completar
        $this->comoAdmin()->get(route('admin.atletas.index'))->assertSee('Sem período escolar');

        $this->comoAdmin()->put(route('admin.atletas.update', $id), $this->dadosEdicao($id))
            ->assertSessionHasErrorsIn('edicao', 'periodo_escolar_atleta');

        $this->comoAdmin()->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, ['periodo_escolar_atleta' => 'INTEGRAL']))
            ->assertSessionHasNoErrors();
        $this->assertSame('INTEGRAL', DB::table('tbl_atletas')->where('id_atleta', $id)->value('periodo_escolar_atleta'));
        $this->comoAdmin()->get(route('admin.atletas.index'))->assertDontSee('Sem período escolar');
    }

    public function test_site_aceita_so_os_periodos_da_lista_e_noite_com_turno(): void
    {
        $this->post(route('cadastro.store'), ['periodo_escolar_atleta' => 'Manhã'])
            ->assertSessionHasErrors('periodo_escolar_atleta')
            ->assertSessionDoesntHaveErrors('turno_treino_atleta');

        $this->post(route('cadastro.store'), ['periodo_escolar_atleta' => 'NOITE'])
            ->assertSessionDoesntHaveErrors('periodo_escolar_atleta')
            ->assertSessionHasErrors('turno_treino_atleta');

        // O formulário mostra as quatro opções e o turno (escondido até escolher Noite)
        $this->get(route('cadastro.index'))
            ->assertSeeInOrder(['value="INTEGRAL"', 'Integral', 'name="turno_treino_atleta"', 'value="AMBOS"', 'Manhã e tarde'], false);
    }

    public function test_app_valida_o_periodo_e_o_turno(): void
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        Sanctum::actingAs(Atleta::find($id));

        $this->patchJson('/api/v1/atleta', ['periodo_escolar_atleta' => ''])->assertStatus(422)->assertJsonValidationErrors('periodo_escolar_atleta');
        $this->patchJson('/api/v1/atleta', ['periodo_escolar_atleta' => 'NOITE'])->assertStatus(422)->assertJsonValidationErrors('turno_treino_atleta');

        $this->patchJson('/api/v1/atleta', ['periodo_escolar_atleta' => 'NOITE', 'turno_treino_atleta' => 'MANHA'])
            ->assertOk()->assertJsonPath('data.turno_treino_atleta', 'MANHA');
        $this->patchJson('/api/v1/atleta', ['periodo_escolar_atleta' => 'TARDE'])
            ->assertOk()->assertJsonPath('data.periodo_escolar_atleta', 'TARDE')->assertJsonPath('data.turno_treino_atleta', null);

        // Mandar só outro campo não mexe no período
        $this->patchJson('/api/v1/atleta', ['escola_atleta' => 'Escola Nova'])->assertOk()->assertJsonPath('data.periodo_escolar_atleta', 'TARDE');
    }
}
