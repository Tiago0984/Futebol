<?php

use App\Models\Atleta;

$x = require __DIR__ . '/_exemplos.php';

$codigos = implode(', ', array_map(fn ($codigo) => "<code>{$codigo}</code>", array_keys(Atleta::POSICOES)));

$campoDoAtleta = fn (string $nome, string $tipo, string $descricao) => [$nome, $tipo, 'corpo', false, $descricao];

return [
    'titulo' => 'Atleta',
    'itens'  => [
        [
            'titulo' => 'Dados do atleta', 'metodo' => 'GET', 'rota' => '/atleta', 'acesso' => 'atleta',
            'doc' => '<p>Os dados do atleta logado, com o endereço e a categoria atual. O atleta é da AACJ, na categoria dele; o time de cada jogo vem na agenda, em <code>time_do_atleta</code>.</p>',
            'campos' => [
                ['times', 'Sempre vazio (mantido para não quebrar o app).'],
                ['posicoes', "Até 3, a principal primeiro; <code>posicao</code> é o código fixo, <code>sigla</code> e <code>nome</code> são para mostrar. Códigos: {$codigos}."],
                ['posicao_atleta', 'O código da principal (<code>null</code> sem posição).'],
                ['responsaveis', 'Só <code>nome</code> e <code>grau_parentesco</code> (lista vazia quando não há). CPF e contatos do responsável não vêm.'],
                ['foto_url / foto_pendente', 'A foto atual e se há uma foto nova esperando a aprovação da secretaria.'],
            ],
            'respostas' => [200 => ['success' => true, 'data' => $x['atleta']]],
        ],
        [
            'titulo' => 'Atualizar atleta', 'metodo' => ['PUT', 'PATCH'], 'rota' => '/atleta', 'acesso' => 'atleta',
            'doc' => <<<'HTML'
                <p>Atualiza os dados que o atleta pode mudar; mande só o que mudou. Campos fora desta lista são ignorados: RG, matrícula, status e o <strong>ano</strong> de nascimento só a secretaria muda. A resposta traz o atleta no formato de <code>GET /atleta</code>.</p>
                <p><strong>Nome, CPF e data de nascimento</strong> ficam registrados para a secretaria: o painel mostra o antes e o depois.</p>
                HTML,
            'parametros' => [
                $campoDoAtleta('nome_atleta', 'texto', 'De 3 a 100 caracteres.'),
                $campoDoAtleta('cpf_atleta', 'texto', 'Com ou sem pontuação; volta como <code>000.000.000-00</code>. Os dígitos verificadores são conferidos e o CPF não pode ser de outro atleta.'),
                $campoDoAtleta('data_nasc_atleta', 'data', '<code>AAAA-MM-DD</code>, uma data que existe, anterior a hoje, de quem tem de 9 a 17 anos no ano. <strong>Só o dia e o mês</strong> podem mudar: o ano decide a categoria e a idade nos campeonatos.'),
                $campoDoAtleta('email_atleta', 'e-mail', 'Único entre os atletas.'),
                $campoDoAtleta('telefone_atleta', 'texto', 'Até 20 caracteres.'),
                $campoDoAtleta('escola_atleta', 'texto', 'Até 100 caracteres.'),
                $campoDoAtleta('serie_atleta', 'texto', 'Até 20 caracteres.'),
                $campoDoAtleta('sala_atleta', 'texto', 'Até 255 caracteres.'),
                $campoDoAtleta('periodo_escolar_atleta', 'texto', '<code>MANHA</code>, <code>TARDE</code>, <code>NOITE</code> ou <code>INTEGRAL</code>; quando vier, não pode ser vazio. O treino é no turno oposto ao da escola (integral treina à tarde).'),
                $campoDoAtleta('turno_treino_atleta', 'texto', 'Com <code>NOITE</code>, obrigatório: <code>MANHA</code>, <code>TARDE</code> ou <code>AMBOS</code>. Nos outros períodos, volta <code>null</code>.'),
                $campoDoAtleta('peso_atleta', 'número', 'De 0 a 999.'),
                $campoDoAtleta('altura_atleta', 'número', 'De 0 a 9,99.'),
                $campoDoAtleta('descricao_atleta', 'texto', 'Até 1000 caracteres.'),
            ],
            'corpo' => ['nome_atleta' => 'Joana Exemplo da Silva', 'cpf_atleta' => '19472778984', 'data_nasc_atleta' => '2013-05-20'],
            'respostas' => [
                200 => ['success' => true, 'message' => 'Dados atualizados com sucesso.', 'data' => array_replace($x['atleta'], ['nome_atleta' => 'Joana Exemplo da Silva', 'data_nasc_atleta' => '2013-05-20T00:00:00-03:00'])],
                422 => ['success' => false, 'message' => 'Para mudar o ano de nascimento, procure a secretaria da escolinha.', 'errors' => ['data_nasc_atleta' => ['Para mudar o ano de nascimento, procure a secretaria da escolinha.']]],
            ],
        ],
        [
            'id' => 'put-atleta-senha', 'titulo' => 'Alterar senha', 'metodo' => 'PUT', 'rota' => '/atleta/senha', 'acesso' => 'atleta',
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
            'titulo' => 'Enviar foto', 'metodo' => 'POST', 'rota' => '/atleta/foto', 'acesso' => 'atleta', 'arquivo' => 'foto',
            'doc' => <<<'HTML'
                <p><strong>O corpo é <code>multipart/form-data</code></strong>, não JSON, com o arquivo no campo <code>foto</code>. Não defina o <code>Content-Type</code> à mão: o <code>FormData</code> e o <code>curl -F</code> põem o <em>boundary</em> sozinhos.</p>
                <p>Aceita JPG, PNG e WebP (HEIC do iPhone só se o servidor conseguir abrir), até <strong>10 MB</strong>. A foto é recortada no quadrado do centro e reduzida a até 512 × 512, em JPG.</p>
                <p><strong>A foto nova espera a aprovação da secretaria</strong>, porque o atleta é menor e a foto aparece no site: até lá, <code>foto_url</code> continua com a atual e <code>foto_pendente</code> vem <code>true</code>. Mandar outra antes da aprovação troca a que esperava. Quando a secretaria aprova ou recusa, o atleta recebe um aviso. O responsável não troca a foto do filho.</p>
                HTML,
            'parametros' => [['foto', 'arquivo', 'corpo', true, 'A imagem, em <code>multipart/form-data</code>.']],
            'exemplos' => [
                ['cURL', 'bash', "curl -X POST \"{BASE}/atleta/foto\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Authorization: Bearer SEU_TOKEN\" \\\n  -F \"foto=@joana.jpg\""],
                ['React Native', 'js', <<<'JS'
                    const API = "{BASE}";
                    const token = await AsyncStorage.getItem("token");

                    const corpo = new FormData();
                    corpo.append("foto", { uri, name: "foto.jpg", type: "image/jpeg" });

                    const resposta = await fetch(`${API}/atleta/foto`, {
                      method: "POST",
                      headers: {
                        Accept: "application/json",
                        Authorization: `Bearer ${token}`,   // sem Content-Type
                      },
                      body: corpo,
                    });

                    const json = await resposta.json();
                    JS],
            ],
            'respostas' => [
                200 => ['success' => true, 'message' => 'Foto enviada. Ela aparece no app depois que a secretaria aprovar.', 'data' => array_replace($x['atleta'], ['foto_pendente' => true])],
                422 => ['success' => false, 'message' => 'A foto deve ser uma imagem JPG ou PNG.', 'errors' => ['foto' => ['A foto deve ser uma imagem JPG ou PNG.']]],
                429 => ['success' => false, 'message' => 'Muitas tentativas. Aguarde um pouco e tente de novo.'],
            ],
            'nota_das_respostas' => 'Acima de 10 MB: <code>422</code> com "A foto deve ter no máximo 10 MB.".',
        ],
        [
            'titulo' => 'Remover foto', 'metodo' => 'DELETE', 'rota' => '/atleta/foto', 'acesso' => 'atleta',
            'doc' => '<p>Tira a foto <strong>na hora</strong> (volta para a padrão: <code>foto_url</code> <code>null</code>, e o app mostra as iniciais) e descarta a foto que esperava aprovação.</p>',
            'respostas' => [200 => ['success' => true, 'message' => 'Foto removida.', 'data' => array_replace($x['atleta'], ['foto_atleta' => 'default-player.jpg', 'foto_url' => null, 'foto_pendente' => false])]],
        ],
        [
            'titulo' => 'Estatísticas', 'metodo' => 'GET', 'rota' => '/atleta/estatisticas', 'rota_do_filho' => '/estatisticas', 'acesso' => 'atleta', 'filho' => true,
            'doc' => <<<'HTML'
                <p>Os números do atleta desde que entrou na escolinha: no <code>total</code>, <code>por_time</code> (do time com mais partidas para o com menos) e na <code>categoria_atual</code>. Quem ainda não jogou: <code>0</code> no <code>total</code>, gols e assistências <code>null</code> e <code>por_time</code> vazio.</p>
                HTML,
            'campos' => [
                ['partidas', 'Jogos (campeonato ou amistoso) em que foi <strong>escalado num time</strong>, publicados, não cancelados e já realizados. Inscrito sem time não conta.'],
                ['convocacoes', 'Convocações para campeonatos (uma por campeonato e categoria).'],
                ['cartoes_amarelos, cartoes_vermelhos', 'No time em que estava escalado naquele jogo.'],
                ['gols, assistencias', 'Da <strong>súmula</strong> lançada no painel, só nos jogos que contam como partida e têm súmula. Gol contra não conta. <strong><code>null</code> quando nenhum jogo do recorte tem súmula</strong> (não registrado, diferente de <code>0</code>): mostre "—".'],
                ['por_time[].categoria', 'As categorias do cadastro do time, num texto (um time tem várias: "Sub-13 Masculino, Sub-15 Masculino").'],
                ['categoria_atual', 'Os mesmos números, só da categoria de hoje: contam os jogos <strong>daquela categoria</strong> e as convocações nela. Quem sobe para jogar acima tem essas partidas no <code>total</code>, não aqui. <code>nome_categoria</code> é o nome curto e <code>categoria</code> traz o sexo. Sem categoria: <code>null</code>.'],
            ],
            'respostas' => [200 => ['success' => true, 'data' => [
                'total' => ['partidas' => 12, 'convocacoes' => 6, 'cartoes_amarelos' => 1, 'cartoes_vermelhos' => 0, 'gols' => 4, 'assistencias' => 2],
                'por_time' => [[
                    'id_time' => 1, 'nome_time' => 'AACJ Masculino', 'logo_url' => "{$x['imagens']}/team/aacj.png", 'categoria' => 'Sub-13 Masculino, Sub-15 Masculino',
                    'partidas' => 12, 'convocacoes' => 6, 'cartoes_amarelos' => 1, 'cartoes_vermelhos' => 0, 'gols' => 4, 'assistencias' => 2,
                ]],
                'categoria_atual' => ['id_categoria' => 7, 'nome_categoria' => 'Sub-15', 'categoria' => 'Sub-15 Masculino', 'partidas' => 10, 'convocacoes' => 5, 'cartoes_amarelos' => 1, 'cartoes_vermelhos' => 0, 'gols' => 4, 'assistencias' => 2],
            ]]],
        ],
        [
            'titulo' => 'Campeonatos do atleta', 'metodo' => 'GET', 'rota' => '/campeonatos-do-atleta', 'acesso' => 'atleta', 'filho' => true,
            'doc' => <<<'HTML'
                <p>Os campeonatos de que o atleta participa (o carrossel da Home): só <strong>ativos</strong> e <strong>não terminados</strong> (data de fim hoje ou depois). Participa quem foi <strong>convocado</strong>, em qualquer categoria, inclusive acima da dele, ou cuja <strong>categoria atual</strong> está no campeonato com time da AACJ (o campeonato já aparece antes da convocação).</p>
                <p>Traz os campos de <a href="#get-campeonatos"><code>GET /campeonatos</code></a>, mais <code>descricao_campeonato</code> e os campos abaixo.</p>
                <p>Ordem: os <strong>em andamento</strong> primeiro (do que termina antes), depois os <strong>próximos</strong> pela data de início. Sem paginação. Nenhum campeonato: <code>data</code> vazio.</p>
                HTML,
            'campos' => [
                ['situacao', '<code>EM_ANDAMENTO</code> (já começou) ou <code>PROXIMO</code>.'],
                ['categorias', '<strong>Só as do atleta</strong>, no formato de <a href="#get-campeonatos"><code>GET /campeonatos</code></a>.'],
                ['convocada', '<code>true</code> se o atleta foi convocado; <code>false</code> quando aparece só pela categoria.'],
                ['convocacoes', 'Cada convocação: <code>{ id_categoria, rotulo, camisa }</code>, sem o time. A camisa vai de 1 a 99. Vazio quando <code>convocada</code> é <code>false</code>.'],
                ['resumo', 'O andamento, contando <strong>só os jogos das categorias do atleta</strong>.'],
                ['encerrado', '<code>true</code> só com <strong>todas</strong> as categorias do campeonato encerradas, inclusive as que não são do atleta.'],
            ],
            'respostas' => [200 => ['success' => true, 'data' => [[
                ...$x['campeonato'],
                'descricao_campeonato' => 'Campeonato de base com as categorias Sub-13 e Sub-15.', 'situacao' => 'EM_ANDAMENTO',
                'convocada' => true, 'convocacoes' => [['id_categoria' => 5, 'rotulo' => 'Sub-13 Masculino', 'camisa' => 8]],
            ]]]],
        ],
    ],
];
