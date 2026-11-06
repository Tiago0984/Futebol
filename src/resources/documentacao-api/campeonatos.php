<?php

$x = require __DIR__ . '/_exemplos.php';

$camposDaLista = [
    ['categorias', 'As categorias do campeonato. Cada uma traz <code>resumo</code> (os campos abaixo, contando <strong>só os jogos daquela categoria</strong>; use este para mostrar a fase e os números de cada uma; sem jogo: <code>fase_atual</code> <code>null</code> e números zerados), <code>encerrada</code> (<code>true</code> com campeão, ou empatada em tudo no 1º lugar, com <code>campeao.situacao</code> = <code>sorteio</code>) e <code>campeao</code> (o mesmo objeto de <code>classificacao[].campeao</code>, ou <code>null</code> enquanto não terminou). O campo antigo <code>categoria</code> continua, para não quebrar o app; use <code>categorias</code>.'],
    ['resumo', 'O andamento geral para o card, <strong>somando todas as categorias</strong> (com Sub-13 e Sub-15 em fases diferentes, a fase é a do próximo jogo de qualquer uma). Só jogos publicados e ativos. <code>fase_atual</code>/<code>fase_atual_label</code>: a do próximo jogo sem placar; com todos jogados, a do último; sem jogos, <code>null</code>. <code>rodada_atual</code>/<code>total_rodadas</code>: só na fase de grupos com rodadas. <code>jogos_realizados</code>/<code>total_jogos</code>: jogos com placar sobre os <strong>já marcados</strong> ("14 de 20 jogos"); o mata-mata só existe depois de montado, então o total cresce. <code>jogos_aguardando_resultado</code>: jogos que já terminaram e estão sem placar; um jogo assim "segura" a <code>fase_atual</code>.'],
    ['encerrado', '<code>true</code> só quando <strong>todas</strong> as categorias estão encerradas. Não depende das datas: o campeonato pode passar da data de fim sem estar encerrado, e vice-versa.'],
    ['tipo_campeonato / tipo_label', 'O tipo (<code>LIGA</code>, <code>COPA</code>, <code>TORNEIO</code>, <code>MATA-MATA</code>, <code>PONTOS CORRIDOS</code>, <code>AMISTOSO</code>) e o rótulo para mostrar ("Pontos Corridos").'],
    ['local_evento, organizador_campeonato, logo_evento / logo_url, banner_evento / banner_url', 'Opcionais: podem vir <code>null</code>. <code>local_evento</code> é só o local principal; cada jogo tem o seu.'],
];

return [
    'titulo' => 'Campeonatos',
    'itens'  => [
        [
            'titulo' => 'Listar campeonatos', 'metodo' => 'GET', 'rota' => '/campeonatos', 'acesso' => 'publico',
            'doc' => '<p>Campeonatos ativos, do mais recente para o mais antigo. Um campeonato pode ter várias categorias (ex.: Sub-11, Sub-15 e Sub-17). Campeonato inativo não aparece.</p>',
            'campos' => $camposDaLista,
            'respostas' => [200 => ['success' => true, 'data' => [$x['campeonato']]]],
        ],
        [
            'titulo' => 'Detalhar campeonato', 'metodo' => 'GET', 'rota' => '/campeonatos/{id}', 'acesso' => 'publico',
            'doc' => '<p>Os mesmos dados da lista, mais <code>descricao_campeonato</code>, os <code>times</code>, os <code>jogos</code> visíveis (publicados; rascunhos e ocultos ficam fora) e a <code>classificacao</code>. Cada jogo é de uma categoria e de uma fase; a classificação é por categoria e grupo (chave) e conta só a fase de grupos.</p>',
            'parametros' => [['{id}', 'inteiro', 'caminho', true, 'O campeonato.']],
            'campos' => [
                ...$camposDaLista,
                ['da_aacj', 'Em todo time (<code>times[]</code>, <code>jogos[].time_casa</code>/<code>time_visitante</code>, linhas da <code>classificacao</code>, <code>campeao</code>, <code>vice</code> e <code>terceiro</code>): <code>true</code> quando é da AACJ. Use para destacar a escolinha.'],
                ['jogos[].id_categoria', 'A categoria do jogo (uma das <code>categorias</code>).'],
                ['jogos[].data_jogo, horario_jogo, local_jogo, status_jogo', 'Vêm do evento do jogo. Os jogos de um campeonato podem ser em locais diferentes.'],
                ['jogos[].fase_jogo / fase', 'A fase: <code>GRUPOS</code>, <code>REPESCAGEM</code>, <code>DEZESSEIS_AVOS</code>, <code>OITAVAS</code>, <code>QUARTAS</code>, <code>SEMIFINAL</code>, <code>TERCEIRO</code>, <code>FINAL</code>; <code>fase</code> é o rótulo ("Fase de grupos", "Disputa de 3º lugar").'],
                ['jogos[].rodada_jogo', 'A rodada na fase de grupos (<code>null</code> sem rodada e no mata-mata).'],
                ['jogos[].placar_time_casa_jogos / _visitante_', 'Placar do <strong>tempo normal</strong> (<code>null</code> = ainda não jogado).'],
                ['jogos[].prorrogacao_..., penaltis_...', 'Só no mata-mata: os gols da prorrogação (jogo único empatado) e a disputa de pênaltis; <code>null</code> quando não houve.'],
                ['jogos[].id_jogo_ida', 'No jogo de volta do mata-mata, o <code>id_jogo</code> da ida (na ida e no jogo único, <code>null</code>). Empate no agregado vai direto para os pênaltis, sem gol fora.'],
                ['jogos[].confronto', 'Mata-mata decidido: <code>id_time</code>, <code>como</code> (<code>tempo normal</code>, <code>prorrogação</code>, <code>agregado</code> ou <code>pênaltis</code>), <code>agregado</code> (só na volta, na ordem mandante × visitante deste jogo) e <code>texto</code>. <code>null</code> fora do mata-mata, na ida e enquanto não há vencedor.'],
                ['classificacao[]', 'Uma entrada por categoria: <code>id_categoria</code>, <code>categoria</code> (rótulo), <code>campeao</code>, <code>classificados</code> (quantos passam de cada grupo, ou <code>null</code>) e <code>grupos</code>.'],
                ['classificacao[].campeao', '<code>null</code> enquanto não há campeão. <code>situacao</code> (<code>campeao</code> ou <code>sorteio</code>), <code>modo</code> (<code>final</code>: o vencedor da final; <code>tabela</code>: pontos corridos, o 1º depois que a escolinha encerra a categoria, com todos os jogos publicados e com placar; com "classificam N por grupo", o campeão sai da final), <code>como</code> (texto), <code>campeao</code>, <code>vice</code> e <code>terceiro</code> (<code>{ id_time, nome, logo, logo_url, da_aacj }</code> ou <code>null</code>; vice e 3º só com final) e <code>empatados</code> (nomes, quando o 1º lugar está empatado em tudo).'],
                ['classificacao[].grupos[]', '<code>grupo</code> (nome; <code>null</code> na categoria sem grupos; "Sem grupo" para quem não está em nenhum), <code>classificados</code> e <code>tabela</code>, com <code>posicao</code>, <code>id_time</code>, <code>time</code>, <code>logo</code>, <code>logo_url</code>, <code>da_aacj</code>, <code>pontos</code>, <code>jogos</code>, <code>vitorias</code>, <code>empates</code>, <code>derrotas</code>, <code>gols_marcados</code>, <code>gols_sofridos</code>, <code>saldo</code>, <code>classificado</code> e <code>empate_sorteio</code> (empatado com outro em todos os critérios: se continuar assim no fim, a ordem sai por sorteio).'],
                ['Ordem da tabela', 'Como no Brasileirão: pontos (vitória 3, empate 1), vitórias, saldo, gols marcados e, com só dois times empatados nisso tudo, o confronto direto. Sem cartões. Empate em tudo: <code>empate_sorteio</code> e, enquanto isso, ordem alfabética.'],
            ],
            'respostas' => [
                200 => ['success' => true, 'data' => [
                    ...$x['campeonato'],
                    'descricao_campeonato' => 'Campeonato de base com as categorias Sub-13 e Sub-15.', 'status_campeonato' => 'ATIVO',
                    'times' => [[...$x['timeAacj'], 'tipo_time' => 'INTERNO']],
                    'jogos' => [[
                        'id_jogo' => 12, 'id_evento' => 80, 'id_campeonato' => 1, 'fase_jogo' => 'SEMIFINAL', 'rodada_jogo' => null, 'id_jogo_ida' => 11,
                        'id_time_casa' => 1, 'id_time_visitante' => 9, 'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1,
                        'prorrogacao_time_casa_jogos' => null, 'prorrogacao_time_visitante_jogos' => null, 'penaltis_time_casa_jogos' => 4, 'penaltis_time_visitante_jogos' => 3,
                        'data_jogo' => '2026-11-14T13:00:00-03:00', 'horario_jogo' => '13:00', 'local_jogo' => 'Arena Norte', 'status_jogo' => 'ATIVO',
                        'fase' => 'Semifinal', 'id_categoria' => 5,
                        'confronto' => ['id_time' => 1, 'como' => 'pênaltis', 'agregado' => [3, 3], 'texto' => 'AACJ Masculino passa nos pênaltis (4 × 3), depois do agregado 3 × 3'],
                        'time_casa' => [...$x['timeAacj'], 'tipo_time' => 'INTERNO'], 'time_visitante' => [...$x['timeRival'], 'tipo_time' => 'EXTERNO'],
                    ]],
                    'classificacao' => [[
                        'id_categoria' => 5, 'categoria' => 'Sub-13 Masculino', 'campeao' => null, 'classificados' => 2,
                        'grupos' => [[
                            'grupo' => 'Chave A', 'classificados' => 2,
                            'tabela' => [[
                                'posicao' => 1, 'id_time' => 1, 'time' => 'AACJ Masculino', 'logo' => 'aacj.png', 'logo_url' => "{$x['imagens']}/team/aacj.png", 'da_aacj' => true,
                                'pontos' => 6, 'jogos' => 2, 'vitorias' => 2, 'empates' => 0, 'derrotas' => 0, 'gols_marcados' => 5, 'gols_sofridos' => 1, 'saldo' => 4,
                                'classificado' => true, 'empate_sorteio' => false,
                            ]],
                        ]],
                    ]],
                ]],
                404 => ['success' => false, 'message' => 'Campeonato não encontrado.'],
            ],
        ],
    ],
];
