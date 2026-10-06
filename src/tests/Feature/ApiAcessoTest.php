<?php

namespace Tests\Feature;

use App\Models\Atleta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 9, Etapa A — segurança do app atual: tokens apagados ao inativar ou rejeitar, atleta não ATIVO
 * recusado nas rotas protegidas, token com validade de 30 dias e limite de tentativas por IP e por e-mail.
 * O formato da resposta do login não muda.
 */
class ApiAcessoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'senha-de-teste';

    // ---------- login ----------

    public function test_login_mantem_o_formato_da_resposta_e_o_token_vale_30_dias(): void
    {
        $this->travelTo('2026-10-10 10:00:00');
        $id = $this->atletaComLogin('ana@exemplo.com');

        $resposta = $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => self::SENHA])->assertOk();

        // Exatamente as mesmas chaves de antes (o token só se conhece pela resposta)
        $this->assertNotEmpty($resposta->json('data.token'));
        $resposta->assertExactJson([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'data'    => [
                'token'  => $resposta->json('data.token'),
                'atleta' => [
                    'id_atleta' => $id, 'nome_atleta' => 'Atleta de Teste', 'email_atleta' => 'ana@exemplo.com',
                    'numero_matricula_atleta' => null, 'foto_atleta' => null,
                ],
            ],
        ]);
        $this->assertSame('2026-11-09 10:00:00', DB::table('personal_access_tokens')->value('expires_at'));
    }

    public function test_token_vale_ate_o_30o_dia_e_depois_e_recusado(): void
    {
        $this->travelTo('2026-10-10 10:00:00');
        $this->atletaComLogin('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');

        $this->travelTo('2026-11-09 09:59:00');
        $this->comToken($token)->getJson('/api/v1/atleta')->assertOk();

        $this->travelTo('2026-11-09 10:01:00');
        $this->comToken($token)->getJson('/api/v1/atleta')->assertUnauthorized();
    }

    // ---------- inativar ou rejeitar apaga os tokens ----------

    public function test_inativar_pelo_admin_apaga_os_tokens_e_o_app_perde_o_acesso(): void
    {
        $id    = $this->atletaComLogin('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');
        $this->comToken($token)->getJson('/api/v1/atleta')->assertOk();

        $this->comoAdmin()->patch(route('admin.atletas.toggleStatus', $id))->assertSessionHas('sucesso');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->comToken($token)->getJson('/api/v1/atleta')->assertUnauthorized();

        // Reativar não devolve o token: precisa logar de novo
        $this->comoAdmin()->patch(route('admin.atletas.toggleStatus', $id));
        $this->comToken($token)->getJson('/api/v1/atleta')->assertUnauthorized();
        $this->login('ana@exemplo.com');
    }

    public function test_rejeitar_e_editar_para_inativo_tambem_apagam_os_tokens(): void
    {
        $rejeitado = $this->atletaComLogin('bia@exemplo.com');
        $this->login('bia@exemplo.com');
        $this->comoAdmin()->patch(route('admin.matriculas.rejeitar', $rejeitado));
        $this->assertSame(0, Atleta::find($rejeitado)->tokens()->count());

        // Qualquer mudança de status pelo model (aqui, a da edição) passa pelo mesmo ponto
        $inativo = $this->atletaComLogin('caio@exemplo.com');
        $this->login('caio@exemplo.com');
        Atleta::find($inativo)->update(['status_atleta' => 'INATIVO']);
        $this->assertSame(0, Atleta::find($inativo)->tokens()->count());
    }

    public function test_mudar_outro_campo_do_atleta_ativo_nao_apaga_os_tokens(): void
    {
        $id = $this->atletaComLogin('ana@exemplo.com');
        $this->login('ana@exemplo.com');

        Atleta::find($id)->update(['escola_atleta' => 'Escola Nova']);

        $this->assertSame(1, Atleta::find($id)->tokens()->count());
    }

    // ---------- middleware: atleta não ATIVO é recusado mesmo com token ----------

    public function test_rota_protegida_recusa_atleta_que_nao_esta_ativo_mesmo_com_token_valido(): void
    {
        $id    = $this->atletaComLogin('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');

        // Status mudado direto no banco (sem o model): o token continua lá, mas a rota recusa
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['status_atleta' => 'INATIVO']);

        $this->comToken($token)->getJson('/api/v1/atleta')
            ->assertForbidden()
            ->assertExactJson(['success' => false, 'message' => 'Cadastro não está ativo. Procure a secretaria da escolinha.']);
        $this->assertSame(0, DB::table('personal_access_tokens')->count()); // e os tokens dele saem

        foreach (['PUT' => '/api/v1/atleta', 'PATCH' => '/api/v1/atleta'] as $metodo => $rota) {
            $this->comToken($token)->json($metodo, $rota, ['escola_atleta' => 'X'])->assertUnauthorized();
        }
    }

    public function test_rota_protegida_continua_aceitando_atleta_ativo(): void
    {
        $this->atletaComLogin('ana@exemplo.com');
        $token = $this->login('ana@exemplo.com');

        $this->comToken($token)->getJson('/api/v1/atleta')->assertOk()->assertJsonPath('data.email_atleta', 'ana@exemplo.com');
        $this->comToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    // ---------- limite de tentativas ----------

    public function test_limite_de_5_tentativas_por_minuto_no_mesmo_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/login', ['email' => "ninguem{$i}@exemplo.com", 'senha' => 'x'])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'outro@exemplo.com', 'senha' => 'x'])->assertStatus(429);

        // Passado o minuto, libera
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/login', ['email' => 'outro@exemplo.com', 'senha' => 'x'])->assertUnauthorized();
    }

    public function test_limite_de_5_tentativas_por_minuto_no_mesmo_email_mesmo_trocando_de_ip(): void
    {
        $this->atletaComLogin('ana@exemplo.com');

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => 'errada'])->assertUnauthorized();
        }

        // Sexta tentativa, de outro IP e com a senha certa: bloqueada pelo e-mail (maiúsculas não escapam)
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/v1/auth/login', ['email' => 'ANA@exemplo.com ', 'senha' => self::SENHA])->assertStatus(429);

        // Outro e-mail, de outro IP: livre
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.100'])
            ->postJson('/api/v1/auth/login', ['email' => 'bia@exemplo.com', 'senha' => 'x'])->assertUnauthorized();
    }

    // ---------- helpers ----------

    // Atleta ATIVO (com categoria, para o botão de status poder reativar) com e-mail e senha conhecida
    private function atletaComLogin(string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);

        return $id;
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => self::SENHA])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        // Cada requisição resolve o token de novo (sem reaproveitar o usuário da anterior)
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
