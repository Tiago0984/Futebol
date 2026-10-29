<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Local do evento na agenda do app (Etapa L3, 29/10/2026): local_detalhe { nome, endereco, mapa_url } quando o evento
 * tem um local cadastrado (o desativado também); null com o texto antigo ou sem local. Na lista, no período, no
 * detalhe e nas mesmas rotas do responsável.
 */
class AppAgendaLocalTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 12:00:00');
    }

    public function test_local_cadastrado_com_link_sem_link_texto_antigo_e_sem_local(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $comLink = $this->local('Campo da AACJ', ['mapa_url_local' => 'https://maps.app.goo.gl/AbCdEf123', 'cep_local' => '13560-000']);
        $semLink = $this->local('Arena Norte', ['complemento_local' => 'Portão 2']);
        $this->evento('Com link', '2026-10-13', [$atleta], 'Campo da AACJ', $comLink);
        $this->evento('Sem link', '2026-10-14', [$atleta], 'Arena Norte', $semLink);
        $this->evento('Texto antigo', '2026-10-15', [$atleta], 'Quadra do bairro', null);
        $this->evento('Sem local', '2026-10-16', [$atleta], null, null);

        $eventos = collect($this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda')->assertOk()->json('data.proximos'))
            ->keyBy('titulo');

        $this->assertSame([
            'nome'     => 'Campo da AACJ',
            'endereco' => 'Rua Um, 10 – Centro, São Carlos/SP, 13560-000',
            'mapa_url' => 'https://maps.app.goo.gl/AbCdEf123',
        ], $eventos['Com link']['local_detalhe']);

        // Sem link: a busca do Google Maps com o nome e o endereço; sem CEP, o endereço termina na UF
        $this->assertSame('Rua Um, 10, Portão 2 – Centro, São Carlos/SP', $eventos['Sem link']['local_detalhe']['endereco']);
        $this->assertSame('https://www.google.com/maps/search/?api=1&query='
            . rawurlencode('Arena Norte, Rua Um, 10, Portão 2 – Centro, São Carlos/SP'), $eventos['Sem link']['local_detalhe']['mapa_url']);

        $this->assertSame('Quadra do bairro', $eventos['Texto antigo']['local']);
        $this->assertNull($eventos['Texto antigo']['local_detalhe']);
        $this->assertNull($eventos['Sem local']['local']);
        $this->assertNull($eventos['Sem local']['local_detalhe']);
    }

    public function test_local_desativado_continua_no_evento(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $idLocal = $this->local('Campo Velho', ['status_local' => 'INATIVO']);
        $this->evento('Treino', '2026-10-13', [$atleta], 'Campo Velho', $idLocal);

        $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda')->assertOk()
            ->assertJsonPath('data.proximos.0.local_detalhe.nome', 'Campo Velho');
    }

    public function test_periodo_detalhe_e_responsavel_trazem_o_mesmo_local_detalhe(): void
    {
        $filho = $this->atleta('ana@exemplo.com');
        $responsavel = $this->responsavel('mae@exemplo.com');
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $filho, 'id_responsavel' => $responsavel, 'grau_parentesco_responsavel' => 'Mãe']);
        $idLocal = $this->local('Campo da AACJ');
        $treino = $this->evento('Treino', '2026-10-13', [$filho], 'Campo da AACJ', $idLocal);
        $esperado = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda')->json('data.proximos.0.local_detalhe');
        $this->assertSame('Campo da AACJ', $esperado['nome']);

        $tokenAtleta = $this->login('ana@exemplo.com');
        $this->comToken($tokenAtleta)->getJson('/api/v1/agenda?de=2026-10-01&ate=2026-10-31')
            ->assertOk()->assertJsonPath('data.eventos.0.local_detalhe', $esperado);
        $this->comToken($tokenAtleta)->getJson("/api/v1/agenda/{$treino}")
            ->assertOk()->assertJsonPath('data.local_detalhe', $esperado);

        $tokenResponsavel = $this->login('mae@exemplo.com', 'responsavel');
        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$filho}/agenda")
            ->assertOk()->assertJsonPath('data.proximos.0.local_detalhe', $esperado);
        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$filho}/agenda/{$treino}")
            ->assertOk()->assertJsonPath('data.local_detalhe', $esperado);
    }

    public function test_locais_carregados_numa_consulta_so(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        foreach (range(1, 5) as $n) {
            $idLocal = $this->local("Campo {$n}");
            $this->evento("Treino {$n}", '2026-10-1' . $n, [$atleta], "Campo {$n}", $idLocal);
        }
        $token = $this->login('ana@exemplo.com');

        DB::enableQueryLog();
        $this->comToken($token)->getJson('/api/v1/agenda')->assertOk()->assertJsonCount(5, 'data.proximos');
        $consultasDoLocal = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `tbl_local`'));
        DB::disableQueryLog();

        // Uma para os próximos e uma para os passados (vazios: o eager load nem consulta), nunca uma por evento
        $this->assertLessThanOrEqual(2, $consultasDoLocal->count());
    }

    private function local(string $nome, array $extra = []): int
    {
        return DB::table('tbl_local')->insertGetId(array_merge([
            'nome_local' => $nome, 'rua_local' => 'Rua Um', 'numero_local' => '10', 'bairro_local' => 'Centro',
            'cidade_local' => 'São Carlos', 'estado_local' => 'SP', 'status_local' => 'ATIVO',
        ], $extra));
    }

    private function evento(string $titulo, string $data, array $inscritos, ?string $local, ?int $idLocal): int
    {
        $id = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => $data,
            'horario_inicio_evento_calendario' => '18:00:00', 'local_evento_calendario' => $local, 'id_local' => $idLocal,
            'status_evento_calendario' => 'ATIVO',
        ]);

        foreach ($inscritos as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert([
                'id_evento_calendario' => $id, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL', 'data_evento_atleta' => now(),
            ]);
        }

        return $id;
    }

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
