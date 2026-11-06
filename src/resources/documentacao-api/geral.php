<?php

$x = require __DIR__ . '/_exemplos.php';

return [
    'titulo' => 'Geral e site',
    'itens'  => [
        [
            'titulo' => 'Status da API', 'metodo' => 'GET', 'rota' => '/status', 'acesso' => 'publico',
            'doc' => '<p>Verifica se a API está online. Útil para testar a conexão.</p>',
            'respostas' => [200 => ['success' => true, 'data' => ['aplicacao' => 'EscolinhaFutebol', 'api' => 'v1', 'status' => 'online']]],
        ],
        [
            'titulo' => 'Listar banners', 'metodo' => 'GET', 'rota' => '/banners', 'acesso' => 'publico',
            'doc' => '<p>Banners ativos da home, na ordem de exibição.</p>',
            'respostas' => [200 => ['success' => true, 'data' => [[
                'id_banner' => 1, 'titulo_banner' => 'BEM-VINDO À AACJ', 'subtitulo_banner' => 'Promovendo transformação social, esporte e cidadania desde 2001.',
                'foto_banner' => 'banner1.jpg', 'ordem_banner' => 1, 'status_banner' => 'ATIVO', 'foto_url' => "{$x['imagens']}/banner/banner1.jpg",
            ]]]],
        ],
        [
            'titulo' => 'Listar categorias', 'metodo' => 'GET', 'rota' => '/categorias', 'acesso' => 'publico',
            'doc' => '<p>Categorias ativas (Sub-9 a Sub-17, masculino e feminino), da mais nova para a mais velha. <code>sexo_categoria</code>: <code>M</code> ou <code>F</code>.</p>',
            'respostas' => [200 => ['success' => true, 'data' => [
                ['id_categoria' => 5, 'nome_categoria' => 'Sub-13', 'idade_min_categoria' => 12, 'idade_max_categoria' => 13, 'sexo_categoria' => 'M', 'status_categoria' => 'ATIVO'],
            ]]],
        ],
        [
            'titulo' => 'Times da categoria', 'metodo' => 'GET', 'rota' => '/categorias/{id}/times', 'acesso' => 'publico',
            'doc' => '<p>Os times ativos que têm a categoria marcada no cadastro, pelo nome. Um mesmo time pode estar em várias categorias. <code>da_aacj</code> é <code>true</code> nos times da escolinha.</p>',
            'parametros' => [['{id}', 'inteiro', 'caminho', true, 'A categoria.']],
            'respostas' => [
                200 => ['success' => true, 'data' => [
                    'categoria' => ['id_categoria' => 5, 'nome_categoria' => 'Sub-13'],
                    'times' => [['id_time' => 1, 'nome_time' => 'AACJ Masculino', 'logo_time' => 'aacj.png', 'tipo_time' => 'INTERNO', 'logo_url' => "{$x['imagens']}/team/aacj.png", 'da_aacj' => true]],
                ]],
                404 => ['success' => false, 'message' => 'Categoria não encontrada.'],
            ],
        ],
        [
            'titulo' => 'Listar notícias', 'metodo' => 'GET', 'rota' => '/noticias', 'acesso' => 'publico',
            'doc' => '<p>Notícias ativas, da mais recente para a mais antiga, sem o texto completo (use o detalhe para o <code>conteudo_noticia</code>).</p>',
            'parametros' => [['categoria', 'texto', 'query', false, 'Só as notícias dessa categoria, com o nome exato (<code>Campeonatos</code>).']],
            'respostas' => [200 => ['success' => true, 'data' => [[
                'id_noticia' => 1, 'titulo_noticia' => 'Sub-15 goleia na estreia da Copa', 'foto_noticia' => 'sub15.jpg', 'categoria_noticia' => 'Campeonatos',
                'data_publicacao_noticia' => '2026-05-28T09:00:00-03:00', 'autor_noticia' => 'Prof. Caio', 'foto_url' => "{$x['imagens']}/news/sub15.jpg",
            ]]]],
        ],
        [
            'titulo' => 'Detalhar notícia', 'metodo' => 'GET', 'rota' => '/noticias/{id}', 'acesso' => 'publico',
            'doc' => '<p>A notícia completa, com o <code>conteudo_noticia</code>.</p>',
            'parametros' => [['{id}', 'inteiro', 'caminho', true, 'A notícia.']],
            'respostas' => [
                200 => ['success' => true, 'data' => [
                    'id_noticia' => 1, 'titulo_noticia' => 'Sub-15 goleia na estreia da Copa', 'conteudo_noticia' => 'Nossa categoria de base entrou em campo...',
                    'foto_noticia' => 'sub15.jpg', 'categoria_noticia' => 'Campeonatos', 'data_publicacao_noticia' => '2026-05-28T09:00:00-03:00',
                    'autor_noticia' => 'Prof. Caio', 'status_noticia' => 'ATIVO', 'foto_url' => "{$x['imagens']}/news/sub15.jpg",
                ]],
                404 => ['success' => false, 'message' => 'Notícia não encontrada.'],
            ],
        ],
    ],
];
