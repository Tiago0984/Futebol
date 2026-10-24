<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\AppDoAtletaController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Pedidos do app para a agenda (24/10/2026): filtro por tipo (?tipo=JOGO,TREINO), período (?de=&ate=, com os
 * concluídos), totais por tipo (sem o filtro de tipo) e o campo "concluido" em cada evento. Sem os parâmetros
 * novos, a resposta continua a mesma (mais "totais" e "concluido").
 */
class AppAgendaFiltrosTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';
    private const HOJE = '2026-10-10 12:00:00'; // sábado, meio-dia

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOJE);
    }

    public function test_filtro_por_tipo_acha_o_jogo_depois_de_mais_de_20_treinos(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        foreach (range(11, 31) as $dia) {
            $this->evento("Treino {$dia}", "2026-10-{$dia}", '18:00:00', [$atleta]);
        }
        $jogo = $this->evento('Jogo', '2026-11-07', '13:00:00', [$atleta], tipo: 'JOGO');
        $reuniao = $this->evento('Reunião', '2026-11-08', '10:00:00', [$atleta], tipo: 'REUNIAO');
        $jogoPassado = $this->evento('Jogo passado', '2026-10-03', '13:00:00', [$atleta], tipo: 'JOGO');
        $this->evento('Treino passado', '2026-10-09', '18:00:00', [$atleta]);
        $token = $this->login('ana@exemplo.com');

        $semFiltro = $this->comToken($token)->getJson('/api/v1/agenda')->assertOk()->json('data');
        $this->assertNotContains($jogo, array_column($semFiltro['proximos'], 'id_evento')); // o problema do app

        $soJogos = $this->comToken($token)->getJson('/api/v1/agenda?tipo=jogo')->assertOk()->json('data');
        $this->assertSame([$jogo], array_column($soJogos['proximos'], 'id_evento'));
        $this->assertSame([$jogoPassado], array_column($soJogos['passados'], 'id_evento'));
        $this->assertSame(1, $soJogos['paginacao']['total']);

        $varios = $this->comToken($token)->getJson('/api/v1/agenda?tipo=JOGO, REUNIAO')->assertOk()->json('data');
        $this->assertSame([$jogo, $reuniao], array_column($varios['proximos'], 'id_evento'));

        // Totais dos próximos (todas as páginas), sem o filtro de tipo, com todos os tipos
        $this->assertSame(['JOGO' => 1, 'TREINO' => 21, 'CAMPEONATO' => 0, 'EVENTO' => 0, 'REUNIAO' => 1,
            'CONFRATERNIZACAO' => 0, 'AVALIACAO' => 0], $soJogos['totais']);
        $this->assertSame($semFiltro['totais'], $soJogos['totais']);
    }

    public function test_tipo_desconhecido_da_422(): void
    {
        $this->atleta('ana@exemplo.com');

        $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda?tipo=JOGO,FESTA')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.tipo.0', 'Tipo desconhecido: FESTA. Use JOGO, TREINO, CAMPEONATO, EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO.');
    }

    public function test_periodo_traz_concluidos_e_cancelados_em_ordem_com_totais(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $antes = $this->evento('Antes', '2026-09-30', '18:00:00', [$atleta]);
        $passado = $this->evento('Passado', '2026-10-06', '18:00:00', [$atleta]);
        $cancelado = $this->evento('Passado cancelado', '2026-10-08', '18:00:00', [$atleta], status: 'CANCELADO');
        $this->evento('Oculto', '2026-10-08', '19:00:00', [$atleta], status: 'INATIVO');
        $hojeSemHorario = $this->evento('Hoje sem horário', '2026-10-10', null, [$atleta]);
        $hojeCedo = $this->evento('Hoje cedo', '2026-10-10', '08:00:00', [$atleta], tipo: 'AVALIACAO');
        $futuro = $this->evento('Futuro', '2026-10-31', '18:00:00', [$atleta], tipo: 'JOGO');
        $this->evento('Depois', '2026-11-01', '18:00:00', [$atleta]);

        $periodo = $this->comToken($this->login('ana@exemplo.com'))
            ->getJson('/api/v1/agenda?de=2026-10-01&ate=2026-10-31')->assertOk()->json('data');

        $this->assertSame(['periodo', 'eventos', 'totais'], array_keys($periodo));
        $this->assertSame(['de' => '2026-10-01', 'ate' => '2026-10-31'], $periodo['periodo']);
        $this->assertSame([$passado, $cancelado, $hojeCedo, $hojeSemHorario, $futuro], array_column($periodo['eventos'], 'id_evento'));
        $this->assertSame([true, true, true, false, false], array_column($periodo['eventos'], 'concluido'));
        $this->assertSame('CANCELADO', $periodo['eventos'][1]['situacao']);
        $this->assertSame(['JOGO' => 1, 'TREINO' => 3, 'CAMPEONATO' => 0, 'EVENTO' => 0, 'REUNIAO' => 0,
            'CONFRATERNIZACAO' => 0, 'AVALIACAO' => 1], $periodo['totais']);
        $this->assertNotContains($antes, array_column($periodo['eventos'], 'id_evento'));

        // Com o tipo: a lista filtra, os totais não
        $treinos = $this->comToken($this->login('ana@exemplo.com'))
            ->getJson('/api/v1/agenda?de=2026-10-01&ate=2026-10-31&tipo=TREINO')->json('data');
        $this->assertSame([$passado, $cancelado, $hojeSemHorario], array_column($treinos['eventos'], 'id_evento'));
        $this->assertSame($periodo['totais'], $treinos['totais']);
    }

    public function test_periodo_invalido_da_422(): void
    {
        $this->atleta('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');
        $max = AppDoAtletaController::PERIODO_MAX_DIAS;

        foreach ([
            '?de=2026-10-01'                  => ['ate', 'Informe a data "até" no formato AAAA-MM-DD (ex.: 2026-11-01).'],
            '?ate=2026-10-01'                 => ['de', 'Informe a data "de" no formato AAAA-MM-DD (ex.: 2026-11-01).'],
            '?de=01/10/2026&ate=2026-10-31'   => ['de', 'Informe a data "de" no formato AAAA-MM-DD (ex.: 2026-11-01).'],
            '?de=2026-02-30&ate=2026-03-31'   => ['de', 'Informe a data "de" no formato AAAA-MM-DD (ex.: 2026-11-01).'],
            '?de=2026-10-31&ate=2026-10-01'   => ['ate', 'A data "até" não pode ser antes da data "de".'],
            '?de=2026-01-01&ate=2026-12-31'   => ['ate', "O período pode ter no máximo {$max} dias."],
        ] as $query => [$campo, $mensagem]) {
            $this->comToken($token)->getJson("/api/v1/agenda{$query}")
                ->assertStatus(422)->assertJsonPath("errors.{$campo}.0", $mensagem);
        }

        // Exatamente o máximo passa (1º de outubro + 92 dias = 1º de janeiro)
        $this->comToken($token)->getJson('/api/v1/agenda?de=2026-10-01&ate=2027-01-01')->assertOk();
    }

    public function test_responsavel_tem_os_mesmos_filtros_do_filho(): void
    {
        $filho = $this->atleta('ana@exemplo.com');
        $responsavel = $this->responsavel('mae@exemplo.com');
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $filho, 'id_responsavel' => $responsavel, 'grau_parentesco_responsavel' => 'Mãe']);
        $this->evento('Treino', '2026-10-13', '18:00:00', [$filho]);
        $this->evento('Jogo', '2026-10-17', '13:00:00', [$filho], tipo: 'JOGO');
        $this->evento('Passado', '2026-10-06', '18:00:00', [$filho]);

        $tokenAtleta = $this->login('ana@exemplo.com');
        $tokenResponsavel = $this->login('mae@exemplo.com', 'responsavel');

        foreach (['?tipo=JOGO', '?de=2026-10-01&ate=2026-10-31', '?de=2026-10-01&ate=2026-10-31&tipo=TREINO'] as $query) {
            $doAtleta = $this->comToken($tokenAtleta)->getJson("/api/v1/agenda{$query}")->assertOk()->json('data');
            $doResponsavel = $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$filho}/agenda{$query}")->assertOk()->json('data');
            $this->assertSame($doAtleta, $doResponsavel, $query);
        }
    }

    // ---------- apoio ----------

    private function atleta(string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);

        return $id;
    }

    private function responsavel(string $email): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua', 'numero_endereco' => '1', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        return DB::table('tbl_responsavel')->insertGetId([
            'id_endereco' => $idEndereco, 'nome_responsavel' => 'Responsável',
            'cpf_responsavel' => $this->cpfDeTeste(400000001), 'rg_responsavel' => '2', 'whatsapp_responsavel' => '(11) 9',
            'email_responsavel' => $email, 'senha_responsavel' => Hash::make(self::SENHA),
        ]);
    }

    private function evento(string $titulo, string $data, ?string $inicio, array $inscritos, string $status = 'ATIVO', string $tipo = 'TREINO'): int
    {
        $id = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => $tipo, 'data_evento_calendario' => $data,
            'horario_inicio_evento_calendario' => $inicio, 'local_evento_calendario' => 'Campo A', 'status_evento_calendario' => $status,
        ]);

        foreach ($inscritos as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert([
                'id_evento_calendario' => $id, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL', 'data_evento_atleta' => now(),
            ]);
        }

        return $id;
    }

    private function login(string $email, string $perfil = 'atleta'): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => self::SENHA, 'perfil' => $perfil])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
