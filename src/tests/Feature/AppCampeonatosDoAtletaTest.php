<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Campeonatos do atleta no app (26/10/2026, carrossel da Home): GET /campeonatos-do-atleta e a mesma rota do
 * responsável. Participa quem foi convocado (qualquer categoria) ou cuja categoria atual tem time da AACJ no
 * campeonato; só ativos e não terminados; só as categorias do atleta; em andamento antes dos próximos.
 */
class AppCampeonatosDoAtletaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';

    private int $sub11;
    private int $sub13;
    private int $sub15;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 12:00:00');
        $this->sub11 = $this->idCategoria('Sub-11', 'M');
        $this->sub13 = $this->idCategoria('Sub-13', 'M');
        $this->sub15 = $this->idCategoria('Sub-15', 'M');
    }

    public function test_convocado_numa_categoria_acima_aparece_com_a_camisa(): void
    {
        $atleta = $this->atleta('theo@exemplo.com', 11, $this->sub11);
        $copa = $this->campeonato('Copa Regional', '2026-10-01', '2026-11-30', ['descricao_campeonato' => 'Copa de base.', 'banner_evento' => null]);
        $aacjSub13 = $this->time('AACJ Sub-13', 'INTERNO', $this->sub13);
        $this->participantes($copa, [$aacjSub13], $this->sub13);
        $this->participantes($copa, [$this->time('Rival', 'EXTERNO', $this->sub15)], $this->sub15);
        $this->convocacaoDireta($copa, $aacjSub13, $atleta, 8);

        $data = $this->comToken($this->login('theo@exemplo.com'))->getJson('/api/v1/campeonatos-do-atleta')
            ->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals([
            'id_campeonato' => $copa, 'nome_campeonato' => 'Copa Regional', 'descricao_campeonato' => 'Copa de base.',
            'tipo_campeonato' => 'PONTOS CORRIDOS', 'tipo_label' => 'Pontos Corridos', 'banner_url' => null,
            'data_inicio_campeonato' => '2026-10-01T00:00:00-03:00', 'situacao' => 'EM_ANDAMENTO', 'convocada' => true,
        ], array_intersect_key($data[0], array_flip(['id_campeonato', 'nome_campeonato', 'descricao_campeonato', 'tipo_campeonato',
            'tipo_label', 'banner_url', 'data_inicio_campeonato', 'situacao', 'convocada'])));
        $this->assertStringEndsWith('/futebol/images/campeonatos/logo.png', $data[0]['logo_url']);
        // Só a categoria dele (o Sub-15 do campeonato fica fora)
        $this->assertSame([['id_categoria' => $this->sub13, 'nome_categoria' => 'Sub-13', 'sexo_categoria' => 'M', 'rotulo' => 'Sub-13 Masculino']], $data[0]['categorias']);
        $this->assertSame([['id_categoria' => $this->sub13, 'rotulo' => 'Sub-13 Masculino', 'camisa' => 8]], $data[0]['convocacoes']);
    }

    public function test_pela_categoria_aparece_antes_da_convocacao_so_com_time_da_aacj(): void
    {
        $this->atleta('ana@exemplo.com', 13, $this->sub13);
        $liga = $this->campeonato('Liga', '2026-11-07', '2026-12-12');
        $this->participantes($liga, [$this->time('AACJ Sub-13', 'INTERNO', $this->sub13)], $this->sub13);
        $this->participantes($liga, [$this->time('AACJ Sub-15', 'INTERNO', $this->sub15)], $this->sub15);
        // Sub-13 só com time de fora: a AACJ não joga nela
        $soDeFora = $this->campeonato('Torneio de fora', '2026-10-01', '2026-11-30');
        $this->participantes($soDeFora, [$this->time('Rival', 'EXTERNO', $this->sub13)], $this->sub13);
        // AACJ só em outra categoria
        $outraCategoria = $this->campeonato('Copa Sub-15', '2026-10-01', '2026-11-30');
        $this->participantes($outraCategoria, [$this->time('AACJ Sub-15 B', 'INTERNO', $this->sub15)], $this->sub15);

        $data = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/campeonatos-do-atleta')->assertOk()->json('data');

        $this->assertSame([$liga], array_column($data, 'id_campeonato'));
        $this->assertSame(['PROXIMO', false, [], ['Sub-13 Masculino']],
            [$data[0]['situacao'], $data[0]['convocada'], $data[0]['convocacoes'], array_column($data[0]['categorias'], 'rotulo')]);
    }

    public function test_inativo_e_terminado_ficam_fora_e_a_ordem_poe_em_andamento_antes(): void
    {
        $atleta = $this->atleta('ana@exemplo.com', 13, $this->sub13);
        $aacj = $this->time('AACJ Sub-13', 'INTERNO', $this->sub13);
        $ids = [];
        foreach ([
            'proximo_tarde'   => ['2026-12-01', '2026-12-20'],
            'andamento_longo' => ['2026-10-01', '2026-12-31'],
            'proximo_cedo'    => ['2026-11-01', '2026-11-30'],
            'andamento_curto' => ['2026-10-20', '2026-10-26'],   // termina hoje: ainda aparece
            'terminado'       => ['2026-09-01', '2026-10-25'],
        ] as $nome => [$inicio, $fim]) {
            $ids[$nome] = $this->campeonato($nome, $inicio, $fim);
            $this->participantes($ids[$nome], [$aacj], $this->sub13);
        }
        $inativo = $this->campeonato('inativo', '2026-10-01', '2026-11-30', ['status_campeonato' => 'INATIVO']);
        $this->participantes($inativo, [$aacj], $this->sub13);
        $this->convocacaoDireta($inativo, $aacj, $atleta, 10);

        $data = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/campeonatos-do-atleta')->assertOk()->json('data');

        $this->assertSame(['andamento_curto', 'andamento_longo', 'proximo_cedo', 'proximo_tarde'], array_column($data, 'nome_campeonato'));
        $this->assertSame(['EM_ANDAMENTO', 'EM_ANDAMENTO', 'PROXIMO', 'PROXIMO'], array_column($data, 'situacao'));
    }

    public function test_convocado_e_da_categoria_no_mesmo_campeonato_traz_as_duas(): void
    {
        $atleta = $this->atleta('ana@exemplo.com', 13, $this->sub13);
        $copa = $this->campeonato('Copa', '2026-10-01', '2026-11-30');
        $this->participantes($copa, [$this->time('AACJ Sub-13', 'INTERNO', $this->sub13)], $this->sub13);
        $aacjSub15 = $this->time('AACJ Sub-15', 'INTERNO', $this->sub15);
        $this->participantes($copa, [$aacjSub15], $this->sub15);
        $this->convocacaoDireta($copa, $aacjSub15, $atleta, 5);

        $data = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/campeonatos-do-atleta')->assertOk()->json('data');

        $this->assertSame(['Sub-13 Masculino', 'Sub-15 Masculino'], array_column($data[0]['categorias'], 'rotulo'));
        $this->assertSame([['id_categoria' => $this->sub15, 'rotulo' => 'Sub-15 Masculino', 'camisa' => 5]], $data[0]['convocacoes']);
    }

    public function test_sem_campeonato_vem_vazio_e_sem_token_401(): void
    {
        $this->atleta('ana@exemplo.com', 13, $this->sub13);

        $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/campeonatos-do-atleta')->assertOk()->assertExactJson(['success' => true, 'data' => []]);
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/campeonatos-do-atleta')->assertUnauthorized();
    }

    public function test_responsavel_ve_o_mesmo_so_pelo_proprio_filho(): void
    {
        $filho = $this->atleta('ana@exemplo.com', 13, $this->sub13);
        $deOutraFamilia = $this->atleta('bia@exemplo.com', 13, $this->sub13);
        $liga = $this->campeonato('Liga', '2026-10-01', '2026-11-30');
        $this->participantes($liga, [$this->time('AACJ Sub-13', 'INTERNO', $this->sub13)], $this->sub13);
        $responsavel = $this->responsavel('mae@exemplo.com');
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $filho, 'id_responsavel' => $responsavel, 'grau_parentesco_responsavel' => 'Mãe']);

        $tokenAtleta = $this->login('ana@exemplo.com');
        $doAtleta = $this->comToken($tokenAtleta)->getJson('/api/v1/campeonatos-do-atleta')->json();
        $tokenResponsavel = $this->login('mae@exemplo.com', 'responsavel');

        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$filho}/campeonatos-do-atleta")
            ->assertOk()->assertExactJson($doAtleta);
        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$deOutraFamilia}/campeonatos-do-atleta")
            ->assertNotFound()->assertJsonPath('message', 'Atleta não encontrado.');
        $this->comToken($tokenResponsavel)->getJson('/api/v1/campeonatos-do-atleta')->assertForbidden();
        $this->comToken($tokenAtleta)->getJson("/api/v1/responsavel/atletas/{$filho}/campeonatos-do-atleta")->assertForbidden();
    }

    public function test_lista_publica_de_campeonatos_traz_o_tipo_label(): void
    {
        $this->campeonato('Liga', '2026-10-01', '2026-11-30');

        $this->getJson('/api/v1/campeonatos')->assertOk()
            ->assertJsonPath('data.0.tipo_campeonato', 'PONTOS CORRIDOS')
            ->assertJsonPath('data.0.tipo_label', 'Pontos Corridos');
    }

    private function campeonato(string $nome, string $inicio, string $fim, array $campos = []): int
    {
        return DB::table('tbl_campeonato')->insertGetId($campos + [
            'id_categoria' => $this->sub13, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => $nome, 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => $inicio, 'data_fim_campeonato' => $fim, 'local_evento' => 'Campo A', 'status_campeonato' => 'ATIVO',
        ]);
    }

    private function time(string $nome, string $tipo, int $idCategoria): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idCategoria, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
    }

    private function atleta(string $email, int $idade, int $idCategoria): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade($idade), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);
        $this->colocarNaCategoria($id, $idCategoria);

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
