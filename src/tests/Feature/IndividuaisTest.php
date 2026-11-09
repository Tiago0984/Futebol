<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Tela Individuais (Eventos › Individuais, 09/11/2026; IndividuaisController): atividades sem categoria em que o
 * técnico escolhe os atletas (exame, avaliação, peneira, reunião), em Próximos e Já realizados, com os atletas,
 * a leitura do aviso e os filtros de tipo, busca e ocultos.
 */
class IndividuaisTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_proximos_e_realizados_so_com_os_individuais(): void
    {
        $exame    = $this->evento('AVALIACAO', 'Exame cardiológico', now()->addDays(3));
        $reuniao  = $this->evento('REUNIAO', 'Conversa com a família', now()->addDays(1));
        $passado  = $this->evento('AVALIACAO', 'Teste físico de outubro', now()->subDays(5));
        $this->evento('TREINO', 'Treino de terça', now()->addDays(2));                                  // não é individual
        $this->evento('REUNIAO', 'Reunião de pais da Sub-11', now()->addDays(2), [
            'id_categoria' => $this->idCategoria('Sub-11', 'M'),                                          // com categoria: Outros
        ]);

        $proximos = $this->comoAdmin()->get(route('admin.individuais.index'))->assertOk()
            ->assertSee('Novo evento individual')->assertSee('id="modalEventoIndividual"', false)
            ->assertDontSee('id="ind_peneira"', false);   // a chave "É uma peneira" saiu (09/11/2026)
        // Do mais perto ao mais longe; o passado e os que não são individuais ficam de fora
        $this->assertSame([$reuniao, $exame], $proximos->viewData('eventos')->pluck('id_evento_calendario')->all());
        $proximos->assertDontSee('Treino de terça')->assertDontSee('Reunião de pais da Sub-11')->assertDontSee('Teste físico de outubro');

        $realizados = $this->comoAdmin()->get(route('admin.individuais.index', ['aba' => 'realizados']))->assertOk();
        $this->assertSame([$passado], $realizados->viewData('eventos')->pluck('id_evento_calendario')->all());
        $realizados->assertSee('Teste físico de outubro');
    }

    public function test_cartao_mostra_os_atletas_ativos_e_quantos_leram_o_aviso(): void
    {
        $ana = $this->criarAtleta($this->nascidoComIdade(12), 'F', 'ATIVO');
        $rui = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $inativo = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->whereIn('id_atleta', [$ana, $rui, $inativo])->update(['nome_atleta' => DB::raw("CONCAT('Atleta ', id_atleta)")]);

        $id = $this->evento('AVALIACAO', 'Exame de sangue', now()->addDays(4));
        $evento = EventoCalendario::find($id);
        foreach ([$ana, $rui, $inativo] as $idAtleta) {
            $evento->inscrever($idAtleta, 'INDIVIDUAL', null);
        }
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);
        DB::table('tbl_notificacao')->where('id_atleta', $ana)->update(['data_leitura_notificacao' => now()]);

        $this->comoAdmin()->get(route('admin.individuais.index'))->assertOk()
            ->assertSee('2 atletas')
            ->assertSee('<span class="individual-atleta">Atleta ' . $ana . '</span>', false)
            ->assertSee('<span class="individual-atleta">Atleta ' . $rui . '</span>', false)
            ->assertDontSee('<span class="individual-atleta">Atleta ' . $inativo . '</span>', false)
            // Os três foram avisados ao serem inscritos; só a Ana leu
            ->assertSee('1 de 3 leram o aviso');
    }

    public function test_filtros_de_tipo_e_busca_sem_os_ocultos(): void
    {
        $ana = $this->criarAtleta($this->nascidoComIdade(13), 'F', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $ana)->update(['nome_atleta' => 'Ana Beatriz Quintela']);

        $peneira = $this->evento('AVALIACAO', 'Peneira · São Caetano', now()->addDays(5), [
            'subtipo_evento_calendario' => EventoCalendario::SUBTIPO_PENEIRA,
        ]);
        $exame   = $this->evento('AVALIACAO', 'Exame médico', now()->addDays(6));
        $reuniao = $this->evento('REUNIAO', 'Reunião individual', now()->addDays(7));
        $oculto  = $this->evento('EVENTO', 'Entrevista oculta', now()->addDays(8), ['status_evento_calendario' => 'INATIVO']);
        EventoCalendario::find($exame)->inscrever($ana, 'INDIVIDUAL', null, notificar: false);

        $ids = fn (array $filtros) => $this->comoAdmin()->get(route('admin.individuais.index', $filtros))->assertOk()
            ->viewData('eventos')->pluck('id_evento_calendario')->all();

        $this->assertSame([$peneira, $exame, $reuniao], $ids([]));                 // ocultos fora por padrão
        // A peneira já cadastrada entra em Avaliação (a opção "Peneira" do filtro saiu: vale como sem filtro)
        $this->assertSame([$peneira, $exame], $ids(['tipo' => 'AVALIACAO']));
        $this->assertSame([$peneira, $exame, $reuniao], $ids(['tipo' => 'PENEIRA']));
        $this->assertSame([$reuniao], $ids(['tipo' => 'REUNIAO']));
        $this->assertSame([$exame], $ids(['busca' => 'Quintela']));                 // pelo nome do atleta inscrito
        $this->assertSame([$reuniao], $ids(['busca' => 'individual']));             // pelo título
        // O "Mostrar ocultos" saiu (09/11/2026): o oculto nunca aparece nesta tela
        $this->assertSame([$peneira, $exame, $reuniao], $ids(['ocultos' => 1]));
        $this->assertNotContains($oculto, $ids(['busca' => 'Entrevista']));
        $this->assertSame([$peneira, $exame, $reuniao], $ids(['tipo' => 'TREINO'])); // tipo fora da lista: sem filtro
    }

    public function test_cancelar_pelo_cartao_volta_para_a_tela(): void
    {
        $id = $this->evento('REUNIAO', 'Conversa com a família', now()->addDays(2));

        $this->comoAdmin()->from(route('admin.individuais.index'))
            ->patch(route('admin.calendario.eventos.cancelar', $id))
            ->assertRedirect(route('admin.individuais.index'));
        $this->assertSame('CANCELADO', EventoCalendario::find($id)->status_evento_calendario);

        $this->comoAdmin()->get(route('admin.individuais.index'))->assertOk()
            ->assertSee('individual-cartao cancelado', false)->assertSee('Cancelado');
    }

    public function test_tela_do_evento_volta_para_individuais(): void
    {
        $id = $this->evento('AVALIACAO', 'Avaliação física', now()->addDays(2));

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $id))->assertOk()
            ->assertSee('href="' . route('admin.individuais.index') . '"', false);
    }

    // Evento sem inscrição pela categoria, a uma data dada (meio-dia, para "hoje" não virar concluído pela hora)
    private function evento(string $tipo, string $titulo, $data, array $extra = []): int
    {
        return EventoCalendario::criarPor(null, array_merge([
            'titulo_evento_calendario'         => $titulo,
            'tipo_evento_calendario'           => $tipo,
            'data_evento_calendario'           => $data->toDateString(),
            'horario_inicio_evento_calendario' => '12:00:00',
            'local_evento_calendario'          => 'Clínica',
            'status_evento_calendario'         => 'ATIVO',
        ], $extra), inscreverCategoria: false)->id_evento_calendario;
    }
}
