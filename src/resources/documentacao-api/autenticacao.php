<?php

$x = require __DIR__ . '/_exemplos.php';

return [
    'titulo' => 'Autenticação',
    'itens'  => [
        [
            'titulo' => 'Login', 'metodo' => 'POST', 'rota' => '/auth/login', 'acesso' => 'publico',
            'doc' => <<<'HTML'
                <p>Entra num dos perfis e devolve o token (30 dias). Sem <code>perfil</code>, vale <code>atleta</code>. A resposta traz os dados básicos de quem entrou; no responsável, também os filhos <code>ATIVO</code>.</p>
                <p>O limite é por e-mail <strong>e perfil</strong>: errar a senha do responsável não bloqueia o atleta do mesmo e-mail.</p>
                HTML,
            'parametros' => [
                ['email', 'e-mail', 'corpo', true, 'O e-mail do perfil.'],
                ['senha', 'texto', 'corpo', true, 'A senha.'],
                ['perfil', 'texto', 'corpo', false, '<code>atleta</code> (padrão) ou <code>responsavel</code>.'],
                ['device_name', 'texto', 'corpo', false, 'Nome do aparelho no token (até 100 caracteres).'],
            ],
            'corpo' => ['email' => 'carla@exemplo.com', 'senha' => '********', 'perfil' => 'responsavel', 'device_name' => 'celular-da-carla'],
            'respostas' => [
                200 => ['success' => true, 'message' => 'Login realizado com sucesso.', 'data' => [
                    'token' => '13|GhIjKl...', 'perfil' => 'responsavel',
                    'responsavel' => ['id_responsavel' => 5, 'nome_responsavel' => 'Carla Exemplo', 'email_responsavel' => 'carla@exemplo.com'],
                    'atletas' => [$x['atletaBasico']],
                ]],
                401 => ['success' => false, 'message' => 'E-mail ou senha inválidos.'],
                403 => ['success' => false, 'message' => 'Cadastro não está ativo. Procure a secretaria da escolinha.'],
                422 => ['success' => false, 'message' => 'O valor de perfil não é válido.', 'errors' => ['perfil' => ['O valor de perfil não é válido.']]],
                429 => ['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'],
            ],
            'nota_das_respostas' => 'O atleta recebe <code>"perfil": "atleta"</code> e o bloco <code>atleta</code> (<code>id_atleta</code>, <code>nome_atleta</code>, <code>email_atleta</code>, <code>numero_matricula_atleta</code>, <code>foto_atleta</code>, <code>foto_url</code>) no lugar de <code>responsavel</code> e <code>atletas</code>. No <code>403</code>, o responsável sem filho ativo recebe "Nenhum atleta ativo vinculado a este responsável. Procure a secretaria da escolinha.".',
        ],
        [
            'titulo' => 'Logout', 'metodo' => 'POST', 'rota' => '/auth/logout', 'acesso' => 'conta',
            'doc' => '<p>Invalida só o token usado na requisição. No app, envie antes o <a href="#delete-dispositivos"><code>DELETE /dispositivos</code></a>, para o aparelho deixar de receber push da conta.</p>',
            'respostas' => [200 => ['success' => true, 'message' => 'Logout realizado com sucesso.']],
        ],
    ],
];
