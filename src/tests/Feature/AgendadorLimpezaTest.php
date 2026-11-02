<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\RecuperacaoSenha;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class AgendadorLimpezaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-02 10:00:00');
    }

    public function test_limpezas_ficam_agendadas_de_madrugada(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($e) => [trim(str($e->command)->after("'artisan'")->toString()) => $e->expression]);

        $this->assertSame([
            'sanctum:prune-expired --hours=24' => '0 3 * * *',
            'auth:clear-resets users' => '5 3 * * *',
            'auth:clear-resets atletas' => '5 3 * * *',
            'auth:clear-resets responsaveis' => '5 3 * * *',
            'model:prune' => '10 3 * * *',
            'queue:prune-failed --hours=720' => '15 3 * * *',
            'queue:work --stop-when-empty --max-time=50' => '* * * * *',
        ], $eventos->all());
    }

    public function test_apaga_so_recuperacoes_vencidas_ha_mais_de_um_dia(): void
    {
        $recuperacao = function (string $envio, ?string $fimCodigo, ?string $fimToken): int {
            return RecuperacaoSenha::create([
                'id_atleta' => $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO'),
                'perfil_recuperacao_senha' => 'ATLETA',
                'email_recuperacao_senha' => uniqid() . '@example.test',
                'data_codigo_recuperacao_senha' => $envio,
                'data_expiracao_codigo_recuperacao_senha' => $fimCodigo,
                'data_expiracao_token_recuperacao_senha' => $fimToken,
            ])->getKey();
        };

        $antiga = $recuperacao('2026-10-30 09:00:00', '2026-10-30 09:15:00', null);
        $antigaComToken = $recuperacao('2026-10-30 09:00:00', null, '2026-10-30 09:20:00');
        $recente = $recuperacao('2026-11-02 09:50:00', '2026-11-02 10:05:00', null);    // código ainda vale
        $deOntem = $recuperacao('2026-11-01 11:00:00', '2026-11-01 11:15:00', null);    // menos de 1 dia

        $this->artisan('model:prune', ['--model' => [RecuperacaoSenha::class]])->assertSuccessful();

        $this->assertEqualsCanonicalizing([$recente, $deOntem], RecuperacaoSenha::pluck('id_recuperacao_senha')->all());
        $this->assertNotContains($antiga, RecuperacaoSenha::pluck('id_recuperacao_senha'));
        $this->assertNotContains($antigaComToken, RecuperacaoSenha::pluck('id_recuperacao_senha'));
    }

    public function test_apaga_tokens_do_app_e_links_de_convite_vencidos(): void
    {
        $atleta = Atleta::findOrFail($this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO'));
        $atleta->createToken('vencido', ['*'], now()->subDays(2));
        $atleta->createToken('valendo', ['*'], now()->addDays(29));

        DB::table('password_reset_tokens_atletas')->insert([
            ['email' => 'vencido@example.test', 'token' => 'x', 'created_at' => now()->subDays(2)],
            ['email' => 'valendo@example.test', 'token' => 'y', 'created_at' => now()->subHour()],
        ]);

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();
        $this->artisan('auth:clear-resets', ['name' => 'atletas'])->assertSuccessful();

        $this->assertSame(['valendo'], $atleta->tokens()->pluck('name')->all());
        $this->assertSame(['valendo@example.test'], DB::table('password_reset_tokens_atletas')->pluck('email')->all());
    }
}
