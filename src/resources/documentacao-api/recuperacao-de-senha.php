<?php

return [
    'titulo' => 'Recuperação de senha',
    'itens'  => [
        [
            'guia' => true, 'id' => 'recuperacao', 'titulo' => 'Visão geral do fluxo',
            'doc' => <<<'HTML'
                <p>O "Esqueci minha senha" do app tem três passos:</p>
                <ol>
                    <li><a href="#post-auth-esqueci-senha">Solicitar o código</a>: 4 dígitos no e-mail do perfil, válidos por 15 minutos.</li>
                    <li><a href="#post-auth-validar-codigo">Validar o código</a>: devolve um <code>reset_token</code>, válido por 10 minutos.</li>
                    <li><a href="#post-auth-redefinir-senha">Redefinir a senha</a> com esse token. Depois, o app volta ao login.</li>
                </ol>
                <p>Nas três rotas, sem <code>perfil</code>, vale <code>atleta</code>, como no login. O app deve guardar o e-mail e o perfil até a tela de redefinição.</p>
                <h3>Links "Defina sua senha"</h3>
                <p>Não são rota da API. Os convites chegam por link na <strong>aprovação da matrícula</strong> (para o atleta, se tiver e-mail, e para o responsável que ainda não tem senha) e quando a secretaria usa <strong>"Reenviar convite"</strong>. O link abre uma página do site, <code>/senha/{perfil}/{token}?email=...</code>, onde a pessoa digita a senha (mínimo de 8 caracteres).</p>
                <ul>
                    <li>O link vale <strong>24 horas</strong> e só pode ser usado uma vez. Vencido, a página orienta a recuperar o acesso por código no app.</li>
                    <li>Ao definir a senha, as sessões do app daquele perfil são encerradas. O outro perfil, mesmo com o mesmo e-mail, continua conectado.</li>
                </ul>
                HTML,
        ],
        [
            'titulo' => 'Solicitar código', 'metodo' => 'POST', 'rota' => '/auth/esqueci-senha', 'acesso' => 'publico',
            'doc' => <<<'HTML'
                <p>Envia ao e-mail do perfil um <strong>código de 4 dígitos, válido por 15 minutos</strong>. A resposta é <strong>sempre a mesma</strong>, exista ou não o e-mail, e o e-mail sai depois da resposta (a resposta não demora mais quando a conta existe).</p>
                <p>Só envia para quem pode entrar no app (atleta <code>ATIVO</code>; responsável com filho <code>ATIVO</code>), no máximo uma vez a cada 60 segundos por conta. Pedidos segurados por esse intervalo e falhas no e-mail recebem a mesma resposta.</p>
                <p>Um novo envio invalida o código e o <code>reset_token</code> anteriores daquela conta.</p>
                HTML,
            'parametros' => [
                ['email', 'e-mail', 'corpo', true, 'O e-mail do perfil.'],
                ['perfil', 'texto', 'corpo', false, '<code>atleta</code> (padrão) ou <code>responsavel</code>.'],
            ],
            'corpo' => ['email' => 'carla@exemplo.com', 'perfil' => 'responsavel'],
            'respostas' => [
                200 => ['success' => true, 'message' => 'Se o e-mail estiver cadastrado, enviaremos um código de verificação.'],
                429 => ['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'],
            ],
        ],
        [
            'titulo' => 'Validar código', 'metodo' => 'POST', 'rota' => '/auth/validar-codigo', 'acesso' => 'publico',
            'doc' => <<<'HTML'
                <p>Envie o código como <strong>texto com exatamente quatro dígitos</strong>, preservando zeros iniciais. Ele tem no máximo 5 tentativas erradas, mesmo trocando de IP: a quinta invalida o código. Validar corretamente também consome o código.</p>
                <p>O <code>reset_token</code> é aleatório, de uso único e válido por <strong>10 minutos</strong>. Não é um token de login e não deve ser salvo no AsyncStorage: use-o na próxima chamada.</p>
                <p>A mesma mensagem de erro vale para código incorreto, expirado, consumido, bloqueado, e-mail sem recuperação ou conta sem acesso.</p>
                HTML,
            'parametros' => [
                ['email', 'e-mail', 'corpo', true, 'O e-mail que recebeu o código.'],
                ['perfil', 'texto', 'corpo', false, '<code>atleta</code> (padrão) ou <code>responsavel</code>.'],
                ['codigo', 'texto', 'corpo', true, 'Os 4 dígitos, como texto.'],
            ],
            'corpo' => ['email' => 'carla@exemplo.com', 'perfil' => 'responsavel', 'codigo' => '0042'],
            'respostas' => [
                200 => ['success' => true, 'data' => ['reset_token' => 'token-aleatorio-retornado-pela-api']],
                422 => ['success' => false, 'message' => 'Código inválido ou expirado.', 'errors' => ['codigo' => ['Código inválido ou expirado.']]],
                429 => ['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'],
            ],
        ],
        [
            'titulo' => 'Redefinir senha', 'metodo' => 'POST', 'rota' => '/auth/redefinir-senha', 'acesso' => 'publico',
            'doc' => <<<'HTML'
                <p>E-mail e perfil já estão ligados ao token e não são enviados nesta etapa. Senha inválida ou confirmação diferente devolvem <code>422</code> em <code>errors.nova_senha</code>, sem consumir o token. Token inválido, usado ou expirado: <code>422</code> com "Token de redefinição inválido ou expirado." em <code>errors.reset_token</code>.</p>
                <p>Ao concluir, todos os tokens daquela conta são revogados, assim como códigos, tokens de redefinição e links antigos. O outro perfil, mesmo com o mesmo e-mail, não muda. A rota não faz login: o app volta para a tela de login.</p>
                HTML,
            'parametros' => [
                ['reset_token', 'texto', 'corpo', true, 'O token devolvido pela validação do código.'],
                ['nova_senha', 'texto', 'corpo', true, 'Pelo menos 8 caracteres.'],
                ['nova_senha_confirmation', 'texto', 'corpo', true, 'Igual à nova senha.'],
            ],
            'corpo' => ['reset_token' => 'token-retornado-na-validacao', 'nova_senha' => 'minha-nova-senha', 'nova_senha_confirmation' => 'minha-nova-senha'],
            'respostas' => [
                200 => ['success' => true, 'message' => 'Senha redefinida com sucesso. Faça login novamente.'],
                422 => ['success' => false, 'message' => 'A nova senha precisa ter pelo menos 8 caracteres.', 'errors' => ['nova_senha' => ['A nova senha precisa ter pelo menos 8 caracteres.']]],
                429 => ['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'],
            ],
        ],
    ],
];
