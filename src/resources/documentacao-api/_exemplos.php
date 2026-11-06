<?php

// Trechos de JSON usados em mais de uma rota da documentação (dados fictícios)
$imagens = 'https://coderatech.tipi5.smpsistema.com.br/AACJ/futebol/images';

$timeAacj = ['id_time' => 1, 'nome_time' => 'AACJ Masculino', 'logo_time' => 'aacj.png', 'logo_url' => "{$imagens}/team/aacj.png", 'da_aacj' => true];
$timeRival = ['id_time' => 9, 'nome_time' => 'Rival FC', 'logo_time' => 'rival.png', 'logo_url' => "{$imagens}/team/rival.png", 'da_aacj' => false];

$resumo = [
    'fase_atual' => 'GRUPOS', 'fase_atual_label' => 'Fase de grupos', 'rodada_atual' => 3, 'total_rodadas' => 5,
    'jogos_realizados' => 4, 'total_jogos' => 10, 'jogos_aguardando_resultado' => 0,
];

$categoria = [
    'id_categoria' => 5, 'nome_categoria' => 'Sub-13', 'sexo_categoria' => 'M', 'rotulo' => 'Sub-13 Masculino',
    'resumo' => $resumo, 'encerrada' => false, 'campeao' => null,
];

$campeonato = [
    'id_campeonato' => 1, 'nome_campeonato' => 'Copa Regional de Base', 'organizador_campeonato' => 'Liga Regional',
    'tipo_campeonato' => 'COPA', 'tipo_label' => 'Copa',
    'logo_evento' => 'copa.png', 'logo_url' => "{$imagens}/campeonatos/copa.png", 'banner_evento' => null, 'banner_url' => null,
    'data_inicio_campeonato' => '2026-10-03T00:00:00-03:00', 'data_fim_campeonato' => '2026-11-28T00:00:00-03:00',
    'local_evento' => 'Arena Norte', 'id_categoria' => 5,
    'categoria' => ['id_categoria' => 5, 'nome_categoria' => 'Sub-13'],
    'categorias' => [$categoria], 'resumo' => $resumo, 'encerrado' => false,
];

$atletaBasico = [
    'id_atleta' => 7, 'nome_atleta' => 'Joana Exemplo', 'numero_matricula_atleta' => 'A100',
    'foto_atleta' => 'default-player.jpg', 'foto_url' => null, 'grau_parentesco_responsavel' => 'Mãe',
];

$atleta = [
    'id_atleta' => 7, 'id_endereco' => 12, 'nome_atleta' => 'Joana Exemplo', 'numero_atleta' => null, 'numero_matricula_atleta' => 'A100',
    'telefone_atleta' => null, 'data_nasc_atleta' => '2013-06-15T00:00:00-03:00', 'cpf_atleta' => '194.727.789-84', 'rg_atleta' => '30.100.200-0',
    'peso_atleta' => '45.00', 'altura_atleta' => '1.55', 'sexo_atleta' => 'F', 'escola_atleta' => 'E.E. Exemplo', 'serie_atleta' => '8º ano',
    'descricao_atleta' => '', 'foto_atleta' => 'joana.jpg', 'sala_atleta' => null, 'periodo_escolar_atleta' => 'MANHA', 'turno_treino_atleta' => null,
    'status_atleta' => 'ATIVO', 'email_atleta' => 'joana@exemplo.com', 'posicao_atleta' => 'VOLANTE',
    'foto_url' => "{$imagens}/our-teams/joana.jpg", 'foto_pendente' => false,
    'responsaveis' => [['nome' => 'Carla Exemplo', 'grau_parentesco' => 'Mãe']],
    'endereco' => [
        'id_endereco' => 12, 'rua_endereco' => 'Rua das Flores', 'numero_endereco' => '120', 'bairro_endereco' => 'Centro',
        'complemento_endereco' => null, 'cep_endereco' => '13560-000', 'cidade_endereco' => 'São Carlos', 'estado_endereco' => 'SP',
    ],
    'categorias' => [
        [
            'id_categoria' => 6, 'nome_categoria' => 'Sub-13',
            'pivot' => [
                'id_atleta' => 7, 'id_categoria' => 6, 'data_inicio_categoria_atleta' => '2026-02-01T09:30:00-03:00', 'data_fim_categoria_atleta' => null,
                'data_atualizacao_categoria_atleta' => '2026-02-01T09:30:00-03:00', 'status_categoria_atleta' => 'ATIVO', 'observacao_categoria_atleta' => null,
            ],
        ],
        [
            'id_categoria' => 4, 'nome_categoria' => 'Sub-11',
            'pivot' => [
                'id_atleta' => 7, 'id_categoria' => 4, 'data_inicio_categoria_atleta' => '2024-02-05T10:00:00-03:00', 'data_fim_categoria_atleta' => '2026-02-01T09:30:00-03:00',
                'data_atualizacao_categoria_atleta' => '2026-02-01T09:30:00-03:00', 'status_categoria_atleta' => 'ENCERRADO', 'observacao_categoria_atleta' => null,
            ],
        ],
    ],
    'posicoes' => [
        ['ordem_atleta_posicao' => 1, 'posicao' => 'VOLANTE', 'sigla' => 'VOL', 'nome' => 'Volante'],
        ['ordem_atleta_posicao' => 2, 'posicao' => 'MEIA_CENTRAL', 'sigla' => 'MC', 'nome' => 'Meia-central'],
    ],
    'times' => [],
];

$local = [
    'nome' => 'Campo da AACJ', 'endereco' => 'Rua das Palmeiras, S/N – Centro, São Carlos/SP, 13560-000',
    'mapa_url' => 'https://maps.app.goo.gl/AbCdEf123',
];

$treino = [
    'id_evento' => 44, 'titulo' => 'Treino da tarde', 'tipo' => 'TREINO', 'tipo_label' => 'TREINO', 'subtipo' => null, 'descricao' => null,
    'categoria' => null, 'data' => '2026-10-13T00:00:00-03:00', 'horario_inicio' => '14:00', 'horario_fim' => '17:00',
    'inicio' => '2026-10-13T14:00:00-03:00', 'fim' => '2026-10-13T17:00:00-03:00', 'local' => 'Campo da AACJ', 'local_detalhe' => $local,
    'responsavel' => 'Carlos Mendes', 'responsavel_id' => 4, 'situacao' => 'CONFIRMADO', 'concluido' => false, 'jogo' => null,
];

$blocoDoJogo = [
    'id_jogo' => 13, 'amistoso' => false, 'campeonato' => ['id_campeonato' => 1, 'nome_campeonato' => 'Copa Regional de Base'],
    'time_casa' => $timeRival, 'time_visitante' => $timeAacj, 'placar' => ['casa' => 1, 'visitante' => 1], 'time_do_atleta' => $timeAacj,
    'horario_chegada' => '09:00', 'chegada' => '2026-10-17T09:00:00-03:00',
];

$jogo = array_replace($treino, [
    'id_evento' => 81, 'titulo' => 'Rival FC x AACJ Masculino', 'tipo' => 'JOGO', 'tipo_label' => 'JOGO', 'categoria' => 'Sub-13 Masculino',
    'data' => '2026-10-17T00:00:00-03:00', 'horario_inicio' => '10:00', 'horario_fim' => null, 'inicio' => '2026-10-17T10:00:00-03:00', 'fim' => null,
    'local' => 'Campo do Rival', 'local_detalhe' => null, 'responsavel' => null, 'responsavel_id' => null, 'jogo' => $blocoDoJogo,
]);

$aviso = [
    'id_notificacao' => 310, 'tipo' => 'ALTERACAO', 'tipo_label' => 'Alteração', 'titulo' => 'Atividade alterada',
    'mensagem' => 'Treino da tarde · ter, 13/10 · 14:00 às 17:00 · Campo B. Mudanças: local Campo A → Campo B.',
    'id_evento' => 44, 'data' => '2026-10-10T09:15:00-03:00', 'lida' => false, 'data_leitura' => null,
];

$paginacao = ['pagina_atual' => 1, 'ultima_pagina' => 3, 'por_pagina' => 20, 'total' => 47];

$totais = ['JOGO' => 3, 'TREINO' => 12, 'CAMPEONATO' => 0, 'EVENTO' => 0, 'REUNIAO' => 1, 'CONFRATERNIZACAO' => 0, 'AVALIACAO' => 0];

return compact(
    'imagens', 'timeAacj', 'timeRival', 'resumo', 'categoria', 'campeonato', 'atletaBasico', 'atleta', 'local',
    'treino', 'blocoDoJogo', 'jogo', 'aviso', 'paginacao', 'totais',
);
