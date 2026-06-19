CREATE TABLE tbl_evento_calendario (
    id_evento_calendario             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo_evento_calendario         VARCHAR(255)                              NOT NULL,
    descricao_evento_calendario      TEXT                                      NULL,
    tipo_evento_calendario           ENUM('jogo', 'treino', 'campeonato')     NOT NULL,
    subtipo_evento_calendario        VARCHAR(60)                               NULL,
    data_evento_calendario           DATE                                      NOT NULL,
    horario_inicio_evento_calendario TIME                                      NOT NULL,
    horario_fim_evento_calendario    TIME                                      NULL,
    local_evento_calendario          VARCHAR(255)                              NOT NULL,
    destaque_evento_calendario       ENUM('SIM', 'NAO') NOT NULL DEFAULT 'NAO',
    status_evento_calendario         ENUM('ATIVO', 'INATIVO') NOT NULL DEFAULT 'ATIVO'
);

CREATE TABLE tbl_grade_treino (
    id_grade_treino              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dia_semana_grade_treino      ENUM('segunda_quarta', 'terca_quinta', 'sexta', 'sabado') NOT NULL,
    categoria_grade_treino       VARCHAR(60)                               NOT NULL,
    tipo_grade_treino            ENUM('treino', 'jogo', 'livre')           NOT NULL DEFAULT 'treino',
    horario_inicio_grade_treino  TIME                                      NULL,
    horario_fim_grade_treino     TIME                                      NULL,
    horario_obs_grade_treino     VARCHAR(60)                               NULL,
    local_grade_treino           VARCHAR(255)                              NOT NULL,
    ordem_grade_treino           INT NOT NULL DEFAULT 1,
    status_grade_treino          ENUM('ATIVO', 'INATIVO') NOT NULL DEFAULT 'ATIVO'
);

INSERT INTO tbl_evento_calendario
    (titulo_evento_calendario, descricao_evento_calendario, tipo_evento_calendario, subtipo_evento_calendario, data_evento_calendario, horario_inicio_evento_calendario, horario_fim_evento_calendario, local_evento_calendario, destaque_evento_calendario, status_evento_calendario)
VALUES
(
    'AACJ Futebol Sub-13 vs Esporte Clube Juventude',
    'Segunda rodada da Liga Premiere. Os atletas devem se apresentar com o uniforme principal (vermelho) com 45 minutos de antecedência. Presença confirmada pelo responsável até sexta-feira.',
    'jogo', 'Jogo Oficial',
    '2026-06-21', '09:00:00', NULL,
    'Campo Central — Mandante',
    'SIM', 'ATIVO'
),
(
    'Treino Integrado de Fundamentos e Posicionamento',
    'Treino especial focado em transição defensiva e finalizações para as categorias Sub-11 e Sub-15. Presença obrigatória para atletas inscritos na próxima rodada do campeonato.',
    'treino', 'Treino Técnico',
    '2026-06-25', '14:30:00', NULL,
    'Campo de Grama — AACJ',
    'NAO', 'ATIVO'
),
(
    'Abertura do Torneio de Inverno AACJ 2026',
    'Festival de integração entre todas as categorias da escolinha. Evento aberto para famílias e comunidade, com praça de alimentação e premiação ao final do dia. Não perca!',
    'campeonato', 'Campeonato',
    '2026-07-05', '08:00:00', '17:00:00',
    'Complexo Esportivo AACJ',
    'NAO', 'ATIVO'
),
(
    'Atlético da Vila vs AACJ Futebol Sub-17',
    'Clássico regional válido pelas quartas de final da Liga Premiere. O ônibus para transporte dos atletas sairá da sede da AACJ pontualmente às 09:15. Confirmação obrigatória até segunda-feira.',
    'jogo', 'Jogo Oficial',
    '2026-07-12', '11:00:00', NULL,
    'Estádio Municipal — Visitante',
    'NAO', 'ATIVO'
),
(
    'Avaliação Física Semestral — Todas as Categorias',
    'Avaliação semestral de desempenho físico conduzida pela clínica parceira Vida Ativa. Todos os atletas devem comparecer em jejum de 2 horas e com atestado médico atualizado.',
    'treino', 'Treino Físico',
    '2026-07-19', '08:00:00', '12:00:00',
    'Ginásio Coberto — AACJ',
    'NAO', 'ATIVO'
);

INSERT INTO tbl_grade_treino
    (dia_semana_grade_treino, categoria_grade_treino, tipo_grade_treino, horario_inicio_grade_treino, horario_fim_grade_treino, horario_obs_grade_treino, local_grade_treino, ordem_grade_treino, status_grade_treino)
VALUES
    ('segunda_quarta', 'Sub-9',        'treino', '08:00:00', '09:30:00', NULL,               'Campo AACJ',      1, 'ATIVO'),
    ('segunda_quarta', 'Sub-11',       'treino', '09:30:00', '11:00:00', NULL,               'Campo AACJ',      2, 'ATIVO'),
    ('terca_quinta',   'Sub-13',       'treino', '08:00:00', '09:30:00', NULL,               'Campo AACJ',      1, 'ATIVO'),
    ('terca_quinta',   'Sub-15',       'treino', '09:30:00', '11:00:00', NULL,               'Campo AACJ',      2, 'ATIVO'),
    ('sexta',          'Sub-17',       'treino', '07:30:00', '09:30:00', NULL,               'Campo AACJ',      1, 'ATIVO'),
    ('sexta',          'Integrado',    'treino', '10:00:00', '12:00:00', NULL,               'Campo AACJ',      2, 'ATIVO'),
    ('sabado',         'Jogos',        'jogo',   NULL,       NULL,       'Horário variável', 'Conforme agenda', 1, 'ATIVO'),
    ('sabado',         'Treino Livre', 'livre',  '09:00:00', '11:00:00', NULL,               'Campo AACJ',      2, 'ATIVO');


-- Atualiza definição dos ENUMs para maiúsculo
ALTER TABLE tbl_evento_calendario
    MODIFY tipo_evento_calendario ENUM('JOGO', 'TREINO', 'CAMPEONATO') NOT NULL;

ALTER TABLE tbl_grade_treino
    MODIFY tipo_grade_treino ENUM('TREINO', 'JOGO', 'LIVRE') NOT NULL DEFAULT 'TREINO'

ALTER TABLE tbl_galeria
ADD COLUMN categoria_galeria VARCHAR(60) NOT NULL DEFAULT 'GERAL' AFTER foto_galeria;

UPDATE tbl_galeria SET categoria_galeria = 'JOGOS' WHERE id_galeria IN (1, 2);

