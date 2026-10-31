<?php

namespace Tests\Feature;

use App\Models\Atleta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class RecuperacaoSenhaConcorrenciaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // Os subprocessos precisam enxergar as fixtures já confirmadas. O trait mantém a trava de banco de testes;
    // o tearDown remove explicitamente apenas os registros criados aqui.
    protected array $connectionsToTransact = [];
    private ?int $idAtleta = null;
    private ?int $idEndereco = null;

    protected function tearDown(): void
    {
        try {
            if ($this->idAtleta !== null) {
                DB::table('tbl_recuperacao_senha')->where('id_atleta', $this->idAtleta)->delete();
                DB::table('personal_access_tokens')->where('tokenable_type', Atleta::class)->where('tokenable_id', $this->idAtleta)->delete();
                DB::table('password_reset_tokens_atletas')->where('email', 'concorrencia@example.test')->delete();
                DB::table('tbl_atletas')->where('id_atleta', $this->idAtleta)->delete();
                DB::table('tbl_endereco')->where('id_endereco', $this->idEndereco)->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_requisicoes_simultaneas_nao_reenviam_codigo_nem_consumem_codigo_ou_token_duas_vezes(): void
    {
        $this->idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $conta = Atleta::findOrFail($this->idAtleta);
        $this->idEndereco = $conta->id_endereco;
        $conta->email_atleta = 'concorrencia@example.test';
        $conta->password = Hash::make('senha-inicial-123');
        $conta->save();
        $conta->createToken('aparelho1');
        $conta->createToken('aparelho2');
        $link = $conta::broker()->createToken($conta);

        $codigos = $this->simultaneos(['operacao' => 'solicitar', 'email' => $conta->emailDoApp()]);
        $this->assertCount(1, array_filter($codigos, fn ($c) => $c !== null));
        $codigo = array_values(array_filter($codigos, fn ($c) => $c !== null))[0];
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/D', $codigo);

        // Seis erros concorrentes em três pares: o contador para em cinco e o código fica inutilizável.
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame([null, null], $this->simultaneos([
                'operacao' => 'validar', 'email' => $conta->emailDoApp(), 'codigo' => $codigo === '0000' ? '0001' : '0000',
            ]));
        }
        $registro = DB::table('tbl_recuperacao_senha')->where('id_atleta', $conta->getKey())->sole();
        $this->assertSame(5, $registro->tentativas_recuperacao_senha);
        $this->assertNull($registro->codigo_hash_recuperacao_senha);
        DB::table('tbl_recuperacao_senha')->where('id_atleta', $conta->getKey())
            ->update(['data_codigo_recuperacao_senha' => now()->subMinutes(2)]);
        $novos = $this->simultaneos(['operacao' => 'solicitar', 'email' => $conta->emailDoApp()]);
        $this->assertCount(1, array_filter($novos, fn ($c) => $c !== null));
        $codigo = array_values(array_filter($novos, fn ($c) => $c !== null))[0];

        $tokens = $this->simultaneos(['operacao' => 'validar', 'email' => $conta->emailDoApp(), 'codigo' => $codigo]);
        $this->assertCount(1, array_filter($tokens, fn ($t) => $t !== null));
        $token = array_values(array_filter($tokens, fn ($t) => $t !== null))[0];

        $resultados = $this->simultaneos(['operacao' => 'redefinir', 'token' => $token, 'senha' => 'senha-nova-123']);
        sort($resultados);
        $this->assertSame([false, true], $resultados);
        $this->assertTrue(Hash::check('senha-nova-123', $conta->fresh()->getAuthPassword()));
        $this->assertSame(0, $conta->tokens()->count());
        $this->assertFalse($conta::broker()->tokenExists($conta, $link));
        $this->assertDatabaseHas('tbl_recuperacao_senha', [
            'id_atleta' => $conta->getKey(), 'codigo_hash_recuperacao_senha' => null,
            'reset_token_hash_recuperacao_senha' => null,
        ]);

        // Um login com a senha antiga deve esperar a mesma trava: se entrar antes da troca, será revogado;
        // se entrar depois, a senha antiga já não confere. Em ambos os casos não sobra sessão.
        DB::table('tbl_recuperacao_senha')->where('id_atleta', $conta->getKey())
            ->update(['data_codigo_recuperacao_senha' => now()->subMinutes(2)]);
        $codigos = array_values(array_filter($this->simultaneos([
            'operacao' => 'solicitar', 'email' => $conta->emailDoApp(),
        ]), fn ($c) => $c !== null));
        $tokens = array_values(array_filter($this->simultaneos([
            'operacao' => 'validar', 'email' => $conta->emailDoApp(), 'codigo' => $codigos[0],
        ]), fn ($t) => $t !== null));
        $disputa = $this->simultaneos([
            'operacao' => 'redefinir', 'token' => $tokens[0], 'senha' => 'senha-final-456',
        ], ['operacao' => 'login', 'email' => $conta->emailDoApp(), 'senha' => 'senha-nova-123']);
        $this->assertTrue($disputa[0]);
        $this->assertContains($disputa[1], [200, 401]);
        $this->assertTrue(Hash::check('senha-final-456', $conta->fresh()->getAuthPassword()));
        $this->assertSame(0, $conta->tokens()->count());
    }

    private function simultaneos(array $dados, ?array $segunda = null): array
    {
        $mysql = config('database.connections.mysql');
        $ambiente = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'db_futebol_test', 'DB_URL' => '',
            'DB_HOST' => $mysql['host'], 'DB_PORT' => (string) $mysql['port'],
            'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => $mysql['password'],
            'MAIL_MAILER' => 'array', 'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4',
        ];
        $processos = [];
        DB::beginTransaction();
        try {
            Atleta::whereKey($this->idAtleta)->lockForUpdate()->firstOrFail();
            for ($i = 0; $i < 2; $i++) {
                $processo = new Process([PHP_BINARY, base_path('tests/Support/recuperacao_senha_worker.php')], base_path(), $ambiente);
                $processo->setTimeout(20)->setInput(json_encode($i === 1 && $segunda ? $segunda : $dados, JSON_THROW_ON_ERROR));
                $processo->start();
                $processos[] = $processo;
            }
            // Lê também saída já recebida: waitUntil só observa saída nova e pode perder um READY rápido.
            $prazo = microtime(true) + 10;
            do {
                $prontos = 0;
                foreach ($processos as $processo) {
                    if (str_contains($processo->getOutput(), 'READY')) {
                        $prontos++;
                    } else {
                        $this->assertTrue($processo->isRunning(), $processo->getErrorOutput());
                    }
                }
                if ($prontos === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $prazo);
            $this->assertSame(2, $prontos, 'Os dois workers devem iniciar antes de liberar a conta.');
            // Ambos chegaram ao serviço enquanto a conta está bloqueada nesta conexão.
            usleep(100000);
            foreach ($processos as $processo) {
                $this->assertTrue($processo->isRunning(), 'A operação deve esperar o bloqueio da conta.');
            }
            DB::commit();

            $resultados = [];
            foreach ($processos as $processo) {
                $processo->wait();
                $this->assertTrue($processo->isSuccessful(), $processo->getErrorOutput());
                $linhas = explode("\n", trim($processo->getOutput()));
                $resultados[] = json_decode(end($linhas), true, 512, JSON_THROW_ON_ERROR)['resultado'];
            }

            return $resultados;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processos as $processo) {
                if ($processo->isRunning()) {
                    $processo->stop();
                }
            }
        }
    }
}
