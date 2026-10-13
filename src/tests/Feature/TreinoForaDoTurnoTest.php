<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase A, Etapa 3: o atleta treina no turno oposto ao da escola; inscrevê-lo à mão num treino de outro turno
 * só avisa (não bloqueia), e a tela do treino marca "Fora do turno". Outros eventos não têm turno.
 */
class TreinoForaDoTurnoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub13M;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
    }

    public function test_motivo_de_cada_periodo(): void
    {
        $motivo = fn (?string $periodo, ?string $turno, string $treino) => (new Atleta([
            'periodo_escolar_atleta' => $periodo, 'turno_treino_atleta' => $turno,
        ]))->foraDoTurno($treino);

        $this->assertNull($motivo('MANHA', null, 'TARDE'));
        $this->assertSame('estuda de manhã', $motivo('MANHA', null, 'MANHA'));
        $this->assertSame('estuda à tarde', $motivo('TARDE', null, 'TARDE'));
        $this->assertSame('estuda em período integral', $motivo('INTEGRAL', null, 'MANHA'));
        $this->assertSame('estuda à noite e escolheu treinar à tarde', $motivo('NOITE', 'TARDE', 'MANHA'));
        $this->assertSame('estuda à noite e não escolheu o turno do treino', $motivo('NOITE', null, 'MANHA'));
        $this->assertNull($motivo('NOITE', 'AMBOS', 'MANHA'));
        $this->assertSame('está sem período escolar', $motivo(null, null, 'TARDE'));
    }

    public function test_inscrever_fora_do_turno_avisa_e_inscreve_e_a_tela_marca(): void
    {
        $treino = $this->treino('09:00');
        $ana = $this->atleta('Ana', 'MANHA'); // estuda de manhã: treina à tarde
        $bia = $this->atleta('Bia', 'TARDE'); // estuda à tarde: treina de manhã

        // O select já mostra quem está fora do turno
        $this->tela($treino)
            ->assertSee('Ana · fora do turno')
            ->assertDontSee('Bia · fora do turno');

        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $treino), ['id_atleta' => $ana])
            ->assertSessionHas('sucesso')
            ->assertSessionHas('avisos_turno', ['Ana estuda de manhã; o treino é de manhã.']);
        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $treino), ['id_atleta' => $bia])
            ->assertSessionMissing('avisos_turno');

        $this->assertEqualsCanonicalizing([$ana, $bia], $treino->inscricoes()->pluck('id_atleta')->all());
        $this->tela($treino)
            ->assertSee('Fora do turno · estuda de manhã')
            ->assertDontSee('Fora do turno · estuda à tarde');
    }

    public function test_adicionar_todos_de_uma_categoria_avisa_quem_ficou_fora_do_turno(): void
    {
        $treino = $this->treino('14:00');
        $this->atleta('Ana', 'MANHA');   // treina à tarde: sem aviso
        $this->atleta('Bia', 'TARDE');   // estuda à tarde
        $this->atleta('Caio', null);     // sem período

        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.categoria', $treino), ['id_categoria' => $this->idSub13M])
            ->assertSessionHas('avisos_turno', ['Bia estuda à tarde; o treino é à tarde.', 'Caio está sem período escolar; o treino é à tarde.']);
        $this->assertSame(3, $treino->inscricoes()->count());
    }

    public function test_evento_que_nao_e_treino_nao_tem_turno(): void
    {
        $reuniao = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Reunião', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'horario_inicio_evento_calendario' => '09:00',
            'status_evento_calendario' => 'ATIVO',
        ], inscreverCategoria: false);
        $ana = $this->atleta('Ana', 'MANHA');

        $this->assertNull($reuniao->turnoDoTreino());
        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $reuniao), ['id_atleta' => $ana])
            ->assertSessionMissing('avisos_turno');
        $this->tela($reuniao)->assertDontSee('fora do turno')->assertDontSee('Fora do turno');
    }

    // ---------- apoio ----------

    private function treino(string $inicio): EventoCalendario
    {
        return EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'horario_inicio_evento_calendario' => $inicio,
            'status_evento_calendario' => 'ATIVO',
        ], inscreverCategoria: false);
    }

    private function atleta(string $nome, ?string $periodo): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome, 'periodo_escolar_atleta' => $periodo]);
        $this->colocarNaCategoria($id, $this->idSub13M);

        return $id;
    }

    private function tela(EventoCalendario $evento)
    {
        return $this->comoAdmin()->get(route('admin.calendario.eventos.show', $evento))->assertOk();
    }

    protected function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }
}
