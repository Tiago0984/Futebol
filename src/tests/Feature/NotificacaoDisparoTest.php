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
 * Disparos de notificação (Fase 8, Etapa 2): INSCRICAO, REMOCAO e AGENDA (mover inscrições).
 * Só atleta ATIVO recebe; evento concluído, cancelado ou oculto não gera INSCRICAO nem REMOCAO.
 * A AGENDA da geração do mês está em GradeGeracaoMesTest; jogo e elenco, em JogoEventoTest e JogoEscalacaoTest.
 */
class NotificacaoDisparoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ---------- INSCRICAO ----------

    public function test_evento_com_categoria_avisa_os_ativos_com_o_texto_congelado(): void
    {
        $ana = $this->atletaNaCategoria('Ana', 'Sub-13', 'M');
        $bia = $this->atletaNaCategoria('Bia', 'Sub-13', 'M');
        $this->atletaNaCategoria('Duda Inativa', 'Sub-13', 'M', 'INATIVO');

        $this->comoAdminFixo()
            ->post(route('admin.calendario.eventos.store'), $this->dadosEvento([
                'titulo_evento_calendario' => 'Treino Sub-13', 'id_categoria' => $this->idCategoria('Sub-13', 'M'),
                'data_evento_calendario' => '2099-05-05', 'horario_inicio_evento_calendario' => '18:00',
                'horario_fim_evento_calendario' => '19:30', 'local_evento_calendario' => 'Campo A',
            ]))
            ->assertSessionHas('sucesso', 'Evento adicionado ao calendário. 2 atleta(s) da categoria inscrito(s). 2 atleta(s) notificado(s).');

        $evento = EventoCalendario::sole();
        $notificacoes = Notificacao::orderBy('id_atleta')->get();

        $this->assertSame([$ana, $bia], $notificacoes->pluck('id_atleta')->all());
        foreach ($notificacoes as $n) {
            $this->assertSame('INSCRICAO', $n->tipo_notificacao);
            $this->assertSame($evento->id_evento_calendario, $n->id_evento_calendario);
            $this->assertSame($this->admin->id_usuario, $n->id_usuario);
            $this->assertSame('Nova atividade na sua agenda', $n->titulo_notificacao);
            $this->assertSame('Treino Sub-13 · ter, 05/05 · 18:00 às 19:30 · Campo A', $n->mensagem_notificacao);
            $this->assertNull($n->data_leitura_notificacao);
        }

        // Congelado: mudar o evento depois não muda o texto já enviado
        DB::table('tbl_evento_calendario')->update(['local_evento_calendario' => 'Campo B']);
        $this->assertStringEndsWith('Campo A', Notificacao::first()->mensagem_notificacao);
    }

    public function test_texto_sem_horario_e_sem_local(): void
    {
        $evento = $this->criarEvento([
            'data_evento_calendario' => '2099-05-09', 'horario_inicio_evento_calendario' => null,
            'horario_fim_evento_calendario' => null, 'local_evento_calendario' => null,
        ]);

        $this->assertSame('Evento de Teste · sáb, 09/05 · horário a definir', Notificacao::descreverEvento($evento));
    }

    public function test_inscricao_individual_avisa_uma_vez(): void
    {
        $evento = $this->criarEvento();
        $carla  = $this->atletaNaCategoria('Carla', 'Sub-15', 'F');
        $rota   = route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario);

        $this->comoAdminFixo()->post($rota, ['id_atleta' => $carla])
            ->assertSessionHas('sucesso', 'Atleta inscrito. 1 atleta(s) notificado(s).');
        // Já inscrita: nada novo, sem contagem
        $this->comoAdminFixo()->post($rota, ['id_atleta' => $carla])
            ->assertSessionHas('sucesso', 'O atleta já estava inscrito.');

        $this->assertSame(1, Notificacao::where('id_atleta', $carla)->where('tipo_notificacao', 'INSCRICAO')->count());
    }

    public function test_adicionar_todos_de_uma_categoria_avisa_so_quem_entrou(): void
    {
        $evento = $this->criarEvento(['tipo_evento_calendario' => 'AVALIACAO']);
        $ana  = $this->atletaNaCategoria('Ana', 'Sub-13', 'M');
        $this->atletaNaCategoria('Bia', 'Sub-13', 'M');
        $evento->inscrever($ana, 'INDIVIDUAL', null); // já estava: não recebe de novo
        $rota = route('admin.calendario.eventos.inscricoes.categoria', $evento->id_evento_calendario);

        $this->comoAdminFixo()->post($rota, ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHas('sucesso', 'Sub-13 Masculino: 1 atleta(s) inscrito(s). 1 atleta(s) notificado(s).');
        $this->comoAdminFixo()->post($rota, ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHas('sucesso', 'Sub-13 Masculino: 0 atleta(s) inscrito(s) (todos já estavam inscritos ou não há atletas ativos).');

        $this->assertSame(1, Notificacao::where('id_atleta', $ana)->count());
        $this->assertSame(2, Notificacao::count());
    }

    public function test_atualizar_inscritos_pela_categoria_avisa_os_novos(): void
    {
        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $novo   = $this->atletaNaCategoria('Novo', 'Sub-13', 'M');

        $this->comoAdminFixo()->post(route('admin.calendario.eventos.inscricoes.atualizar', $evento->id_evento_calendario))
            ->assertSessionHas('sucesso', '1 atleta(s) da categoria inscrito(s). 1 atleta(s) notificado(s).');

        $this->assertSame(['INSCRICAO'], Notificacao::where('id_atleta', $novo)->pluck('tipo_notificacao')->all());
    }

    // ---------- REMOCAO ----------

    public function test_remover_inscricao_avisa_o_atleta(): void
    {
        $evento = $this->criarEvento();
        $duda   = $this->atletaNaCategoria('Duda', 'Sub-15', 'F');
        $evento->inscrever($duda, 'INDIVIDUAL', null);

        $this->comoAdminFixo()
            ->delete(route('admin.calendario.eventos.inscricoes.destroy', [$evento->id_evento_calendario, $duda]))
            ->assertSessionHas('sucesso', 'Inscrição removida. 1 atleta(s) notificado(s).');

        $remocao = Notificacao::where('tipo_notificacao', 'REMOCAO')->sole();
        $this->assertSame($duda, $remocao->id_atleta);
        $this->assertSame($this->admin->id_usuario, $remocao->id_usuario);
        $this->assertSame('Atividade removida da sua agenda', $remocao->titulo_notificacao);
        $this->assertStringStartsWith('Você não está mais nesta atividade: Evento de Teste · ', $remocao->mensagem_notificacao);
    }

    public function test_troca_de_categoria_do_evento_avisa_quem_entra_e_quem_sai(): void
    {
        $ana       = $this->atletaNaCategoria('Ana Sub13', 'Sub-13', 'M');
        $inativo   = $this->atletaNaCategoria('Ivo Sub13', 'Sub-13', 'M');
        $bia       = $this->atletaNaCategoria('Bia Sub15', 'Sub-15', 'M');
        $convidado = $this->atletaNaCategoria('Caio Convidado', 'Sub-17', 'M');

        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $evento->inscrever($convidado, 'INDIVIDUAL', null);
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);
        Notificacao::query()->delete(); // só interessa o que a edição gera

        $this->comoAdminFixo()
            ->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dadosEdicaoEvento($evento, [
                'id_categoria' => $this->idCategoria('Sub-15', 'M'),
            ]))
            ->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg, '2 removido(s). As inscrições individuais foram mantidas. 2 atleta(s) notificado(s).'));

        // Ana sai (REMOCAO), Bia entra (INSCRICAO); o inativo sai sem aviso; o convidado fica, sem aviso
        $this->assertSame(
            [$ana => 'REMOCAO', $bia => 'INSCRICAO'],
            Notificacao::orderBy('id_atleta')->pluck('tipo_notificacao', 'id_atleta')->all(),
        );
    }

    // ---------- AGENDA: mover inscrições ----------

    public function test_mover_inscricoes_manda_um_resumo_so(): void
    {
        $idSub13 = $this->idCategoria('Sub-13', 'M');
        $idSub15 = $this->idCategoria('Sub-15', 'M');
        $davi    = $this->atletaNaCategoria('Davi', 'Sub-13', 'M', 'ATIVO', 14);

        $sai1 = $this->criarEvento(['titulo_evento_calendario' => 'Treino Sub13 A', 'id_categoria' => $idSub13]);
        $sai2 = $this->criarEvento(['titulo_evento_calendario' => 'Treino Sub13 B', 'id_categoria' => $idSub13]);
        $entra = $this->criarEvento(['titulo_evento_calendario' => 'Treino Sub15', 'id_categoria' => $idSub15]);
        $this->comoAdminFixo()->put(route('admin.atletas.update', $davi), $this->dadosEdicao($davi, ['id_categoria' => $idSub15]));
        Notificacao::query()->delete(); // só interessa o que o "Mover inscrições" gera

        $this->comoAdminFixo()
            ->post(route('admin.atletas.moverInscricoes', $davi), ['de' => $idSub13, 'para' => $idSub15])
            ->assertSessionHas('sucesso', 'Inscrições de Davi movidas: saiu de 2 evento(s) e entrou em 1. 1 atleta(s) notificado(s).');

        $agenda = Notificacao::sole(); // sem INSCRICAO nem REMOCAO por evento
        $this->assertSame('AGENDA', $agenda->tipo_notificacao);
        $this->assertNull($agenda->id_evento_calendario);
        $this->assertSame($this->admin->id_usuario, $agenda->id_usuario);
        $this->assertSame('Sua agenda mudou', $agenda->titulo_notificacao);
        $this->assertSame('Sua categoria agora é Sub-15 Masculino: você saiu de 2 atividades da Sub-13 Masculino e entrou em 1 atividade da Sub-15 Masculino.',
            $agenda->mensagem_notificacao);
        $this->assertSame($idSub13, $agenda->dados_notificacao['de']);
        $this->assertSame($idSub15, $agenda->dados_notificacao['para']);
        $this->assertEqualsCanonicalizing([$sai1->id_evento_calendario, $sai2->id_evento_calendario], $agenda->dados_notificacao['saiu_de']);
        $this->assertSame([$entra->id_evento_calendario], $agenda->dados_notificacao['entrou_em']);
    }

    // ---------- quando não avisa ----------

    public function test_evento_concluido_nao_avisa(): void
    {
        $this->atletaNaCategoria('Ana', 'Sub-13', 'M');

        $this->comoAdminFixo()
            ->post(route('admin.calendario.eventos.store'), $this->dadosEvento([
                'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'data_evento_calendario' => now()->subWeek()->toDateString(),
            ]))
            ->assertSessionHas('sucesso', 'Evento adicionado ao calendário. 1 atleta(s) da categoria inscrito(s). 0 atleta(s) notificado(s).');

        $this->assertSame(1, DB::table('tbl_evento_atleta')->count()); // inscreve, mas não avisa
        $this->assertSame(0, Notificacao::count());
    }

    public function test_evento_de_hoje_que_ja_terminou_nao_avisa(): void
    {
        $this->travelTo(now()->setTime(20, 0));
        $evento = $this->criarEvento([
            'data_evento_calendario' => now()->toDateString(),
            'horario_inicio_evento_calendario' => '08:00', 'horario_fim_evento_calendario' => '09:00',
        ]);

        $evento->inscrever($this->atletaNaCategoria('Ana', 'Sub-13', 'M'), 'INDIVIDUAL', null);

        $this->assertSame(0, Notificacao::count());
    }

    public function test_evento_cancelado_ou_oculto_nao_avisa_inscricao_nem_remocao(): void
    {
        foreach (['CANCELADO', 'INATIVO'] as $status) {
            $evento = $this->criarEvento();
            $evento->update(['status_evento_calendario' => $status]);
            $atleta = $this->atletaNaCategoria("Atleta {$status}", 'Sub-13', 'M');

            $this->comoAdminFixo()
                ->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $atleta])
                ->assertSessionHas('sucesso', 'Atleta inscrito. 0 atleta(s) notificado(s).');
            $this->comoAdminFixo()
                ->delete(route('admin.calendario.eventos.inscricoes.destroy', [$evento->id_evento_calendario, $atleta]))
                ->assertSessionHas('sucesso', 'Inscrição removida. 0 atleta(s) notificado(s).');
        }

        $this->assertSame(0, Notificacao::count());
    }

    public function test_atleta_inativo_nao_recebe(): void
    {
        $evento  = $this->criarEvento();
        $inativo = $this->atletaNaCategoria('Ivo', 'Sub-13', 'M', 'INATIVO');

        $this->assertTrue($evento->inscrever($inativo, 'INDIVIDUAL', null));
        $this->assertTrue($evento->removerInscricao($inativo));

        $this->assertSame(0, $evento->atletasNotificados);
        $this->assertSame(0, Notificacao::count());
    }

    public function test_remover_quem_nao_esta_inscrito_nao_avisa(): void
    {
        $evento = $this->criarEvento();
        $ana    = $this->atletaNaCategoria('Ana', 'Sub-13', 'M');

        $this->comoAdminFixo()
            ->delete(route('admin.calendario.eventos.inscricoes.destroy', [$evento->id_evento_calendario, $ana]))
            ->assertSessionHas('sucesso', 'Inscrição removida.');

        $this->assertSame(0, Notificacao::count());
    }

    // ---------- helpers ----------

    private function comoAdminFixo(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function atletaNaCategoria(string $nome, string $categoria, string $sexo, string $status = 'ATIVO', ?int $idade = null): int
    {
        $idade ??= ['Sub-9' => 9, 'Sub-11' => 11, 'Sub-13' => 13, 'Sub-15' => 15, 'Sub-17' => 17][$categoria];
        $id = $this->criarAtleta($this->nascidoComIdade($idade), $sexo, $status);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria($categoria, $sexo));

        return $id;
    }

    private function criarEvento(array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [...$this->dadosEvento($extra), 'status_evento_calendario' => 'ATIVO']);
    }

    private function dadosEvento(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => 'Evento de Teste',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '10:30',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }

    private function dadosEdicaoEvento(EventoCalendario $alvo, array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => $alvo->titulo_evento_calendario,
            'tipo_evento_calendario'           => $alvo->tipo_evento_calendario,
            'id_categoria'                     => $alvo->id_categoria,
            'data_evento_calendario'           => $alvo->data_evento_calendario->toDateString(),
            'horario_inicio_evento_calendario' => substr((string) $alvo->horario_inicio_evento_calendario, 0, 5),
            'horario_fim_evento_calendario'    => substr((string) $alvo->horario_fim_evento_calendario, 0, 5),
            'local_evento_calendario'          => $alvo->local_evento_calendario,
        ], $extra);
    }
}
