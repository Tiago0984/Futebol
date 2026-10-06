<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Disparos de notificação (Fase 8, Etapa 3): ALTERACAO (data, horário ou local), CANCELAMENTO e REATIVACAO
 * (cancelar/reativar e ocultar/mostrar). Só inscrito ATIVO recebe; evento concluído não avisa.
 * A edição pela tela de Jogos está em JogoEventoTest.
 */
class NotificacaoAlteracaoStatusTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ---------- ALTERACAO ----------

    public function test_mudar_data_e_local_avisa_os_inscritos_ativos_listando_o_que_mudou(): void
    {
        [$evento, $ana, $bia] = $this->eventoComInscritos();

        $this->editar($evento, ['data_evento_calendario' => '2099-05-06', 'local_evento_calendario' => 'Campo B'])
            ->assertSessionHas('sucesso', 'Evento atualizado. 2 atleta(s) notificado(s).');

        $notificacoes = Notificacao::orderBy('id_atleta')->get();
        $this->assertSame([$ana, $bia], $notificacoes->pluck('id_atleta')->all()); // o inativo não recebe
        $alteracao = $notificacoes->first();
        $this->assertSame('ALTERACAO', $alteracao->tipo_notificacao);
        $this->assertSame($evento->id_evento_calendario, $alteracao->id_evento_calendario);
        $this->assertSame($this->admin->id_usuario, $alteracao->id_usuario);
        $this->assertSame('Atividade alterada', $alteracao->titulo_notificacao);
        $this->assertSame('Treino · qua, 06/05 · 09:00 às 10:30 · Campo B. Mudanças: data 05/05 → 06/05; local Campo A → Campo B.',
            $alteracao->mensagem_notificacao);
        $this->assertSame([
            'data_evento_calendario'  => ['2099-05-05', '2099-05-06'],
            'local_evento_calendario' => ['Campo A', 'Campo B'],
        ], $alteracao->dados_notificacao['campos']);
    }

    public function test_mudar_horario_lista_inicio_e_fim(): void
    {
        [$evento] = $this->eventoComInscritos();

        $this->editar($evento, ['horario_inicio_evento_calendario' => '10:00', 'horario_fim_evento_calendario' => '']);

        $this->assertStringEndsWith('Mudanças: horário de início 09:00 → 10:00; horário de fim 10:30 → a definir.',
            Notificacao::first()->mensagem_notificacao);
    }

    public function test_mudar_titulo_tipo_ou_descricao_nao_avisa(): void
    {
        [$evento] = $this->eventoComInscritos();

        $this->editar($evento, [
            'titulo_evento_calendario' => 'Outro título', 'tipo_evento_calendario' => 'AVALIACAO',
            'descricao_evento_calendario' => 'Trazer chuteira',
        ])->assertSessionHas('sucesso', 'Evento atualizado.');

        $this->assertSame(0, Notificacao::count());
    }

    public function test_mudar_categoria_e_data_juntas_cada_um_recebe_uma_so(): void
    {
        $ana       = $this->atleta('Ana Sub13', 'Sub-13');
        $bia       = $this->atleta('Bia Sub15', 'Sub-15');
        $convidado = $this->atleta('Caio Convidado', 'Sub-17');
        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $evento->inscrever($convidado, 'INDIVIDUAL', null);
        Notificacao::query()->delete();

        $this->editar($evento, ['id_categoria' => $this->idCategoria('Sub-15', 'M'), 'data_evento_calendario' => '2099-05-06'])
            ->assertSessionHas('sucesso', 'Evento atualizado. Inscrições pela categoria: 1 atleta(s) inscrito(s), 1 removido(s).'
                . ' As inscrições individuais foram mantidas. 3 atleta(s) notificado(s).');

        // Quem sai: só REMOCAO; quem entra: só INSCRICAO (com a data nova); quem fica: só ALTERACAO
        $this->assertSame(
            [$ana => 'REMOCAO', $bia => 'INSCRICAO', $convidado => 'ALTERACAO'],
            Notificacao::orderBy('id_atleta')->pluck('tipo_notificacao', 'id_atleta')->all(),
        );
        $this->assertStringContainsString('qua, 06/05', Notificacao::where('id_atleta', $bia)->value('mensagem_notificacao'));
    }

    public function test_editar_evento_cancelado_oculto_ou_que_fica_no_passado_nao_avisa(): void
    {
        foreach (['CANCELADO', 'INATIVO'] as $status) {
            [$evento] = $this->eventoComInscritos();
            DB::table('tbl_evento_calendario')->where('id_evento_calendario', $evento->id_evento_calendario)
                ->update(['status_evento_calendario' => $status]);

            $this->editar($evento->fresh(), ['local_evento_calendario' => 'Campo B'])
                ->assertSessionHas('sucesso', 'Evento atualizado. 0 atleta(s) notificado(s).');
        }

        // Ativo, mas a data nova já passou: concluído não avisa
        [$evento] = $this->eventoComInscritos();
        $this->editar($evento, ['data_evento_calendario' => now()->subWeek()->toDateString()])
            ->assertSessionHas('sucesso', 'Evento atualizado. 0 atleta(s) notificado(s).');

        $this->assertSame(0, Notificacao::count());
    }

    // ---------- CANCELAMENTO e REATIVACAO ----------

    public function test_cancelar_e_reativar_avisam_os_inscritos_ativos(): void
    {
        [$evento, $ana, $bia] = $this->eventoComInscritos();
        $rota = route('admin.calendario.eventos.cancelar', $evento->id_evento_calendario);

        $this->comoAdminFixo()->patch($rota)->assertSessionHas('sucesso', 'Evento cancelado. 2 atleta(s) notificado(s).');
        $cancelamento = Notificacao::where('id_atleta', $ana)->sole();
        $this->assertSame('CANCELAMENTO', $cancelamento->tipo_notificacao);
        $this->assertSame('Atividade cancelada', $cancelamento->titulo_notificacao);
        $this->assertSame('Esta atividade foi cancelada: Treino · ter, 05/05 · 09:00 às 10:30 · Campo A', $cancelamento->mensagem_notificacao);
        $this->assertSame($this->admin->id_usuario, $cancelamento->id_usuario);

        $this->comoAdminFixo()->patch($rota)->assertSessionHas('sucesso', 'Evento reativado. 2 atleta(s) notificado(s).');
        $reativacao = Notificacao::where('id_atleta', $bia)->where('tipo_notificacao', 'REATIVACAO')->sole();
        $this->assertSame('Atividade confirmada de novo', $reativacao->titulo_notificacao);
        $this->assertSame('Esta atividade voltou para a sua agenda: Treino · ter, 05/05 · 09:00 às 10:30 · Campo A', $reativacao->mensagem_notificacao);

        $this->assertSame(['CANCELAMENTO' => 2, 'REATIVACAO' => 2], $this->contarPorTipo());
    }

    public function test_ocultar_evento_ativo_avisa_como_cancelamento_e_mostrar_como_reativacao(): void
    {
        [$evento] = $this->eventoComInscritos();
        $rota = route('admin.calendario.eventos.ocultar', $evento->id_evento_calendario);

        $this->comoAdminFixo()->patch($rota)->assertSessionHas('sucesso', 'Evento ocultado. 2 atleta(s) notificado(s).');
        $this->assertSame(['CANCELAMENTO' => 2], $this->contarPorTipo());

        $this->comoAdminFixo()->patch($rota)->assertSessionHas('sucesso', 'Evento visível de novo. 2 atleta(s) notificado(s).');
        $this->assertSame(['CANCELAMENTO' => 2, 'REATIVACAO' => 2], $this->contarPorTipo());
    }

    public function test_ocultar_e_mostrar_evento_cancelado_nao_avisa(): void
    {
        [$evento] = $this->eventoComInscritos();
        $this->comoAdminFixo()->patch(route('admin.calendario.eventos.cancelar', $evento->id_evento_calendario));
        Notificacao::query()->delete(); // só interessa o que vem depois do cancelamento
        $rota = route('admin.calendario.eventos.ocultar', $evento->id_evento_calendario);

        $this->comoAdminFixo()->patch($rota)->assertSessionHas('sucesso', 'Evento ocultado. 0 atleta(s) notificado(s).');
        $this->comoAdminFixo()->patch($rota)
            ->assertSessionHas('sucesso', 'Evento visível de novo (continua cancelado). 0 atleta(s) notificado(s).');

        $this->assertSame(0, Notificacao::count());
    }

    public function test_cancelar_evento_concluido_nao_avisa(): void
    {
        [$evento] = $this->eventoComInscritos();
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $evento->id_evento_calendario)
            ->update(['data_evento_calendario' => now()->subWeek()->toDateString()]);

        $this->comoAdminFixo()->patch(route('admin.calendario.eventos.cancelar', $evento->id_evento_calendario))
            ->assertSessionHas('sucesso', 'Evento cancelado. 0 atleta(s) notificado(s).');

        $this->assertSame(0, Notificacao::count());
    }

    // ---------- helpers ----------

    private function comoAdminFixo(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /**
     * Treino em 05/05/2099 (ter), 09:00 às 10:30, Campo A, com Ana e Bia (ATIVO) e Ivo (inativo depois de
     * inscrito) inscritos. As notificações da inscrição são apagadas: os testes olham só a ação seguinte.
     */
    private function eventoComInscritos(): array
    {
        $evento = $this->criarEvento();
        $ana = $this->atleta('Ana', 'Sub-13');
        $bia = $this->atleta('Bia', 'Sub-13');
        $ivo = $this->atleta('Ivo', 'Sub-13');
        foreach ([$ana, $bia, $ivo] as $id) {
            $evento->inscrever($id, 'INDIVIDUAL', null);
        }
        DB::table('tbl_atletas')->where('id_atleta', $ivo)->update(['status_atleta' => 'INATIVO']);
        Notificacao::query()->delete();

        return [$evento, $ana, $bia];
    }

    private function atleta(string $nome, string $categoria): int
    {
        $idade = ['Sub-13' => 13, 'Sub-15' => 15, 'Sub-17' => 17][$categoria];
        $id = $this->criarAtleta($this->nascidoComIdade($idade), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria($categoria, 'M'));

        return $id;
    }

    private function criarEvento(array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor(null, array_merge([
            'titulo_evento_calendario'         => 'Treino',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => '2099-05-05',
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '10:30',
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => 'ATIVO',
        ], $extra));
    }

    // Formulário de edição do evento, com os valores atuais e as mudanças de $extra
    private function editar(EventoCalendario $evento, array $extra)
    {
        return $this->comoAdminFixo()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), array_merge([
            'titulo_evento_calendario'         => $evento->titulo_evento_calendario,
            'tipo_evento_calendario'           => $evento->tipo_evento_calendario,
            'id_categoria'                     => $evento->id_categoria,
            'data_evento_calendario'           => $evento->data_evento_calendario->toDateString(),
            'horario_inicio_evento_calendario' => substr((string) $evento->horario_inicio_evento_calendario, 0, 5),
            'horario_fim_evento_calendario'    => substr((string) $evento->horario_fim_evento_calendario, 0, 5),
            'local_evento_calendario'          => $evento->local_evento_calendario,
        ], $extra));
    }

    private function contarPorTipo(): array
    {
        return Notificacao::selectRaw('tipo_notificacao, COUNT(*) n')->groupBy('tipo_notificacao')->orderBy('tipo_notificacao')
            ->pluck('n', 'tipo_notificacao')->map(fn ($n) => (int) $n)->all();
    }
}
