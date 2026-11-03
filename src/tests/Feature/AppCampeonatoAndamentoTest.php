<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Andamento por categoria (pedido do app, 02/11/2026), igual nas três rotas (GET /campeonatos, /campeonatos/{id} e
 * /campeonatos-do-atleta): categorias[].resumo (a mesma conta do resumo geral, que continua como estava),
 * jogos_aguardando_resultado (concluídos sem placar) nos dois, categorias[].encerrada e campeao (o objeto de
 * classificacao[].campeao do detalhe) e "encerrado" do campeonato, só com todas as categorias encerradas.
 */
class AppCampeonatoAndamentoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $sub13;
    private int $sub15;
    private int $copa;
    private int $aacj;
    private int $rival;
    private int $santos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-29 12:00:00');
        $this->sub13 = $this->idCategoria('Sub-13', 'M');
        $this->sub15 = $this->idCategoria('Sub-15', 'M');
        $this->copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->sub13, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-20', 'status_campeonato' => 'ATIVO',
        ]);
        $this->aacj = $this->time('AACJ Masculino', 'INTERNO');
        $this->rival = $this->time('Rival FC', 'EXTERNO');
        $this->santos = $this->time('Santos', 'EXTERNO');
        $this->participantes($this->copa, [$this->aacj, $this->rival], $this->sub13);
        $this->participantes($this->copa, [$this->aacj, $this->rival, $this->santos], $this->sub15);
    }

    public function test_resumo_por_categoria_igual_na_lista_e_no_detalhe_com_jogos_aguardando_resultado(): void
    {
        $this->jogo($this->sub13, '2026-10-10', $this->aacj, $this->rival, [2, 1], rodada: 1);
        $this->jogo($this->sub13, '2026-10-20', $this->rival, $this->aacj, null, rodada: 2);       // passou, sem placar
        $this->jogo($this->sub13, '2026-11-05', $this->aacj, $this->rival, null, rodada: 3);
        $this->jogo($this->sub15, '2026-11-07', $this->aacj, $this->santos, null, fase: 'FINAL');

        $lista = $this->daLista();
        $detalhe = $this->getJson("/api/v1/campeonatos/{$this->copa}")->assertOk()->json('data');

        $sub13 = ['fase_atual' => 'GRUPOS', 'fase_atual_label' => 'Fase de grupos', 'rodada_atual' => 2, 'total_rodadas' => 3,
            'jogos_realizados' => 1, 'total_jogos' => 3, 'jogos_aguardando_resultado' => 1];
        $sub15 = ['fase_atual' => 'FINAL', 'fase_atual_label' => 'Final', 'rodada_atual' => null, 'total_rodadas' => null,
            'jogos_realizados' => 0, 'total_jogos' => 1, 'jogos_aguardando_resultado' => 0];
        $geral = ['fase_atual' => 'GRUPOS', 'fase_atual_label' => 'Fase de grupos', 'rodada_atual' => 2, 'total_rodadas' => 3,
            'jogos_realizados' => 1, 'total_jogos' => 4, 'jogos_aguardando_resultado' => 1];

        foreach ([$lista, $detalhe] as $data) {
            $this->assertSame([$this->sub13 => $sub13, $this->sub15 => $sub15], $this->porCategoria($data, 'resumo'));
            $this->assertSame($geral, $data['resumo']);
            $this->assertSame([$this->sub13 => false, $this->sub15 => false], $this->porCategoria($data, 'encerrada'));
            $this->assertSame([$this->sub13 => null, $this->sub15 => null], $this->porCategoria($data, 'campeao'));
            $this->assertFalse($data['encerrado']);
        }
    }

    public function test_jogo_de_hoje_aguarda_resultado_so_depois_do_horario(): void
    {
        $this->jogo($this->sub13, '2026-10-29', $this->aacj, $this->rival, null, rodada: 1, horario: '10:00:00');  // já passou
        $this->jogo($this->sub13, '2026-10-29', $this->rival, $this->aacj, null, rodada: 1, horario: '15:00:00');  // ainda não

        $this->assertSame(1, $this->daLista()['resumo']['jogos_aguardando_resultado']);
    }

    public function test_campeao_da_final_e_dos_pontos_corridos_e_encerrado_so_com_todas_as_categorias(): void
    {
        // Sub-15: final empatada, decidida nos pênaltis
        $this->jogo($this->sub15, '2026-10-25', $this->rival, $this->aacj, [1, 1], fase: 'FINAL', extras: [
            'prorrogacao_time_casa_jogos' => 0, 'prorrogacao_time_visitante_jogos' => 0,
            'penaltis_time_casa_jogos' => 4, 'penaltis_time_visitante_jogos' => 5,
        ]);
        // Sub-13: pontos corridos, todos os jogos com placar, mas ainda sem encerrar (pode vir um returno)
        $this->jogo($this->sub13, '2026-10-10', $this->aacj, $this->rival, [2, 0], rodada: 1);

        $data = $this->daLista();
        $campeao = $this->porCategoria($data, 'campeao')[$this->sub15];
        $this->assertSame(['campeao', 'final'], [$campeao['situacao'], $campeao['modo']]);
        $this->assertSame(['AACJ Masculino', true], [$campeao['campeao']['nome'], $campeao['campeao']['da_aacj']]);
        $this->assertSame(['Rival FC', false], [$campeao['vice']['nome'], $campeao['vice']['da_aacj']]);
        $this->assertStringEndsWith('/futebol/images/team/time.png', $campeao['campeao']['logo_url']);
        $this->assertSame([$this->sub13 => false, $this->sub15 => true], $this->porCategoria($data, 'encerrada'));
        $this->assertFalse($data['encerrado']);

        $this->encerrar($this->sub13);

        foreach ([$this->daLista(), $this->getJson("/api/v1/campeonatos/{$this->copa}")->json('data')] as $data) {
            $doSub13 = $this->porCategoria($data, 'campeao')[$this->sub13];
            $this->assertSame(['campeao', 'tabela', 'AACJ Masculino'], [$doSub13['situacao'], $doSub13['modo'], $doSub13['campeao']['nome']]);
            $this->assertSame([$this->sub13 => true, $this->sub15 => true], $this->porCategoria($data, 'encerrada'));
            $this->assertTrue($data['encerrado']);
        }

        // No detalhe, categorias[].campeao é o mesmo objeto de classificacao[].campeao
        $detalhe = $this->getJson("/api/v1/campeonatos/{$this->copa}")->json('data');
        $this->assertSame(collect($detalhe['classificacao'])->mapWithKeys(fn ($c) => [$c['id_categoria'] => $c['campeao']])->sortKeys()->all(),
            collect($this->porCategoria($detalhe, 'campeao'))->sortKeys()->all());
    }

    public function test_empate_em_tudo_no_topo_encerra_a_categoria_com_sorteio(): void
    {
        $this->jogo($this->sub13, '2026-10-10', $this->aacj, $this->rival, [1, 1], rodada: 1);
        $this->encerrar($this->sub13);

        $campeao = $this->porCategoria($this->daLista(), 'campeao')[$this->sub13];

        $this->assertSame(['sorteio', null], [$campeao['situacao'], $campeao['campeao']]);
        $this->assertTrue($this->porCategoria($this->daLista(), 'encerrada')[$this->sub13]);
    }

    public function test_campeonatos_do_atleta_mostra_so_as_categorias_dele_e_encerrado_conta_todas(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->update(['email_atleta' => 'atleta@example.test', 'password' => Hash::make('teste1234')]);
        $this->colocarNaCategoria($idAtleta, $this->sub13);
        $this->jogo($this->sub13, '2026-10-10', $this->aacj, $this->rival, [2, 0], rodada: 1);
        $this->encerrar($this->sub13);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'atleta@example.test', 'senha' => 'teste1234'])->assertOk()->json('data.token');

        $data = $this->withToken($token)->getJson('/api/v1/campeonatos-do-atleta')->assertOk()->json('data.0');

        $this->assertSame([$this->sub13], array_column($data['categorias'], 'id_categoria'));
        $this->assertTrue($data['categorias'][0]['encerrada']);
        $this->assertSame(1, $data['categorias'][0]['resumo']['jogos_realizados']);
        $this->assertFalse($data['encerrado']);   // o Sub-15 do campeonato continua em aberto
    }

    private function daLista(): array
    {
        return collect($this->getJson('/api/v1/campeonatos')->assertOk()->json('data'))->firstWhere('id_campeonato', $this->copa);
    }

    private function porCategoria(array $data, string $campo): array
    {
        return collect($data['categorias'])->mapWithKeys(fn ($c) => [$c['id_categoria'] => $c[$campo]])->all();
    }

    private function encerrar(int $idCategoria): void
    {
        DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->copa)->where('id_categoria', $idCategoria)
            ->update(['data_encerramento_campeonato_categoria' => now()]);
    }

    private function jogo(int $categoria, string $data, int $casa, int $visitante, ?array $placar, ?int $rodada = null,
        string $fase = 'GRUPOS', string $horario = '14:00:00', array $extras = []): void
    {
        $idEvento = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Jogo', 'tipo_evento_calendario' => 'JOGO', 'data_evento_calendario' => $data,
            'horario_inicio_evento_calendario' => $horario, 'id_categoria' => $categoria, 'status_evento_calendario' => 'ATIVO',
        ]);
        DB::table('tbl_jogos')->insert([
            'id_evento' => $idEvento, 'id_campeonato' => $this->copa, 'fase_jogo' => $fase,
            'rodada_jogo' => $fase === 'GRUPOS' ? $rodada : null, 'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
            'placar_time_casa_jogos' => $placar[0] ?? null, 'placar_time_visitante_jogos' => $placar[1] ?? null, ...$extras,
        ]);
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId(['logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO']);
    }
}
