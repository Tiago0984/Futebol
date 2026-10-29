<?php

use App\Http\Controllers\Api\V1\AtletaController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        // API do app: recusa atleta que não está ATIVO, mesmo com token válido. Dois perfis (Fase 9): cada
        // token só nas rotas do seu perfil; responsável só com filho ATIVO. Um alias() só: cada chamada
        // substitui a lista anterior
        $middleware->alias([
            'atleta.ativo'      => \App\Http\Middleware\AtletaAtivo::class,
            'perfil'            => \App\Http\Middleware\PerfilDoApp::class,
            'responsavel.ativo' => \App\Http\Middleware\ResponsavelComAtletaAtivo::class,
            // Admin: aviso de quem ficou fora de uma inscrição por estar afastado (16/10/2026)
            'avisa.afastados'   => \App\Http\Middleware\AvisaAfastadosIgnorados::class,
        ]);

        // API em português (mensagens de validação de lang/pt_BR)
        $middleware->api(prepend: [\App\Http\Middleware\IdiomaDaApi::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Na API, erros (401, 404, 422...) sempre voltam em JSON, mesmo sem o header Accept
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        // Na API, todo erro sai no mesmo formato das respostas da escolinha: { "success": false, "message" }
        // em português (mais "errors" no 422). Erro inesperado (500): a mensagem genérica, sem detalhes, a não
        // ser com APP_DEBUG ligado (no local, o Laravel mostra o erro inteiro)
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $erro = fn (string $mensagem, int $status, array $extra = [], array $headers = []) => response()->json(
                ['success' => false, 'message' => $mensagem, ...$extra], $status, $headers
            );

            return match (true) {
                $e instanceof ValidationException => $erro(
                    $e->validator->errors()->first(), $e->status, ['errors' => $e->errors()]
                ),
                $e instanceof AuthenticationException => $erro(
                    'Faça login para continuar: o token não foi enviado, é inválido ou venceu.', 401
                ),
                $e instanceof ThrottleRequestsException => $erro(
                    'Muitas tentativas. Aguarde um pouco e tente de novo.', 429, [], $e->getHeaders()
                ),
                // Corpo acima do post_max_size (o PHP descarta tudo): na foto do atleta, o mesmo 422 do campo, que o
                // app mostra embaixo da foto (29/10/2026)
                $e instanceof PostTooLargeException => $request->is('api/v1/atleta/foto')
                    ? $erro(AtletaController::MENSAGEM_FOTO_TAMANHO, 422, ['errors' => ['foto' => [AtletaController::MENSAGEM_FOTO_TAMANHO]]])
                    : $erro('O envio passou do tamanho máximo aceito pelo servidor.', 413),
                $e instanceof NotFoundHttpException => $erro('Endereço não encontrado.', 404),
                $e instanceof MethodNotAllowedHttpException => $erro(
                    'Método não aceito nesta rota.', 405, [], $e->getHeaders()
                ),
                $e instanceof HttpExceptionInterface => $erro(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Não foi possível atender a requisição.',
                    $e->getStatusCode(), [], $e->getHeaders()
                ),
                config('app.debug') => null,
                default => $erro('Erro interno. Tente de novo em instantes.', 500),
            };
        });
    })->create();
