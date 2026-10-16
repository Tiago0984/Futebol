<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\AtletaAfastamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Afastamento do atleta, Etapa 1 (16/10/2026): a tabela, a tela do atleta (registrar, mudar as datas, encerrar,
 * excluir) e o selo na lista de Atletas. O atleta continua ATIVO; fim obrigatório (dá para prorrogar); motivo só no
 * admin; sem sobreposição. A agenda (treinos do período) é a Etapa 2.
 */
class AtletaAfastamentoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-04 10:00:00'); // quarta-feira
        $this->admin = User::factory()->admin()->create();
        $this->idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
    }

    public function test_tabela_com_as_colunas_e_as_fks(): void
    {
        $this->assertTrue(Schema::hasColumns('tbl_atleta_afastamento', [
            'id_atleta_afastamento', 'id_atleta', 'data_inicio_atleta_afastamento', 'data_fim_atleta_afastamento',
            'motivo_atleta_afastamento', 'id_usuario', 'data_atleta_afastamento',
        ]));
        $fks = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_atleta_afastamento'"))->pluck('CONSTRAINT_NAME')->sort()->values()->all();
        $this->assertSame(['fk_atleta_afastamento_atleta', 'fk_atleta_afastamento_usuario'], $fks);
    }

    public function test_registrar_com_quem_registrou_e_o_atleta_continua_ativo(): void
    {
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-11-03', 'data_fim_atleta_afastamento' => '2026-11-10',
            'motivo_atleta_afastamento' => 'Gripe'])
            ->assertSessionHas('sucesso', fn ($m) => str_starts_with($m, 'Afastamento de 03/11 a 10/11 registrado.'));

        $this->assertDatabaseHas('tbl_atleta_afastamento', [
            'id_atleta' => $this->idAtleta, 'data_inicio_atleta_afastamento' => '2026-11-03',
            'data_fim_atleta_afastamento' => '2026-11-10', 'motivo_atleta_afastamento' => 'Gripe', 'id_usuario' => $this->admin->id_usuario,
        ]);
        $this->assertSame('ATIVO', Atleta::find($this->idAtleta)->status_atleta);

        $this->actingAs($this->admin, 'admin')->get(route('admin.atletas.afastamentos', $this->idAtleta))->assertOk()
            ->assertSee('03/11 a 10/11')->assertSee('Gripe')->assertSee('Em andamento');
    }

    public function test_fim_obrigatorio_depois_do_inicio_e_hoje_ou_depois(): void
    {
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-11-03', 'data_fim_atleta_afastamento' => ''])
            ->assertSessionHasErrors('data_fim_atleta_afastamento');
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-11-10', 'data_fim_atleta_afastamento' => '2026-11-08'])
            ->assertSessionHasErrors();
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-10-20', 'data_fim_atleta_afastamento' => '2026-11-03'])
            ->assertSessionHasErrors(); // já acabou ontem

        $this->assertDatabaseCount('tbl_atleta_afastamento', 0);
    }

    public function test_sem_sobreposicao_e_so_atleta_ativo(): void
    {
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-11-03', 'data_fim_atleta_afastamento' => '2026-11-10']);
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-11-10', 'data_fim_atleta_afastamento' => '2026-11-20'])
            ->assertSessionHasErrors();
        $this->assertSame(['Já existe um afastamento de 03/11 a 10/11 que cruza essas datas.'],
            session('errors')->all());
        $this->registrar(['data_inicio_atleta_afastamento' => '2026-11-11', 'data_fim_atleta_afastamento' => '2026-11-20'])
            ->assertSessionHasNoErrors();

        $inativo = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'INATIVO');
        $this->actingAs($this->admin, 'admin')->post(route('admin.atletas.afastamentos.store', $inativo), [
            'data_inicio_atleta_afastamento' => '2026-11-04', 'data_fim_atleta_afastamento' => '2026-11-05',
        ])->assertSessionHas('erro');
        $this->assertSame(0, AtletaAfastamento::where('id_atleta', $inativo)->count());
    }

    public function test_prorrogar_mantem_o_inicio_de_quem_ja_comecou(): void
    {
        $afastamento = $this->criar('2026-11-03', '2026-11-10');

        $this->mudar($afastamento, ['data_inicio_atleta_afastamento' => '2026-11-03', 'data_fim_atleta_afastamento' => '2026-11-17'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-17', $afastamento->fresh()->data_fim_atleta_afastamento->toDateString());

        $this->mudar($afastamento, ['data_inicio_atleta_afastamento' => '2026-11-05', 'data_fim_atleta_afastamento' => '2026-11-17'])
            ->assertSessionHasErrors();
        $this->assertSame('2026-11-03', $afastamento->fresh()->data_inicio_atleta_afastamento->toDateString());

        // O que ainda não começou pode mudar o início
        $futuro = $this->criar('2026-11-20', '2026-11-25');
        $this->mudar($futuro, ['data_inicio_atleta_afastamento' => '2026-11-22', 'data_fim_atleta_afastamento' => '2026-11-25'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-22', $futuro->fresh()->data_inicio_atleta_afastamento->toDateString());
    }

    public function test_encerrar_volta_hoje_e_excluir(): void
    {
        $afastamento = $this->criar('2026-11-03', '2026-11-10');
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.atletas.afastamentos.encerrar', [$this->idAtleta, $afastamento->id_atleta_afastamento]))
            ->assertSessionHas('sucesso');
        $this->assertSame('2026-11-03', $afastamento->fresh()->data_fim_atleta_afastamento->toDateString());
        $this->assertTrue($afastamento->fresh()->jaTerminou());

        // Começa hoje: não encerra (exclui)
        $hoje = $this->criar('2026-11-04', '2026-11-06');
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.atletas.afastamentos.encerrar', [$this->idAtleta, $hoje->id_atleta_afastamento]))
            ->assertSessionHas('erro');
        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.atletas.afastamentos.destroy', [$this->idAtleta, $hoje->id_atleta_afastamento]))
            ->assertSessionHas('sucesso');
        $this->assertModelMissing($hoje);

        // De outro atleta: 404
        $outro = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.atletas.afastamentos.destroy', [$outro, $afastamento->id_atleta_afastamento]))
            ->assertNotFound();
    }

    public function test_selo_na_lista_de_atletas(): void
    {
        $this->criar('2026-11-03', '2026-11-10');
        $proximo = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        AtletaAfastamento::create(['id_atleta' => $proximo, 'data_inicio_atleta_afastamento' => '2026-11-20',
            'data_fim_atleta_afastamento' => '2026-11-25']);
        $passado = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        AtletaAfastamento::create(['id_atleta' => $passado, 'data_inicio_atleta_afastamento' => '2026-10-01',
            'data_fim_atleta_afastamento' => '2026-10-05']);

        $this->actingAs($this->admin, 'admin')->get(route('admin.atletas.index'))->assertOk()
            ->assertSee('Afastado até 10/11')
            ->assertSee('Afastado de 20/11 a 25/11')
            ->assertDontSee('01/10 a 05/10')
            ->assertSee(route('admin.atletas.afastamentos', $this->idAtleta));
    }

    public function test_exclusao_definitiva_do_atleta_apaga_os_afastamentos(): void
    {
        $this->criar('2026-11-03', '2026-11-10');

        $this->assertTrue(Atleta::find($this->idAtleta)->excluirComDependencias());
        $this->assertDatabaseCount('tbl_atleta_afastamento', 0);
    }

    private function registrar(array $dados)
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.atletas.afastamentos.store', $this->idAtleta), $dados);
    }

    private function mudar(AtletaAfastamento $afastamento, array $dados)
    {
        return $this->actingAs($this->admin, 'admin')
            ->put(route('admin.atletas.afastamentos.update', [$afastamento->id_atleta, $afastamento->id_atleta_afastamento]), $dados);
    }

    private function criar(string $inicio, string $fim): AtletaAfastamento
    {
        return AtletaAfastamento::create(['id_atleta' => $this->idAtleta,
            'data_inicio_atleta_afastamento' => $inicio, 'data_fim_atleta_afastamento' => $fim]);
    }
}
