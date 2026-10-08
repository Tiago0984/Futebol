<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Grade de treino com id_categoria (coluna única; itens gerais sem categoria).
 */
class GradeTreinoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- migration ----------

    public function test_migration_associa_sub_n_a_categoria_masculina_e_deixa_gerais_sem_categoria(): void
    {
        $ids = [];
        foreach (['Sub-9', 'Sub-13', 'Sub-17', 'Integrado', 'Jogos', 'Treino Livre'] as $texto) {
            $ids[$texto] = $this->criarGrade(['categoria_grade_treino' => $texto]);
        }

        $migration = $this->migrationDaGrade();
        $migration->preencherIdCategoria($migration->mapaSubParaCategoria());

        $gravados = DB::table('tbl_grade_treino')->pluck('id_categoria', 'id_grade_treino');
        $this->assertSame($this->idCategoria('Sub-9', 'M'), $gravados[$ids['Sub-9']]);
        $this->assertSame($this->idCategoria('Sub-13', 'M'), $gravados[$ids['Sub-13']]);
        $this->assertSame($this->idCategoria('Sub-17', 'M'), $gravados[$ids['Sub-17']]);
        $this->assertNull($gravados[$ids['Integrado']]);
        $this->assertNull($gravados[$ids['Jogos']]);
        $this->assertNull($gravados[$ids['Treino Livre']]);
    }

    public function test_migration_trava_se_sobrar_sub_sem_categoria(): void
    {
        $this->criarGrade(['categoria_grade_treino' => 'Sub-13']);
        $this->criarGrade(['categoria_grade_treino' => 'Sub-19']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sub-19');

        $this->migrationDaGrade()->mapaSubParaCategoria();
    }

    // ---------- admin ----------

    public function test_admin_cria_horario_com_categoria(): void
    {
        $idSub13F = $this->idCategoria('Sub-13', 'F');

        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['id_categoria' => $idSub13F]))
            ->assertSessionHasNoErrors();

        // O rótulo vem do nome da categoria, mesmo que o formulário mande outro texto
        $this->assertDatabaseHas('tbl_grade_treino', [
            'id_categoria' => $idSub13F, 'categoria_grade_treino' => 'Sub-13', 'local_grade_treino' => 'Campo B',
        ]);
    }

    public function test_admin_cria_item_geral_com_nome(): void
    {
        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['id_categoria' => '', 'categoria_grade_treino' => 'Integrado']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_grade_treino', ['id_categoria' => null, 'categoria_grade_treino' => 'Integrado']);
    }

    public function test_admin_recusa_item_geral_sem_nome(): void
    {
        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['id_categoria' => '', 'categoria_grade_treino' => '']))
            ->assertSessionHasErrors('categoria_grade_treino');

        $this->assertDatabaseCount('tbl_grade_treino', 0);
    }

    public function test_admin_recusa_categoria_inativa_tipo_invalido_e_local_vazio(): void
    {
        $idSub9F = $this->idCategoria('Sub-9', 'F');
        DB::table('tbl_categoria')->where('id_categoria', $idSub9F)->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade([
                'id_categoria'       => $idSub9F,
                'tipo_grade_treino'  => 'OUTRO',
                'local_grade_treino' => '',
            ]))
            ->assertSessionHasErrors(['id_categoria', 'tipo_grade_treino', 'local_grade_treino']);

        $this->assertDatabaseCount('tbl_grade_treino', 0);
    }

    // ---------- um dia por linha ----------

    public function test_novo_horario_com_varios_dias_cria_uma_linha_por_dia_na_ordem_da_semana(): void
    {
        $idSub11 = $this->idCategoria('Sub-11', 'M');

        // Marcados fora de ordem: as linhas saem na ordem da semana
        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['id_categoria' => $idSub11, 'dias_semana_grade_treino' => ['quarta', 'domingo', 'segunda']]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', '3 horários adicionados à grade (um por dia: Segunda, Quarta, Domingo).');

        $linhas = DB::table('tbl_grade_treino')->orderBy('id_grade_treino')->get();
        $this->assertSame(['segunda', 'quarta', 'domingo'], $linhas->pluck('dia_semana_grade_treino')->all());
        foreach ($linhas as $linha) {
            $this->assertSame([$idSub11, 'Sub-11', '10:00:00', '11:30:00', 'Campo B', 'ATIVO'], [$linha->id_categoria,
                $linha->categoria_grade_treino, $linha->horario_inicio_grade_treino, $linha->horario_fim_grade_treino,
                $linha->local_grade_treino, $linha->status_grade_treino]);
        }
    }

    public function test_novo_horario_com_um_dia_mantem_a_mensagem(): void
    {
        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['categoria_grade_treino' => 'Integrado']))
            ->assertSessionHas('sucesso', 'Horário adicionado à grade.');

        $this->assertDatabaseCount('tbl_grade_treino', 1);
    }

    public function test_novo_horario_exige_dia_valido_e_nao_grava_nada(): void
    {
        foreach ([[], ['segunda', 'segunda_quarta'], ['terca', 'terca']] as $dias) {
            $this->comoAdmin()
                ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['categoria_grade_treino' => 'Integrado', 'dias_semana_grade_treino' => $dias]))
                ->assertSessionHasErrors();
        }

        $this->comoAdmin()
            ->post(route('admin.calendario.grade.store'), $this->dadosGrade(['categoria_grade_treino' => 'Integrado', 'dias_semana_grade_treino' => []]))
            ->assertSessionHasErrors(['dias_semana_grade_treino' => 'Marque pelo menos um dia da semana.']);

        $this->assertDatabaseCount('tbl_grade_treino', 0);
    }

    public function test_edicao_muda_o_dia_de_uma_linha_so(): void
    {
        $idGrade = $this->criarGrade(['categoria_grade_treino' => 'Integrado']);
        $outra   = $this->criarGrade(['categoria_grade_treino' => 'Integrado']);

        $this->comoAdmin()
            ->put(route('admin.calendario.grade.update', $idGrade), $this->dadosGrade(['categoria_grade_treino' => 'Integrado', 'dia_semana_grade_treino' => 'domingo']))
            ->assertSessionHasNoErrors();
        $this->comoAdmin()
            ->put(route('admin.calendario.grade.update', $idGrade), $this->dadosGrade(['categoria_grade_treino' => 'Integrado', 'dia_semana_grade_treino' => 'segunda_quarta']))
            ->assertSessionHasErrors('dia_semana_grade_treino');

        $this->assertSame('domingo', DB::table('tbl_grade_treino')->where('id_grade_treino', $idGrade)->value('dia_semana_grade_treino'));
        $this->assertSame('terca', DB::table('tbl_grade_treino')->where('id_grade_treino', $outra)->value('dia_semana_grade_treino'));
        $this->assertDatabaseCount('tbl_grade_treino', 2);
    }

    public function test_modal_novo_horario_tem_uma_caixa_por_dia(): void
    {
        $resposta = $this->comoAdmin()->get(route('admin.calendario.index', ['tab' => 'grade']))->assertOk()
            ->assertSee('name="dias_semana_grade_treino[]"', false);

        foreach (['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'] as $dia) {
            $resposta->assertSee('id="cad_g_dia_' . $dia . '" value="' . $dia . '"', false);
        }
    }

    public function test_admin_troca_horario_de_categoria_para_geral(): void
    {
        $idGrade = $this->criarGrade([
            'categoria_grade_treino' => 'Sub-13', 'id_categoria' => $this->idCategoria('Sub-13', 'M'),
        ]);

        $this->comoAdmin()
            ->put(route('admin.calendario.grade.update', $idGrade), $this->dadosGrade([
                'id_categoria' => '', 'categoria_grade_treino' => 'Treino Livre', 'tipo_grade_treino' => 'LIVRE',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_grade_treino', [
            'id_grade_treino' => $idGrade, 'id_categoria' => null, 'categoria_grade_treino' => 'Treino Livre',
        ]);
    }

    public function test_tela_do_admin_lista_a_grade_com_o_rotulo(): void
    {
        $this->criarGrade(['categoria_grade_treino' => 'Sub-13', 'id_categoria' => $this->idCategoria('Sub-13', 'F')]);
        $this->criarGrade(['categoria_grade_treino' => 'Integrado']);

        $this->comoAdmin()
            ->get(route('admin.calendario.index', ['tab' => 'grade']))
            ->assertOk()
            ->assertSee('Sub-13 Feminino')
            ->assertSee('Integrado')
            ->assertSee('Geral (sem categoria)');
    }

    public function test_tela_do_admin_mostra_o_horario_sem_segundos(): void
    {
        $this->criarGrade(['categoria_grade_treino' => 'Integrado']); // 08:00 às 09:30
        $this->criarGrade(['categoria_grade_treino' => 'Jogos', 'horario_inicio_grade_treino' => null,
            'horario_fim_grade_treino' => null, 'horario_obs_grade_treino' => 'Conforme tabela']);

        $this->comoAdmin()
            ->get(route('admin.calendario.index', ['tab' => 'grade']))
            ->assertOk()
            ->assertSee('08:00 às 09:30')
            ->assertDontSee('08:00:00 –', false);
    }

    // ---------- site ----------

    public function test_site_mostra_a_grade_com_categoria_e_itens_gerais(): void
    {
        $this->criarGrade(['categoria_grade_treino' => 'Sub-13', 'id_categoria' => $this->idCategoria('Sub-13', 'F')]);
        $this->criarGrade(['categoria_grade_treino' => 'Treino Livre', 'tipo_grade_treino' => 'LIVRE']);

        $this->get('/calendario')
            ->assertOk()
            ->assertSee('Sub-13 Feminino')
            ->assertSee('cat-sub13', false) // mesma cor da Sub-13 masculina
            ->assertSee('Treino Livre');
    }

    public function test_site_mostra_um_cartao_por_dia_na_ordem_da_semana(): void
    {
        $this->criarGrade(['categoria_grade_treino' => 'Item Do Sabado', 'dia_semana_grade_treino' => 'sabado']);
        $this->criarGrade(['categoria_grade_treino' => 'Item Da Segunda', 'dia_semana_grade_treino' => 'segunda']);
        $this->criarGrade(['categoria_grade_treino' => 'Item Da Quarta', 'dia_semana_grade_treino' => 'quarta']);
        $this->criarGrade(['categoria_grade_treino' => 'Item Inativo', 'dia_semana_grade_treino' => 'quinta', 'status_grade_treino' => 'INATIVO']);

        $this->get('/calendario')->assertOk()
            ->assertSeeInOrder(['Segunda', 'Item Da Segunda', 'Quarta', 'Item Da Quarta', 'Sábado', 'Item Do Sabado'])
            ->assertDontSee('Item Inativo')
            ->assertDontSee('<span>Quinta</span>', false) // dia sem horário ativo não tem cartão
            ->assertSee('Os treinos de domingo são reservados para repouso.');
    }

    public function test_site_tira_a_frase_do_domingo_quando_ha_treino_no_domingo(): void
    {
        $this->criarGrade(['categoria_grade_treino' => 'Item Do Domingo', 'dia_semana_grade_treino' => 'domingo']);

        $this->get('/calendario')->assertOk()
            ->assertSee('<span>Domingo</span>', false)
            ->assertSee('Item Do Domingo')
            ->assertDontSee('Os treinos de domingo são reservados para repouso.')
            ->assertSee('Alterações de horário são comunicadas com antecedência');
    }

    // ---------- ordem ----------

    public function test_grade_do_dia_segue_o_horario_de_inicio_no_site_e_no_admin(): void
    {
        // "ordem" ao contrário do horário, e um item sem horário: deve ficar por último no dia
        $this->criarGrade(['categoria_grade_treino' => 'Item Sem Horario', 'horario_inicio_grade_treino' => null,
            'horario_fim_grade_treino' => null, 'horario_obs_grade_treino' => 'Conforme tabela', 'ordem_grade_treino' => 0]);
        $this->criarGrade(['categoria_grade_treino' => 'Item Das Dez', 'horario_inicio_grade_treino' => '10:00', 'ordem_grade_treino' => 1]);
        $this->criarGrade(['categoria_grade_treino' => 'Item Das Nove e Meia', 'horario_inicio_grade_treino' => '09:30', 'ordem_grade_treino' => 2]);
        // Outro dia vem antes, mesmo com horário mais tarde
        $this->criarGrade(['categoria_grade_treino' => 'Item De Segunda', 'dia_semana_grade_treino' => 'segunda',
            'horario_inicio_grade_treino' => '18:00', 'ordem_grade_treino' => 9]);

        $esperado = ['Item De Segunda', 'Item Das Nove e Meia', 'Item Das Dez', 'Item Sem Horario'];

        $this->get('/calendario')->assertOk()->assertSeeInOrder($esperado);

        $this->comoAdmin()
            ->get(route('admin.calendario.index', ['tab' => 'grade']))
            ->assertOk()
            ->assertSeeInOrder($esperado);
    }

    // ---------- helpers ----------

    private function migrationDaGrade(): object
    {
        return require database_path('migrations/2026_10_03_000006_add_id_categoria_to_tbl_grade_treino.php');
    }

    private function criarGrade(array $dados): int
    {
        return DB::table('tbl_grade_treino')->insertGetId(array_merge([
            'dia_semana_grade_treino'     => 'terca',
            'tipo_grade_treino'           => 'TREINO',
            'horario_inicio_grade_treino' => '08:00',
            'horario_fim_grade_treino'    => '09:30',
            'local_grade_treino'          => 'Campo A',
            'status_grade_treino'         => 'ATIVO',
        ], $dados));
    }

    // Formulário do "Novo Horário" (dias marcados) e da edição (um dia), num só: cada rota lê o seu campo
    private function dadosGrade(array $extra = []): array
    {
        return array_merge([
            'dias_semana_grade_treino'    => ['segunda'],
            'dia_semana_grade_treino'     => 'segunda',
            'tipo_grade_treino'           => 'TREINO',
            'categoria_grade_treino'      => 'Texto ignorado',
            'horario_inicio_grade_treino' => '10:00',
            'horario_fim_grade_treino'    => '11:30',
            'local_grade_treino'          => 'Campo B',
            'ordem_grade_treino'          => 1,
        ], $extra);
    }
}
