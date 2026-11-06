<?php

$x = require __DIR__ . '/_exemplos.php';

$leitura = ' A leitura é <strong>de cada pessoa</strong>: o que o responsável marca como lido não muda a leitura do atleta nem a do outro responsável.';

return [
    'titulo' => 'Avisos (notificações)',
    'itens'  => [
        [
            'id' => 'avisos-get', 'titulo' => 'Listar avisos', 'metodo' => 'GET', 'rota' => '/notificacoes', 'acesso' => 'atleta', 'filho' => true, 'nota_do_filho' => $leitura,
            'doc' => '<p>Os avisos do atleta, do mais novo para o mais antigo, com 20 por página. <code>lida</code> e <code>data_leitura</code> são de quem está lendo. Título e mensagem ficam como foram enviados.</p>',
            'parametros' => [['page', 'inteiro', 'query', false, 'A página (padrão 1).']],
            'query' => ['page' => 1],
            'campos' => [
                ['tipo / tipo_label', '<code>INSCRICAO</code>, <code>REMOCAO</code>, <code>ALTERACAO</code>, <code>CANCELAMENTO</code> e <code>REATIVACAO</code> (com <code>id_evento</code>); <code>AGENDA</code> (resumo, como "Agenda de dezembro disponível", ou o aviso de afastamento) e <code>AVISO</code> (recado da escolinha, inclusive "Foto aprovada" e "Foto não aprovada"), com <code>id_evento</code> <code>null</code>.'],
                ['id_evento', 'O evento do aviso, para abrir o <a href="#get-agenda-idevento">detalhe</a>.'],
            ],
            'respostas' => [200 => ['success' => true, 'data' => ['notificacoes' => [$x['aviso']], 'paginacao' => ['pagina_atual' => 1, 'ultima_pagina' => 1, 'por_pagina' => 20, 'total' => 1]]]],
        ],
        [
            'titulo' => 'Contar não lidos', 'metodo' => 'GET', 'rota' => '/notificacoes/nao-lidas', 'acesso' => 'atleta', 'filho' => true, 'nota_do_filho' => $leitura,
            'doc' => '<p>Quantos avisos ainda não foram lidos por quem está logado.</p>',
            'respostas' => [200 => ['success' => true, 'data' => ['nao_lidas' => 3]]],
        ],
        [
            'titulo' => 'Marcar aviso como lido', 'metodo' => 'PATCH', 'rota' => '/notificacoes/{idNotificacao}/lida', 'acesso' => 'atleta', 'filho' => true, 'nota_do_filho' => $leitura,
            'doc' => '<p>Marca um aviso como lido e devolve o aviso atualizado. Marcar de novo mantém a data da primeira leitura. Aviso de outro atleta, ou inexistente: <code>404</code>.</p>',
            'parametros' => [['{idNotificacao}', 'inteiro', 'caminho', true, 'O aviso.']],
            'respostas' => [
                200 => ['success' => true, 'data' => array_replace($x['aviso'], ['lida' => true, 'data_leitura' => '2026-10-10T12:00:00-03:00'])],
                404 => ['success' => false, 'message' => 'Notificação não encontrada.'],
            ],
        ],
        [
            'titulo' => 'Marcar todos como lidos', 'metodo' => 'PATCH', 'rota' => '/notificacoes/lidas', 'acesso' => 'atleta', 'filho' => true, 'nota_do_filho' => $leitura,
            'doc' => '<p>Marca todos os avisos não lidos como lidos e devolve quantos mudaram.</p>',
            'respostas' => [200 => ['success' => true, 'data' => ['marcadas' => 3]]],
        ],
    ],
];
