<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClienteExpo
{
    public const URL = 'https://exp.host/--/api/v2/push/send';

    /** @param array<int, array> $mensagens */
    public function enviar(array $mensagens, callable $apagarTokenInvalido): void
    {
        foreach (array_chunk($mensagens, 100) as $lote) {
            try {
                $cliente = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(10);
                if ($token = config('services.expo.access_token')) {
                    $cliente = $cliente->withToken($token);
                }

                // Sem repetição automática: uma resposta perdida pode já ter sido aceita pela Expo.
                $resposta = $cliente->post(self::URL, $lote);
                if (! $resposta->successful()) {
                    Log::warning('Falha HTTP no envio de push pela Expo.', ['status' => $resposta->status(), 'quantidade' => count($lote)]);
                    continue;
                }

                $tickets = $resposta->json('data');
                if (! is_array($tickets) || ! array_is_list($tickets) || count($tickets) !== count($lote)) {
                    Log::warning('Resposta inesperada da Expo no envio de push.', ['quantidade' => count($lote)]);
                    continue;
                }

                foreach ($tickets as $indice => $ticket) {
                    if (($ticket['status'] ?? null) === 'ok') {
                        continue;
                    }
                    if (($ticket['status'] ?? null) === 'error' && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                        $apagarTokenInvalido($lote[$indice]['to']);
                    } else {
                        // Nunca registrar corpo, tokens ou mensagem retornada pela Expo (podem conter o token).
                        Log::warning('Ticket de push recusado ou inesperado na Expo.', ['id_notificacao' => $lote[$indice]['data']['id_notificacao']]);
                    }
                }
            } catch (Throwable $erro) {
                Log::warning('Falha no envio de push pela Expo.', ['erro' => $erro::class, 'quantidade' => count($lote)]);
            }
        }
    }
}
