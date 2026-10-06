<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Histórico de alterações do evento e status derivados Alterado e Concluído (Fase 4, Etapa 3).
 */
class EventoHistoricoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------- histórico ----------

    public function test_edicao_registra_campo_valores_quem_e_quando(): void
    {
        Carbon::setTestNow('2026-10-05 14:30:00');
        $editor = User::factory()->admin()->create();
        $id     = $this->criarEvento(['local_evento_calendario' => 'Campo A']);

        $this->actingAs($editor, 'admin')
            ->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, ['local_evento_calendario' => 'Campo B']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_historico', [
            'id_evento_calendario'          => $id,
            'campo_evento_historico'        => 'local_evento_calendario',
            'valor_antigo_evento_historico' => 'Campo A',
            'valor_novo_evento_historico'   => 'Campo B',
            'id_usuario'                    => $editor->id_usuario,
            'data_evento_historico'         => '2026-10-05 14:30:00',
        ]);
        $this->assertSame(1, DB::table('tbl_evento_historico')->count());
    }

    public function test_salvar_sem_mudar_nada_nao_registra_historico(): void
    {
        // O formulário manda "09:00" e o banco guarda "09:00:00": não pode virar "mudança"
        $id = $this->criarEvento(['horario_inicio_evento_calendario' => '09:00:00', 'horario_fim_evento_calendario' => '11:00:00']);

        $this->comoAdmin()
            ->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, [
                'horario_inicio_evento_calendario' => '09:00',
                'horario_fim_evento_calendario'    => '11:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('tbl_evento_historico')->count());
    }

    public function test_registra_os_campos_combinados_e_nao_a_descricao(): void
    {
        $id = $this->criarEvento();

        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, [
            'titulo_evento_calendario'         => 'Título Novo',
            'tipo_evento_calendario'           => 'TREINO',
            'id_categoria'                     => $this->idCategoria('Sub-13', 'M'),
            'data_evento_calendario'           => now()->addDays(10)->toDateString(),
            'horario_inicio_evento_calendario' => '10:00',
            'horario_fim_evento_calendario'    => '12:00',
            'local_evento_calendario'          => 'Campo Novo',
            'descricao_evento_calendario'      => 'Descrição nova (não entra no histórico)',
        ]))->assertSessionHasNoErrors();

        $campos = DB::table('tbl_evento_historico')->pluck('campo_evento_historico')->sort()->values()->all();

        $this->assertSame([
            'data_evento_calendario', 'horario_fim_evento_calendario', 'horario_inicio_evento_calendario',
            'id_categoria', 'local_evento_calendario', 'tipo_evento_calendario', 'titulo_evento_calendario',
        ], $campos);
    }

    public function test_cancelar_e_ocultar_registram_o_status(): void
    {
        $admin = User::factory()->admin()->create();
        $id    = $this->criarEvento();

        $this->actingAs($admin, 'admin')->patch(route('admin.calendario.eventos.cancelar', $id));
        $this->actingAs($admin, 'admin')->patch(route('admin.calendario.eventos.ocultar', $id));

        $mudancas = DB::table('tbl_evento_historico')
            ->where('campo_evento_historico', 'status_evento_calendario')
            ->orderBy('id_evento_historico')
            ->get(['valor_antigo_evento_historico', 'valor_novo_evento_historico', 'id_usuario']);

        $this->assertSame(['ATIVO', 'CANCELADO'], [$mudancas[0]->valor_antigo_evento_historico, $mudancas[0]->valor_novo_evento_historico]);
        $this->assertSame(['CANCELADO', 'INATIVO'], [$mudancas[1]->valor_antigo_evento_historico, $mudancas[1]->valor_novo_evento_historico]);
        $this->assertSame($admin->id_usuario, (int) $mudancas[1]->id_usuario);
    }

    public function test_modal_de_edicao_recebe_o_historico_legivel(): void
    {
        $editor = User::factory()->admin()->create(['nome_usuario' => 'Editora Teste']);
        $id     = $this->criarEvento(['local_evento_calendario' => 'Campo A']);

        $this->actingAs($editor, 'admin')->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, [
            'local_evento_calendario' => 'Campo B',
            'id_categoria'            => $this->idCategoria('Sub-15', 'F'),
        ]));

        $this->comoAdmin()
            ->get(route('admin.calendario.index'))
            ->assertOk()
            ->assertSee('Editora Teste')
            ->assertSee('Local: Campo A → Campo B', false)
            ->assertSee('Categoria: (vazio) → Sub-15 Feminino', false);
    }

    // ---------- Alterado ----------

    public function test_mudar_local_de_evento_futuro_vira_alterado(): void
    {
        $id = $this->criarEvento();

        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, ['local_evento_calendario' => 'Outro Campo']));

        $this->assertSame('ALTERADO', EventoCalendario::find($id)->situacao);
    }

    public function test_mudar_so_o_titulo_nao_vira_alterado(): void
    {
        $id = $this->criarEvento();

        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, ['titulo_evento_calendario' => 'Só o Título']));

        $this->assertSame(1, DB::table('tbl_evento_historico')->count());
        $this->assertSame('ATIVO', EventoCalendario::find($id)->situacao);
    }

    public function test_alterado_aparece_no_admin_e_nunca_no_site(): void
    {
        // CAMPEONATO: o site só mostra campeonatos e jogos de campeonato
        $id = $this->criarEvento(['titulo_evento_calendario' => 'Jogo Remarcado XYZ', 'tipo_evento_calendario' => 'CAMPEONATO']);
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, [
            'data_evento_calendario' => now()->addDays(12)->toDateString(),
        ]));

        $this->comoAdmin()->get(route('admin.calendario.index'))
            ->assertSee('<span class="badge-status pendente">Alterado</span>', false);

        $this->get('/calendario')
            ->assertOk()
            ->assertSee('Jogo Remarcado XYZ')
            ->assertDontSee('Alterado')
            ->assertDontSee('alteração');
    }

    public function test_lista_do_admin_carrega_a_alteracao_numa_consulta_so(): void
    {
        foreach (range(1, 5) as $i) {
            $id = $this->criarEvento(['titulo_evento_calendario' => "Evento {$i}"]);
            $this->comoAdmin()->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, ['local_evento_calendario' => "Local {$i}"]));
        }

        $eventos = EventoCalendario::comAlteracao()->get();

        DB::enableQueryLog();
        $situacoes = $eventos->map->situacao->unique()->values()->all();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['ALTERADO'], $situacoes);
        $this->assertSame(0, $consultas, 'A situação não deve consultar o banco por evento.');
    }

    // ---------- Concluído ----------

    public function test_concluido_pela_data_e_pelo_horario(): void
    {
        Carbon::setTestNow('2026-10-05 15:00:00');

        $casos = [
            'ontem'                       => [['data_evento_calendario' => '2026-10-04'], 'CONCLUIDO'],
            'amanhã'                      => [['data_evento_calendario' => '2026-10-06'], 'ATIVO'],
            'hoje, fim já passou'         => [['horario_inicio_evento_calendario' => '13:00', 'horario_fim_evento_calendario' => '14:30'], 'CONCLUIDO'],
            'hoje, fim ainda não passou'  => [['horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '16:00'], 'ATIVO'],
            'hoje, sem fim, início passou'=> [['horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => null], 'CONCLUIDO'],
            'hoje, sem fim, início não'   => [['horario_inicio_evento_calendario' => '18:00', 'horario_fim_evento_calendario' => null], 'ATIVO'],
            'hoje, sem horário'           => [['horario_inicio_evento_calendario' => null, 'horario_fim_evento_calendario' => null], 'ATIVO'],
        ];

        foreach ($casos as $nome => [$dados, $esperado]) {
            $id = $this->criarEvento(['data_evento_calendario' => '2026-10-05', ...$dados]);
            $this->assertSame($esperado, EventoCalendario::find($id)->situacao, "Caso: {$nome}");
        }
    }

    public function test_cancelado_continua_cancelado_depois_da_data(): void
    {
        $id = $this->criarEvento(['data_evento_calendario' => now()->subMonth()->toDateString(), 'status_evento_calendario' => 'CANCELADO']);

        $this->assertSame('CANCELADO', EventoCalendario::find($id)->situacao);
    }

    public function test_evento_passado_e_alterado_aparece_como_concluido(): void
    {
        $id = $this->criarEvento();
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $id), $this->dadosEdicao($id, [
            'data_evento_calendario' => now()->subDay()->toDateString(),
        ]));

        $this->assertSame('CONCLUIDO', EventoCalendario::find($id)->situacao);
    }

    // ---------- helpers ----------

    private function criarEvento(array $extra = []): int
    {
        return DB::table('tbl_evento_calendario')->insertGetId(array_merge([
            'titulo_evento_calendario'         => 'Evento de Teste',
            'tipo_evento_calendario'           => 'JOGO',
            'data_evento_calendario'           => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '09:00:00',
            'horario_fim_evento_calendario'    => '11:00:00',
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => 'ATIVO',
        ], $extra));
    }

    // Formulário de edição com os dados atuais do evento (horários como o navegador manda: "H:i")
    private function dadosEdicao(int $id, array $extra = []): array
    {
        $evento = DB::table('tbl_evento_calendario')->where('id_evento_calendario', $id)->first();

        return array_merge([
            'titulo_evento_calendario'         => $evento->titulo_evento_calendario,
            'tipo_evento_calendario'           => $evento->tipo_evento_calendario,
            'id_categoria'                     => $evento->id_categoria,
            'data_evento_calendario'           => $evento->data_evento_calendario,
            'horario_inicio_evento_calendario' => $evento->horario_inicio_evento_calendario ? substr($evento->horario_inicio_evento_calendario, 0, 5) : null,
            'horario_fim_evento_calendario'    => $evento->horario_fim_evento_calendario ? substr($evento->horario_fim_evento_calendario, 0, 5) : null,
            'local_evento_calendario'          => $evento->local_evento_calendario,
            'descricao_evento_calendario'      => $evento->descricao_evento_calendario,
        ], $extra);
    }
}
