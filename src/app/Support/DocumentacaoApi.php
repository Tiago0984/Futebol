<?php

namespace App\Support;

/**
 * Conteúdo da página /api/documentacao (06/11/2026). Cada rota é descrita uma vez, nos arquivos de
 * resources/documentacao-api/; daqui saem o menu da esquerda, os exemplos de cURL e React Native, a coleção do
 * Postman e a tabela "Todas as rotas". Rota nova da API entra num desses arquivos (o DocumentacaoApiTest falha sem ela).
 *
 * Item de um grupo: guia (texto sem rota, com 'guia' => true) ou endpoint, com:
 * titulo, metodo (um método, ou lista: ['PUT', 'PATCH']), rota, acesso (chave de ACESSOS), doc (HTML do repositório),
 * parametros ([nome, tipo, onde, obrigatório, descrição]), query, corpo, respostas (status => array do JSON), campos,
 * exemplos (no lugar dos gerados, para o envio de arquivo), filho (repete a rota sob /responsavel/atletas/{idAtleta})
 * e rota_do_filho (o fim dela, quando é outro: /estatisticas).
 */
class DocumentacaoApi
{
    public const PRODUCAO = 'https://coderatech.tipi5.smpsistema.com.br/AACJ/api/v1';
    public const LOCAL    = 'http://localhost:8080/api/v1';

    public const ACESSOS = [
        'publico'     => ['rotulo' => 'Pública', 'classe' => 'publico'],
        'conta'       => ['rotulo' => 'Qualquer perfil', 'classe' => 'conta'],
        'app'         => ['rotulo' => 'Atleta ou responsável', 'classe' => 'conta'],
        'atleta'      => ['rotulo' => 'Atleta', 'classe' => 'atleta'],
        'responsavel' => ['rotulo' => 'Responsável', 'classe' => 'responsavel'],
    ];

    // A ordem do menu
    private const ARQUIVOS = [
        'primeiros-passos', 'geral', 'campeonatos', 'autenticacao', 'recuperacao-de-senha', 'atleta', 'agenda', 'avisos',
        'responsavel', 'fale-conosco', 'aparelhos', 'referencia',
    ];

    // Valores das rotas nos exemplos
    private const VALORES_DA_ROTA = ['{idAtleta}' => '7', '{idEvento}' => '81', '{idNotificacao}' => '310', '{id}' => '1'];

    private const NAO_LOGADO = 'Faça login para continuar: o token não foi enviado, é inválido ou venceu.';

    private static ?array $grupos = null;

    /** Os grupos do menu, com os ids e as rotas do responsável já montados. */
    public static function grupos(): array
    {
        if (self::$grupos !== null) {
            return self::$grupos;
        }

        $grupos = array_map(fn ($arquivo) => require resource_path("documentacao-api/{$arquivo}.php"), self::ARQUIVOS);

        foreach ($grupos as &$grupo) {
            $grupo['itens'] = array_map(fn ($item) => self::completar($item), $grupo['itens']);
        }
        unset($grupo);

        // As rotas do atleta que o responsável também tem, sob o filho, viram um grupo depois do Responsável
        $doFilho = collect($grupos)->flatMap(fn ($grupo) => $grupo['itens'])->filter(fn ($item) => $item['filho'] ?? false)
            ->map(fn ($item) => self::completar(self::daRotaDoFilho($item)))->values()->all();
        $posicao = array_search('Responsável', array_column($grupos, 'titulo'), true) + 1;
        array_splice($grupos, $posicao, 0, [['titulo' => 'Responsável · dados do filho', 'itens' => $doFilho]]);

        return self::$grupos = $grupos;
    }

    /** Todos os itens com rota, na ordem do menu. */
    public static function endpoints(): array
    {
        return collect(self::grupos())->flatMap(fn ($grupo) => $grupo['itens'])
            ->reject(fn ($item) => $item['guia'] ?? false)->values()->all();
    }

    /** Cada método e rota documentados: [['GET', '/api/v1/status'], ...], sem repetir. */
    public static function rotas(): array
    {
        return collect(self::endpoints())
            ->flatMap(fn ($item) => array_map(fn ($metodo) => [$metodo, "/api/v1{$item['rota']}"], $item['metodos']))
            ->unique(fn ($rota) => implode(' ', $rota))->values()->all();
    }

    /** Os ambientes do seletor do topo; a API em que a página está aberta vem marcada. */
    public static function servidores(string $atual): array
    {
        $servidores = [['nome' => 'Produção', 'url' => self::PRODUCAO], ['nome' => 'Local (Docker)', 'url' => self::LOCAL]];

        return in_array($atual, array_column($servidores, 'url'), true)
            ? $servidores
            : [...$servidores, ['nome' => 'Este servidor', 'url' => $atual]];
    }

    public static function json(mixed $valor): string
    {
        $json = json_encode($valor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // O PHP identa com 4 espaços; nos exemplos, 2
        return preg_replace_callback('/^ +/m', fn ($espacos) => str_repeat(' ', strlen($espacos[0]) / 2), $json);
    }

    /** Requisição de exemplo, [rótulo, linguagem, código], em cURL e em React Native. */
    public static function requisicoes(array $item, string $base): array
    {
        if (isset($item['exemplos'])) {
            return array_map(fn ($exemplo) => [$exemplo[0], $exemplo[1], str_replace('{BASE}', $base, $exemplo[2])], $item['exemplos']);
        }

        return [
            ['cURL', 'bash', self::curl($item, $base)],
            ['React Native', 'js', self::reactNative($item, $base)],
        ];
    }

    /** Coleção do Postman (formato 2.1), com as variáveis baseUrl e token. */
    public static function colecaoPostman(string $base): array
    {
        $pastas = collect(self::grupos())->map(function ($grupo) {
            $itens = collect($grupo['itens'])->reject(fn ($item) => $item['guia'] ?? false)
                ->flatMap(fn ($item) => array_map(fn ($metodo) => self::itemDoPostman($item, $metodo), $item['metodos']))
                ->values()->all();

            return ['name' => $grupo['titulo'], 'item' => $itens];
        })->filter(fn ($pasta) => $pasta['item'] !== [])->values()->all();

        return [
            'info' => [
                'name'   => 'API AACJ Futebol',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [['key' => 'baseUrl', 'value' => $base], ['key' => 'token', 'value' => '']],
            'item'     => $pastas,
        ];
    }

    private static function completar(array $item): array
    {
        if ($item['guia'] ?? false) {
            return $item + ['exemplos' => []];
        }

        $item['metodos'] = (array) $item['metodo'];
        $item['id'] ??= strtolower($item['metodos'][0]) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace(['{', '}'], '', $item['rota']))), '-');

        // Toda rota com login pode responder 401
        $item['respostas'] ??= [];
        if ($item['acesso'] !== 'publico') {
            $item['respostas'][401] ??= ['success' => false, 'message' => self::NAO_LOGADO];
        }
        ksort($item['respostas']);

        return $item;
    }

    private static function daRotaDoFilho(array $item): array
    {
        $parametro = ['{idAtleta}', 'inteiro', 'caminho', true, 'O filho, da lista de <code>/responsavel/atletas</code>.'];

        $item['rota']       = '/responsavel/atletas/{idAtleta}' . ($item['rota_do_filho'] ?? $item['rota']);
        $item['acesso']     = 'responsavel';
        $item['id']         = "filho-{$item['id']}";
        $item['filho']      = false;
        $item['parametros'] = [$parametro, ...($item['parametros'] ?? [])];
        $item['respostas'][404] ??= ['success' => false, 'message' => 'Atleta não encontrado.'];
        $item['doc'] = '<p>A mesma rota do atleta, para o responsável ver a do filho, com <strong>as mesmas respostas</strong>. '
            . 'O filho precisa ser do responsável logado e estar <code>ATIVO</code>; senão, <code>404</code> "Atleta não encontrado.".'
            . ($item['nota_do_filho'] ?? '') . '</p>' . $item['doc'];
        unset($item['nota_do_filho']);

        return $item;
    }

    private static function caminhoDeExemplo(array $item): string
    {
        $caminho = strtr($item['rota'], self::VALORES_DA_ROTA);

        return empty($item['query']) ? $caminho : $caminho . '?' . urldecode(http_build_query($item['query']));
    }

    private static function curl(array $item, string $base): string
    {
        $metodo = $item['metodos'][count($item['metodos']) - 1];
        $linhas = ['curl' . ($metodo === 'GET' ? '' : " -X {$metodo}") . ' "' . $base . self::caminhoDeExemplo($item) . '"', '  -H "Accept: application/json"'];

        if ($item['acesso'] !== 'publico') {
            $linhas[] = '  -H "Authorization: Bearer SEU_TOKEN"';
        }
        if (isset($item['corpo'])) {
            $linhas[] = '  -H "Content-Type: application/json"';
            $linhas[] = "  -d '" . str_replace("'", "'\\''", self::json($item['corpo'])) . "'";
        }

        return implode(" \\\n", $linhas);
    }

    private static function reactNative(array $item, string $base): string
    {
        $metodo   = $item['metodos'][count($item['metodos']) - 1];
        $cabecalhos = ['Accept: "application/json",'];
        if (isset($item['corpo'])) {
            $cabecalhos[] = '"Content-Type": "application/json",';
        }
        if ($item['acesso'] !== 'publico') {
            $cabecalhos[] = 'Authorization: `Bearer ${token}`,';
        }

        $opcoes = $metodo === 'GET' ? [] : ["  method: \"{$metodo}\","];
        $opcoes[] = '  headers: {';
        foreach ($cabecalhos as $cabecalho) {
            $opcoes[] = "    {$cabecalho}";
        }
        $opcoes[] = '  },';
        if (isset($item['corpo'])) {
            $corpo = str_replace("\n", "\n  ", self::json($item['corpo']));
            $opcoes[] = "  body: JSON.stringify({$corpo}),";
        }

        $inicio = ["const API = \"{$base}\";"];
        if ($item['acesso'] !== 'publico') {
            $inicio[] = 'const token = await AsyncStorage.getItem("token");';
        }

        return implode("\n", [
            ...$inicio, '',
            'const resposta = await fetch(`${API}' . self::caminhoDeExemplo($item) . '`, {',
            ...$opcoes,
            '});', '',
            'const json = await resposta.json();',
        ]);
    }

    private static function itemDoPostman(array $item, string $metodo): array
    {
        $caminho = self::caminhoDeExemplo($item);
        $pedido  = [
            'method' => $metodo,
            'header' => [['key' => 'Accept', 'value' => 'application/json']],
            'url'    => ['raw' => '{{baseUrl}}' . $caminho, 'host' => ['{{baseUrl}}'],
                'path' => array_values(array_filter(explode('/', strtok($caminho, '?')))),
                'query' => collect($item['query'] ?? [])->map(fn ($valor, $chave) => ['key' => $chave, 'value' => (string) $valor])->values()->all()],
        ];

        if ($item['acesso'] !== 'publico') {
            $pedido['auth'] = ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']]];
        }
        if (isset($item['corpo'])) {
            $pedido['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $pedido['body'] = ['mode' => 'raw', 'raw' => self::json($item['corpo']), 'options' => ['raw' => ['language' => 'json']]];
        }
        if ($item['arquivo'] ?? false) {
            $pedido['body'] = ['mode' => 'formdata', 'formdata' => [['key' => $item['arquivo'], 'type' => 'file', 'src' => []]]];
        }

        return ['name' => $item['titulo'] . (count($item['metodos']) > 1 ? " ({$metodo})" : ''), 'request' => $pedido];
    }
}
