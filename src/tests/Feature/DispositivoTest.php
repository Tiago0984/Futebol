<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Dispositivo;
use App\Models\Responsavel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class DispositivoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const CORPO = ['token' => 'ExponentPushToken[teste]', 'plataforma' => 'android', 'notificacoes_ativas' => true];

    private function atleta(): Atleta
    {
        return Atleta::findOrFail($this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO'));
    }

    private function como(Atleta|Responsavel $conta): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($conta->createToken('teste')->plainTextToken);
    }

    public function test_cria_atualiza_e_transfere_token_entre_contas_e_perfis(): void
    {
        $this->travelTo('2026-11-01 10:00:00');
        $atleta = $this->atleta();
        $outro = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($atleta->getKey()));
        $this->como($atleta)->postJson('/api/v1/dispositivos', self::CORPO)->assertOk()->assertExactJson(['success' => true]);
        $id = Dispositivo::sole()->getKey();
        $this->assertDatabaseHas('tbl_dispositivo', ['id_atleta' => $atleta->getKey(), 'id_responsavel' => null, 'ativo_dispositivo' => 1]);

        $this->travel(2)->minutes();
        $this->como($outro)->postJson('/api/v1/dispositivos', array_replace(self::CORPO, [
            'plataforma' => 'ios', 'notificacoes_ativas' => false, 'id_atleta' => $atleta->getKey(), 'id_responsavel' => $responsavel->getKey(),
        ]))->assertOk();
        $registro = Dispositivo::sole();
        $this->assertSame($id, $registro->getKey());
        $this->assertSame($outro->getKey(), $registro->id_atleta);
        $this->assertNull($registro->id_responsavel);
        $this->assertFalse($registro->ativo_dispositivo);
        $this->assertSame('ios', $registro->plataforma);
        $this->assertSame('2026-11-01 10:02:00', $registro->data_ultimo_uso->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 10:00:00', $registro->data_criacao_dispositivo->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 10:02:00', $registro->data_atualizacao_dispositivo->format('Y-m-d H:i:s'));

        $this->como($responsavel)->postJson('/api/v1/dispositivos', self::CORPO)->assertOk();
        $this->assertDatabaseHas('tbl_dispositivo', ['id_dispositivo' => $id, 'id_atleta' => null, 'id_responsavel' => $responsavel->getKey()]);
        $this->como($atleta)->postJson('/api/v1/dispositivos', self::CORPO)->assertOk();
        $this->assertDatabaseHas('tbl_dispositivo', ['id_dispositivo' => $id, 'id_atleta' => $atleta->getKey(), 'id_responsavel' => null]);
        $this->assertDatabaseCount('tbl_dispositivo', 1);
    }

    public function test_delete_so_remove_o_proprio_sem_revelar_outro_dono_ou_token_inexistente(): void
    {
        $atleta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($atleta->getKey()));
        $this->como($responsavel)->postJson('/api/v1/dispositivos', self::CORPO)->assertOk();
        $this->como($atleta)->deleteJson('/api/v1/dispositivos', ['token' => self::CORPO['token']])->assertOk()->assertExactJson(['success' => true]);
        $this->assertDatabaseCount('tbl_dispositivo', 1);
        $this->como($responsavel)->deleteJson('/api/v1/dispositivos', ['token' => 'ExpoPushToken[inexistente]'])->assertOk()->assertExactJson(['success' => true]);
        $this->como($responsavel)->deleteJson('/api/v1/dispositivos', ['token' => self::CORPO['token']])->assertOk()->assertExactJson(['success' => true]);
        $this->assertDatabaseCount('tbl_dispositivo', 0);
    }

    public function test_auth_obrigatoria_e_perfis_do_painel_recusados(): void
    {
        $this->postJson('/api/v1/dispositivos', self::CORPO)->assertUnauthorized()->assertJsonPath('success', false);
        $this->deleteJson('/api/v1/dispositivos', ['token' => self::CORPO['token']])->assertUnauthorized();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')->postJson('/api/v1/dispositivos', self::CORPO)->assertForbidden();
        $this->assertDatabaseCount('tbl_dispositivo', 0);
    }

    public static function invalidos(): iterable
    {
        yield 'sem token' => [['token' => null], 'token'];
        yield 'token array' => [['token' => []], 'token'];
        yield 'prefixo errado' => [['token' => 'OutroToken[teste]'], 'token'];
        yield 'token vazio' => [['token' => 'ExpoPushToken[]'], 'token'];
        yield 'sem fechamento' => [['token' => 'ExpoPushToken[teste'], 'token'];
        yield 'token longo' => [['token' => 'ExpoPushToken[' . str_repeat('a', 250) . ']'], 'token'];
        yield 'sem plataforma' => [['plataforma' => null], 'plataforma'];
        yield 'plataforma incorreta' => [['plataforma' => 'windows'], 'plataforma'];
        yield 'sem preferencia' => [['notificacoes_ativas' => null], 'notificacoes_ativas'];
        yield 'preferencia texto' => [['notificacoes_ativas' => 'false'], 'notificacoes_ativas'];
    }

    #[DataProvider('invalidos')]
    public function test_validacao_por_campo(array $extra, string $campo): void
    {
        $this->como($this->atleta())->postJson('/api/v1/dispositivos', array_replace(self::CORPO, $extra))
            ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonValidationErrors($campo);
        $this->assertDatabaseCount('tbl_dispositivo', 0);
    }

    public function test_delete_tambem_valida_token(): void
    {
        $this->como($this->atleta())->deleteJson('/api/v1/dispositivos', [])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_tokens_diferenciam_maiusculas_e_limite_de_255_e_aceito(): void
    {
        $conta = $this->atleta();
        foreach (['ExpoPushToken[abc]', 'ExpoPushToken[ABC]', 'ExpoPushToken[' . str_repeat('a', 240) . ']'] as $token) {
            $this->como($conta)->postJson('/api/v1/dispositivos', array_replace(self::CORPO, ['token' => $token]))->assertOk();
        }
        $this->assertDatabaseCount('tbl_dispositivo', 3);
    }

    public function test_banco_exige_exatamente_um_dono_e_exclui_em_cascata(): void
    {
        $atleta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($atleta->getKey()));
        $base = ['token' => 'ExpoPushToken[check]', 'plataforma' => 'android', 'data_ultimo_uso' => now(),
            'data_criacao_dispositivo' => now(), 'data_atualizacao_dispositivo' => now()];
        foreach ([[null, null], [$atleta->getKey(), $responsavel->getKey()]] as [$idAtleta, $idResponsavel]) {
            try {
                DB::table('tbl_dispositivo')->insert($base + ['id_atleta' => $idAtleta, 'id_responsavel' => $idResponsavel]);
                $this->fail('CHECK deveria recusar dono inválido.');
            } catch (QueryException $erro) {
                $this->assertStringContainsString('dispositivo_um_dono', $erro->getMessage());
            }
        }
        $this->como($atleta)->postJson('/api/v1/dispositivos', self::CORPO)->assertOk();
        $this->como($responsavel)->postJson('/api/v1/dispositivos', array_replace(self::CORPO, ['token' => 'ExpoPushToken[responsavel]']))->assertOk();
        DB::table('tbl_atleta_responsavel')->where('id_atleta', $atleta->getKey())->delete();
        DB::table('tbl_atletas')->where('id_atleta', $atleta->getKey())->delete();
        $this->assertDatabaseCount('tbl_dispositivo', 1);
        DB::table('tbl_responsavel')->where('id_responsavel', $responsavel->getKey())->delete();
        $this->assertDatabaseCount('tbl_dispositivo', 0);
    }
}
