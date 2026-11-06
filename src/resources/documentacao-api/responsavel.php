<?php

$x = require __DIR__ . '/_exemplos.php';

$responsavel = [
    'id_responsavel' => 5, 'nome_responsavel' => 'Carla Exemplo', 'email_responsavel' => 'carla@exemplo.com', 'cpf_responsavel' => '123.456.789-09',
    'rg_responsavel' => '', 'telefone_responsavel' => null, 'whatsapp_responsavel' => '(11) 90000-0000',
    'endereco' => [
        'cep_endereco' => '13560-000', 'rua_endereco' => 'Rua das Flores', 'numero_endereco' => '120', 'bairro_endereco' => 'Centro',
        'complemento_endereco' => null, 'cidade_endereco' => 'São Carlos', 'estado_endereco' => 'SP',
        'compartilhado_com' => [['id_atleta' => 7, 'nome_atleta' => 'Joana Exemplo']],
    ],
    'atletas' => [$x['atletaBasico']],
];

return [
    'titulo' => 'Responsável',
    'itens'  => [
        [
            'titulo' => 'Dados do responsável', 'metodo' => 'GET', 'rota' => '/responsavel', 'acesso' => 'responsavel',
            'doc' => '<p>Os dados do responsável logado, o endereço e os filhos <code>ATIVO</code>. A senha nunca vem.</p>',
            'campos' => [['endereco.compartilhado_com', 'Os filhos que moram no <strong>mesmo endereço</strong> (o mesmo cadastro de endereço, ou outro com o mesmo endereço escrito; lista vazia quando nenhum), para o app perguntar se a mudança vale também para eles.']],
            'respostas' => [200 => ['success' => true, 'data' => $responsavel]],
        ],
        [
            'titulo' => 'Atualizar responsável', 'metodo' => ['PUT', 'PATCH'], 'rota' => '/responsavel', 'acesso' => 'responsavel',
            'doc' => '<p>Mande só o que mudou; campos fora desta lista, como o RG, são ignorados. Responde com os dados de <code>GET /responsavel</code>, já atualizados. Cada mudança fica registrada para a secretaria: o painel mostra o antes e o depois.</p>',
            'parametros' => [
                ['nome_responsavel', 'texto', 'corpo', false, 'De 3 a 100 caracteres.'],
                ['cpf_responsavel', 'texto', 'corpo', false, 'Com ou sem pontuação; volta como <code>000.000.000-00</code>. Os dígitos verificadores são conferidos e o CPF não pode ser de outro responsável.'],
                ['email_responsavel', 'e-mail', 'corpo', false, 'O login: único entre responsáveis, gravado em minúsculas.'],
                ['telefone_responsavel', 'texto', 'corpo', false, 'Até 20 caracteres.'],
                ['whatsapp_responsavel', 'texto', 'corpo', false, 'Até 20 caracteres.'],
                ['endereco', 'objeto', 'corpo', false, 'O <strong>bloco inteiro</strong>: <code>cep_endereco</code> (8 dígitos, com ou sem hífen; volta como <code>00000-000</code>), <code>rua_endereco</code> (até 100), <code>numero_endereco</code> (até 6), <code>bairro_endereco</code> (até 50), <code>complemento_endereco</code> (opcional), <code>cidade_endereco</code> (até 50) e <code>estado_endereco</code> (UF, 2 letras). Os erros vêm por campo, como <code>errors["endereco.cep_endereco"]</code>.'],
                ['endereco_dos_filhos', 'booleano', 'corpo', false, 'Padrão <code>true</code>. Só conta quando o endereço é compartilhado: <code>true</code> muda o do responsável e o dos filhos de <code>compartilhado_com</code>; <code>false</code> muda só o do responsável. O endereço de quem não está na lista nunca muda.'],
            ],
            'corpo' => [
                'nome_responsavel' => 'Carla Exemplo da Silva', 'cpf_responsavel' => '19472778984',
                'endereco' => ['cep_endereco' => '13560000', 'rua_endereco' => 'Rua das Flores', 'numero_endereco' => '120', 'bairro_endereco' => 'Centro', 'complemento_endereco' => null, 'cidade_endereco' => 'São Carlos', 'estado_endereco' => 'SP'],
                'endereco_dos_filhos' => false,
            ],
            'respostas' => [
                200 => ['success' => true, 'data' => array_replace($responsavel, ['nome_responsavel' => 'Carla Exemplo da Silva', 'cpf_responsavel' => '194.727.789-84'])],
                422 => ['success' => false, 'message' => 'Este CPF já está cadastrado para outro responsável.', 'errors' => ['cpf_responsavel' => ['Este CPF já está cadastrado para outro responsável.']]],
            ],
            'nota_das_respostas' => 'CEP inválido: <code>422</code> com "Informe um CEP com 8 dígitos (ex.: 13560-000)." em <code>errors["endereco.cep_endereco"]</code>.',
        ],
        [
            'titulo' => 'Alterar senha', 'metodo' => 'PUT', 'rota' => '/responsavel/senha', 'acesso' => 'responsavel',
            'doc' => '<p>Troca a senha e encerra as sessões dos outros aparelhos. O token atual continua valendo.</p>',
            'parametros' => [
                ['senha_atual', 'texto', 'corpo', true, 'A senha de agora.'],
                ['nova_senha', 'texto', 'corpo', true, 'Pelo menos 8 caracteres.'],
                ['nova_senha_confirmation', 'texto', 'corpo', true, 'Igual à nova senha.'],
            ],
            'corpo' => ['senha_atual' => '********', 'nova_senha' => '********', 'nova_senha_confirmation' => '********'],
            'respostas' => [
                200 => ['success' => true, 'message' => 'Senha alterada com sucesso.'],
                422 => ['success' => false, 'message' => 'Senha atual incorreta.'],
            ],
        ],
        [
            'titulo' => 'Listar filhos', 'metodo' => 'GET', 'rota' => '/responsavel/atletas', 'acesso' => 'responsavel',
            'doc' => '<p>Os filhos <code>ATIVO</code>, para escolher de qual ver a agenda e os avisos. Cada um com os avisos ainda não lidos <strong>por este responsável</strong>.</p>',
            'respostas' => [200 => ['success' => true, 'data' => [[...$x['atletaBasico'], 'notificacoes_nao_lidas' => 3]]]],
        ],
        [
            'titulo' => 'Dados do filho', 'metodo' => 'GET', 'rota' => '/responsavel/atletas/{idAtleta}', 'acesso' => 'responsavel',
            'doc' => <<<'HTML'
                <p>Os dados do filho, <strong>exatamente o mesmo JSON</strong> de <a href="#get-atleta"><code>GET /atleta</code></a>, inclusive o histórico em <code>categorias</code>, em modo leitura: o responsável não edita o filho (<code>405</code>).</p>
                <p>Em todas as rotas de um filho, o servidor confere que o atleta é filho do responsável logado e está <code>ATIVO</code>; senão, <code>404</code> (o mesmo para atleta de outra família, inativado ou inexistente).</p>
                HTML,
            'parametros' => [['{idAtleta}', 'inteiro', 'caminho', true, 'O filho, da lista de <code>/responsavel/atletas</code>.']],
            'respostas' => [
                200 => ['success' => true, 'data' => $x['atleta']],
                404 => ['success' => false, 'message' => 'Atleta não encontrado.'],
            ],
        ],
    ],
];
