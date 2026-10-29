<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Telas de Campeonatos do app (29/10/2026): da_aacj nos times do GET /campeonatos/{id} (times, jogos e
 * classificação), o resumo do card (fase, rodada e jogos realizados / já marcados) no GET /campeonatos e no
 * GET /campeonatos-do-atleta, e a "participação" do próprio atleta no detalhe do jogo da agenda.
 */
class AppCampeonatosAppTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';

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
        $this->participantes($this->copa, [$this->aacj, $this->rival, $this->santos], $this->sub13);
    }

    public function test_detalhe_do_campeonato_marca_os_times_da_aacj(): void
    {
        $this->jogo('2026-10-10', $this->aacj, $this->rival, [2, 1], rodada: 1);

        $data = $this->getJson("/api/v1/campeonatos/{$this->copa}")->assertOk()->json('data');

        $this->assertSame([$this->aacj => true, $this->rival => false, $this->santos => false],
            collect($data['times'])->mapWithKeys(fn ($t) => [$t['id_time'] => $t['da_aacj']])->sortKeys()->all());
        $this->assertTrue($data['jogos'][0]['time_casa']['da_aacj']);
        $this->assertFalse($data['jogos'][0]['time_visitante']['da_aacj']);
        $tabela = $data['classificacao'][0]['grupos'][0]['tabela'];
        $this->assertSame([['AACJ Masculino', true], ['Santos', false], ['Rival FC', false]],
            array_map(fn ($l) => [$l['time'], $l['da_aacj']], $tabela));
    }

    public function test_resumo_na_fase_de_grupos_e_no_mata_mata_so_com_jogos_publicados_e_ativos(): void
    {
        $this->jogo('2026-10-10', $this->aacj, $this->rival, [2, 1], rodada: 1);
        $this->jogo('2026-10-10', $this->santos, $this->rival, [0, 0], rodada: 1);
        $this->jogo('2026-11-07', $this->aacj, $this->santos, null, rodada: 2);
        $this->jogo('2026-11-14', $this->rival, $this->santos, null, rodada: 3);
        // Fora da conta: rascunho, cancelado e oculto
        $this->jogo('2026-11-14', $this->aacj, $this->rival, null, rodada: 3, publicado: false);
        $this->jogo('2026-11-14', $this->aacj, $this->rival, null, rodada: 3, status: 'CANCELADO');
        $this->jogo('2026-11-14', $this->aacj, $this->rival, null, rodada: 3, status: 'INATIVO');

        $this->assertSame([
            'fase_atual' => 'GRUPOS', 'fase_atual_label' => 'Fase de grupos', 'rodada_atual' => 2, 'total_rodadas' => 3,
            'jogos_realizados' => 2, 'total_jogos' => 4,
        ], $this->resumoDaLista());

        // Grupos jogados e a semifinal marcada (sem placar): fase atual é a semifinal, sem rodada; o total cresce
        DB::table('tbl_jogos')->whereNull('placar_time_casa_jogos')->update(['placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 0]);
        $this->jogo('2026-11-21', $this->aacj, $this->santos, null, fase: 'SEMIFINAL');
        $this->assertSame([
            'fase_atual' => 'SEMIFINAL', 'fase_atual_label' => 'Semifinal', 'rodada_atual' => null, 'total_rodadas' => null,
            // os 3 ocultos/cancelados/rascunho receberam placar no update, mas continuam fora
            'jogos_realizados' => 4, 'total_jogos' => 5,
        ], $this->resumoDaLista());

        // Sem nenhum jogo: tudo vazio
        DB::table('tbl_campeonato')->insert([
            'id_categoria' => $this->sub13, 'nome_campeonato' => 'Copa Nova', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => '2026-12-01', 'data_fim_campeonato' => '2026-12-20', 'status_campeonato' => 'ATIVO',
        ]);
        $this->assertSame([
            'fase_atual' => null, 'fase_atual_label' => null, 'rodada_atual' => null, 'total_rodadas' => null,
            'jogos_realizados' => 0, 'total_jogos' => 0,
        ], collect($this->getJson('/api/v1/campeonatos')->json('data'))->firstWhere('nome_campeonato', 'Copa Nova')['resumo']);
    }

    public function test_resumo_dos_campeonatos_do_atleta_so_com_as_categorias_dele(): void
    {
        $this->atleta('ana@exemplo.com', $this->sub13);   // Sub-13, sem convocação: entra pela categoria atual
        $this->participantes($this->copa, [$this->rival, $this->santos], $this->sub15);
        $this->jogo('2026-10-10', $this->aacj, $this->rival, [2, 1], rodada: 1);
        $this->jogo('2026-11-07', $this->aacj, $this->santos, null, rodada: 2);
        $this->jogo('2026-11-07', $this->rival, $this->santos, [1, 1], rodada: 1, categoria: $this->sub15);

        $doAtleta = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/campeonatos-do-atleta')->assertOk()->json('data.0.resumo');
        $this->assertSame(['jogos_realizados' => 1, 'total_jogos' => 2], array_intersect_key($doAtleta, array_flip(['jogos_realizados', 'total_jogos'])));
        // Na lista geral, todas as categorias
        $this->assertSame([2, 3], [$this->resumoDaLista()['jogos_realizados'], $this->resumoDaLista()['total_jogos']]);
    }

    public function test_participacao_sempre_presente_no_detalhe_do_jogo(): void
    {
        $atleta = $this->atleta('ana@exemplo.com', $this->sub13);
        $this->convocacaoDireta($this->copa, $this->aacj, $atleta, 10);
        $semEscalacao = $this->jogo('2026-11-07', $this->aacj, $this->rival, null, rodada: 2, inscritos: [$atleta]);
        $token = $this->login('ana@exemplo.com');

        // Convocado, sem escalação: "Convocado · camisa 10 · escalação a definir"
        $this->assertSame([
            'convocado' => true, 'camisa' => 10, 'escalado' => false, 'time' => null, 'titular' => null, 'titular_label' => null, 'posicao' => null,
        ], $this->comToken($token)->getJson("/api/v1/agenda/{$semEscalacao}")->assertOk()->json('data.jogo.participacao'));

        // Escalado, com a posição gravada e reserva
        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $semEscalacao)
            ->update(['id_time' => $this->aacj, 'posicao_evento_atleta' => 'VOLANTE', 'titular_evento_atleta' => 'RESERVA']);
        $participacao = $this->comToken($token)->getJson("/api/v1/agenda/{$semEscalacao}")->json('data.jogo.participacao');
        $this->assertSame(['escalado' => true, 'titular' => 'RESERVA', 'titular_label' => 'Reserva'],
            array_intersect_key($participacao, array_flip(['escalado', 'titular', 'titular_label'])));
        $this->assertSame(['codigo' => 'VOLANTE', 'sigla' => 'VOL', 'nome' => 'Volante'], $participacao['posicao']);
        $this->assertSame(['AACJ Masculino', true], [$participacao['time']['nome_time'], $participacao['time']['da_aacj']]);

        // Amistoso: sem convocação (null), escalado no time da AACJ
        $amistoso = $this->jogo('2026-11-14', $this->aacj, $this->santos, null, inscritos: [$atleta], campeonato: false);
        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $amistoso)->update(['id_time' => $this->aacj]);
        $this->assertSame([
            'convocado' => null, 'camisa' => null, 'escalado' => true, 'titular' => null, 'titular_label' => null, 'posicao' => null,
        ], array_diff_key($this->comToken($token)->getJson("/api/v1/agenda/{$amistoso}")->json('data.jogo.participacao'), ['time' => 1]));
    }

    private function resumoDaLista(): array
    {
        return collect($this->getJson('/api/v1/campeonatos')->assertOk()->json('data'))->firstWhere('id_campeonato', $this->copa)['resumo'];
    }

    private function jogo(string $data, int $casa, int $visitante, ?array $placar, ?int $rodada = null, string $fase = 'GRUPOS',
        bool $publicado = true, string $status = 'ATIVO', ?int $categoria = null, array $inscritos = [], bool $campeonato = true): int
    {
        $idEvento = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Jogo', 'tipo_evento_calendario' => 'JOGO', 'data_evento_calendario' => $data,
            'horario_inicio_evento_calendario' => '14:00:00', 'id_categoria' => $categoria ?? $this->sub13, 'status_evento_calendario' => $status,
            ...($publicado ? [] : ['data_publicacao_evento_calendario' => null]),
        ]);
        DB::table('tbl_jogos')->insert([
            'id_evento' => $idEvento, 'id_campeonato' => $campeonato ? $this->copa : null, 'fase_jogo' => $campeonato ? $fase : null,
            'rodada_jogo' => $campeonato && $fase === 'GRUPOS' ? $rodada : null, 'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
            'placar_time_casa_jogos' => $placar[0] ?? null, 'placar_time_visitante_jogos' => $placar[1] ?? null,
        ]);
        foreach ($inscritos as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert([
                'id_evento_calendario' => $idEvento, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL', 'data_evento_atleta' => now(),
            ]);
        }

        return $idEvento;
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId(['logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO']);
    }

    private function atleta(string $email, int $idCategoria): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);
        $this->colocarNaCategoria($id, $idCategoria);

        return $id;
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => self::SENHA])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
