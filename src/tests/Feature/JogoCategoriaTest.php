<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase H, Etapa H3: o jogo de campeonato tem a categoria dele (a do evento, uma das categorias do campeonato).
 * Ela vale para os times oferecidos (participantes da categoria), a convocação e a idade. Jogo sem time da AACJ
 * nasce publicado. Mudar a categoria de um jogo não refaz as inscrições (decisão do dono do projeto, 15/10/2026),
 * mas exige os dois times na categoria nova (30/10/2026: times de categorias diferentes nunca se enfrentam).
 */
class JogoCategoriaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private string $dia;
    private int $idSub15M;
    private int $idSub17M;
    private int $copa;
    private int $aacj15;
    private int $aacj17;
    private int $botafogo;
    private int $santos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->dia      = now()->addDays(10)->toDateString();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->idSub17M = $this->idCategoria('Sub-17', 'M');
        $this->copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub17M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Copa São Carlos', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Arena Juscelino',
        ]);
        $this->aacj15   = $this->time('AACJ Sub-15', 'INTERNO', $this->idSub15M);
        $this->aacj17   = $this->time('AACJ Sub-17', 'INTERNO', $this->idSub17M);
        $this->botafogo = $this->time('Botafogo', 'EXTERNO', $this->idSub15M);
        $this->santos   = $this->time('Santos', 'EXTERNO', $this->idSub15M);
        $this->participantes($this->copa, [$this->aacj15, $this->botafogo, $this->santos], $this->idSub15M);
        $this->participantes($this->copa, [$this->aacj17, $this->botafogo], $this->idSub17M);
    }

    public function test_jogo_inscreve_os_convocados_da_categoria_dele_com_a_idade_dela(): void
    {
        $ana  = $this->atleta('Ana', 15, $this->idSub15M); // convocada nos dois times (o Sub-15 sobe)
        $caio = $this->atleta('Caio', 16, $this->idSub17M);
        $this->convocacaoDireta($this->copa, $this->aacj15, $ana, 10);
        $this->convocacaoDireta($this->copa, $this->aacj17, $ana, 4);
        $this->convocacaoDireta($this->copa, $this->aacj17, $caio, 9);

        // Sub-17: entram a Ana (pelo AACJ Sub-17) e o Caio (16 cabe no Sub-17)
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $this->idSub17M, 'id_time_casa' => $this->aacj17]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '2 atleta(s) da convocação inscrito(s).'));
        $jogo = Jogo::with('evento.categoria')->sole();
        $this->assertSame($this->idSub17M, (int) $jogo->evento->id_categoria);
        $this->assertEquals([$ana => $this->aacj17, $caio => $this->aacj17], $this->inscritos($jogo));
        $this->assertSame('Sub-17 Masculino, até 17 anos', $jogo->limiteDeIdade());
        $this->assertSame([$ana => 4, $caio => 9], $jogo->camisas());

        // Sub-15: o Caio passa da idade do jogo
        $jogo->evento->update(['id_categoria' => $this->idSub15M]);
        $this->assertSame([$caio], array_keys($jogo->fresh('evento.categoria')->acimaDaIdade([$ana, $caio])));
    }

    public function test_categoria_e_obrigatoria_e_precisa_ser_do_campeonato(): void
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo())
            ->assertSessionHasErrors(['id_categoria' => 'Escolha a categoria do jogo (uma das categorias do campeonato).']);
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $this->idCategoria('Sub-13', 'M')]))
            ->assertSessionHasErrors(['id_categoria' => 'A categoria do jogo precisa ser uma das categorias do campeonato.']);
        $this->assertSame(0, Jogo::count());
    }

    public function test_times_so_entre_os_participantes_da_categoria_do_jogo(): void
    {
        // O AACJ Sub-15 não participa do Sub-17; o Botafogo, das duas
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $this->idSub17M, 'id_time_casa' => $this->aacj15]))
            ->assertSessionHasErrors(['id_time_casa' => 'O mandante não participa do Sub-17 Masculino do campeonato Copa São Carlos. '
                . 'Inclua o time nos participantes dessa categoria, ou escolha outro.']);
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $this->idSub15M]))
            ->assertSessionHasNoErrors();

        // O formulário leva as categorias e os participantes de cada uma
        $this->comoAdmin()->get(route('admin.jogos.index'))
            ->assertSee('data-categorias="' . e(json_encode([$this->idSub15M, $this->idSub17M])) . '"', false)
            ->assertSee('data-participantes="' . e(json_encode([
                $this->idSub15M => [$this->aacj15, $this->botafogo, $this->santos],
                $this->idSub17M => [$this->aacj17, $this->botafogo],
            ])) . '"', false);
    }

    public function test_jogo_sem_time_da_aacj_nasce_publicado(): void
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo([
            'id_categoria' => $this->idSub15M, 'id_time_casa' => $this->botafogo, 'id_time_visitante' => $this->santos,
        ]))->assertSessionHas('sucesso', 'Jogo registrado e publicado (sem time da AACJ: não há atletas a inscrever nem a avisar).');

        $jogo = Jogo::with('evento')->sole();
        $this->assertTrue($jogo->evento->estaPublicado());
        $this->assertSame(0, $jogo->evento->inscricoes()->count());
    }

    public function test_mudar_a_categoria_do_jogo_nao_refaz_as_inscricoes(): void
    {
        $caio = $this->atleta('Caio', 16, $this->idSub17M);
        $this->convocacaoDireta($this->copa, $this->aacj17, $caio, 9);
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $this->idSub17M, 'id_time_casa' => $this->aacj17]));
        $jogo = Jogo::sole();

        // Passa o jogo para o Sub-15 (o time joga as duas categorias, como a AACJ Masculino): o Caio continua
        // inscrito, marcado acima da idade
        $this->participantes($this->copa, [$this->aacj17], $this->idSub15M);
        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo([
            'id_categoria' => $this->idSub15M, 'id_time_casa' => $this->aacj17,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($this->idSub15M, (int) $jogo->evento->fresh()->id_categoria);
        $this->assertDatabaseHas('tbl_evento_atleta', ['id_evento_calendario' => $jogo->id_evento, 'id_atleta' => $caio]);
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSee('Acima da idade do campeonato (Sub-15 Masculino, até 15 anos)');
    }

    // 30/10/2026: times de categorias diferentes nunca se enfrentam. Mudar a categoria do jogo exige os dois times
    // nela; sem mudar a categoria, os times que o jogo já tem continuam aceitos (participante que saiu depois)
    public function test_passar_o_jogo_para_outra_categoria_exige_os_times_nela(): void
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $this->idSub17M, 'id_time_casa' => $this->aacj17]))
            ->assertSessionHasNoErrors();
        $jogo = Jogo::sole();

        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo([
            'id_categoria' => $this->idSub15M, 'id_time_casa' => $this->aacj17,
        ]))->assertSessionHasErrors(['id_time_casa' => 'O mandante não participa do Sub-15 Masculino do campeonato Copa São Carlos. '
            . 'Inclua o time nos participantes dessa categoria, ou escolha outro.']);
        $this->assertSame($this->idSub17M, (int) $jogo->evento->fresh()->id_categoria);

        // O Botafogo sai do Sub-17 depois do jogo marcado: mudar só a data continua possível
        DB::table('tbl_campeonato_time')->where('id_campeonato', $this->copa)->where('id_categoria', $this->idSub17M)
            ->where('id_time', $this->botafogo)->delete();
        $outroDia = now()->addDays(11)->toDateString();
        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo([
            'id_categoria' => $this->idSub17M, 'id_time_casa' => $this->aacj17, 'data_evento_calendario' => $outroDia,
        ]))->assertSessionHasNoErrors();
        $this->assertSame($outroDia, $jogo->evento->fresh()->data_evento_calendario->toDateString());
    }

    public function test_categoria_sem_participantes_nao_recebe_jogo(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        DB::table('tbl_campeonato_categoria')->insert(['id_campeonato' => $this->copa, 'id_categoria' => $idSub13M]);

        $this->comoAdmin()->post(route('admin.jogos.store'), $this->dadosJogo(['id_categoria' => $idSub13M]))
            ->assertSessionHasErrors(['id_categoria' => 'O Sub-13 Masculino ainda não tem times no campeonato Copa São Carlos. '
                . 'Inclua os participantes dessa categoria (Editar campeonato) antes de marcar o jogo.']);
        $this->assertSame(0, Jogo::count());
    }

    // ---------- apoio ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function dadosJogo(array $extra = []): array
    {
        return array_merge([
            'id_campeonato' => $this->copa, 'id_time_casa' => $this->aacj15, 'id_time_visitante' => $this->botafogo,
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '',
            'local_evento_calendario' => '', 'placar_time_casa_jogos' => '', 'placar_time_visitante_jogos' => '',
        ], $extra);
    }

    // Inscrições do jogo: [id_atleta => id_time]
    private function inscritos(Jogo $jogo): array
    {
        return DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->orderBy('id_atleta')
            ->pluck('id_time', 'id_atleta')->map(fn ($id) => (int) $id)->all();
    }

    private function atleta(string $nome, int $idade, int $idCategoria): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade($idade), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $idCategoria);

        return $id;
    }

    private function time(string $nome, string $tipo, int $idCategoria): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idCategoria, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
            'status_time' => 'ATIVO',
        ]);
    }
}
