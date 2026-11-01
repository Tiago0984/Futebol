<?php

namespace App\Services;

use App\Models\Dispositivo;
use App\Models\Notificacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

class EnviaPushDaNotificacao
{
    public function agendar(array $ids): void
    {
        if (! $ids) {
            return;
        }

        try {
            DB::afterCommit(function () use ($ids) {
                try {
                    // always: o aviso já foi confirmado, mesmo se outra etapa da resposta der erro.
                    defer(fn () => $this->enviar($ids))->always();
                } catch (Throwable $erro) {
                    $this->registrarFalha($erro);
                }
            });
        } catch (Throwable $erro) {
            $this->registrarFalha($erro);
        }
    }

    public function enviar(array $ids): void
    {
        try {
            // Busca novamente os vínculos, a atividade e a preferência após a resposta; não congela
            // o dono do celular antes do commit. Nada vai para a fila database.
            Notificacao::whereKey($ids)->with([
                'atleta:id_atleta,nome_atleta,status_atleta',
                'atleta.responsaveis' => fn ($q) => $q->select('tbl_responsavel.id_responsavel')->withCount([
                    'atletas' => fn ($filhos) => $filhos->select(DB::raw('COUNT(DISTINCT tbl_atletas.id_atleta)')),
                ]),
            ])->chunkById(100, function ($avisos) {
                $atletas = $avisos->pluck('atleta')->filter(fn ($a) => $a?->status_atleta === 'ATIVO')->keyBy('id_atleta');
                $responsaveis = $atletas->flatMap(fn ($a) => $a->responsaveis)->pluck('id_responsavel')->unique();
                $aparelhos = Dispositivo::where('ativo_dispositivo', true)->where(fn ($q) => $q
                    ->whereIn('id_atleta', $atletas->keys())->orWhereIn('id_responsavel', $responsaveis))->get();
                $porAtleta = $aparelhos->whereNotNull('id_atleta')->groupBy('id_atleta');
                $porResponsavel = $aparelhos->whereNotNull('id_responsavel')->groupBy('id_responsavel');
                $mensagens = [];

                foreach ($avisos as $aviso) {
                    $atleta = $atletas->get($aviso->id_atleta);
                    if (! $atleta) {
                        continue;
                    }
                    foreach ($porAtleta->get($atleta->getKey(), []) as $aparelho) {
                        $mensagens[] = $this->mensagem($aviso, $aparelho->token);
                    }
                    foreach ($atleta->responsaveis->unique('id_responsavel') as $responsavel) {
                        $prefixo = $responsavel->atletas_count > 1
                            ? preg_split('/\s+/u', trim($atleta->nome_atleta))[0] . ' · ' : '';
                        foreach ($porResponsavel->get($responsavel->getKey(), []) as $aparelho) {
                            $mensagens[] = $this->mensagem($aviso, $aparelho->token, $prefixo);
                        }
                    }
                }

                app(ClienteExpo::class)->enviar($mensagens, fn (string $token) => Dispositivo::where('token', $token)->delete());
            }, 'id_notificacao');
        } catch (Throwable $erro) {
            $this->registrarFalha($erro);
        }
    }

    private function mensagem(Notificacao $aviso, string $token, string $prefixo = ''): array
    {
        return [
            'to' => $token,
            'title' => $prefixo . $aviso->titulo_notificacao,
            'body' => $aviso->mensagem_notificacao,
            'sound' => 'default',
            'channelId' => 'default',
            'priority' => 'high',
            'data' => [
                'id_notificacao' => (int) $aviso->getKey(),
                'id_atleta' => (int) $aviso->id_atleta,
                ...($aviso->id_evento_calendario === null ? [] : ['id_evento' => (int) $aviso->id_evento_calendario]),
                'tipo' => $aviso->tipo_notificacao,
            ],
        ];
    }

    private function registrarFalha(Throwable $erro): void
    {
        Log::warning('Falha ao preparar push das notificações.', ['erro' => $erro::class]);
    }
}
