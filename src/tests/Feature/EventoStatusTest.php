<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Status gravado do evento: ATIVO / CANCELADO / INATIVO (Fase 4, Etapa 1).
 * Status muda só pelas ações de cancelar e ocultar; não há exclusão.
 */
class EventoStatusTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_evento_nasce_ativo(): void
    {
        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.store'), [
                'titulo_evento_calendario' => 'Evento Novo',
                'tipo_evento_calendario'   => 'JOGO',
                'data_evento_calendario'   => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', ['titulo_evento_calendario' => 'Evento Novo', 'status_evento_calendario' => 'ATIVO']);
    }

    public function test_cancelar_e_reativar(): void
    {
        $id = $this->criarEvento('ATIVO');

        $this->comoAdmin()->patch(route('admin.calendario.eventos.cancelar', $id))->assertSessionHas('sucesso', 'Evento cancelado. 0 atleta(s) notificado(s).');
        $this->assertSame('CANCELADO', $this->statusDe($id));

        $this->comoAdmin()->patch(route('admin.calendario.eventos.cancelar', $id))->assertSessionHas('sucesso', 'Evento reativado. 0 atleta(s) notificado(s).');
        $this->assertSame('ATIVO', $this->statusDe($id));
    }

    public function test_evento_oculto_nao_pode_ser_cancelado(): void
    {
        $id = $this->criarEvento('INATIVO');

        $this->comoAdmin()->patch(route('admin.calendario.eventos.cancelar', $id))->assertSessionHas('erro');
        $this->assertSame('INATIVO', $this->statusDe($id));
    }

    public function test_ocultar_e_mostrar_restaura_o_status_anterior(): void
    {
        $id = $this->criarEvento('CANCELADO');

        $this->comoAdmin()->patch(route('admin.calendario.eventos.ocultar', $id))->assertSessionHas('sucesso', 'Evento ocultado. 0 atleta(s) notificado(s).');
        $this->assertSame('INATIVO', $this->statusDe($id));

        // Mostrar devolve o status de antes de ocultar (pelo histórico): cancelado volta cancelado
        $this->comoAdmin()->patch(route('admin.calendario.eventos.ocultar', $id))
            ->assertSessionHas('sucesso', 'Evento visível de novo (continua cancelado). 0 atleta(s) notificado(s).');
        $this->assertSame('CANCELADO', $this->statusDe($id));
    }

    public function test_mostrar_evento_que_estava_ativo_volta_ativo(): void
    {
        $id = $this->criarEvento('ATIVO');

        $this->comoAdmin()->patch(route('admin.calendario.eventos.ocultar', $id));
        $this->comoAdmin()->patch(route('admin.calendario.eventos.ocultar', $id))->assertSessionHas('sucesso', 'Evento visível de novo. 0 atleta(s) notificado(s).');

        $this->assertSame('ATIVO', $this->statusDe($id));
    }

    public function test_mostrar_evento_oculto_sem_historico_volta_ativo(): void
    {
        // Ocultado antes do histórico existir: sem "status anterior", volta como ATIVO
        $id = $this->criarEvento('INATIVO');

        $this->comoAdmin()->patch(route('admin.calendario.eventos.ocultar', $id));

        $this->assertSame('ATIVO', $this->statusDe($id));
    }

    public function test_edicao_nao_muda_o_status(): void
    {
        $id = $this->criarEvento('CANCELADO');

        $this->comoAdmin()
            ->put(route('admin.calendario.eventos.update', $id), [
                'titulo_evento_calendario' => 'Título Novo',
                'tipo_evento_calendario'   => 'JOGO',
                'data_evento_calendario'   => now()->addWeek()->toDateString(),
                'status_evento_calendario' => 'ATIVO',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', [
            'id_evento_calendario' => $id, 'titulo_evento_calendario' => 'Título Novo', 'status_evento_calendario' => 'CANCELADO',
        ]);
    }

    public function test_nao_existe_mais_exclusao_de_evento(): void
    {
        $id = $this->criarEvento('CANCELADO');

        $this->assertFalse(Route::has('admin.calendario.eventos.destroy'));

        $this->comoAdmin()
            ->delete('/admin/calendario/eventos/' . $id)
            ->assertStatus(405); // a URL existe (PUT), mas não aceita DELETE

        $this->assertDatabaseHas('tbl_evento_calendario', ['id_evento_calendario' => $id]);
    }

    public function test_admin_mostra_o_selo_de_cada_status(): void
    {
        $this->criarEvento('ATIVO');
        $this->criarEvento('CANCELADO');
        $this->criarEvento('INATIVO');

        $this->comoAdmin()
            ->get(route('admin.calendario.index'))
            ->assertOk()
            ->assertSee('<span class="badge-status ativo">Ativo</span>', false)
            ->assertSee('<span class="badge-status inativo">Cancelado</span>', false)
            ->assertSee('<span class="badge-status rejeitado">Oculto</span>', false)
            ->assertDontSee('Confirmado');
    }

    public function test_proximo_evento_do_site_ignora_cancelado_e_oculto(): void
    {
        $this->criarEvento('CANCELADO', ['titulo_evento_calendario' => 'Cancelado Amanha', 'data_evento_calendario' => now()->addDay()->toDateString()]);
        $this->criarEvento('INATIVO',   ['titulo_evento_calendario' => 'Oculto Depois',    'data_evento_calendario' => now()->addDays(2)->toDateString()]);
        $this->criarEvento('ATIVO',     ['titulo_evento_calendario' => 'Ativo Semana Que Vem', 'data_evento_calendario' => now()->addWeek()->toDateString()]);

        $resposta = $this->get('/calendario')->assertOk();

        // O destaque (cal-next-title) é o ativo; o cancelado aparece só na lista
        $resposta->assertSee('<h3 class="cal-next-title">Ativo Semana Que Vem</h3>', false)
            ->assertSee('Cancelado Amanha');
    }

    public function test_site_mostra_a_definir_quando_o_evento_nao_tem_horario(): void
    {
        $this->criarEvento('ATIVO', [
            'titulo_evento_calendario'         => 'Sem Horario XYZ',
            'horario_inicio_evento_calendario' => null,
            'horario_fim_evento_calendario'    => null,
        ]);

        $this->get('/calendario')->assertOk()->assertSee('A definir');
    }

    public function test_lista_do_admin_mostra_horario_sem_segundos(): void
    {
        $this->criarEvento('ATIVO', ['horario_inicio_evento_calendario' => '17:00:00', 'horario_fim_evento_calendario' => '18:30:00']);
        $this->criarEvento('ATIVO', ['titulo_evento_calendario' => 'Sem Horario', 'horario_inicio_evento_calendario' => null]);

        $this->comoAdmin()
            ->get(route('admin.calendario.index'))
            ->assertOk()
            ->assertSee('17:00 às 18:30')
            ->assertDontSee('17:00:00 – 18:30:00') // formato antigo da coluna (o data-inicio do botão pode ter segundos)
            ->assertSee('A definir');
    }

    public function test_horario_texto(): void
    {
        $evento = new EventoCalendario([
            'horario_inicio_evento_calendario' => '09:00:00',
            'horario_fim_evento_calendario'    => '11:30:00',
        ]);
        $this->assertSame('09:00 às 11:30', $evento->horario_texto);

        $evento->horario_fim_evento_calendario = null;
        $this->assertSame('09:00', $evento->horario_texto);

        $evento->horario_inicio_evento_calendario = null;
        $this->assertSame('A definir', $evento->horario_texto);
    }

    // ---------- helpers ----------

    private function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'admin');
    }

    private function criarEvento(string $status, array $extra = []): int
    {
        return DB::table('tbl_evento_calendario')->insertGetId(array_merge([
            'titulo_evento_calendario'         => "Evento {$status}",
            'tipo_evento_calendario'           => 'JOGO',
            'subtipo_evento_calendario'        => 'Amistoso',
            'data_evento_calendario'           => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '09:00',
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => $status,
        ], $extra));
    }

    private function statusDe(int $id): string
    {
        return DB::table('tbl_evento_calendario')->where('id_evento_calendario', $id)->value('status_evento_calendario');
    }
}
