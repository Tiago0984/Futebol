<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Responsavel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * GET /api/v1/atleta/estatisticas (22/10/2026): partidas (escalado num time, em jogo publicado, ativo e já
 * realizado), convocações e cartões, no total, por time e na categoria atual; gols e assistências da súmula
 * (Etapa S2), null quando nenhum jogo do recorte tem súmula.
 * O responsável vê a mesma coisa do filho.
 */
class AtletaEstatisticasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $id;
    private int $idCampeonato;
    private int $sub15;
    private int $sub17;
    private int $externo;

    protected function setUp(): void
    {
        parent::setUp();

        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->id = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        $this->colocarNaCategoria($this->id, $idSub15M);

        $this->idCampeonato = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $idSub15M, 'nome_campeonato' => 'Copa Regional', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => now()->subMonth()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
        ]);

        $time = fn (string $nome, string $tipo, string $categoria) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idCategoria($categoria, 'M'), 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
        $this->sub15   = $time('AACJ Sub-15', 'INTERNO', 'Sub-15');
        $this->sub17   = $time('AACJ Sub-17 A', 'INTERNO', 'Sub-17');
        $this->externo = $time('Botafogo', 'EXTERNO', 'Sub-15');
    }

    public function test_conta_so_jogos_escalado_publicados_ativos_e_ja_realizados(): void
    {
        $jogou1 = $this->jogo(-14, $this->sub15);
        $jogou2 = $this->jogo(-7, $this->sub15);
        $outroTime = $this->jogo(-3, $this->sub17);
        $this->jogo(-10, $this->sub15, status: 'CANCELADO');
        $this->jogo(-9, $this->sub15, status: 'INATIVO');            // oculto
        $this->jogo(-8, $this->sub15, rascunho: true);
        $this->jogo(5, $this->sub15);                                 // ainda não aconteceu
        $this->jogo(-6, $this->sub15, escalar: false);               // inscrito sem time

        $this->convocacaoDireta($this->idCampeonato, $this->sub15, $this->id, 10);
        DB::table('tbl_cartoes')->insert([
            ['id_atleta' => $this->id, 'id_jogo' => $jogou1->id_jogo, 'id_campeonato' => $this->idCampeonato, 'tipo_cartao' => 'AMARELO'],
            ['id_atleta' => $this->id, 'id_jogo' => $outroTime->id_jogo, 'id_campeonato' => $this->idCampeonato, 'tipo_cartao' => 'VERMELHO'],
            ['id_atleta' => $this->id, 'id_jogo' => $jogou2->id_jogo, 'id_campeonato' => $this->idCampeonato, 'tipo_cartao' => 'AMARELO'],
        ]);

        Sanctum::actingAs(Atleta::find($this->id));
        $dados = $this->getJson('/api/v1/atleta/estatisticas')->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertSame(
            ['partidas' => 3, 'convocacoes' => 1, 'cartoes_amarelos' => 2, 'cartoes_vermelhos' => 1, 'gols' => null, 'assistencias' => null],
            $dados['total']
        );

        // O time com mais partidas primeiro; cada número no time da escalação
        $this->assertSame(['AACJ Sub-15', 'AACJ Sub-17 A'], array_column($dados['por_time'], 'nome_time'));
        [$sub15, $sub17] = $dados['por_time'];
        $this->assertSame([2, 1, 2, 0], [$sub15['partidas'], $sub15['convocacoes'], $sub15['cartoes_amarelos'], $sub15['cartoes_vermelhos']]);
        $this->assertSame([1, 0, 0, 1], [$sub17['partidas'], $sub17['convocacoes'], $sub17['cartoes_amarelos'], $sub17['cartoes_vermelhos']]);
        $this->assertSame('Sub-17 Masculino', $sub17['categoria']);
        $this->assertStringEndsWith('time.png', $sub15['logo_url']);
        $this->assertNull($sub15['gols']);
    }

    public function test_atleta_sem_jogos_tem_tudo_zero_e_nenhum_time(): void
    {
        Sanctum::actingAs(Atleta::find($this->id));

        $this->getJson('/api/v1/atleta/estatisticas')->assertOk()
            ->assertJsonPath('data.total.partidas', 0)
            ->assertJsonPath('data.total.convocacoes', 0)
            ->assertJsonPath('data.total.gols', null)
            ->assertJsonPath('data.por_time', []);
    }

    public function test_convocado_sem_jogo_aparece_no_time_com_zero_partidas(): void
    {
        $this->convocacaoDireta($this->idCampeonato, $this->sub17, $this->id, 7);
        Sanctum::actingAs(Atleta::find($this->id));

        $this->getJson('/api/v1/atleta/estatisticas')->assertOk()
            ->assertJsonPath('data.por_time.0.nome_time', 'AACJ Sub-17 A')
            ->assertJsonPath('data.por_time.0.partidas', 0)
            ->assertJsonPath('data.por_time.0.convocacoes', 1);
    }

    public function test_categoria_atual_conta_so_os_jogos_e_convocacoes_dela(): void
    {
        $idSub15 = $this->idCategoria('Sub-15', 'M');
        $idSub17 = $this->idCategoria('Sub-17', 'M');
        $this->participantes($this->idCampeonato, [$this->sub17], $idSub17);

        $noSub15 = $this->jogo(-14, $this->sub15, idCategoria: $idSub15);
        $this->jogo(-10, $this->sub15);                                   // jogo sem categoria (amistoso): vale a do time
        $subiu = $this->jogo(-7, $this->sub17, idCategoria: $idSub17);   // sobe para o Sub-17: só no total
        $this->jogo(-3, $this->sub15, idCategoria: $idSub15, status: 'CANCELADO');

        $this->convocacaoDireta($this->idCampeonato, $this->sub15, $this->id, 10);
        $this->convocacaoDireta($this->idCampeonato, $this->sub17, $this->id, 4);
        DB::table('tbl_cartoes')->insert([
            ['id_atleta' => $this->id, 'id_jogo' => $noSub15->id_jogo, 'id_campeonato' => $this->idCampeonato, 'tipo_cartao' => 'AMARELO'],
            ['id_atleta' => $this->id, 'id_jogo' => $subiu->id_jogo, 'id_campeonato' => $this->idCampeonato, 'tipo_cartao' => 'VERMELHO'],
        ]);

        Sanctum::actingAs(Atleta::find($this->id));
        $dados = $this->getJson('/api/v1/atleta/estatisticas')->assertOk()->json('data');

        $this->assertSame([
            'id_categoria' => $idSub15, 'nome_categoria' => 'Sub-15', 'categoria' => 'Sub-15 Masculino',
            'partidas' => 2, 'convocacoes' => 1, 'cartoes_amarelos' => 1, 'cartoes_vermelhos' => 0, 'gols' => null, 'assistencias' => null,
        ], $dados['categoria_atual']);
        $this->assertSame([3, 2, 1, 1], [$dados['total']['partidas'], $dados['total']['convocacoes'], $dados['total']['cartoes_amarelos'], $dados['total']['cartoes_vermelhos']]);
    }

    public function test_gols_e_assistencias_vem_da_sumula_so_nos_jogos_com_sumula(): void
    {
        $idSub15 = $this->idCategoria('Sub-15', 'M');
        $idSub17 = $this->idCategoria('Sub-17', 'M');
        $this->participantes($this->idCampeonato, [$this->sub17], $idSub17);
        $colega = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');

        $comSumula = $this->jogo(-14, $this->sub15, idCategoria: $idSub15);
        $semSumula = $this->jogo(-10, $this->sub15, idCategoria: $idSub15);       // placar sem gols lançados
        $subiu = $this->jogo(-7, $this->sub17, idCategoria: $idSub17);           // súmula sem gol dele: 0, não null
        $rascunho = $this->jogo(-5, $this->sub15, rascunho: true, idCategoria: $idSub15);
        $this->jogo(-3, $this->sub17, idCategoria: $idSub17);                    // Sub-17 sem súmula

        $gol = fn (Jogo $jogo, int $idTime, ?int $autor, ?int $assistencia = null, string $tipo = 'NORMAL') => [
            'id_jogo' => $jogo->id_jogo, 'id_time' => $idTime, 'id_atleta' => $autor, 'id_atleta_assistencia' => $assistencia,
            'tipo_jogo_gol' => $tipo, 'periodo_jogo_gol' => 'TEMPO_NORMAL',
        ];
        DB::table('tbl_jogo_gol')->insert([
            $gol($comSumula, $this->sub15, $this->id, $colega),
            $gol($comSumula, $this->sub15, $this->id, null, 'PENALTI'),
            $gol($comSumula, $this->sub15, $colega, $this->id),
            $gol($comSumula, $this->externo, $this->id, null, 'CONTRA'),          // contra dele: não conta
            $gol($comSumula, $this->sub15, null, null, 'CONTRA'),                // contra do adversário
            $gol($subiu, $this->sub17, $colega),
            $gol($subiu, $this->sub17, null),                                    // autor não identificado
            $gol($rascunho, $this->sub15, $this->id),                            // rascunho não conta como partida
        ]);
        DB::table('tbl_jogos')->where('id_jogo', $semSumula->id_jogo)->update(['placar_time_casa_jogos' => 3, 'placar_time_visitante_jogos' => 0]);

        Sanctum::actingAs(Atleta::find($this->id));
        $dados = $this->getJson('/api/v1/atleta/estatisticas')->assertOk()->json('data');

        $this->assertSame([4, 2, 1], [$dados['total']['partidas'], $dados['total']['gols'], $dados['total']['assistencias']]);
        $porTime = collect($dados['por_time'])->keyBy('nome_time');
        $this->assertSame([2, 1], [$porTime['AACJ Sub-15']['gols'], $porTime['AACJ Sub-15']['assistencias']]);
        $this->assertSame([0, 0], [$porTime['AACJ Sub-17 A']['gols'], $porTime['AACJ Sub-17 A']['assistencias']]);
        $this->assertSame([2, 2, 1], [$dados['categoria_atual']['partidas'], $dados['categoria_atual']['gols'], $dados['categoria_atual']['assistencias']]);

        // Sem nenhum jogo com súmula no recorte: null (não registrado), e não 0
        DB::table('tbl_jogo_gol')->where('id_jogo', $comSumula->id_jogo)->delete();
        $dados = $this->getJson('/api/v1/atleta/estatisticas')->assertOk()->json('data');
        $this->assertSame([0, 0], [$dados['total']['gols'], $dados['total']['assistencias']]);   // o Sub-17 ainda tem súmula
        $this->assertSame([null, null], [$dados['categoria_atual']['gols'], $dados['categoria_atual']['assistencias']]);
        $this->assertNull(collect($dados['por_time'])->firstWhere('nome_time', 'AACJ Sub-15')['gols']);

        // O responsável vê os mesmos números
        Sanctum::actingAs(Responsavel::find($this->criarResponsavel($this->id)));
        $this->assertSame($dados, $this->getJson("/api/v1/responsavel/atletas/{$this->id}/estatisticas")->assertOk()->json('data'));
    }

    public function test_sem_categoria_ativa_categoria_atual_e_null(): void
    {
        $this->jogo(-7, $this->sub15);
        Atleta::find($this->id)->trocarCategoria(null);   // só encerra a linha ativa
        Sanctum::actingAs(Atleta::find($this->id));

        $this->getJson('/api/v1/atleta/estatisticas')->assertOk()
            ->assertJsonPath('data.categoria_atual', null)
            ->assertJsonPath('data.total.partidas', 1);
    }

    public function test_responsavel_ve_as_do_filho_e_nao_as_de_outro_atleta(): void
    {
        $this->jogo(-7, $this->sub15);
        $idResponsavel = $this->criarResponsavel($this->id);
        $outro = $this->criarAtleta($this->nascidoComIdade(14), 'M', 'ATIVO');
        Sanctum::actingAs(Responsavel::find($idResponsavel));

        $this->getJson("/api/v1/responsavel/atletas/{$this->id}/estatisticas")->assertOk()
            ->assertJsonPath('data.total.partidas', 1)
            ->assertJsonPath('data.categoria_atual.nome_categoria', 'Sub-15')
            ->assertJsonPath('data.categoria_atual.partidas', 1);
        $this->getJson("/api/v1/responsavel/atletas/{$outro}/estatisticas")->assertNotFound();
        $this->getJson('/api/v1/atleta/estatisticas')->assertForbidden(); // rota do perfil atleta
    }

    public function test_sem_token_da_401(): void
    {
        $this->getJson('/api/v1/atleta/estatisticas')->assertUnauthorized();
    }

    // Jogo do time da AACJ contra o externo, com o atleta escalado nele (ou só inscrito, sem time)
    private function jogo(int $dias, int $idTime, string $status = 'ATIVO', bool $rascunho = false, bool $escalar = true, ?int $idCategoria = null): Jogo
    {
        $evento = EventoCalendario::criarPor(null, rascunho: $rascunho, inscreverCategoria: false, dados: [
            'id_categoria'                     => $idCategoria,
            'titulo_evento_calendario'         => 'Jogo de teste',
            'tipo_evento_calendario'           => 'JOGO',
            'data_evento_calendario'           => now()->addDays($dias)->toDateString(),
            'horario_inicio_evento_calendario' => '14:00:00',
            'local_evento_calendario'          => 'Arena',
            'status_evento_calendario'         => $status,
        ]);
        $jogo = Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $this->idCampeonato,
            'id_time_casa' => $idTime, 'id_time_visitante' => $this->externo,
        ]);
        DB::table('tbl_evento_atleta')->insert([
            'id_evento_calendario' => $evento->id_evento_calendario, 'id_atleta' => $this->id,
            'origem_evento_atleta' => 'INDIVIDUAL', 'id_time' => $escalar ? $idTime : null,
        ]);

        return $jogo;
    }
}
