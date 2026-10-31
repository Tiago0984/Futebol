<?php

namespace Tests\Feature;

use App\Mail\CodigoRecuperacaoSenhaMail;
use App\Models\Atleta;
use App\Models\RecuperacaoSenha;
use App\Models\Responsavel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class RecuperacaoSenhaCodigoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'senha-antiga-123';
    private const NOVA = 'senha-nova-456';
    private const ERRO = 'Código inválido ou expirado.';
    private const GENERICA = ['success' => true, 'message' => 'Se o e-mail estiver cadastrado, enviaremos um código de verificação.'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-31 10:00:00');
    }

    private function conta(string $perfil = 'atleta', string $email = 'pessoa@example.test'): Atleta|Responsavel
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        if ($perfil === 'responsavel') {
            $idResponsavel = $this->criarResponsavel($id);
            DB::table('tbl_responsavel')->where('id_responsavel', $idResponsavel)
                ->update(['email_responsavel' => $email, 'senha_responsavel' => Hash::make(self::SENHA)]);

            return Responsavel::findOrFail($idResponsavel);
        }
        DB::table('tbl_atletas')->where('id_atleta', $id)
            ->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);

        return Atleta::findOrFail($id);
    }

    private function identidade(Atleta|Responsavel $conta): array
    {
        return ['email' => $conta->emailDoApp(), 'perfil' => $conta::PERFIL];
    }

    private function solicitar(Atleta|Responsavel $conta): string
    {
        $this->postJson('/api/v1/auth/esqueci-senha', $this->identidade($conta))->assertOk()->assertExactJson(self::GENERICA);

        return Mail::sent(CodigoRecuperacaoSenhaMail::class)->last()->codigo;
    }

    private function validar(Atleta|Responsavel $conta, string $codigo, string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/validar-codigo',
            $this->identidade($conta) + ['codigo' => $codigo]);
    }

    private function redefinir(string $token, array $campos = [])
    {
        return $this->postJson('/api/v1/auth/redefinir-senha', array_replace([
            'reset_token' => $token, 'nova_senha' => self::NOVA, 'nova_senha_confirmation' => self::NOVA,
        ], $campos));
    }

    private function registro(Atleta|Responsavel $conta): RecuperacaoSenha
    {
        return RecuperacaoSenha::where('id_' . $conta::PERFIL, $conta->getKey())->sole();
    }

    public static function perfis(): iterable
    {
        yield 'atleta' => ['atleta'];
        yield 'responsável' => ['responsavel'];
    }

    #[DataProvider('perfis')]
    public function test_fluxo_completo_hashes_uso_unico_login_e_revogacao(string $perfil): void
    {
        $conta = $this->conta($perfil);
        $sessaoAntiga = $conta->createToken('aparelho1')->plainTextToken;
        $conta->createToken('aparelho2');
        $linkAntigo = $conta::broker()->createToken($conta);
        $codigo = $this->solicitar($conta);
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/D', $codigo);
        $registro = $this->registro($conta);
        $this->assertTrue(Hash::check($codigo, $registro->codigo_hash_recuperacao_senha));
        $this->assertNotSame($codigo, $registro->codigo_hash_recuperacao_senha);
        $this->assertSame('2026-10-31 10:15:00', $registro->data_expiracao_codigo_recuperacao_senha->toDateTimeString());
        $this->assertArrayNotHasKey('codigo_hash_recuperacao_senha', $registro->toArray());
        $this->assertTrue($conta::broker()->tokenExists($conta, $linkAntigo)); // Pedir código não desfaz convites.

        $resposta = $this->validar($conta, $codigo)->assertOk();
        $token = $resposta->json('data.reset_token');
        $resposta->assertExactJson(['success' => true, 'data' => ['reset_token' => $token]]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
        $registro->refresh();
        $this->assertNull($registro->codigo_hash_recuperacao_senha);
        $this->assertSame(hash('sha256', $token), $registro->reset_token_hash_recuperacao_senha);
        $this->assertSame('2026-10-31 10:10:00', $registro->data_expiracao_token_recuperacao_senha->toDateTimeString());
        $this->validar($conta, $codigo)->assertUnprocessable()->assertJsonPath('message', self::ERRO);

        $this->redefinir($token)->assertOk()->assertExactJson([
            'success' => true, 'message' => 'Senha redefinida com sucesso. Faça login novamente.',
        ]);
        $this->assertTrue(Hash::check(self::NOVA, $conta->fresh()->getAuthPassword()));
        $this->assertSame(0, $conta->tokens()->count());
        $this->assertNull($this->registro($conta)->reset_token_hash_recuperacao_senha);
        $this->assertNull($this->registro($conta)->codigo_hash_recuperacao_senha);
        $this->solicitar($conta);
        Mail::assertSent(CodigoRecuperacaoSenhaMail::class, 1); // A troca concluída também preserva os 60 segundos.
        $this->assertFalse($conta::broker()->tokenExists($conta, $linkAntigo));
        $this->redefinir($token)->assertUnprocessable()->assertJsonValidationErrors('reset_token');

        $this->app['auth']->forgetGuards();
        $this->withToken($sessaoAntiga)->getJson('/api/v1/' . $perfil)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $this->identidade($conta) + ['senha' => self::NOVA])->assertOk();
        $this->postJson('/api/v1/auth/login', $this->identidade($conta) + ['senha' => self::SENHA])->assertUnauthorized();
    }

    public function test_zeros_iniciais_sao_aceitos_e_email_e_normalizado(): void
    {
        $conta = $this->conta();
        $this->solicitar($conta);
        $this->registro($conta)->update(['codigo_hash_recuperacao_senha' => Hash::make('0007')]);
        $this->postJson('/api/v1/auth/validar-codigo', [
            'email' => ' PESSOA@example.test ', 'codigo' => '0007', // Sem perfil continua sendo atleta.
        ])->assertOk()->assertJsonStructure(['success', 'data' => ['reset_token']]);
        $html = (new CodigoRecuperacaoSenhaMail('Ana', 'atleta', '0007'))->render();
        $this->assertStringContainsString('0007', $html);
        $this->assertStringContainsString('15 minutos', $html);
    }

    public function test_quinto_erro_invalida_codigo_mesmo_trocando_ip(): void
    {
        $conta = $this->conta();
        $codigo = $this->solicitar($conta);
        $errado = $codigo === '0000' ? '0001' : '0000';
        for ($i = 1; $i <= 5; $i++) {
            $this->validar($conta, $errado, "192.0.2.{$i}")->assertUnprocessable()
                ->assertJsonPath('message', self::ERRO)->assertJsonPath('errors.codigo.0', self::ERRO);
            $this->assertSame($i, $this->registro($conta)->tentativas_recuperacao_senha);
        }
        $this->assertNull($this->registro($conta)->codigo_hash_recuperacao_senha);
        $this->validar($conta, $codigo, '192.0.2.6')->assertUnprocessable()->assertJsonPath('message', self::ERRO);
        $this->assertNull($this->registro($conta)->reset_token_hash_recuperacao_senha);
    }

    public function test_quatro_erros_ainda_permite_validar_codigo_correto(): void
    {
        $conta = $this->conta();
        $codigo = $this->solicitar($conta);
        for ($i = 0; $i < 4; $i++) {
            $this->validar($conta, $codigo === '0000' ? '0001' : '0000')->assertUnprocessable();
        }
        $this->validar($conta, $codigo)->assertOk();
    }

    public function test_codigo_expira_exatamente_em_15_minutos(): void
    {
        $conta = $this->conta();
        $codigo = $this->solicitar($conta);
        $this->travel(15)->minutes();
        $this->validar($conta, $codigo)->assertUnprocessable()->assertJsonPath('message', self::ERRO);
    }

    public function test_token_expira_exatamente_em_10_minutos_e_nao_troca_senha(): void
    {
        $conta = $this->conta();
        $token = $this->validar($conta, $this->solicitar($conta))->json('data.reset_token');
        $this->travel(10)->minutes();
        $this->redefinir($token)->assertUnprocessable()->assertJsonValidationErrors('reset_token');
        $this->assertTrue(Hash::check(self::SENHA, $conta->fresh()->getAuthPassword()));
    }

    public function test_cooldown_e_reenvio_invalida_token_anterior(): void
    {
        $conta = $this->conta();
        $codigo = $this->solicitar($conta);
        $token = $this->validar($conta, $codigo)->json('data.reset_token');
        $this->travel(59)->seconds();
        $this->solicitar($conta);
        Mail::assertSent(CodigoRecuperacaoSenhaMail::class, 1);
        $this->assertSame(hash('sha256', $token), $this->registro($conta)->reset_token_hash_recuperacao_senha);
        $this->travel(1)->seconds();
        $novo = $this->solicitar($conta);
        Mail::assertSent(CodigoRecuperacaoSenhaMail::class, 2);
        $this->assertNull($this->registro($conta)->reset_token_hash_recuperacao_senha);
        $this->assertSame(0, $this->registro($conta)->tentativas_recuperacao_senha);
        $this->redefinir($token)->assertUnprocessable();
        $this->validar($conta, $novo)->assertOk();
    }

    public function test_mesmo_email_em_perfis_diferentes_nao_mistura_recuperacoes_nem_sessoes(): void
    {
        $atleta = $this->conta();
        $responsavel = $this->conta('responsavel');
        $this->solicitar($atleta);
        $this->solicitar($responsavel);
        $this->registro($atleta)->update(['codigo_hash_recuperacao_senha' => Hash::make('0001')]);
        $this->registro($responsavel)->update(['codigo_hash_recuperacao_senha' => Hash::make('0002')]);
        $atleta->createToken('atleta');
        $responsavel->createToken('responsavel');
        $this->validar($responsavel, '0001')->assertUnprocessable();
        $token = $this->validar($responsavel, '0002')->json('data.reset_token');
        $this->redefinir($token)->assertOk();
        $this->assertSame(1, $atleta->tokens()->count());
        $this->assertTrue(Hash::check(self::SENHA, $atleta->fresh()->getAuthPassword()));
        $this->validar($atleta, '0001')->assertOk();
    }

    public static function senhasInvalidas(): iterable
    {
        yield 'curta' => [['nova_senha' => '1234567', 'nova_senha_confirmation' => '1234567'], 'A nova senha precisa ter pelo menos 8 caracteres.'];
        yield 'diferentes' => [['nova_senha_confirmation' => 'outra-senha'], 'A confirmação não é igual à nova senha.'];
        yield 'sem confirmação' => [['nova_senha_confirmation' => null], 'A confirmação não é igual à nova senha.'];
        yield 'vazia' => [['nova_senha' => ''], 'Informe a nova senha.'];
        yield 'array' => [['nova_senha' => ['senha']], 'A nova senha deve ser um texto.'];
    }

    #[DataProvider('senhasInvalidas')]
    public function test_senha_invalida_retorna_errors_por_campo_sem_consumir_token(array $campos, string $erro): void
    {
        $conta = $this->conta();
        $token = $this->validar($conta, $this->solicitar($conta))->json('data.reset_token');
        $this->redefinir($token, $campos)->assertUnprocessable()->assertJsonPath('errors.nova_senha.0', $erro);
        $this->assertTrue(Hash::check(self::SENHA, $conta->fresh()->getAuthPassword()));
        $this->redefinir($token)->assertOk();
    }

    public function test_codigo_inexistente_malformado_e_token_invalido_nao_trocam_senha(): void
    {
        foreach (['1234', '123', 'abcd', 1234, ['1234']] as $i => $codigo) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$i}"])
                ->postJson('/api/v1/auth/validar-codigo', ['email' => 'ninguem@example.test', 'codigo' => $codigo])
                ->assertUnprocessable()->assertJsonPath('message', self::ERRO);
        }
        $this->redefinir(str_repeat('a', 64))->assertUnprocessable()->assertJsonValidationErrors('reset_token');
        $this->redefinir('invalido')->assertUnprocessable()->assertJsonValidationErrors('reset_token');
        Mail::assertNothingSent();
    }

    #[DataProvider('perfis')]
    public function test_perda_de_acesso_impede_validacao_e_redefinicao(string $perfil): void
    {
        $conta = $this->conta($perfil);
        $codigo = $this->solicitar($conta);
        $token = $this->validar($conta, $codigo)->json('data.reset_token');
        $idAtleta = $perfil === 'atleta' ? $conta->getKey() : $conta->atletas()->first()->getKey();
        DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->update(['status_atleta' => 'INATIVO']);
        $this->validar($conta, $codigo)->assertUnprocessable();
        $this->redefinir($token)->assertUnprocessable();
        $this->assertTrue(Hash::check(self::SENHA, $conta->fresh()->getAuthPassword()));
    }

    public function test_alterar_email_invalida_recuperacao_do_email_anterior(): void
    {
        foreach ([null, 'outro@example.test'] as $emailAlterado) {
            $conta = $this->conta();
            $codigo = $this->solicitar($conta);
            $token = $this->validar($conta, $codigo)->json('data.reset_token');
            DB::table('tbl_atletas')->where('id_atleta', $conta->getKey())->update(['email_atleta' => $emailAlterado]);
            $this->redefinir($token)->assertUnprocessable();
            $this->assertTrue(Hash::check(self::SENHA, $conta->fresh()->getAuthPassword()));
        }
    }

    public function test_email_transferido_para_outra_conta_nao_reaproveita_recuperacao_antiga(): void
    {
        $anterior = $this->conta();
        $nova = $this->conta('atleta', 'outra@example.test');
        $tokenAntigo = $this->validar($anterior, $this->solicitar($anterior))->json('data.reset_token');
        DB::table('tbl_atletas')->where('id_atleta', $anterior->getKey())->update(['email_atleta' => 'mudou@example.test']);
        DB::table('tbl_atletas')->where('id_atleta', $nova->getKey())->update(['email_atleta' => 'pessoa@example.test']);
        $nova->refresh();
        $codigo = $this->solicitar($nova);
        $this->assertDatabaseCount('tbl_recuperacao_senha', 2);
        $this->redefinir($tokenAntigo)->assertUnprocessable();
        $tokenNovo = $this->validar($nova, $codigo)->json('data.reset_token');
        $this->redefinir($tokenNovo)->assertOk();
        $this->assertTrue(Hash::check(self::SENHA, $anterior->fresh()->getAuthPassword()));
        $this->assertTrue(Hash::check(self::NOVA, $nova->fresh()->getAuthPassword()));
    }

    public function test_link_de_convite_continua_funcionando_e_invalida_token_de_codigo(): void
    {
        $conta = $this->conta();
        $link = $conta::broker()->createToken($conta);
        $token = $this->validar($conta, $this->solicitar($conta))->json('data.reset_token');
        $this->post(route('senha.salvar', 'atleta'), [
            'email' => $conta->emailDoApp(), 'token' => $link, 'senha' => self::NOVA, 'senha_confirmation' => self::NOVA,
        ])->assertRedirect(route('senha.definida'));
        $this->assertNull($this->registro($conta)->reset_token_hash_recuperacao_senha);
        $this->assertNull($this->registro($conta)->codigo_hash_recuperacao_senha);
        $this->redefinir($token)->assertUnprocessable();
    }

    #[DataProvider('perfis')]
    public function test_troca_autenticada_preserva_sessao_atual_e_invalida_recuperacao(string $perfil): void
    {
        $conta = $this->conta($perfil);
        $tokenReset = $this->validar($conta, $this->solicitar($conta))->json('data.reset_token');
        $link = $conta::broker()->createToken($conta);
        $tokenAtual = $conta->createToken('atual')->plainTextToken;
        $conta->createToken('outro');
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenAtual)->putJson("/api/v1/{$perfil}/senha", [
            'senha_atual' => self::SENHA, 'nova_senha' => self::NOVA, 'nova_senha_confirmation' => self::NOVA,
        ])->assertOk();
        $this->assertSame(1, $conta->tokens()->count());
        $this->assertNull($this->registro($conta)->reset_token_hash_recuperacao_senha);
        $this->assertNull($this->registro($conta)->codigo_hash_recuperacao_senha);
        $this->assertFalse($conta::broker()->tokenExists($conta, $link));
        $this->redefinir($tokenReset)->assertUnprocessable();
    }

    public static function rotasPublicas(): iterable
    {
        yield 'solicitar' => ['/auth/esqueci-senha'];
        yield 'validar' => ['/auth/validar-codigo'];
        yield 'redefinir' => ['/auth/redefinir-senha'];
    }

    #[DataProvider('rotasPublicas')]
    public function test_limite_por_ip_com_retry_after_exposto_por_cors(string $rota): void
    {
        $this->withHeader('Origin', 'https://app.example.test');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1' . $rota, [
                'email' => "pessoa{$i}@example.test", 'codigo' => '1234',
                'reset_token' => str_repeat('a', 64), 'nova_senha' => self::NOVA, 'nova_senha_confirmation' => self::NOVA,
            ])->assertStatus($rota === '/auth/esqueci-senha' ? 200 : 422);
        }
        $this->postJson('/api/v1' . $rota, [])->assertTooManyRequests()->assertHeader('Retry-After')
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After')
            ->assertJsonPath('message', 'Muitas tentativas. Aguarde um pouco e tente de novo.');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])->postJson('/api/v1' . $rota, [])->assertUnprocessable();
    }

    public function test_falha_no_smtp_mantem_resposta_generica_sem_logar_segredos(): void
    {
        $conta = $this->conta();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP indisponível'));
        Log::spy();
        $this->postJson('/api/v1/auth/esqueci-senha', $this->identidade($conta))->assertExactJson(self::GENERICA);
        Log::shouldHaveReceived('warning')->once()->with('Falha ao enviar código de recuperação do app.', [
            'perfil' => 'atleta', 'id' => $conta->getKey(), 'tipo_erro' => RuntimeException::class,
        ]);
    }
}
