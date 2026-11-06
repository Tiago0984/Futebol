<?php

return [
    'titulo' => 'Fale conosco',
    'itens'  => [
        [
            'titulo' => 'Enviar mensagem', 'metodo' => 'POST', 'rota' => '/fale-conosco', 'acesso' => 'app',
            'doc' => <<<'HTML'
                <p>Exige o token de um atleta <code>ATIVO</code> ou de um responsável com filho <code>ATIVO</code>. O remetente, o perfil e o e-mail de resposta vêm do token; não envie esses dados no corpo. Espaços nas pontas são removidos e campos vazios são recusados.</p>
                <p><code>id_fale_conosco</code> é o protocolo da mensagem. O <strong>201 confirma a gravação</strong> para atendimento pelo painel. O e-mail de aviso à escolinha sai depois, pela fila, e uma falha nele não desfaz a gravação: não reenvie a mensagem.</p>
                <p>O limite é de 3 por minuto por conta, compartilhado entre os tokens dela, e requisições inválidas também contam. Bloqueada, a rota não grava nem envia nada.</p>
                HTML,
            'parametros' => [
                ['assunto', 'texto', 'corpo', true, 'Até 100 caracteres.'],
                ['mensagem', 'texto', 'corpo', true, 'Até 2000 caracteres.'],
            ],
            'corpo' => ['assunto' => 'Dúvida sobre os treinos', 'mensagem' => 'Gostaria de confirmar o horário do treino.'],
            'respostas' => [
                201 => ['success' => true, 'message' => 'Mensagem recebida com sucesso.', 'data' => ['id_fale_conosco' => 123, 'data_envio' => '2026-10-31T10:00:00-03:00']],
                403 => ['success' => false, 'message' => 'Cadastro não está ativo. Procure a secretaria da escolinha.'],
                422 => ['success' => false, 'message' => 'O campo assunto é obrigatório.', 'errors' => ['assunto' => ['O campo assunto é obrigatório.'], 'mensagem' => ['O campo mensagem é obrigatório.']]],
                429 => ['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'],
            ],
        ],
    ],
];
