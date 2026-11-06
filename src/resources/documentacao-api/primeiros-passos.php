<?php

use App\Support\DocumentacaoApi;

return [
    'titulo' => 'Primeiros passos',
    'itens'  => [
        [
            'guia' => true, 'id' => 'introducao', 'titulo' => 'Introdução',
            'doc' => <<<'HTML'
                <p class="lead">API REST do aplicativo da Escolinha de Futebol AACJ (Associação Atlética Cohab Jucelino e Adjacência), usada pelo atleta e pelo responsável: dados, agenda de treinos e jogos, avisos, campeonatos e os conteúdos públicos do site.</p>
                <p>A API recebe e devolve <strong>JSON</strong> e usa os códigos de status HTTP para indicar sucesso ou falha. Todas as rotas começam com <code>/api/v1</code>.</p>
                <h3>Base URL</h3>
                <p>As rotas desta documentação são relativas à URL base do ambiente. Troque o ambiente nos botões do topo para atualizar todos os exemplos.</p>
                <div class="table-wrap"><table class="table compacta">
                    <thead><tr><th>Ambiente</th><th>URL base</th></tr></thead>
                    <tbody>
                        <tr><td>Produção</td><td><code>{PRODUCAO}</code></td></tr>
                        <tr><td>Local (Docker)</td><td><code>{LOCAL}</code></td></tr>
                    </tbody>
                </table></div>
                <p>Selecionado agora: <code class="js-base">{BASE}</code></p>
                <h3>Como ler esta página</h3>
                <p>O menu da esquerda lista todas as rotas com o método (<span class="method get">GET</span> <span class="method post">POST</span> <span class="method put">PUT</span> <span class="method patch">PATCH</span> <span class="method delete">DELETE</span>). Use a busca e os filtros de método para achar uma rota. À direita de cada uma, na prancheta, ficam a requisição, em cURL e em React Native, e as respostas por código de status. Cada rota tem o seu número de camisa.</p>
                <table class="table">
                    <thead><tr><th>Etiqueta</th><th>Quem pode chamar</th></tr></thead>
                    <tbody>
                        <tr><td><span class="auth-tag publico">Pública</span></td><td>Qualquer um, sem token</td></tr>
                        <tr><td><span class="auth-tag atleta">Atleta</span></td><td>Token do perfil atleta, com o cadastro <code>ATIVO</code></td></tr>
                        <tr><td><span class="auth-tag responsavel">Responsável</span></td><td>Token do perfil responsável, com algum filho <code>ATIVO</code></td></tr>
                        <tr><td><span class="auth-tag conta">Atleta ou responsável</span></td><td>Token de qualquer um dos dois perfis</td></tr>
                    </tbody>
                </table>
                HTML,
            'exemplos' => [
                ['titulo' => 'Requisição de teste', 'lang' => 'bash', 'rotulo' => 'cURL', 'codigo' => "curl \"{BASE}/status\" \\\n  -H \"Accept: application/json\""],
                ['titulo' => 'Resposta', 'lang' => 'json', 'rotulo' => '200', 'codigo' => DocumentacaoApi::json(['success' => true, 'data' => ['aplicacao' => 'EscolinhaFutebol', 'api' => 'v1', 'status' => 'online']])],
            ],
        ],
        [
            'guia' => true, 'id' => 'autenticacao', 'titulo' => 'Perfis e token',
            'doc' => <<<'HTML'
                <p>O app tem <strong>dois perfis de login</strong>, cada um com o próprio e-mail e senha:</p>
                <ul>
                    <li><strong>atleta</strong> (padrão): vê e edita os próprios dados, vê a sua agenda e os seus avisos. Só entra com o cadastro <code>ATIVO</code>.</li>
                    <li><strong>responsavel</strong>: vê os próprios dados e, de cada filho <code>ATIVO</code>, tudo o que o atleta vê, em modo leitura. Escolhe o filho pela lista de <a href="#get-responsavel-atletas"><code>/responsavel/atletas</code></a>. Só entra com pelo menos um filho <code>ATIVO</code>.</li>
                </ul>
                <p>O mesmo e-mail pode ser de um atleta e de um responsável; o campo <code>perfil</code> do login diz qual conta usar. Um responsável é um cadastro só, com um login, mesmo com vários filhos.</p>
                <h3>Token</h3>
                <p>O login devolve um token (Laravel Sanctum) que vale <strong>30 dias</strong>. Envie em todas as rotas protegidas:</p>
                <p><code>Authorization: Bearer SEU_TOKEN</code></p>
                <p>Depois de 30 dias, ou depois do logout, o token responde <code>401</code>: faça login de novo. Ele também deixa de valer quando:</p>
                <ul>
                    <li>o atleta é inativado, ou o responsável fica sem filho ativo;</li>
                    <li>a senha é redefinida pelo código do "Esqueci minha senha" ou pelo link "Defina sua senha" (todos os tokens daquele perfil);</li>
                    <li>a senha é trocada no app (os dos outros aparelhos; o token atual continua valendo).</li>
                </ul>
                <p><strong>Cada token só entra na área do seu perfil:</strong> o token do atleta recebe <code>403</code> nas rotas <code>/responsavel...</code> e o do responsável recebe <code>403</code> nas rotas do atleta.</p>
                HTML,
            'exemplos' => [
                ['titulo' => 'Cabeçalho das rotas protegidas', 'lang' => 'bash', 'rotulo' => 'cURL', 'codigo' => "curl \"{BASE}/atleta\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Authorization: Bearer SEU_TOKEN\""],
                ['titulo' => 'Token de outro perfil', 'lang' => 'json', 'rotulo' => '403', 'codigo' => DocumentacaoApi::json(['success' => false, 'message' => 'Esta área é do perfil responsável. Entre no app como responsável.'])],
            ],
        ],
        [
            'guia' => true, 'id' => 'postman', 'titulo' => 'Testando no Postman',
            'doc' => <<<'HTML'
                <p>O botão <strong>Coleção Postman</strong>, no topo, baixa um arquivo com todas as rotas, já separadas pelos grupos do menu.</p>
                <ol>
                    <li>No Postman, use <em>Import</em> e escolha o arquivo baixado.</li>
                    <li>A coleção traz as variáveis <code>baseUrl</code> (o ambiente escolhido no topo ao baixar) e <code>token</code>.</li>
                    <li>Faça o <strong>Login</strong> e copie o <code>token</code> da resposta para a variável <code>token</code> da coleção. As rotas protegidas já usam essa variável.</li>
                </ol>
                HTML,
        ],
        [
            'guia' => true, 'id' => 'respostas', 'titulo' => 'Respostas e erros',
            'doc' => <<<'HTML'
                <p>Todas as respostas são JSON e têm o campo <code>success</code>. Em caso de sucesso, os dados vêm em <code>data</code>; em caso de erro (qualquer um: 401, 403, 404, 405, 422, 429, 500), vem uma <code>message</code> em português, que o app pode mostrar como está. Envie sempre o cabeçalho <code>Accept: application/json</code>.</p>
                <p>No <code>422</code> (dados inválidos) vem também <code>errors</code>, com as mensagens de cada campo; <code>message</code> é a primeira delas.</p>
                <p>Nos exemplos, cada resposta traz um cartão, como no futebol: <span class="cartao s2 cartao-texto"></span> verde para sucesso, <span class="cartao s4 cartao-texto"></span> amarelo para um erro de quem chamou (<code>4xx</code>) e <span class="cartao s5 cartao-texto"></span> vermelho para um erro do servidor (<code>5xx</code>).</p>
                <table class="table">
                    <thead><tr><th>Código</th><th>Quando</th></tr></thead>
                    <tbody>
                        <tr><td><code>200</code> / <code>201</code></td><td>Sucesso (<code>201</code> no Fale conosco: a mensagem foi gravada)</td></tr>
                        <tr><td><code>401</code></td><td>Login com e-mail ou senha errados ("E-mail ou senha inválidos."); rota protegida sem token, ou com token inválido, vencido ou encerrado ("Faça login para continuar: o token não foi enviado, é inválido ou venceu.")</td></tr>
                        <tr><td><code>403</code></td><td>Atleta sem cadastro ativo; responsável sem filho ativo (os tokens dele são apagados); token de um perfil na área do outro</td></tr>
                        <tr><td><code>404</code></td><td>Filho que não é do responsável, inativado ou inexistente; aviso que não é daquele atleta; evento ou registro que não existe; endereço que não existe na API ("Endereço não encontrado.")</td></tr>
                        <tr><td><code>405</code></td><td>Método não aceito na rota, como <code>PATCH</code> nos dados do filho ("Método não aceito nesta rota.")</td></tr>
                        <tr><td><code>422</code></td><td>Dados inválidos, com a mensagem de cada campo em português</td></tr>
                        <tr><td><code>429</code></td><td>Limite de requisições: aguarde os segundos do cabeçalho <code>Retry-After</code> ("Muitas tentativas. Aguarde um pouco e tente de novo.")</td></tr>
                        <tr><td><code>500</code></td><td>Erro interno, sem detalhes: tente de novo em instantes ("Erro interno. Tente de novo em instantes.")</td></tr>
                    </tbody>
                </table>
                HTML,
            'exemplos' => [
                ['titulo' => 'Sucesso', 'lang' => 'json', 'rotulo' => '200', 'codigo' => DocumentacaoApi::json(['success' => true, 'data' => new stdClass])],
                ['titulo' => 'Erro', 'lang' => 'json', 'rotulo' => '404', 'codigo' => DocumentacaoApi::json(['success' => false, 'message' => 'Atleta não encontrado.'])],
                ['titulo' => 'Dados inválidos', 'lang' => 'json', 'rotulo' => '422', 'codigo' => DocumentacaoApi::json(['success' => false, 'message' => 'O campo e-mail é obrigatório.', 'errors' => ['email' => ['O campo e-mail é obrigatório.']]])],
            ],
        ],
        [
            'guia' => true, 'id' => 'datas', 'titulo' => 'Datas e horários',
            'doc' => <<<'HTML'
                <p>Datas no formato ISO 8601, com o fuso de Brasília e sem milissegundos: <code>2026-10-12T18:00:00-03:00</code>. Quem converte a data (<code>new Date(...)</code>) acerta o instante; quem só recorta o texto lê a hora local.</p>
                <p>Data sem hora (dia do evento, data de nascimento) vem com a meia-noite: <code>2026-10-12T00:00:00-03:00</code>. Datas antigas, de quando havia horário de verão, podem vir com <code>-02:00</code>.</p>
                <p>Horários soltos vêm sem segundos, como texto: <code>"18:00"</code>. Sem horário definido: <code>null</code>.</p>
                HTML,
        ],
        [
            'guia' => true, 'id' => 'paginacao', 'titulo' => 'Paginação',
            'doc' => <<<'HTML'
                <p>As listas paginadas (agenda e avisos) recebem <code>?page=N</code> (padrão 1) e têm <strong>20 itens por página</strong>. Junto com a lista vem o bloco <code>paginacao</code>. Página além da última devolve a lista vazia, com a mesma <code>paginacao</code>.</p>
                HTML,
            'exemplos' => [
                ['titulo' => 'Bloco de paginação', 'lang' => 'json', 'rotulo' => 'JSON', 'codigo' => DocumentacaoApi::json(['paginacao' => ['pagina_atual' => 1, 'ultima_pagina' => 3, 'por_pagina' => 20, 'total' => 47]])],
            ],
        ],
        [
            'guia' => true, 'id' => 'imagens', 'titulo' => 'Imagens',
            'doc' => <<<'HTML'
                <p>Os campos de imagem trazem o nome do arquivo (<code>logo_time</code>, <code>logo_evento</code>, <code>banner_evento</code>, <code>foto_banner</code>, <code>foto_noticia</code>, <code>foto_atleta</code>) e, ao lado, o <strong>endereço completo</strong> para mostrar no app: <code>logo_url</code>, <code>banner_url</code> ou <code>foto_url</code>. Sem imagem: <code>null</code>.</p>
                HTML,
            'exemplos' => [
                ['titulo' => 'Nome e endereço da imagem', 'lang' => 'json', 'rotulo' => 'JSON', 'codigo' => DocumentacaoApi::json(['logo_time' => 'aacj.png', 'logo_url' => 'https://coderatech.tipi5.smpsistema.com.br/AACJ/futebol/images/team/aacj.png'])],
            ],
        ],
        [
            'guia' => true, 'id' => 'limites', 'titulo' => 'Limites de requisições',
            'doc' => <<<'HTML'
                <table class="table">
                    <thead><tr><th>Rota</th><th>Limite</th></tr></thead>
                    <tbody>
                        <tr><td>Todas</td><td>120 por minuto por conta logada (atleta ou responsável) e 60 por minuto por IP sem login</td></tr>
                        <tr><td><code>POST /auth/login</code></td><td>5 por minuto por IP e 5 por minuto por e-mail e perfil</td></tr>
                        <tr><td><code>POST /auth/esqueci-senha</code></td><td>5 por minuto por IP e 3 a cada 10 minutos por e-mail e perfil</td></tr>
                        <tr><td><code>POST /auth/validar-codigo</code>, <code>POST /auth/redefinir-senha</code></td><td>5 por minuto por IP em cada rota; o código também tem no máximo 5 erros, em qualquer IP</td></tr>
                        <tr><td><code>POST</code> e <code>DELETE /atleta/foto</code></td><td>Juntos, 5 por minuto e 20 por hora por atleta</td></tr>
                        <tr><td><code>POST /fale-conosco</code></td><td>3 por minuto por conta (perfil + ID), além do limite geral</td></tr>
                    </tbody>
                </table>
                <p>As respostas trazem os cabeçalhos <code>X-RateLimit-Limit</code> e <code>X-RateLimit-Remaining</code>. Acima do limite, a resposta é <code>429</code> com <code>Retry-After</code> em segundos, exposto por CORS (<code>Access-Control-Expose-Headers</code>) para leitura no app web. Guarde no app o que muda pouco (campeonatos, notícias, banners) em vez de buscar a cada tela.</p>
                HTML,
            'exemplos' => [
                ['titulo' => 'Acima do limite', 'lang' => 'json', 'rotulo' => '429', 'codigo' => DocumentacaoApi::json(['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'])],
            ],
        ],
    ],
];
