<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Alerta de conflito de horário (Fase 5, Etapa 3): mesmo dia e horários sobrepostos, só eventos ATIVO
 * e atletas ATIVO; sem fim, duração padrão por tipo; sem início, aviso fraco. Alerta com confirmação.
 */
class EventoConflitoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private string $dia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dia = now()->addWeek()->toDateString();
    }

    // ---------- intervalo e duração padrão ----------

    public function test_intervalo_usa_o_fim_ou_a_duracao_padrao_do_tipo(): void
    {
        $fim = fn (array $dados) => $this->eventoNaoSalvo($dados)->intervalo()[1]->format('H:i');

        $this->assertSame('10:00', $fim(['horario_fim_evento_calendario' => '10:00']));
        $this->assertSame('11:00', $fim(['tipo_evento_calendario' => 'JOGO']));
        $this->assertSame('10:30', $fim(['tipo_evento_calendario' => 'TREINO']));
        $this->assertSame('10:00', $fim(['tipo_evento_calendario' => 'AVALIACAO']));
        $this->assertSame('23:59', $fim(['tipo_evento_calendario' => 'CAMPEONATO']));
        $this->assertSame('11:00', $fim(['tipo_evento_calendario' => 'REUNIAO']));
        $this->assertNull($this->eventoNaoSalvo(['horario_inicio_evento_calendario' => null])->intervalo());
    }

    // ---------- quando há conflito ----------

    public function test_sobreposicao_no_mesmo_dia_e_conflito(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo 10h', 'horario_inicio_evento_calendario' => '10:00', 'horario_fim_evento_calendario' => '12:00']);

        $conflitos = $this->eventoNaoSalvo(['horario_inicio_evento_calendario' => '09:00', 'horario_fim_evento_calendario' => '10:30'])->conflitosPara([$atleta]);

        $this->assertCount(1, $conflitos);
        $this->assertFalse($conflitos[0]['fraco']);
        $this->assertSame('Ana: horário sobrepõe "Jogo 10h" (TREINO, ' . now()->addWeek()->format('d/m') . ', 10:00 às 12:00).',
            EventoCalendario::descreverConflito($conflitos[0]));
    }

    public function test_nao_e_conflito_encostar_outro_dia_cancelado_oculto_ou_atleta_inativo(): void
    {
        $ana = $this->atleta('Ana');
        $this->eventoComAtleta($ana, ['horario_inicio_evento_calendario' => '10:30', 'horario_fim_evento_calendario' => '12:00']); // encosta
        $this->eventoComAtleta($ana, ['data_evento_calendario' => now()->addWeeks(2)->toDateString()]);                            // outro dia
        $this->eventoComAtleta($ana, ['status_evento_calendario' => 'CANCELADO']);
        $this->eventoComAtleta($ana, ['status_evento_calendario' => 'INATIVO']);

        $bia = $this->atleta('Bia');
        $this->eventoComAtleta($bia);
        DB::table('tbl_atletas')->where('id_atleta', $bia)->update(['status_atleta' => 'INATIVO']);

        $novo = $this->eventoNaoSalvo(['horario_inicio_evento_calendario' => '09:00', 'horario_fim_evento_calendario' => '10:30']);

        $this->assertCount(0, $novo->conflitosPara([$ana, $bia]));
    }

    public function test_evento_sem_inicio_gera_aviso_fraco(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo a definir', 'tipo_evento_calendario' => 'JOGO',
            'horario_inicio_evento_calendario' => null, 'horario_fim_evento_calendario' => null]);

        $conflitos = $this->eventoNaoSalvo()->conflitosPara([$atleta]);

        $this->assertCount(1, $conflitos);
        $this->assertTrue($conflitos[0]['fraco']);
        $this->assertSame('Ana: também está em "Jogo a definir" no mesmo dia (JOGO, ' . now()->addWeek()->format('d/m')
            . ', A definir); horário a definir, confira.', EventoCalendario::descreverConflito($conflitos[0]));
    }

    public function test_eventos_com_o_mesmo_titulo_se_distinguem_pelo_tipo_no_aviso(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Sub-13', 'tipo_evento_calendario' => 'JOGO']);
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Sub-13', 'tipo_evento_calendario' => 'TREINO']);

        $linhas = collect($this->eventoNaoSalvo()->conflitosPara([$atleta]))
            ->map(fn ($c) => EventoCalendario::descreverConflito($c));

        $this->assertCount(2, $linhas);
        $this->assertCount(2, $linhas->unique());
        $this->assertTrue($linhas->contains(fn ($l) => str_contains($l, '(JOGO, ')));
        $this->assertTrue($linhas->contains(fn ($l) => str_contains($l, '(TREINO, ')));
    }

    public function test_evento_cancelado_nao_gera_conflito(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta);

        $this->assertCount(0, $this->eventoNaoSalvo(['status_evento_calendario' => 'CANCELADO'])->conflitosPara([$atleta]));
    }

    // ---------- confirmação nos pontos de inscrição ----------

    public function test_inscricao_individual_com_conflito_pede_confirmacao(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta);
        $evento = $this->criarEvento(['titulo_evento_calendario' => 'Outro Treino']);
        $rota   = route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario);
        $tela   = route('admin.calendario.eventos.show', $evento->id_evento_calendario);

        // Sem confirmar: volta com o alerta e não inscreve
        $this->comoAdmin()->from($tela)->post($rota, ['id_atleta' => $atleta])
            ->assertRedirect($tela)
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1 && count($p['fracos']) === 0 && $p['metodo'] === 'POST');
        $this->assertFalse($this->inscrito($evento, $atleta));

        // A tela mostra o alerta com o botão que reenvia os dados
        $this->comoAdmin()->from($tela)->followingRedirects()->post($rota, ['id_atleta' => $atleta])
            ->assertSee('Conflito de horário')
            ->assertSee('Confirmar mesmo assim')
            ->assertSee('<input type="hidden" name="id_atleta" value="' . $atleta . '">', false)
            ->assertSee('<input type="hidden" name="confirmar_conflito" value="1">', false);

        // Confirmando: inscreve
        $this->comoAdmin()->post($rota, ['id_atleta' => $atleta, 'confirmar_conflito' => '1'])
            ->assertSessionHas('sucesso', 'Atleta inscrito. 1 atleta(s) notificado(s).');
        $this->assertTrue($this->inscrito($evento, $atleta));
    }

    public function test_so_aviso_de_mesmo_dia_nao_bloqueia_e_mostra_aviso_informativo(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo a definir', 'horario_inicio_evento_calendario' => null, 'horario_fim_evento_calendario' => null]);
        $evento = $this->criarEvento(['titulo_evento_calendario' => 'Treino Manhã']);
        $rota   = route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario);
        $tela   = route('admin.calendario.eventos.show', $evento->id_evento_calendario);

        // Salva direto, sem pedir confirmação
        $this->comoAdmin()->from($tela)->post($rota, ['id_atleta' => $atleta])
            ->assertSessionMissing('conflitos_pendentes')
            ->assertSessionHas('sucesso', 'Atleta inscrito. 1 atleta(s) notificado(s).')
            ->assertSessionHas('avisos_mesmo_dia', fn ($avisos) => count($avisos) === 1 && str_contains($avisos[0], 'Jogo a definir'));
        $this->assertTrue($this->inscrito($evento, $atleta));

        // A próxima tela mostra o aviso azul, sem o botão de confirmar (à tarde: não sobrepõe o Treino Manhã)
        $outro = $this->criarEvento(['titulo_evento_calendario' => 'Treino Extra', 'horario_inicio_evento_calendario' => '15:00', 'horario_fim_evento_calendario' => '16:00']);
        $telaOutro = route('admin.calendario.eventos.show', $outro->id_evento_calendario);
        $this->comoAdmin()->from($telaOutro)->followingRedirects()
            ->post(route('admin.calendario.eventos.inscricoes.store', $outro->id_evento_calendario), ['id_atleta' => $atleta])
            ->assertSee('alert alert-info', false)
            ->assertSee('Mesmo dia — confira o horário')
            ->assertDontSee('Confirmar mesmo assim');
    }

    public function test_conflito_real_e_aviso_de_mesmo_dia_juntos_pedem_confirmacao_e_aparecem_separados(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo Sobreposto']); // 09:00 às 10:30
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Reuniao Sem Hora', 'horario_inicio_evento_calendario' => null, 'horario_fim_evento_calendario' => null]);
        $evento = $this->criarEvento(['titulo_evento_calendario' => 'Treino Novo', 'horario_inicio_evento_calendario' => '10:00', 'horario_fim_evento_calendario' => '11:00']);
        $rota   = route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario);
        $tela   = route('admin.calendario.eventos.show', $evento->id_evento_calendario);

        $this->comoAdmin()->from($tela)->post($rota, ['id_atleta' => $atleta])
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1 && str_contains($p['fortes'][0], 'Jogo Sobreposto')
                && count($p['fracos']) === 1 && str_contains($p['fracos'][0], 'Reuniao Sem Hora'));
        $this->assertFalse($this->inscrito($evento, $atleta));

        $this->comoAdmin()->from($tela)->followingRedirects()->post($rota, ['id_atleta' => $atleta])
            ->assertSeeInOrder(['Conflito de horário', 'Jogo Sobreposto', 'Mesmo dia — confira o horário', 'Reuniao Sem Hora', 'Confirmar mesmo assim']);
    }

    public function test_reativar_com_so_aviso_de_mesmo_dia_nao_fala_em_conflito(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo a definir', 'horario_inicio_evento_calendario' => null, 'horario_fim_evento_calendario' => null]);
        $cancelado = $this->eventoComAtleta($atleta, ['status_evento_calendario' => 'CANCELADO']);

        $this->comoAdmin()
            ->patch(route('admin.calendario.eventos.cancelar', $cancelado->id_evento_calendario))
            ->assertSessionHas('sucesso', 'Evento reativado.')
            ->assertSessionHas('avisos_mesmo_dia');
    }

    public function test_inscricao_sem_conflito_nao_pede_confirmacao(): void
    {
        $atleta = $this->atleta('Ana');
        $evento = $this->criarEvento();

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $atleta])
            ->assertSessionMissing('conflitos_pendentes')
            ->assertSessionHas('sucesso', 'Atleta inscrito. 1 atleta(s) notificado(s).');
    }

    public function test_criar_evento_com_categoria_em_conflito_pede_confirmacao(): void
    {
        $idSub13 = $this->idCategoria('Sub-13', 'M');
        $atleta  = $this->atleta('Ana');
        $this->eventoComAtleta($atleta);
        $dados = [
            'titulo_evento_calendario' => 'Treino Novo', 'tipo_evento_calendario' => 'TREINO', 'id_categoria' => $idSub13,
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => '09:30', 'horario_fim_evento_calendario' => '11:00',
        ];

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $dados)->assertSessionHas('conflitos_pendentes');
        $this->assertDatabaseMissing('tbl_evento_calendario', ['titulo_evento_calendario' => 'Treino Novo']);

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), [...$dados, 'confirmar_conflito' => '1'])
            ->assertSessionHas('sucesso', 'Evento adicionado ao calendário. 1 atleta(s) da categoria inscrito(s). 1 atleta(s) notificado(s).');
    }

    public function test_editar_horario_com_conflito_pede_confirmacao_e_editar_titulo_nao(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta); // 09:00 às 10:30
        $tarde = $this->criarEvento(['titulo_evento_calendario' => 'Treino Tarde', 'horario_inicio_evento_calendario' => '15:00', 'horario_fim_evento_calendario' => '16:00']);
        $tarde->inscrever($atleta, 'INDIVIDUAL', null);
        $rota = route('admin.calendario.eventos.update', $tarde->id_evento_calendario);

        // Título não mexe em horário: sem alerta
        $this->comoAdmin()->put($rota, $this->dadosEdicaoEvento($tarde, ['titulo_evento_calendario' => 'Treino da Tarde']))
            ->assertSessionMissing('conflitos_pendentes');

        // Passar para 10:00: sobrepõe o das 09:00 às 10:30
        $this->comoAdmin()->put($rota, $this->dadosEdicaoEvento($tarde->fresh(), ['horario_inicio_evento_calendario' => '10:00', 'horario_fim_evento_calendario' => '11:00']))
            ->assertSessionHas('conflitos_pendentes', fn ($p) => $p['metodo'] === 'PUT');
        $this->assertSame('15:00:00', $tarde->fresh()->horario_inicio_evento_calendario);

        $this->comoAdmin()->put($rota, [...$this->dadosEdicaoEvento($tarde->fresh(), ['horario_inicio_evento_calendario' => '10:00', 'horario_fim_evento_calendario' => '11:00']), 'confirmar_conflito' => '1'])
            ->assertSessionHas('sucesso');
        $this->assertSame('10:00:00', $tarde->fresh()->horario_inicio_evento_calendario);
    }

    public function test_adicionar_todos_de_uma_categoria_com_conflito_pede_confirmacao(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta);
        $evento = $this->criarEvento(['tipo_evento_calendario' => 'AVALIACAO']);

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.categoria', $evento->id_evento_calendario), ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHas('conflitos_pendentes');
        $this->assertFalse($this->inscrito($evento, $atleta));
    }

    public function test_reativar_evento_avisa_conflito_sem_bloquear(): void
    {
        $atleta = $this->atleta('Ana');
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo Ativo']);
        $cancelado = $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Treino Cancelado', 'status_evento_calendario' => 'CANCELADO']);

        $this->comoAdmin()
            ->patch(route('admin.calendario.eventos.cancelar', $cancelado->id_evento_calendario))
            ->assertSessionHas('sucesso', fn ($msg) => str_starts_with($msg, 'Evento reativado. Atenção, conflito de horário:')
                && str_contains($msg, 'Jogo Ativo'));

        $this->assertSame('ATIVO', $cancelado->fresh()->status_evento_calendario);
    }

    public function test_aviso_de_mover_inscricoes_lista_os_conflitos(): void
    {
        // 14 anos, ainda na Sub-13 M; inscrito num jogo às 09:00
        $atleta = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $atleta)->update(['nome_atleta' => 'Davi']);
        $this->colocarNaCategoria($atleta, $this->idCategoria('Sub-13', 'M'));
        $this->eventoComAtleta($atleta, ['titulo_evento_calendario' => 'Jogo da Manhã']);

        // Treino da Sub-15 no mesmo horário: entrar nele criaria conflito
        $idSub15 = $this->idCategoria('Sub-15', 'M');
        $this->criarEvento(['titulo_evento_calendario' => 'Treino Sub15', 'id_categoria' => $idSub15]);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $atleta), $this->dadosEdicao($atleta, ['id_categoria' => $idSub15]))
            ->assertSessionHas('mover_inscricoes', fn ($aviso) => count($aviso['conflitos']) === 1
                && str_contains($aviso['conflitos'][0], 'Treino Sub15')
                && str_contains($aviso['conflitos'][0], 'Jogo da Manhã'));
    }

    // ---------- helpers ----------

    // Atleta ativo na Sub-13 M
    private function atleta(string $nome): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));

        return $id;
    }

    private function dadosEvento(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => 'Treino Manhã',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '10:30',
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => 'ATIVO',
        ], $extra);
    }

    private function criarEvento(array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor(null, $this->dadosEvento($extra));
    }

    private function eventoComAtleta(int $idAtleta, array $extra = []): EventoCalendario
    {
        $evento = $this->criarEvento($extra);
        $evento->inscrever($idAtleta, 'INDIVIDUAL', null);

        return $evento;
    }

    // Evento ainda não salvo, para simular (sem fim: duração padrão do tipo)
    private function eventoNaoSalvo(array $extra = []): EventoCalendario
    {
        return new EventoCalendario($this->dadosEvento(['horario_fim_evento_calendario' => null, ...$extra]));
    }

    private function dadosEdicaoEvento(EventoCalendario $evento, array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => $evento->titulo_evento_calendario,
            'tipo_evento_calendario'           => $evento->tipo_evento_calendario,
            'id_categoria'                     => $evento->id_categoria,
            'data_evento_calendario'           => $evento->data_evento_calendario->toDateString(),
            'horario_inicio_evento_calendario' => substr((string) $evento->horario_inicio_evento_calendario, 0, 5),
            'horario_fim_evento_calendario'    => substr((string) $evento->horario_fim_evento_calendario, 0, 5),
            'local_evento_calendario'          => $evento->local_evento_calendario,
        ], $extra);
    }

    private function inscrito(EventoCalendario $evento, int $idAtleta): bool
    {
        return DB::table('tbl_evento_atleta')
            ->where('id_evento_calendario', $evento->id_evento_calendario)
            ->where('id_atleta', $idAtleta)
            ->exists();
    }
}
