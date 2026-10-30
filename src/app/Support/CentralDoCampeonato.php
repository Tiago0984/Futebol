<?php

namespace App\Support;

use App\Models\Campeonato;

/**
 * Central do campeonato (padrão das telas do painel, Fase 3, 30/10/2026): as abas de um campeonato, cada uma
 * uma tela com o seu endereço, e a linha de caminho delas. Num lugar só, para as telas das abas, as de dentro
 * (Convocados, Gerar jogos, Próxima fase, jogadores do time no jogo) e o cabeçalho
 * (componente x-admin.central-do-campeonato) usarem os mesmos nomes e endereços.
 */
class CentralDoCampeonato
{
    // chave => [rótulo, rota, ícone do Bootstrap Icons], na ordem do trabalho: montar, convocar, jogar, classificar, decidir
    public const ABAS = [
        'visao'         => ['Visão geral', 'admin.campeonatos.show', 'speedometer2'],
        'participantes' => ['Participantes e convocação', 'admin.campeonatos.participantes', 'people'],
        'jogos'         => ['Jogos', 'admin.campeonatos.times', 'calendar2-week'],
        'grupos'        => ['Grupos (chaves) e classificação', 'admin.campeonatos.grupos', 'diagram-3'],
        'mata-mata'     => ['Mata-mata e campeão', 'admin.campeonatos.mata-mata', 'trophy'],
    ];

    // Endereço da aba; com a categoria, já no bloco dela (#categoria-ID)
    public static function url(Campeonato|int $campeonato, string $aba, ?int $idCategoria = null): string
    {
        $id  = $campeonato instanceof Campeonato ? $campeonato->id_campeonato : $campeonato;
        $url = route(self::ABAS[$aba][1], $id);

        return $idCategoria ? "{$url}#categoria-{$idCategoria}" : $url;
    }

    /**
     * Eventos › Campeonatos › nome do campeonato (Visão geral) › aba › $depois. $depois é a tela de dentro da aba
     * (ex.: "Convocados · AACJ Masculino · Sub-13"); a última parte fica sem link. $idCategoria leva o link da
     * aba ao bloco daquela categoria.
     */
    public static function caminho(Campeonato $campeonato, string $aba = 'visao', ?string $depois = null, ?int $idCategoria = null): array
    {
        $naVisao = $aba === 'visao' && ! $depois;
        $caminho = [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', route('admin.campeonatos.index')],
            [$campeonato->nome_campeonato, $naVisao ? null : self::url($campeonato, 'visao')],
        ];
        if ($aba !== 'visao') {
            $caminho[] = [self::ABAS[$aba][0], $depois ? self::url($campeonato, $aba, $idCategoria) : null];
        }
        if ($depois) {
            $caminho[] = [$depois, null];
        }

        return $caminho;
    }
}
