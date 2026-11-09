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

        // A chave "É uma peneira" saiu do formulário (dono do projeto, 09/11/2026); a marcação por faixa de ano
        // de nascimento e sexo continua na caixa de atletas
        $this->comoAdmin()->get(route('admin.individuais.index'))->assertOk()
            ->assertDontSee('data-peneira="1"', false)
            ->assertDontSee('id="ind_peneira"', false)->assertDontSee('É uma peneira (avaliação técnica de um clube)')
            ->assertDontSee(EventoCalendario::SUBTIPO_PENEIRA)
            ->assertSee('Marcar só quem cabe')
            ->assertSee('data-ano="' . (now()->year - 14) . '" data-sexo="M"', false)
            ->assertSee('(' . (now()->year - 14) . ')');
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

        // O formulário individual não tem mais o Subtipo (09/11/2026): o enviado por fora é ignorado, então o evento
        // não vira peneira; os escolhidos continuam inscritos e avisados
        $peneira = EventoCalendario::where('titulo_evento_calendario', 'Peneira · São Caetano')->sole();
        $this->assertNull($peneira->subtipo_evento_calendario);
        $this->assertFalse($peneira->ehPeneira());
        $this->assertNull($peneira->id_categoria);
        $this->assertEqualsCanonicalizing($escolhidos, $peneira->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('tbl_notificacao')->where('id_atleta', $deFora)->count());

        // Na tela Individuais, sem o selo; nada no site (avaliação fica fora da agenda pública)
        $this->actingAs($admin, 'admin')->get(route('admin.individuais.index'))
            ->assertOk()->assertSee('Peneira · São Caetano')->assertDontSee('<i class="bi bi-binoculars"></i> Peneira</span>', false);
        $this->get('/calendario')->assertOk()->assertDontSee('Peneira · São Caetano');
    }
}
