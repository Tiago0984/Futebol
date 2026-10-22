<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase E (dono do projeto, 21/10/2026): peneira = evento individual do tipo Avaliação com o subtipo "Avaliação
 * técnica (peneira)", com a caixa de atletas filtrável por ano de nascimento e sexo. Só os marcados são inscritos
 * e avisados no app; nada no site.
 */
class PeneiraTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_tela_tem_o_botao_e_o_filtro_por_ano_e_sexo(): void
    {
        $id = $this->criarAtleta((now()->year - 14) . '-03-01', 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-15', 'M'));

        $this->comoAdmin()->get(route('admin.calendario.index', ['ramo' => 'individuais']))->assertOk()
            ->assertSee('Nova peneira')->assertSee('data-peneira="1"', false)
            ->assertSee('Marcar só quem cabe')
            ->assertSee('data-ano="' . (now()->year - 14) . '" data-sexo="M"', false)
            ->assertSee('(' . (now()->year - 14) . ')')
            ->assertSee(EventoCalendario::SUBTIPO_PENEIRA);
    }

    public function test_so_os_escolhidos_sao_inscritos_e_avisados_e_nada_vai_ao_site(): void
    {
        $escolhidos = [];
        foreach ([13, 14] as $idade) {
            $escolhidos[] = $id = $this->criarAtleta($this->nascidoComIdade($idade), 'M', 'ATIVO');
            $this->colocarNaCategoria($id, $this->idCategoria('Sub-15', 'M'));
        }
        $deFora = $this->criarAtleta($this->nascidoComIdade(10), 'M', 'ATIVO');
        $this->colocarNaCategoria($deFora, $this->idCategoria('Sub-11', 'M'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin')->post(route('admin.calendario.eventos.individual'), [
            'tipo_evento_calendario'      => 'AVALIACAO',
            'subtipo_evento_calendario'   => EventoCalendario::SUBTIPO_PENEIRA,
            'titulo_evento_calendario'    => 'Peneira · São Caetano',
            'data_evento_calendario'      => now()->addDays(10)->toDateString(),
            'horario_inicio_evento_calendario' => '09:00',
            'local_evento_calendario'     => 'CT do São Caetano',
            'descricao_evento_calendario' => 'Avaliação técnica do São Caetano. Traga chuteira, caneleira e documento.',
            'atletas'                     => $escolhidos,
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso', fn ($m) => str_contains($m, '2 atleta(s) inscrito(s). 2 atleta(s) notificado(s).'));

        $peneira = EventoCalendario::where('titulo_evento_calendario', 'Peneira · São Caetano')->sole();
        $this->assertTrue($peneira->ehPeneira());
        $this->assertNull($peneira->id_categoria);
        $this->assertEqualsCanonicalizing($escolhidos, $peneira->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('tbl_notificacao')->where('id_atleta', $deFora)->count());

        // Selo na lista de Individuais; nada no site (avaliação fica fora da agenda pública)
        $this->actingAs($admin, 'admin')->get(route('admin.calendario.index', ['ramo' => 'individuais', 'mes' => now()->addDays(10)->format('Y-m')]))
            ->assertOk()->assertSee('Peneira · São Caetano')->assertSee('>Peneira</span>', false);
        $this->get('/calendario')->assertOk()->assertDontSee('Peneira · São Caetano');
    }
}
