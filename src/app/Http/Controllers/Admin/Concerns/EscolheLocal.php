<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Local;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Select "Local" (Etapa L2, 30/10/2026; partial admin.partials.select-local), no evento, na grade, no jogo, no local
 * principal do campeonato e nos geradores de jogos: [id_local, texto do local] pela regra de Local::daEscolha() (só
 * ativos; o inativo e o texto antigo que já estão podem ficar). Erro no campo do select.
 *
 * Sem o campo do select no pedido (requisição antiga, que manda só o texto, como os testes de antes da L2), vale o
 * texto livre, sem local; o mesmo texto de antes mantém o local que o registro já tinha. Sem nenhum dos dois, o local
 * gravado fica como está (como antes, quando o campo ausente não era atualizado). Os formulários sempre mandam o select.
 */
trait EscolheLocal
{
    private function localEscolhido(Request $request, string $campoTexto, ?int $idAtual, ?string $textoAtual, string $campo = 'id_local'): array
    {
        if (! $request->has($campo) && ! $request->has($campoTexto)) {
            return [$idAtual, $textoAtual];
        }

        if (! $request->has($campo)) {
            $texto = trim((string) $request->input($campoTexto));
            if ($texto === '') {
                return [null, null];
            }

            return $texto === $textoAtual ? [$idAtual, $textoAtual] : [null, $texto];
        }

        $escolha = Local::daEscolha((string) $request->input($campo, ''), $idAtual, $textoAtual);

        if (is_string($escolha)) {
            throw ValidationException::withMessages([$campo => $escolha]);
        }

        return $escolha;
    }
}
