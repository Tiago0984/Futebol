<?php

// Processo auxiliar exclusivo dos testes de concorrência. Segredos entram por stdin, não por argumentos.
use App\Mail\CodigoRecuperacaoSenhaMail;
use App\Http\Controllers\Api\V1\AuthController;
use App\Services\RecuperaSenhaPorCodigo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'db_futebol_test'
    || filled(config('database.connections.mysql.url'))) {
    throw new RuntimeException('Worker abortado: permitido somente no banco db_futebol_test.');
}
if (DB::connection()->getDatabaseName() !== 'db_futebol_test') {
    throw new RuntimeException('Worker conectado ao banco errado.');
}
Mail::fake();
$dados = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$servico = app(RecuperaSenhaPorCodigo::class);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);

if ($dados['operacao'] === 'solicitar') {
    $servico->solicitar($dados['email'], 'atleta');
    app(DeferredCallbackCollection::class)->invoke(); // o envio sai depois da resposta (defer)
    $resultado = Mail::sent(CodigoRecuperacaoSenhaMail::class)->last()?->codigo;
} elseif ($dados['operacao'] === 'validar') {
    $resultado = $servico->validar($dados['email'], 'atleta', $dados['codigo']);
} elseif ($dados['operacao'] === 'redefinir') {
    $resultado = $servico->redefinir($dados['token'], $dados['senha']);
} elseif ($dados['operacao'] === 'login') {
    $resposta = app(AuthController::class)->login(Request::create('/api/v1/auth/login', 'POST', [
        'email' => $dados['email'], 'perfil' => 'atleta', 'senha' => $dados['senha'],
    ]));
    $resultado = $resposta->getStatusCode();
} else {
    throw new RuntimeException('Operação desconhecida no worker.');
}
fwrite(STDOUT, json_encode(['resultado' => $resultado], JSON_THROW_ON_ERROR) . "\n");
