<?php

// A tabela é montada na view, a partir das rotas documentadas (partial api._todas-as-rotas)
return [
    'titulo' => 'Referência',
    'itens'  => [
        [
            'guia' => true, 'id' => 'rotas', 'titulo' => 'Todas as rotas', 'largura_total' => true,
            'doc' => '<p>Todas as rotas da API em uma tabela. <code>{nome}</code> vai no caminho da URL; <code>?</code> no fim do nome indica um parâmetro opcional; nas rotas <code>GET</code> os parâmetros vão na query string e nas demais no corpo JSON (a foto, em <code>multipart/form-data</code>). As rotas que não são <strong>públicas</strong> exigem <code>Authorization: Bearer SEU_TOKEN</code> do perfil indicado.</p>',
        ],
    ],
];
