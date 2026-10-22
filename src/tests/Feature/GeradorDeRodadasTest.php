<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\User;
use App\Services\GeradorDeRodadas;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase I, Etapa I2 (dono do projeto, 21/10/2026): gerar os jogos de todos contra todos de uma categoria do
 * campeonato (só turno ou turno e returno), com prévia, e o "Publicar a Rodada N".
 */
class GeradorDeRodadasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $copa;
    private int $aacj;
    private array $externos;
    private int $atleta;
    private string $sabado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->sabado   = today()->next(\Carbon\CarbonInterface::SATURDAY)->toDateString();
        $this->copa     = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => today()->toDateString(), 'data_fim_campeonato' => today()->addMonths(6)->toDateString(),
            'local_evento' => 'Arena Municipal', 'status_campeonato' => 'ATIVO',
        ]);
        $this->aacj     = $this->time('AACJ Sub-15', 'INTERNO');
        $this->externos = [$this->time('Botafogo'), $this->time('Estrela'), $this->time('Santos')];
        $this->participantes($this->copa, [$this->aacj, ...$this->externos], $this->idSub15M);

        // Um convocado do AACJ Sub-15 (14 anos no ano dos jogos)
        $this->atleta = $this->criarAtleta((today()->year - 14) . '-05-10', 'M', 'ATIVO');
        $this->convocacaoDireta($this->copa, $this->aacj, $this->atleta, 10);
    }

    // ---------- método do círculo ----------

    public function test_todos_contra_todos_com_numero_par_e_impar(): void
    {
        foreach ([4 => 3, 5 => 5, 6 => 5] as $quantos => $rodadasEsperadas) {
            $times   = range(1, $quantos);
            $rodadas = GeradorDeRodadas::confrontos($times);
            $this->assertCount($rodadasEsperadas, $rodadas, "{$quantos} times");

            $pares = [];
            foreach ($rodadas as $rodada) {
                $naRodada = collect($rodada['jogos'])->flatten()->push($rodada['folga'])->filter()->all();
                $this->assertSame($quantos, count(array_unique($naRodada)), 'cada time uma vez por rodada (jogando ou folgando)');
                foreach ($rodada['jogos'] as [$casa, $fora]) {
                    $pares[] = min($casa, $fora) . '-' . max($casa, $fora);
                }
            }
            $this->assertSame($quantos * ($quantos - 1) / 2, count($pares));
            $this->assertSame(count($pares), count(array_unique($pares)), 'cada par uma vez só no turno');

            // Número ímpar: cada time folga uma vez
            if ($quantos % 2) {
                $this->assertEqualsCanonicalizing($times, array_column($rodadas, 'folga'));
            }
        }
    }

    public function test_turno_e_returno_inverte_o_mando(): void
    {
        $rodadas = GeradorDeRodadas::confrontos(range(1, 6), returno: true);
        $this->assertCount(10, $rodadas);

        $jogos = collect($rodadas)->flatMap(fn ($r) => array_map(fn ($j) => "{$j[0]}>{$j[1]}", $r['jogos']));
        $this->assertCount(30, $jogos);
        $this->assertSame(30, $jogos->unique()->count(), 'cada par uma vez em cada mando');

        // Mandos equilibrados no turno: cada time com 2 ou 3 jogos em casa
        $emCasa = collect(array_slice($rodadas, 0, 5))->flatMap(fn ($r) => array_column($r['jogos'], 0))->countBy();
        $this->assertTrue($emCasa->every(fn ($n) => $n >= 2 && $n <= 3), json_encode($emCasa));
    }

    // ---------- gerar ----------

    public function test_previa_e_geracao_pelo_caminho_da_tela_de_jogos(): void
    {
        $this->comoAdmin()->get(route('admin.campeonatos.grupos', $this->copa))->assertOk()->assertSee('Gerar jogos (todos contra todos)');

        $this->comoAdmin()->get(route('admin.campeonatos.gerar-jogos', [$this->copa, $this->idSub15M, ...$this->opcoes()]))->assertOk()
            ->assertSee('4</strong> time(s)', false)->assertSee('3</strong> rodada(s)', false)->assertSee('6</strong> jogo(s)', false)
            ->assertSee('Rodada 3');
        $this->assertSame(0, Jogo::count(), 'a prévia não grava nada');

        $this->gerar()->assertRedirect(route('admin.campeonatos.times', $this->copa))
            ->assertSessionHas('sucesso', '6 jogo(s) do Sub-15 Masculino criado(s) em 3 rodada(s): 3 em rascunho (com time da AACJ: '
                . 'publique a rodada ou cada jogo para avisar os atletas) e 3 publicado(s) (sem time da AACJ).');

        $jogos = Jogo::with('evento')->get();
        $this->assertCount(6, $jogos);
        $this->assertSame([1 => 2, 2 => 2, 3 => 2], $jogos->countBy('rodada_jogo')->sortKeys()->all());
        $this->assertTrue($jogos->every(fn ($j) => $j->fase_jogo === 'GRUPOS' && (int) $j->evento->id_categoria === $this->idSub15M
            && $j->evento->local_evento_calendario === 'Arena Municipal'));

        // Datas: uma rodada por semana; horários de hora em hora, com a duração
        $rodada1 = $jogos->where('rodada_jogo', 1)->sortBy('evento.horario_inicio_evento_calendario')->values();
        $this->assertSame([$this->sabado, $this->sabado], $rodada1->map(fn ($j) => $j->evento->data_evento_calendario->toDateString())->all());
        $this->assertSame(['13:00:00', '14:00:00'], $rodada1->map(fn ($j) => $j->evento->horario_inicio_evento_calendario)->all());
        $this->assertSame('14:00:00', $rodada1[0]->evento->horario_fim_evento_calendario);
        $this->assertSame(today()->next(\Carbon\CarbonInterface::SATURDAY)->addDays(14)->toDateString(),
            $jogos->firstWhere('rodada_jogo', 3)->evento->data_evento_calendario->toDateString());

        // Com o AACJ: rascunho, convocado inscrito e escalado; entre externos: publicado. Ninguém avisado
        $daAcj = $jogos->filter(fn ($j) => in_array($this->aacj, [$j->id_time_casa, $j->id_time_visitante]));
        $this->assertCount(3, $daAcj);
        $this->assertTrue($daAcj->every(fn ($j) => ! $j->evento->estaPublicado()
            && DB::table('tbl_evento_atleta')->where('id_evento_calendario', $j->id_evento)->where('id_atleta', $this->atleta)->value('id_time') == $this->aacj));
        $this->assertTrue($jogos->diffKeys($daAcj)->every(fn ($j) => $j->evento->estaPublicado()));
        $this->assertSame(0, DB::table('tbl_notificacao')->count());

        // A categoria já tem jogos: gerar de novo é recusado
        $this->gerar()->assertSessionHas('erro', fn ($erro) => str_contains($erro, 'O Sub-15 Masculino já tem 6 jogo(s) da fase de grupos.'));
        $this->assertSame(6, Jogo::count());
    }

    public function test_publicar_a_rodada_avisa_os_atletas(): void
    {
        $this->gerar(['returno' => 1]);
        $this->assertSame(12, Jogo::count());

        $this->comoAdmin()->get(route('admin.campeonatos.times', $this->copa))->assertOk()
            ->assertSee('Publicar a Rodada 1')->assertSee('Publicar a Rodada 6');

        $this->comoAdmin()->patch(route('admin.campeonatos.rodadas.publicar', $this->copa), ['id_categoria' => $this->idSub15M, 'rodada_jogo' => 1])
            ->assertSessionHas('sucesso', 'Rodada 1: 1 jogo(s) publicado(s). 1 atleta(s) notificado(s).');

        $this->assertSame(1, DB::table('tbl_notificacao')->where('id_atleta', $this->atleta)->count());
        $this->assertTrue(Jogo::with('evento')->where('rodada_jogo', 1)->get()->every(fn ($j) => $j->evento->estaPublicado()));
        $this->assertFalse(Jogo::with('evento')->where('rodada_jogo', 2)->get()->every(fn ($j) => $j->evento->estaPublicado()));

        // De novo: nada a publicar
        $this->comoAdmin()->patch(route('admin.campeonatos.rodadas.publicar', $this->copa), ['id_categoria' => $this->idSub15M, 'rodada_jogo' => 1])
            ->assertSessionHas('aviso', 'Rodada 1: nenhum jogo em rascunho para publicar.');
    }

    public function test_com_grupos_cada_grupo_tem_os_proprios_confrontos(): void
    {
        $grupoA = DB::table('tbl_campeonato_grupo')->insertGetId(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => 'Grupo A']);
        $grupoB = DB::table('tbl_campeonato_grupo')->insertGetId(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => 'Grupo B']);
        $porTime = [$this->aacj => $grupoA, $this->externos[0] => $grupoA, $this->externos[1] => $grupoA];
        foreach ($porTime as $idTime => $idGrupo) {
            DB::table('tbl_campeonato_time')->where('id_campeonato', $this->copa)->where('id_time', $idTime)->update(['id_campeonato_grupo' => $idGrupo]);
        }

        // Santos sem grupo: recusado
        $this->gerar()->assertSessionHas('erro', fn ($erro) => str_contains($erro, 'Ponha todos os times num grupo (chave) antes de gerar: Santos.'));

        // Santos sozinho no Grupo B: só o Grupo A tem jogos (3 rodadas, uma folga em cada)
        DB::table('tbl_campeonato_time')->where('id_time', $this->externos[2])->update(['id_campeonato_grupo' => $grupoB]);
        $this->gerar()->assertSessionHasNoErrors();
        $jogos = Jogo::all();
        $this->assertCount(3, $jogos);
        $this->assertFalse($jogos->contains(fn ($j) => in_array($this->externos[2], [$j->id_time_casa, $j->id_time_visitante])));
    }

    public function test_conflito_de_horario_dos_convocados_pede_confirmacao(): void
    {
        // O convocado já tem um treino no 1º sábado, das 13:00 às 15:00
        $treino = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => $this->sabado,
            'horario_inicio_evento_calendario' => '13:00', 'horario_fim_evento_calendario' => '15:00', 'status_evento_calendario' => 'ATIVO',
        ], inscreverCategoria: false);
        $treino->inscrever($this->atleta, 'INDIVIDUAL', $this->admin->id_usuario);

        $this->comoAdmin()->get(route('admin.campeonatos.gerar-jogos', [$this->copa, $this->idSub15M, ...$this->opcoes()]))
            ->assertSee('Conflito de horário')->assertSee('Confirmar mesmo assim e gerar');

        $this->gerar()->assertSessionHas('erro', fn ($erro) => str_starts_with($erro, 'Nada foi gravado: há conflito de horário'));
        $this->assertSame(0, Jogo::count());

        $this->gerar(['confirmar_conflitos' => 1])->assertSessionHas('sucesso');
        $this->assertSame(6, Jogo::count());
    }

    public function test_aviso_de_time_que_ja_joga_no_dia(): void
    {
        // O Botafogo já tem um amistoso no 1º sábado: a prévia avisa (sem bloquear) em todos os times dele naquele dia
        Jogo::criarComEvento($this->admin->id_usuario, [
            'id_campeonato' => null, 'id_time_casa' => $this->aacj, 'id_time_visitante' => $this->externos[0],
        ], [
            'titulo_evento_calendario' => 'AACJ Sub-15 x Botafogo', 'tipo_evento_calendario' => 'JOGO', 'id_categoria' => $this->idSub15M,
            'data_evento_calendario' => $this->sabado, 'horario_inicio_evento_calendario' => '09:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        $this->comoAdmin()->get(route('admin.campeonatos.gerar-jogos', [$this->copa, $this->idSub15M, ...$this->opcoes()]))->assertOk()
            ->assertSee('Botafogo já tem jogo em ' . \Illuminate\Support\Carbon::parse($this->sabado)->format('d/m') . ' às 09:00 (AACJ Sub-15 x Botafogo).')
            ->assertSee('Gerar 6 jogo(s)');
    }

    public function test_campeonato_inativo_e_categoria_de_fora(): void
    {
        DB::table('tbl_campeonato')->where('id_campeonato', $this->copa)->update(['status_campeonato' => 'INATIVO']);
        $this->gerar()->assertSessionHas('erro', fn ($erro) => str_contains($erro, 'O campeonato Copa está inativo.'));
        $this->assertSame(0, Jogo::count());

        $this->comoAdmin()->get(route('admin.campeonatos.gerar-jogos', [$this->copa, $this->idCategoria('Sub-17', 'M')]))->assertNotFound();
    }

    // ---------- auxiliares ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function opcoes(array $extra = []): array
    {
        return array_merge(['returno' => 0, 'data' => $this->sabado, 'dias' => 7, 'horario' => '13:00', 'intervalo' => 60, 'duracao' => 60, 'local' => ''], $extra);
    }

    private function gerar(array $extra = [])
    {
        return $this->comoAdmin()->post(route('admin.campeonatos.gerar-jogos.gerar', [$this->copa, $this->idSub15M]), $this->opcoes($extra));
    }

    private function time(string $nome, string $tipo = 'EXTERNO'): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
    }
}
