<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Base da geração de eventos pela grade (Fase 7, Etapa 1): datas do mês, dados do evento,
 * linhas que não geram, origem do evento (grade + data) e exclusão da linha da grade.
 * Novembro de 2026 começa num domingo e tem 5 segundas (2, 9, 16, 23, 30).
 */
class GradeGeracaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- datas do mês ----------

    public function test_datas_do_mes_para_os_sete_dias_da_grade(): void
    {
        $esperado = [
            'segunda' => ['02', '09', '16', '23', '30'],
            'terca'   => ['03', '10', '17', '24'],
            'quarta'  => ['04', '11', '18', '25'],
            'quinta'  => ['05', '12', '19', '26'],
            'sexta'   => ['06', '13', '20', '27'],
            'sabado'  => ['07', '14', '21', '28'],
            'domingo' => ['01', '08', '15', '22', '29'],
        ];

        foreach ($esperado as $dia => $dias) {
            $grade = $this->grade(['dia_semana_grade_treino' => $dia]);

            $this->assertSame($dias, $grade->datasNoMes(2026, 11)->map->format('d')->all(), $dia);
        }
    }

    public function test_datas_do_mes_a_partir_de_uma_data_minima(): void
    {
        $grade = $this->grade(['dia_semana_grade_treino' => 'segunda']);

        // A data mínima conta (16/11 é segunda); a hora não importa
        $datas = $grade->datasNoMes(2026, 11, Carbon::parse('2026-11-16 15:00'));
        $this->assertSame(['2026-11-16', '2026-11-23', '2026-11-30'],
            $datas->map->toDateString()->all());

        // Data mínima depois do fim do mês: nada
        $this->assertCount(0, $grade->datasNoMes(2026, 11, Carbon::parse('2026-12-01')));
    }

    // ---------- dados do evento ----------

    public function test_dados_do_evento_de_linha_com_categoria(): void
    {
        $idSub13F = $this->idCategoria('Sub-13', 'F');
        $grade = $this->grade(['categoria_grade_treino' => 'Sub-13', 'id_categoria' => $idSub13F,
            'horario_inicio_grade_treino' => '10:00', 'horario_fim_grade_treino' => '11:00', 'local_grade_treino' => 'Campo B']);

        $dados = $grade->dadosEventoPara(Carbon::parse('2026-11-03'));

        $this->assertSame('Treino Sub-13 Feminino', $dados['titulo_evento_calendario']);
        $this->assertSame('TREINO', $dados['tipo_evento_calendario']);
        $this->assertNull($dados['subtipo_evento_calendario']);
        $this->assertSame($idSub13F, $dados['id_categoria']);
        $this->assertSame('2026-11-03', $dados['data_evento_calendario']);
        $this->assertSame('10:00:00', $dados['horario_inicio_evento_calendario']);
        $this->assertSame('11:00:00', $dados['horario_fim_evento_calendario']);
        $this->assertSame('Campo B', $dados['local_evento_calendario']);
        $this->assertSame('ATIVO', $dados['status_evento_calendario']);
        // A origem não vai nos dados do formulário
        $this->assertArrayNotHasKey('id_grade_treino', $dados);
        $this->assertArrayNotHasKey('data_grade_evento_calendario', $dados);
    }

    public function test_dados_do_evento_do_integrado_e_do_treino_livre(): void
    {
        $integrado = $this->grade(['categoria_grade_treino' => 'Integrado'])->dadosEventoPara(Carbon::parse('2026-11-06'));
        $this->assertSame('Treino Integrado', $integrado['titulo_evento_calendario']);
        $this->assertSame('TREINO', $integrado['tipo_evento_calendario']);
        $this->assertNull($integrado['id_categoria']);

        // LIVRE não existe no ENUM do evento: TREINO com subtipo "Livre"; o rótulo já começa com "Treino"
        $livre = $this->grade(['categoria_grade_treino' => 'Treino Livre', 'tipo_grade_treino' => 'LIVRE'])
            ->dadosEventoPara(Carbon::parse('2026-11-07'));
        $this->assertSame('Treino Livre', $livre['titulo_evento_calendario']);
        $this->assertSame('TREINO', $livre['tipo_evento_calendario']);
        $this->assertSame('Livre', $livre['subtipo_evento_calendario']);
        $this->assertNull($livre['id_categoria']);
    }

    public function test_dados_do_evento_criam_um_evento_valido(): void
    {
        $grade = $this->grade(['categoria_grade_treino' => 'Integrado']);

        $evento = EventoCalendario::criarPor(null, $grade->dadosEventoPara(Carbon::parse('2026-11-06')));

        $this->assertDatabaseHas('tbl_evento_calendario', [
            'id_evento_calendario' => $evento->id_evento_calendario, 'titulo_evento_calendario' => 'Treino Integrado',
            'horario_inicio_evento_calendario' => '08:00:00', 'status_evento_calendario' => 'ATIVO',
        ]);
    }

    // ---------- linhas que geram ou não ----------

    public function test_linhas_que_nao_geram_eventos(): void
    {
        $idSub9F = $this->idCategoria('Sub-9', 'F');
        DB::table('tbl_categoria')->where('id_categoria', $idSub9F)->update(['status_categoria' => 'INATIVO']);

        $naoGeram = [
            'jogo'              => ['categoria_grade_treino' => 'Jogos', 'tipo_grade_treino' => 'JOGO'],
            'inativa'           => ['categoria_grade_treino' => 'Integrado', 'status_grade_treino' => 'INATIVO'],
            'categoria inativa' => ['categoria_grade_treino' => 'Sub-9', 'id_categoria' => $idSub9F],
            'sem início'        => ['categoria_grade_treino' => 'Integrado', 'horario_inicio_grade_treino' => null,
                'horario_fim_grade_treino' => null, 'horario_obs_grade_treino' => 'Horário variável'],
        ];

        foreach ($naoGeram as $caso => $dados) {
            $grade = $this->grade($dados);
            $this->assertFalse($grade->geraEventos(), $caso);
            $this->assertNotNull($grade->motivoQueNaoGera(), $caso);
        }

        $this->assertStringContainsString('Sub-9 Feminino', $this->grade($naoGeram['categoria inativa'])->motivoQueNaoGera());
    }

    public function test_linhas_que_geram_eventos(): void
    {
        $geram = [
            'com categoria' => ['categoria_grade_treino' => 'Sub-13', 'id_categoria' => $this->idCategoria('Sub-13', 'M')],
            'integrado'     => ['categoria_grade_treino' => 'Integrado'],
            'livre'         => ['categoria_grade_treino' => 'Treino Livre', 'tipo_grade_treino' => 'LIVRE'],
            'sem fim'       => ['categoria_grade_treino' => 'Integrado', 'horario_fim_grade_treino' => null],
        ];

        foreach ($geram as $caso => $dados) {
            $this->assertTrue($this->grade($dados)->geraEventos(), $caso);
        }
    }

    // ---------- origem do evento (grade + data) ----------

    public function test_relacoes_entre_evento_e_grade(): void
    {
        $grade = $this->grade(['categoria_grade_treino' => 'Integrado']);
        $idEvento = $this->eventoDaGrade($grade->id_grade_treino, '2026-11-06');

        $evento = EventoCalendario::find($idEvento);
        $this->assertSame($grade->id_grade_treino, $evento->grade->id_grade_treino);
        $this->assertSame('2026-11-06', $evento->data_grade_evento_calendario->toDateString());
        $this->assertSame([$idEvento], $grade->eventos->pluck('id_evento_calendario')->all());
    }

    public function test_mesma_grade_e_mesma_data_nao_se_repetem(): void
    {
        $idGrade = $this->grade(['categoria_grade_treino' => 'Integrado'])->id_grade_treino;
        $this->eventoDaGrade($idGrade, '2026-11-06');
        $this->eventoDaGrade($idGrade, '2026-11-13'); // outra data: pode

        $this->expectException(UniqueConstraintViolationException::class);
        $this->eventoDaGrade($idGrade, '2026-11-06');
    }

    public function test_eventos_sem_grade_nao_colidem_no_indice_unico(): void
    {
        $this->eventoDaGrade(null, null);
        $this->eventoDaGrade(null, null);

        $this->assertSame(2, DB::table('tbl_evento_calendario')->whereNull('id_grade_treino')->count());
    }

    public function test_formulario_de_evento_nao_grava_nem_troca_a_origem(): void
    {
        $idGrade = $this->grade(['categoria_grade_treino' => 'Integrado'])->id_grade_treino;
        $origem  = ['id_grade_treino' => $idGrade, 'data_grade_evento_calendario' => '2026-11-06'];
        $dados   = [
            'titulo_evento_calendario' => 'Reunião', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario'   => now()->addWeek()->toDateString(), 'horario_inicio_evento_calendario' => '10:00',
        ];

        // Criar: a origem enviada no formulário é ignorada
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), [...$dados, ...$origem])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_evento_calendario', [
            'titulo_evento_calendario' => 'Reunião', 'id_grade_treino' => null, 'data_grade_evento_calendario' => null,
        ]);

        // Editar um evento gerado: a origem continua a mesma
        $idEvento = $this->eventoDaGrade($idGrade, '2026-11-13');
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $idEvento), [
            ...$dados, 'id_grade_treino' => null, 'data_grade_evento_calendario' => '2026-12-25',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_evento_calendario', [
            'id_evento_calendario' => $idEvento, 'id_grade_treino' => $idGrade, 'data_grade_evento_calendario' => '2026-11-13',
        ]);
    }

    // ---------- exclusão da linha da grade ----------

    public function test_linha_que_gerou_eventos_nao_e_excluida(): void
    {
        $idGrade = $this->grade(['categoria_grade_treino' => 'Integrado'])->id_grade_treino;
        $this->eventoDaGrade($idGrade, '2026-11-06');

        $this->comoAdmin()
            ->delete(route('admin.calendario.grade.destroy', $idGrade))
            ->assertRedirect(route('admin.calendario.index', ['tab' => 'grade']))
            ->assertSessionHas('erro', fn ($msg) => str_contains($msg, 'Inativar'));

        $this->assertDatabaseHas('tbl_grade_treino', ['id_grade_treino' => $idGrade]);
    }

    public function test_linha_sem_eventos_e_excluida(): void
    {
        $idGrade = $this->grade(['categoria_grade_treino' => 'Integrado'])->id_grade_treino;

        $this->comoAdmin()
            ->delete(route('admin.calendario.grade.destroy', $idGrade))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseMissing('tbl_grade_treino', ['id_grade_treino' => $idGrade]);
    }

    // ---------- helpers ----------

    private function grade(array $dados): GradeTreino
    {
        $id = DB::table('tbl_grade_treino')->insertGetId(array_merge([
            'dia_semana_grade_treino'     => 'sexta',
            'categoria_grade_treino'      => 'Integrado',
            'tipo_grade_treino'           => 'TREINO',
            'horario_inicio_grade_treino' => '08:00',
            'horario_fim_grade_treino'    => '09:30',
            'local_grade_treino'          => 'Campo A',
            'status_grade_treino'         => 'ATIVO',
        ], $dados));

        return GradeTreino::findOrFail($id);
    }

    // Evento com a origem gravada direto (a origem não é fillable; a tela de geração vem na Etapa 2)
    private function eventoDaGrade(?int $idGrade, ?string $dataOrigem): int
    {
        return DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario'     => 'Treino',
            'tipo_evento_calendario'       => 'TREINO',
            'data_evento_calendario'       => $dataOrigem ?? now()->addWeek()->toDateString(),
            'status_evento_calendario'     => 'ATIVO',
            'id_grade_treino'              => $idGrade,
            'data_grade_evento_calendario' => $dataOrigem,
        ]);
    }
}
