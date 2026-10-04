<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 6, Etapa 1: jogo = evento JOGO + times, campeonato e placar.
 */
class JogoEventoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private string $dia;
    private int $idSub11M;
    private int $idCampeonato;
    private int $azul;
    private int $verde;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dia          = now()->addDays(10)->toDateString();
        $this->idSub11M     = $this->idCategoria('Sub-11', 'M');
        $this->idCampeonato = $this->campeonato('Copa Escola', $this->idSub11M, 'Quadra A');
        $this->azul         = $this->time('Time Azul', 'INTERNO');
        $this->verde        = $this->time('Time Verde', 'INTERNO');
        $this->visitante    = $this->time('Time Visitante', 'EXTERNO');
    }

    // ---------- criar ----------

    public function test_jogo_de_campeonato_cria_o_evento_com_titulo_categoria_local_e_inscritos(): void
    {
        $idAtleta = $this->atletaSub11();

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())
            ->assertRedirect(route('admin.jogos.index'))
            ->assertSessionHas('sucesso', 'Jogo registrado. 1 atleta(s) da categoria inscrito(s).');

        $jogo   = Jogo::with('evento')->sole();
        $evento = $jogo->evento;

        $this->assertSame('Time Azul x Time Visitante', $evento->titulo_evento_calendario);
        $this->assertSame('JOGO', $evento->tipo_evento_calendario);
        $this->assertSame($this->idSub11M, (int) $evento->id_categoria);
        $this->assertSame('Quadra A', $evento->local_evento_calendario); // vazio = local do campeonato
        $this->assertSame('ATIVO', $evento->status_evento_calendario);
        $this->assertNotNull($evento->id_usuario);                       // responsável = admin logado
        $this->assertTrue($evento->inscricoes()->where('id_atleta', $idAtleta)->exists());

        $this->assertNull($jogo->placar_time_casa_jogos);                // vazio = não jogado
        $this->assertSame("{$this->dia} 19:00:00", $jogo->data_jogo->format('Y-m-d H:i:s')); // vem do evento
    }

    public function test_amistoso_usa_a_categoria_escolhida_e_ignora_categoria_em_jogo_de_campeonato(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo([
            'id_campeonato' => 'AMISTOSO', 'id_categoria' => $idSub13M, 'local_evento_calendario' => 'Campo B',
        ]))->assertSessionHasNoErrors();

        $amistoso = Jogo::with('evento')->sole();
        $this->assertTrue($amistoso->ehAmistoso());
        $this->assertSame($idSub13M, (int) $amistoso->evento->id_categoria);
        $this->assertSame('Campo B', $amistoso->evento->local_evento_calendario);

        // No jogo de campeonato, a categoria enviada não vale: é a do campeonato
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $idSub13M]));
        $this->assertSame($this->idSub11M, (int) Jogo::where('id_campeonato', $this->idCampeonato)->sole()->evento->id_categoria);
    }

    public function test_amistoso_sem_categoria_nao_inscreve_ninguem(): void
    {
        $this->atletaSub11();

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_campeonato' => 'AMISTOSO']))
            ->assertSessionHas('sucesso', 'Jogo registrado.');

        $this->assertSame(0, Jogo::sole()->evento->inscricoes()->count());
    }

    public function test_validacoes_do_jogo(): void
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_campeonato' => '']))
            ->assertSessionHasErrors(['id_campeonato' => 'Escolha o campeonato do jogo, ou "Amistoso".']);

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_time_visitante' => $this->azul]))
            ->assertSessionHasErrors('id_time_visitante');

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['placar_time_casa_jogos' => 2]))
            ->assertSessionHasErrors('placar_time_visitante_jogos');

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_campeonato' => 999999]))
            ->assertSessionHasErrors('id_campeonato');

        $this->assertSame(0, Jogo::count());
        $this->assertSame(0, EventoCalendario::count());
    }

    public function test_criar_jogo_com_conflito_pede_confirmacao(): void
    {
        $idAtleta = $this->atletaSub11();
        $treino = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => '19:30',
            'horario_fim_evento_calendario' => '20:30', 'status_evento_calendario' => 'ATIVO',
        ]);
        $treino->inscrever($idAtleta, 'INDIVIDUAL', null);

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1);
        $this->assertSame(0, Jogo::count());

        $this->comoAdmin()->post(route('admin.jogos.store'), [...$this->dadosJogo(), 'confirmar_conflito' => 1])
            ->assertSessionHas('sucesso');
        $this->assertSame(1, Jogo::count());
    }

    // ---------- editar ----------

    public function test_editar_muda_o_evento_com_historico_e_regenera_o_titulo(): void
    {
        $jogo = $this->criarJogo();

        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo([
            'id_time_casa'                     => $this->verde,
            'horario_inicio_evento_calendario' => '20:00',
            'local_evento_calendario'          => 'Campo C',
            'placar_time_casa_jogos'           => 3,
            'placar_time_visitante_jogos'      => 1,
        ]))->assertSessionHas('sucesso', 'Jogo atualizado.');

        $jogo->refresh();
        $evento = $jogo->evento;
        $this->assertSame('Time Verde x Time Visitante', $evento->titulo_evento_calendario);
        $this->assertSame('20:00:00', $evento->horario_inicio_evento_calendario);
        $this->assertSame(3, $jogo->placar_time_casa_jogos);
        $this->assertSame("{$this->dia} 20:00:00", $jogo->data_jogo->format('Y-m-d H:i:s'));

        $campos = $evento->historico()->pluck('campo_evento_historico')->all();
        $this->assertEqualsCanonicalizing(['titulo_evento_calendario', 'horario_inicio_evento_calendario', 'local_evento_calendario'], $campos);
        $this->assertSame('ALTERADO', $evento->situacao);
    }

    public function test_virar_amistoso_sem_categoria_tira_as_inscricoes_automaticas(): void
    {
        $this->atletaSub11();
        $jogo = $this->criarJogo();
        $this->assertSame(1, $jogo->evento->inscricoes()->count());

        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo(['id_campeonato' => 'AMISTOSO']))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '0 atleta(s) inscrito(s), 1 removido(s)'));

        $this->assertNull($jogo->refresh()->id_campeonato);
        $this->assertSame(0, $jogo->evento->inscricoes()->count());
    }

    public function test_editar_a_data_pelo_calendario_muda_a_data_do_jogo(): void
    {
        $jogo = $this->criarJogo();
        $outroDia = now()->addDays(20)->toDateString();

        $jogo->evento->atualizarComHistorico(['data_evento_calendario' => $outroDia, 'horario_inicio_evento_calendario' => '08:15'], null);

        $this->assertSame("{$outroDia} 08:15:00", $jogo->fresh('evento')->data_jogo->format('Y-m-d H:i:s'));
    }

    // ---------- lista, status e exclusão ----------

    public function test_lista_mostra_amistoso_categoria_e_situacao_do_evento(): void
    {
        $jogo = $this->criarJogo();
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_campeonato' => 'AMISTOSO', 'id_time_casa' => $this->verde]));

        $this->comoAdmin()->patch(route('admin.calendario.eventos.cancelar', $jogo->id_evento))
            ->assertSessionHas('sucesso', 'Evento cancelado.');

        $this->comoAdmin()->get(route('admin.jogos.index'))
            ->assertOk()
            ->assertSee('Amistoso</span>', false)
            ->assertSee('Sub-11 Masculino')
            ->assertSee('Cancelado</span>', false)
            ->assertSee(route('admin.calendario.eventos.show', $jogo->id_evento));
    }

    public function test_lista_mostra_a_contagem_de_inscritos_ativos_como_no_calendario(): void
    {
        $this->atletaSub11();
        $this->atletaSub11();
        $inativo = $this->atletaSub11();
        $jogo = $this->criarJogo();
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);

        $botao = 'title="Inscritos (2)">';
        $this->comoAdmin()->get(route('admin.jogos.index'))->assertOk()
            ->assertSee($botao, false)
            ->assertSee('<small class="ms-1">2</small>', false);

        // Mesma contagem do Calendário (o inativo continua inscrito, mas não conta)
        $this->comoAdmin()->get(route('admin.calendario.index'))->assertOk()->assertSee($botao, false);
        $this->assertSame(3, $jogo->evento->inscricoes()->count());
    }

    public function test_jogo_nao_tem_mais_exclusao_nem_status_proprio(): void
    {
        $this->assertFalse(Route::has('admin.jogos.destroy'));
        $this->assertFalse(Route::has('admin.jogos.toggleStatus'));
        $this->assertFalse(Route::has('admin.jogos.create'));
        $this->assertFalse(Route::has('admin.jogos.edit'));
    }

    // ---------- estrutura (Etapa 2) ----------
    // O teste da migration da Etapa 1 (evento dos jogos existentes) saiu: ele gravava data_jogo e
    // status_jogo, colunas que a Etapa 2 removeu. Ela foi ensaiada com o backup e aplicada (batch 19).

    public function test_jogo_sem_data_e_status_proprios_e_sempre_com_evento(): void
    {
        $this->assertFalse(Schema::hasColumn('tbl_jogos', 'data_jogo'));
        $this->assertFalse(Schema::hasColumn('tbl_jogos', 'status_jogo'));

        $idEvento = DB::selectOne("SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_jogos' AND COLUMN_NAME = 'id_evento'");
        $this->assertSame('NO', $idEvento->IS_NULLABLE);
    }

    // ---------- ajudantes ----------

    private function dadosJogo(array $extra = []): array
    {
        return array_merge([
            'id_campeonato'                    => $this->idCampeonato,
            'id_time_casa'                     => $this->azul,
            'id_time_visitante'                => $this->visitante,
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '19:00',
            'horario_fim_evento_calendario'    => '',
            'local_evento_calendario'          => '',
            'placar_time_casa_jogos'           => '',
            'placar_time_visitante_jogos'      => '',
        ], $extra);
    }

    private function criarJogo(array $extra = []): Jogo
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo($extra))->assertSessionHasNoErrors();

        return Jogo::with('evento')->latest('id_jogo')->first();
    }

    private function atletaSub11(): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idSub11M);

        return $id;
    }

    private function campeonato(string $nome, int $idCategoria, string $local): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria'           => $idCategoria,
            'logo_evento'            => 'logo.png',
            'banner_evento'          => 'banner.png',
            'nome_campeonato'        => $nome,
            'organizador_campeonato' => 'Escola',
            'tipo_campeonato'        => 'MATA-MATA',
            'data_inicio_campeonato' => now()->toDateString(),
            'data_fim_campeonato'    => now()->addMonth()->toDateString(),
            'local_evento'           => $local,
        ]);
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M,
            'logo_time'    => 'time.png',
            'nome_time'    => $nome,
            'tipo_time'    => $tipo,
        ]);
    }
}
