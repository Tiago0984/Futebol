<?php

use App\Http\Controllers\Api\V1\AppDoAtletaController;

$x = require __DIR__ . '/_exemplos.php';

$maximo = AppDoAtletaController::PERIODO_MAX_DIAS;

$tipos = ['tipo', 'texto', 'query', false, 'Só esses tipos, separados por vírgula (<code>JOGO,CAMPEONATO</code>), sem diferenciar maiúsculas. Tipo desconhecido: <code>422</code>.'];

$camposDoEvento = [
    ['tipo / tipo_label', '<code>JOGO</code>, <code>TREINO</code>, <code>CAMPEONATO</code>, <code>EVENTO</code>, <code>REUNIAO</code>, <code>CONFRATERNIZACAO</code>, <code>AVALIACAO</code>; o rótulo vem com acento (<code>REUNIÃO</code>).'],
    ['categoria', '"Sub-13 Masculino", ou <code>null</code> em evento sem categoria (treino integrado, exame individual).'],
    ['data', 'Dia do evento, com fuso (meia-noite).'],
    ['horario_inicio / horario_fim', '<code>"HH:MM"</code> ou <code>null</code> ("A definir").'],
    ['inicio / fim', 'Data e hora completas com fuso; <code>null</code> quando o horário não está definido.'],
    ['local', 'O nome do local, para mostrar (ou <code>null</code>, sem local).'],
    ['local_detalhe', 'Quando o local é <strong>cadastrado</strong>: <code>nome</code>, <code>endereco</code> (uma linha pronta, sem o nome; para o Google Agenda, use <code>nome + ", " + endereco</code>) e <code>mapa_url</code> (sempre <code>https</code> do Google Maps: o link cadastrado ou a busca pelo nome e endereço). <code>null</code> sem local, ou com local escrito à mão, de antes do cadastro: mostre só o <code>local</code>, sem o "Abrir mapa".'],
    ['responsavel', 'Quem conduz a atividade, ou <code>null</code>. Mostre como "Responsável: Carlos Mendes". Em eventos antigos pode vir um texto escrito à mão ("Prof. Carlos"), sem perfil.'],
    ['responsavel_id', 'Id do profissional da comissão técnica, para abrir o <a href="#get-comissao-tecnica-id">perfil</a>; <code>null</code> no texto antigo ou sem responsável: esconda o botão. Profissional desativado continua no evento, mas o perfil responde <code>404</code>.'],
    ['situacao', '<code>CONFIRMADO</code> ou <code>CANCELADO</code>. Mudanças de data, horário ou local chegam pelos <a href="#avisos-get">avisos</a>; a agenda já mostra os dados novos.'],
    ['concluido', '<code>true</code> quando o evento já terminou: dia anterior a hoje, ou hoje com o horário de fim já passado (sem fim, vale o início; sem nenhum dos dois, só no dia seguinte).'],
    ['jogo', '<code>null</code> fora de jogo. No jogo: <code>amistoso</code>; <code>campeonato</code> (<code>null</code> no amistoso); <code>time_casa</code> e <code>time_visitante</code>; <code>placar</code> <code>{ "casa", "visitante" }</code> ou <code>null</code>; <code>time_do_atleta</code> (ou <code>null</code>, ainda sem escalação); <code>horario_chegada</code> e <code>chegada</code>, 1 hora antes do início, ou <code>null</code> sem horário. Mostre como "Chegar às 09:00".'],
];

return [
    'titulo' => 'Agenda',
    'itens'  => [
        [
            'titulo' => 'Agenda', 'metodo' => 'GET', 'rota' => '/agenda', 'acesso' => 'atleta', 'filho' => true,
            'doc' => <<<'HTML'
                <p>Só os eventos em que o atleta está <strong>inscrito</strong>: treinos, jogos, avaliações, reuniões. Eventos ocultos pela secretaria e jogos em rascunho não aparecem.</p>
                <ul>
                    <li><code>proximos</code>: o que ainda não terminou (inclusive o de hoje em andamento e os cancelados), do mais perto ao mais longe, <strong>paginado</strong>. No mesmo dia, evento sem horário vem por último.</li>
                    <li><code>passados</code>: os <strong>3 últimos concluídos e não cancelados</strong>, do mais recente ao mais antigo; iguais em todas as páginas.</li>
                    <li><code>totais</code>: quantos <code>proximos</code> existem de cada tipo, somando <strong>todas as páginas</strong> e <strong>sem considerar o <code>?tipo</code></strong>, para os contadores dos outros filtros continuarem certos. O total da lista filtrada está em <code>paginacao.total</code>.</li>
                </ul>
                HTML,
            'parametros' => [['page', 'inteiro', 'query', false, 'A página (padrão 1), 20 eventos por página.'], $tipos],
            'query' => ['page' => 1],
            'campos' => $camposDoEvento,
            'respostas' => [
                200 => ['success' => true, 'data' => ['proximos' => [$x['treino']], 'paginacao' => $x['paginacao'], 'passados' => [$x['jogo']], 'totais' => $x['totais']]],
                422 => ['success' => false, 'message' => 'Tipo desconhecido: FESTA. Use JOGO, TREINO, CAMPEONATO, EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO.', 'errors' => ['tipo' => ['Tipo desconhecido: FESTA. Use JOGO, TREINO, CAMPEONATO, EVENTO, REUNIAO, CONFRATERNIZACAO, AVALIACAO.']]],
            ],
        ],
        [
            'id' => 'get-agenda-periodo', 'titulo' => 'Agenda de um período', 'metodo' => 'GET', 'rota' => '/agenda', 'acesso' => 'atleta', 'filho' => true,
            'doc' => <<<HTML
                <p>Para a semana ou o mês: com <code>de</code> e <code>ate</code>, a resposta muda. <code>eventos</code> traz <strong>todos</strong> os eventos do período, concluídos ou não, confirmados ou cancelados, do mais antigo ao mais novo (no mesmo dia, sem horário por último), <strong>sem paginação</strong> (<code>page</code> é ignorado). Valem as mesmas regras de inscrição, oculto e rascunho.</p>
                <p>Cada evento vem no formato da <a href="#get-agenda">agenda</a>; use <code>concluido</code> para separar o que já passou. <code>totais</code> conta os eventos do período por tipo, sem o filtro de tipo.</p>
                HTML,
            'parametros' => [
                ['de', 'data', 'query', true, "Primeiro dia, <code>AAAA-MM-DD</code> (dia de Brasília, inclusive). Período de no máximo <strong>{$maximo} dias</strong>."],
                ['ate', 'data', 'query', true, 'Último dia, <code>AAAA-MM-DD</code>, inclusive.'],
                $tipos,
            ],
            'query' => ['de' => '2026-11-01', 'ate' => '2026-11-30'],
            'respostas' => [
                200 => ['success' => true, 'data' => ['periodo' => ['de' => '2026-11-01', 'ate' => '2026-11-30'], 'eventos' => [$x['treino']], 'totais' => $x['totais']]],
                422 => ['success' => false, 'message' => "O período pode ter no máximo {$maximo} dias.", 'errors' => ['ate' => ["O período pode ter no máximo {$maximo} dias."]]],
            ],
            'nota_das_respostas' => 'Outros erros de <code>422</code>: "Informe a data "até" no formato AAAA-MM-DD (ex.: 2026-11-01)." e "A data "até" não pode ser antes da data "de".".',
        ],
        [
            'titulo' => 'Detalhar evento', 'metodo' => 'GET', 'rota' => '/agenda/{idEvento}', 'acesso' => 'atleta', 'filho' => true,
            'doc' => <<<'HTML'
                <p>O "Ver detalhes" da agenda: o evento com <strong>os mesmos campos da lista</strong> (<code>id_evento</code> é o mesmo da agenda e dos avisos) e, no jogo, os campos abaixo. Não traz os colegas inscritos nem a escalação do time (dados de outros atletas).</p>
                <p>Evento em que o atleta não está inscrito, oculto, jogo em rascunho ou inexistente: <code>404</code>.</p>
                HTML,
            'parametros' => [['{idEvento}', 'inteiro', 'caminho', true, 'O <code>id_evento</code> da agenda ou do aviso.']],
            'campos' => [
                ['jogo.adversario', 'O outro time, pelo time em que o atleta está escalado; sem escalação, o adversário do time da AACJ; <code>null</code> quando os dois times são da AACJ e ele ainda não foi escalado.'],
                ['jogo.fase_jogo / fase / fase_completa', 'A fase, o rótulo e o texto completo ("Oitavas de final · volta"). <code>fase_jogo</code> e <code>fase</code> são <code>null</code> no amistoso.'],
                ['jogo.rodada', 'Só na fase de grupos.'],
                ['jogo.perna', '<code>IDA</code>, <code>VOLTA</code> ou <code>null</code> no jogo único.'],
                ['jogo.prorrogacao, jogo.penaltis', '<code>{ "casa", "visitante" }</code> ou <code>null</code>.'],
                ['jogo.confronto', 'Quem passa no mata-mata, o mesmo bloco da <a href="#get-campeonatos-id">API de campeonatos</a>; <code>null</code> fora do mata-mata, na ida ou sem resultado.'],
                ['jogo.participacao', '"Sua participação", <strong>sempre presente</strong> no jogo, só com os dados do próprio atleta. <code>convocado</code> (<code>null</code> no amistoso, que não tem convocação) e <code>camisa</code> (a da convocação, ou <code>null</code>). <code>escalado</code> e <code>time</code> (o time em que ele joga, ou <code>null</code>). <code>titular</code> (<code>TITULAR</code>, <code>RESERVA</code> ou <code>null</code>, escalação ainda não salva) com <code>titular_label</code>. <code>posicao</code> (<code>{ "codigo", "sigla", "nome" }</code>): a vaga dele no campo, só para o titular; o reserva vem com <code>null</code>. Mudar a escalação <strong>não gera aviso</strong>: vale o que estiver salvo quando o app consultar.'],
            ],
            'respostas' => [
                200 => ['success' => true, 'data' => array_replace($x['jogo'], ['jogo' => [
                    ...$x['blocoDoJogo'], 'penaltis' => ['casa' => 3, 'visitante' => 4], 'adversario' => $x['timeRival'],
                    'fase_jogo' => 'SEMIFINAL', 'fase' => 'Semifinal', 'fase_completa' => 'Semifinal · volta', 'rodada' => null, 'perna' => 'VOLTA', 'prorrogacao' => null,
                    'confronto' => ['id_time' => 1, 'como' => 'pênaltis', 'agregado' => [3, 3], 'texto' => 'AACJ Masculino passa nos pênaltis (4 × 3), depois do agregado 3 × 3'],
                    'participacao' => [
                        'convocado' => true, 'camisa' => 10, 'escalado' => true, 'time' => $x['timeAacj'], 'titular' => 'TITULAR', 'titular_label' => 'Titular',
                        'posicao' => ['codigo' => 'VOLANTE', 'sigla' => 'VOL', 'nome' => 'Volante'],
                    ],
                ]])],
                404 => ['success' => false, 'message' => 'Evento não encontrado.'],
            ],
        ],
        [
            'titulo' => 'Perfil da comissão técnica', 'metodo' => 'GET', 'rota' => '/comissao-tecnica/{id}', 'acesso' => 'atleta', 'filho' => true,
            'doc' => '<p>O perfil de quem conduz a atividade. Não há lista: o app abre o perfil pelo <code>responsavel_id</code> do evento da agenda. Profissional desativado ou inexistente: <code>404</code>.</p>',
            'parametros' => [['{id}', 'inteiro', 'caminho', true, 'O <code>responsavel_id</code> do evento.']],
            'campos' => [
                ['foto_url', 'Só quando o profissional autorizou mostrar a foto no app; senão <code>null</code> (mostre as iniciais).'],
                ['contato', 'Só o institucional (da escolinha): <code>telefone</code> (só os dígitos, DDD + número, 10 ou 11: formate como "(11) 4000-0000") e <code>email</code>, cada um podendo vir <code>null</code>. O bloco é <code>null</code> sem nenhum dos dois: esconda o "Entrar em contato".'],
                ['tempo_na_aacj', 'Contado da <code>data_inicio</code> até hoje; <code>null</code> sem a data, ou com ela no futuro.'],
                ['licenca, especialidade', 'Podem vir <code>null</code>.'],
            ],
            'respostas' => [
                200 => ['success' => true, 'data' => [
                    'id_comissao_tecnica' => 4, 'nome' => 'Carlos Mendes', 'cargo' => 'TREINADOR_TECNICO', 'cargo_label' => 'Treinador técnico',
                    'foto_url' => "{$x['imagens']}/comissao/a1b2c3.png",
                    'categoria_principal' => ['id_categoria' => 5, 'nome_categoria' => 'Sub-13', 'categoria' => 'Sub-13 Masculino'],
                    'categorias_atendidas' => [
                        ['id_categoria' => 5, 'nome_categoria' => 'Sub-13', 'categoria' => 'Sub-13 Masculino'],
                        ['id_categoria' => 7, 'nome_categoria' => 'Sub-15', 'categoria' => 'Sub-15 Masculino'],
                    ],
                    'data_inicio' => '2024-03-01', 'tempo_na_aacj' => ['anos' => 2, 'meses' => 7, 'texto' => '2 anos e 7 meses'],
                    'licenca' => 'Licença C CBF', 'especialidade' => 'Categorias de base', 'contato' => ['telefone' => '1133334444', 'email' => 'carlos@aacj.com.br'],
                ]],
                404 => ['success' => false, 'message' => 'Profissional não encontrado.'],
            ],
        ],
    ],
];
