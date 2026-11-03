<?php

namespace App\Services;

use App\Models\Atleta;
use App\Models\EventoAtleta;
use Illuminate\Support\Collection;

/**
 * Escalação num campo, no estilo dos jogos de videogame (03/11/2026): as formações e as vagas de cada uma.
 * - daCategoria(): a home ("Nossas categorias"), montada pelas posições do cadastro. Não é a escalação de um jogo.
 * - doJogo() e sugestoes(): a tela do jogo no admin, onde o técnico põe cada atleta numa vaga (posição e
 *   titular/reserva gravados em tbl_evento_atleta). A formação não é gravada: sai das posições dos titulares.
 */
class Escalacao
{
    // Titulares no campo (dono do projeto, 03/11/2026: nunca mais de 11)
    public const TITULARES_MAX = 11;

    // Vagas do campo: as posições aceitas, da melhor para a pior; a 1ª dá a sigla, o setor e a posição gravada
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
    // ataque (0) ao próprio gol (100). Linhas com uns 20 de distância, para os cartões não se cobrirem. Todas com
    // TITULARES_MAX vagas, nesta ordem: gol, defesa, meio, ataque (trocar de formação na tela mantém a ordem)
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

    // Posição gravada para quem joga na vaga ('ZAG' → 'ZAGUEIRO')
    public static function posicaoDaVaga(string $sigla): string
    {
        return self::VAGAS[$sigla][0];
    }

    /**
     * Home: a escalação dos atletas de uma categoria (já em ordem de nome, com as posições e as convocações
     * carregadas): ['formacao' => '4-3-3', 'titulares' => [['x', 'y', 'sigla', 'atleta' (null = vaga vazia),
     * 'camisa']], 'reservas' => [['sigla', 'atleta', 'camisa']]]. A camisa é a da convocação mais recente na categoria.
     */
    public static function daCategoria(Collection $atletas, int $idCategoria): array
    {
        $melhor = self::melhorFormacao(self::itensDoCadastro($atletas));

        $porId  = $atletas->keyBy('id_atleta');
        $camisa = fn (Atleta $atleta) => $atleta->convocacoes
            ->where('id_categoria', $idCategoria)
            ->sortByDesc('id_convocacao')
            ->first()?->camisa_convocacao;

        $titulares = [];
        foreach (self::FORMACOES[$melhor['formacao']] as $i => [$sigla, $x, $y]) {
            $atleta      = isset($melhor['vagas'][$i]) ? $porId[$melhor['vagas'][$i]] : null;
            $titulares[] = ['x' => $x, 'y' => $y, 'sigla' => $sigla, 'atleta' => $atleta, 'camisa' => $atleta ? $camisa($atleta) : null];
        }

        $reservas = $atletas
            ->reject(fn (Atleta $atleta) => in_array($atleta->id_atleta, $melhor['vagas'], true))
            ->sortBy(fn (Atleta $atleta) => [self::ordemDoSetor($atleta->posicoes->first()?->posicao), $atleta->nome_atleta])
            ->map(fn (Atleta $atleta) => [
                'sigla'  => $atleta->posicoes->first()?->sigla ?? '—',
                'atleta' => $atleta,
                'camisa' => $camisa($atleta),
            ])
            ->values()
            ->all();

        return ['formacao' => $melhor['formacao'], 'titulares' => $titulares, 'reservas' => $reservas];
    }

    /**
     * Tela do jogo: onde está cada titular gravado ($inscricoes: os escalados num time, com a posição e o
     * titular/reserva do jogo). A formação é a que encaixa mais titulares na posição gravada; no empate, a
     * $preferida (a que o técnico acabou de salvar) ou a primeira. ['formacao', 'vagas' => [i => id_atleta|null],
     * 'sem_vaga' => [id_atleta]]: titulares que não couberam (gravados antes do limite) viram reserva ao salvar.
     */
    public static function doJogo(Collection $inscricoes, ?string $preferida = null): array
    {
        $titulares = $inscricoes->where('titular_evento_atleta', 'TITULAR')
            ->map(fn (EventoAtleta $i) => [
                'id'        => (int) $i->id_atleta,
                'principal' => $i->posicao_evento_atleta,
                'posicoes'  => array_filter([$i->posicao_evento_atleta]),
            ])->values();

        $melhor = self::melhorFormacao($titulares, isset(self::FORMACOES[$preferida]) ? $preferida : null);
        $vagas  = array_map(fn ($i) => $melhor['vagas'][$i] ?? null, array_keys(self::FORMACOES[$melhor['formacao']]));

        return [
            'formacao' => $melhor['formacao'],
            'vagas'    => $vagas,
            'sem_vaga' => $titulares->pluck('id')->diff($melhor['vagas'])->values()->all(),
        ];
    }

    /**
     * "Sugerir pelo cadastro" da tela do jogo: para cada formação, quem fica em cada vaga pelas posições do
     * cadastro dos atletas (a mesma regra da home): [formacao => [i => id_atleta|null]].
     */
    public static function sugestoes(Collection $atletas): array
    {
        $itens = self::itensDoCadastro($atletas);
        $sugestoes = [];
        foreach (self::FORMACOES as $nome => $vagas) {
            $preenchido = self::preencher($vagas, $itens)['vagas'];
            $sugestoes[$nome] = array_map(fn ($i) => $preenchido[$i] ?? null, array_keys($vagas));
        }

        return $sugestoes;
    }

    // "Ana Maria Gomes da Silva" → "Ana Silva" (o nome inteiro fica na dica e na página Jogadores)
    public static function nomeCurto(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome));

        return count($partes) > 1 ? $partes[0] . ' ' . end($partes) : $partes[0];
    }

    // "Ana Maria Gomes da Silva" → "AS" (no lugar da foto)
    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome));
        $letras = mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');

        return mb_strtoupper($letras);
    }

    // Ordem do setor da posição, para listar do goleiro ao ataque (sem posição no fim)
    public static function ordemDoSetor(?string $posicao): int
    {
        return $posicao ? self::ORDEM_DOS_SETORES[Atleta::POSICOES[$posicao]['setor']] : 9;
    }

    // Atletas (em ordem de nome) como itens do preenchimento, pelas posições do cadastro
    private static function itensDoCadastro(Collection $atletas): Collection
    {
        return $atletas->map(fn (Atleta $atleta) => [
            'id'        => (int) $atleta->id_atleta,
            'principal' => $atleta->posicoes->first()?->posicao,
            'posicoes'  => $atleta->posicoes->pluck('posicao')->all(),
        ])->values();
    }

    // A formação com mais pontos no preenchimento (empate: a preferida, se empatou, ou a primeira da lista)
    private static function melhorFormacao(Collection $itens, ?string $preferida = null): array
    {
        $melhor = null;
        foreach (self::FORMACOES as $nome => $vagas) {
            $tentativa = self::preencher($vagas, $itens) + ['formacao' => $nome];
            if ($melhor === null || $tentativa['pontos'] > $melhor['pontos']
                || ($tentativa['pontos'] === $melhor['pontos'] && $nome === $preferida)) {
                $melhor = $tentativa;
            }
        }

        return $melhor;
    }

    /**
     * Preenche as vagas em passadas, da combinação mais certa para a menos: (1) a principal é a posição da vaga,
     * (2) a principal é aceita na vaga, (3) outra posição é aceita, (4) a principal é do mesmo setor, (5) quem
     * sobrou, fora o gol (goleiro na linha só se não houver mais ninguém). $itens: ['id', 'principal', 'posicoes'].
     * Devolve ['vagas' => [i => id], 'pontos'] (soma das passadas: 5, 4, 3, 1, 0), para comparar as formações.
     */
    private static function preencher(array $vagas, Collection $itens): array
    {
        $ocupadas = [];
        $usados   = [];
        $pontos   = 0;
        $setor    = fn (?string $posicao) => $posicao ? Atleta::POSICOES[$posicao]['setor'] : null;

        $passadas = [
            5 => fn (array $item, array $aceitas) => $item['principal'] === $aceitas[0],
            4 => fn (array $item, array $aceitas) => in_array($item['principal'], $aceitas, true),
            3 => fn (array $item, array $aceitas) => (bool) array_intersect($item['posicoes'], $aceitas),
            1 => fn (array $item, array $aceitas) => $item['principal'] !== null && $setor($item['principal']) === $setor($aceitas[0]),
            0 => fn (array $item, array $aceitas) => $aceitas[0] !== 'GOLEIRO',
        ];

        foreach ($passadas as $valor => $serve) {
            foreach ($vagas as $i => [$sigla]) {
                if (isset($ocupadas[$i])) {
                    continue;
                }
                $aceitas    = self::VAGAS[$sigla];
                $candidatos = $itens->filter(fn (array $item) => ! isset($usados[$item['id']]) && $serve($item, $aceitas));
                if ($valor === 0) {
                    // Na última passada, quem não é goleiro primeiro
                    $candidatos = $candidatos->sortBy(fn (array $item) => $item['principal'] === 'GOLEIRO' ? 1 : 0);
                }
                if ($escolhido = $candidatos->first()) {
                    $ocupadas[$i] = $escolhido['id'];
                    $usados[$escolhido['id']] = true;
                    $pontos += $valor;
                }
            }
        }

        ksort($ocupadas);

        return ['vagas' => $ocupadas, 'pontos' => $pontos];
    }
}
