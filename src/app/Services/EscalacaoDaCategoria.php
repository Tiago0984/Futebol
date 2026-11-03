<?php

namespace App\Services;

use App\Models\Atleta;
use Illuminate\Support\Collection;

/**
 * Escalação da home ("Nossas categorias", 03/11/2026): os atletas ativos de uma categoria num campo, no estilo dos
 * jogos de videogame, pelas posições do cadastro. Não é a escalação de um jogo (posição e titular/reserva do jogo
 * ficam só no admin): o sistema escolhe a formação em que mais atletas jogam na posição deles e põe o resto no banco.
 */
class EscalacaoDaCategoria
{
    // Vagas do campo: as posições aceitas, da melhor para a pior; a 1ª dá a sigla e o setor da vaga
    public const VAGAS = [
        'GOL' => ['GOLEIRO'],
        'LE'  => ['LATERAL_ESQUERDO', 'LATERAL_DIREITO'],
        'ZAG' => ['ZAGUEIRO', 'LIBERO'],
        'LIB' => ['LIBERO', 'ZAGUEIRO'],
        'LD'  => ['LATERAL_DIREITO', 'LATERAL_ESQUERDO'],
        'VOL' => ['VOLANTE', 'MEIA_CENTRAL'],
        'MC'  => ['MEIA_CENTRAL', 'VOLANTE', 'MEIA_OFENSIVO'],
        'MO'  => ['MEIA_OFENSIVO', 'MEIA_CENTRAL', 'SEGUNDO_ATACANTE'],
        'ME'  => ['MEIA_ESQUERDA', 'PONTA_ESQUERDA', 'MEIA_CENTRAL'],
        'MD'  => ['MEIA_DIREITA', 'PONTA_DIREITA', 'MEIA_CENTRAL'],
        'PE'  => ['PONTA_ESQUERDA', 'MEIA_ESQUERDA'],
        'PD'  => ['PONTA_DIREITA', 'MEIA_DIREITA'],
        'CA'  => ['CENTROAVANTE', 'SEGUNDO_ATACANTE'],
        'SA'  => ['SEGUNDO_ATACANTE', 'CENTROAVANTE', 'MEIA_OFENSIVO'],
    ];

    // Formações, na ordem de preferência no empate: [vaga, x, y], com x da esquerda (0) à direita (100) e y do
    // ataque (0) ao próprio gol (100). Linhas com uns 20 de distância, para os cartões não se cobrirem
    public const FORMACOES = [
        '4-3-3' => [
            ['GOL', 50, 93],
            ['LE', 12, 68], ['ZAG', 36, 73], ['ZAG', 64, 73], ['LD', 88, 68],
            ['MC', 25, 44], ['VOL', 50, 52], ['MO', 75, 44],
            ['PE', 15, 18], ['CA', 50, 12], ['PD', 85, 18],
        ],
        '4-4-2' => [
            ['GOL', 50, 93],
            ['LE', 12, 68], ['ZAG', 36, 73], ['ZAG', 64, 73], ['LD', 88, 68],
            ['ME', 12, 44], ['VOL', 37, 50], ['MC', 63, 50], ['MD', 88, 44],
            ['CA', 36, 15], ['SA', 64, 15],
        ],
        '4-2-3-1' => [
            ['GOL', 50, 93],
            ['LE', 12, 68], ['ZAG', 36, 73], ['ZAG', 64, 73], ['LD', 88, 68],
            ['VOL', 36, 53], ['VOL', 64, 53],
            ['PE', 14, 31], ['MO', 50, 33], ['PD', 86, 31],
            ['CA', 50, 11],
        ],
        '3-5-2' => [
            ['GOL', 50, 93],
            ['ZAG', 26, 72], ['LIB', 50, 74], ['ZAG', 74, 72],
            ['ME', 10, 45], ['VOL', 33, 53], ['MO', 50, 34], ['VOL', 67, 53], ['MD', 90, 45],
            ['CA', 36, 13], ['SA', 64, 13],
        ],
    ];

    // Ordem dos setores no banco de reservas (o atleta sem posição fica no fim)
    private const ORDEM_DOS_SETORES = ['Goleiro' => 0, 'Defensores' => 1, 'Meio-campistas' => 2, 'Atacantes' => 3];

    /**
     * Monta a escalação dos atletas (já em ordem de nome, com as posições e as convocações carregadas):
     * ['formacao' => '4-3-3', 'titulares' => [['x', 'y', 'sigla', 'atleta' (null = vaga vazia), 'camisa']],
     *  'reservas' => [['sigla', 'atleta', 'camisa']]]. A camisa é a da convocação mais recente na categoria.
     */
    public static function montar(Collection $atletas, int $idCategoria): array
    {
        $melhor = null;
        foreach (self::FORMACOES as $nome => $vagas) {
            $tentativa = self::preencher($vagas, $atletas);
            if ($melhor === null || $tentativa['pontos'] > $melhor['pontos']) {
                $melhor = $tentativa + ['formacao' => $nome];
            }
        }

        $camisa = fn (Atleta $atleta) => $atleta->convocacoes
            ->where('id_categoria', $idCategoria)
            ->sortByDesc('id_convocacao')
            ->first()?->camisa_convocacao;

        $titulares = array_map(fn ($vaga) => $vaga + ['camisa' => $vaga['atleta'] ? $camisa($vaga['atleta']) : null], $melhor['titulares']);

        $reservas = $atletas
            ->reject(fn (Atleta $atleta) => in_array($atleta->id_atleta, $melhor['escalados'], true))
            ->sortBy(fn (Atleta $atleta) => [self::ordemDoSetor($atleta), $atleta->nome_atleta])
            ->map(fn (Atleta $atleta) => [
                'sigla'  => $atleta->posicoes->first()?->sigla ?? '—',
                'atleta' => $atleta,
                'camisa' => $camisa($atleta),
            ])
            ->values()
            ->all();

        return ['formacao' => $melhor['formacao'], 'titulares' => $titulares, 'reservas' => $reservas];
    }

    // "Ana Maria Gomes da Silva" → "Ana Silva" (o nome inteiro fica na dica e na página Jogadores)
    public static function nomeCurto(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome));

        return count($partes) > 1 ? $partes[0] . ' ' . end($partes) : $partes[0];
    }

    /**
     * Preenche as vagas em passadas, da combinação mais certa para a menos: (1) a principal é a posição da vaga,
     * (2) a principal é aceita na vaga, (3) outra posição do atleta é aceita, (4) a principal é do mesmo setor,
     * (5) quem sobrou, fora o gol (goleiro na linha só se não houver mais ninguém). Pontos = soma das passadas
     * (5, 4, 3, 1, 0), para comparar as formações.
     */
    private static function preencher(array $vagas, Collection $atletas): array
    {
        $titulares = array_map(fn ($v) => ['x' => $v[1], 'y' => $v[2], 'sigla' => $v[0], 'atleta' => null], $vagas);
        $escalados = [];
        $pontos    = 0;

        $passadas = [
            5 => fn (Atleta $a, array $aceitas) => self::principal($a) === $aceitas[0],
            4 => fn (Atleta $a, array $aceitas) => in_array(self::principal($a), $aceitas, true),
            3 => fn (Atleta $a, array $aceitas) => (bool) array_intersect($a->posicoes->pluck('posicao')->all(), $aceitas),
            1 => fn (Atleta $a, array $aceitas) => self::principal($a) !== null
                && Atleta::POSICOES[self::principal($a)]['setor'] === Atleta::POSICOES[$aceitas[0]]['setor'],
            0 => fn (Atleta $a, array $aceitas) => $aceitas[0] !== 'GOLEIRO',
        ];

        foreach ($passadas as $valor => $serve) {
            foreach ($titulares as $i => $vaga) {
                if ($vaga['atleta']) {
                    continue;
                }
                $aceitas    = self::VAGAS[$vaga['sigla']];
                $candidatos = $atletas->filter(fn (Atleta $a) => ! in_array($a->id_atleta, $escalados, true) && $serve($a, $aceitas));
                if ($valor === 0) {
                    // Na última passada, quem não é goleiro primeiro
                    $candidatos = $candidatos->sortBy(fn (Atleta $a) => self::principal($a) === 'GOLEIRO' ? 1 : 0, SORT_REGULAR);
                }
                if ($escolhido = $candidatos->first()) {
                    $titulares[$i]['atleta'] = $escolhido;
                    $escalados[] = $escolhido->id_atleta;
                    $pontos += $valor;
                }
            }
        }

        return ['titulares' => $titulares, 'escalados' => $escalados, 'pontos' => $pontos];
    }

    private static function principal(Atleta $atleta): ?string
    {
        return $atleta->posicoes->first()?->posicao;
    }

    private static function ordemDoSetor(Atleta $atleta): int
    {
        $principal = self::principal($atleta);

        return $principal ? self::ORDEM_DOS_SETORES[Atleta::POSICOES[$principal]['setor']] : 9;
    }
}
