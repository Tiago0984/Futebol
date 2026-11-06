<?php

use App\Support\DocumentacaoApi;

$invalido = ['success' => false, 'message' => 'O campo token é obrigatório.', 'errors' => ['token' => ['O campo token é obrigatório.']]];

return [
    'titulo' => 'Aparelhos e push',
    'itens'  => [
        [
            'guia' => true, 'id' => 'push', 'titulo' => 'Notificações push',
            'doc' => <<<'HTML'
                <p>Os avisos continuam gravados na API. Depois da ação no painel, o servidor tenta enviar um push (Expo) aos aparelhos ativos do atleta e dos responsáveis vinculados. Sem aparelho ativo, não há envio. Falha no push não desfaz a ação nem o aviso.</p>
                <p>Responsável com mais de um filho recebe o título com o primeiro nome do filho ("Joana · Aviso da escolinha").</p>
                <h3>Dados do push</h3>
                <p>O exemplo ao lado é o campo <code>data</code> do push, com ids inteiros. <code>id_evento</code> só existe quando o aviso tem evento: abra o compromisso; sem ele, abra a lista de avisos. No perfil responsável, selecione primeiro o filho por <code>id_atleta</code>. O push não marca o aviso como lido: use as rotas de leitura.</p>
                HTML,
            'exemplos' => [
                ['titulo' => 'Campo data do push', 'lang' => 'json', 'rotulo' => 'JSON', 'codigo' => DocumentacaoApi::json(['id_notificacao' => 310, 'id_atleta' => 17, 'id_evento' => 42, 'tipo' => 'INSCRICAO'])],
            ],
        ],
        [
            'titulo' => 'Registrar aparelho', 'metodo' => 'POST', 'rota' => '/dispositivos', 'acesso' => 'app',
            'doc' => <<<'HTML'
                <p>Registre o aparelho ao abrir o app logado e ao mudar a preferência de notificações. O dono vem do token: não envie ids de contas. Se outra pessoa entrar no mesmo aparelho, o POST transfere o token para ela.</p>
                <p>A rota funciona mesmo depois da inativação da conta, para desligar o aparelho; o envio só contempla atletas ativos. Vale o limite geral da API, sem limite adicional.</p>
                HTML,
            'parametros' => [
                ['token', 'texto', 'corpo', true, 'O token Expo, até 255 caracteres, com o prefixo <code>ExponentPushToken[</code> ou <code>ExpoPushToken[</code>, conteúdo não vazio e <code>]</code> no fim. Diferencia maiúsculas de minúsculas.'],
                ['plataforma', 'texto', 'corpo', true, '<code>android</code> ou <code>ios</code>.'],
                ['notificacoes_ativas', 'booleano', 'corpo', true, '<code>false</code> guarda o aparelho desligado.'],
            ],
            'corpo' => ['token' => 'ExponentPushToken[xxxx]', 'plataforma' => 'android', 'notificacoes_ativas' => true],
            'respostas' => [200 => ['success' => true], 422 => $invalido],
        ],
        [
            'titulo' => 'Remover aparelho', 'metodo' => 'DELETE', 'rota' => '/dispositivos', 'acesso' => 'app',
            'doc' => '<p>No logout, envie este DELETE <strong>antes</strong> de encerrar o token. Apaga só o aparelho da conta logada; se o token não existir ou for de outra conta, responde o mesmo <code>200</code>.</p>',
            'parametros' => [['token', 'texto', 'corpo', true, 'O token Expo do aparelho.']],
            'corpo' => ['token' => 'ExponentPushToken[xxxx]'],
            'respostas' => [200 => ['success' => true], 422 => $invalido],
        ],
    ],
];
