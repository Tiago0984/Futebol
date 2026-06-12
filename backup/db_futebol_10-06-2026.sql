-- Insere o 3º time interno para completar os 4 times do campeonato
INSERT INTO `tbl_time` (`id_categoria`, `logo_time`, `nome_time`, `tipo_time`)
VALUES (1, 'time-preto.jpg', 'Time Preto', 'INTERNO');

-- Atualiza o nome da coluna `numero_atleta` para `numero_matricula_atleta` na tabela `tbl_atletas`
ALTER TABLE `db_futebol`.`tbl_atletas` 
CHANGE COLUMN `numero_atleta` `numero_matricula_atleta` VARCHAR(20) CHARACTER SET 'utf8mb4' NOT NULL ;


-- Atualiza a coluna `foto_atleta` para ter um valor padrão de 'default-player.jpg' caso nenhuma foto seja fornecida
ALTER TABLE `tbl_atletas` 
MODIFY COLUMN `foto_atleta` varchar(255) NOT NULL DEFAULT 'default-player.jpg';


-- ==========================================================================
-- 🟦 1. POPULANDO O TIME AZUL (id_time = 1)
-- ==========================================================================

-- --- Atleta 1: Goleiro Titular (Time Azul) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Alisson Becker Cópia', '20260001', '2008-05-14', '111.222.333-44', '112223334', 
    82.00, 1.91, 'M', 'Escola Base Azul', '1º Ano', 'Goleiro seguro e com excelente envergadura nas saídas de gol.', 
    'alisson_azul.jpg', 101, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (1, @atleta_id, 1, 'Goleiro', 15, 15, 0, 58);

-- --- Atleta 2: Defesa Titular (Time Azul) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Thiago Silva Jr', '20260002', '2008-09-20', '222.333.444-55', '223334445', 
    76.50, 1.83, 'M', 'Escola Base Azul', '2º Ano', 'Zagueiro clássico com ótimo tempo de bola e desarmes limpos.', 
    'thiago_azul.jpg', 201, 'TARDE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (1, @atleta_id, 3, 'CB', 14, 15, 1, 0);

-- --- Atleta 3: Meio-Campo Titular (Time Azul) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Lucas Paquetá Base', '20260003', '2009-02-10', '333.444.555-66', '334445556', 
    68.00, 1.77, 'M', 'Escola Base Azul', '9º Ano', 'Meio-campista muito criativo, ótimo passe em profundidade.', 
    'lucas_azul.jpg', 901, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (1, @atleta_id, 10, 'AM', 15, 15, 6, 0);

-- --- Atleta 4: Atacante Titular (Time Azul) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Neymar Silva', '20260004', '2008-02-05', '444.555.666-77', '445556667', 
    64.00, 1.74, 'M', 'Escola Base Azul', '2º Ano', 'Artilheiro do time, extrema velocidade pelos lados e drible agudo.', 
    'neymar_azul.jpg', 202, 'NOITE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (1, @atleta_id, 11, 'AT', 13, 15, 14, 0);

-- --- Atleta 5: Reserva de Linha (Time Azul) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Bruno Reserva Azul', '20260005', '2009-06-11', '555.666.777-88', '556667778', 
    70.00, 1.79, 'M', 'Escola Base Azul', '9º Ano', 'Reserva polivalente, entra bem tanto na zaga quanto no meio-campo.', 
    'bruno_azul.jpg', 902, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (1, @atleta_id, 14, 'CB', 6, 12, 0, 0);


-- ==========================================================================
-- 🟩 2. POPULANDO O TIME VERDE (id_time = 2)
-- ==========================================================================

-- --- Atleta 6: Goleiro Titular (Time Verde) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Ederson Mota', '20260006', '2008-08-22', '666.777.888-99', '667778889', 
    80.00, 1.87, 'M', 'Colégio Central Verde', '2º Ano', 'Excelente jogo com os pés e reposição rápida de bola.', 
    'ederson_verde.jpg', 203, 'TARDE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (2, @atleta_id, 12, 'Goleiro', 14, 14, 0, 42);

-- --- Atleta 7: Defesa Titular (Time Verde) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Marquinhos Correia', '20260007', '2008-11-30', '777.888.999-00', '778889990', 
    74.00, 1.82, 'M', 'Colégio Central Verde', '1º Ano', 'Zagueiro rápido na cobertura e muito forte no combate aéreo.', 
    'marquinhos_verde.jpg', 102, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (2, @atleta_id, 4, 'CB', 14, 14, 2, 0);

-- --- Atleta 8: Meio-Campo Titular (Time Verde) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Casemiro Andrade', '20260008', '2008-03-12', '888.999.000-11', '889990001', 
    79.00, 1.84, 'M', 'Colégio Central Verde', '2º Ano', 'Primeiro volante clássico de forte marcação e proteção da zaga.', 
    'casemiro_verde.jpg', 204, 'TARDE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (2, @atleta_id, 5, 'VO', 13, 14, 3, 0);

-- --- Atleta 9: Atacante Titular (Time Verde) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Rodrygo Goes Clone', '20260009', '2009-01-25', '999.000.111-22', '990001112', 
    65.00, 1.73, 'M', 'Colégio Central Verde', '9º Ano', 'Centroavante de movimentação intensa, ótimo finalizador.', 
    'rodrygo_verde.jpg', 903, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (2, @atleta_id, 9, 'CF', 14, 14, 10, 0);

-- --- Atleta 10: Reserva Atacante (Time Verde) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Gabriel Barbosa Reserva', '20260010', '2008-07-19', '000.111.222-33', '001112223', 
    72.00, 1.76, 'M', 'Colégio Central Verde', '2º Ano', 'Atacante oportunista de área com bom poder de finalização.', 
    'gabigol_verde.jpg', 205, 'NOITE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (2, @atleta_id, 19, 'CF', 8, 14, 4, 0);


-- ==========================================================================
-- ⬛ 3. POPULANDO O TIME PRETO (id_time = 4)
-- ==========================================================================

-- --- Atleta 11: Goleiro Titular (Time Preto) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Weverton Rocha', '20260011', '2008-04-03', '121.232.343-45', '121232343', 
    84.00, 1.89, 'M', 'Instituto Técnico Preto', '2º Ano', 'Goleiro de ótimos reflexos e muito seguro em bolas paradas.', 
    'weverton_preto.jpg', 206, 'NOITE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (4, @atleta_id, 22, 'Goleiro', 12, 12, 0, 39);

-- --- Atleta 12: Defesa Titular (Time Preto) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Éder Militão Jr', '20260012', '2008-01-18', '232.343.454-56', '232343454', 
    78.00, 1.86, 'M', 'Instituto Técnico Preto', '2º Ano', 'Zagueiro de muita força física e excelente impulsão aérea.', 
    'militao_preto.jpg', 207, 'TARDE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (4, @atleta_id, 13, 'CB', 11, 12, 1, 0);

-- --- Atleta 13: Meio-Campista Titular (Time Preto) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Gerson Coringa', '20260013', '2008-10-05', '343.454.565-67', '343454565', 
    75.00, 1.80, 'M', 'Instituto Técnico Preto', '1º Ano', 'Meio-campo robusto que dita o ritmo e protege a posse de bola.', 
    'gerson_preto.jpg', 103, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (4, @atleta_id, 8, 'ME', 12, 12, 4, 0);

-- --- Atleta 14: Atacante Titular (Time Preto) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Vini Jr Estilo', '20260014', '2009-07-12', '454.565.676-78', '454565676', 
    66.00, 1.76, 'M', 'Instituto Técnico Preto', '9º Ano', 'Ponta incisivo, veloz no mano a mano que quebra as linhas de defesa.', 
    'vini_preto.jpg', 904, 'MANHÃ', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (4, @atleta_id, 7, 'AT', 12, 12, 11, 0);

-- --- Atleta 15: Reserva Meio-Campo (Time Preto) ---
INSERT INTO `tbl_atleta` (
    `id_endereco`, `nome_atleta`, `numero_matricula_atleta`, `data_nasc_atleta`, `cpf_atleta`, `rg_atleta`, 
    `peso_atleta`, `altura_atleta`, `sexo_atleta`, `escola_atleta`, `serie_atleta`, `descricao_atleta`, 
    `foto_atleta`, `sala_atleta`, `periodo_escolar_atleta`, `status_atleta`
) VALUES (
    1, 'Everton Ribeiro Sub', '20260015', '2008-03-29', '565.676.787-89', '565676787', 
    63.00, 1.71, 'M', 'Instituto Técnico Preto', '2º Ano', 'Meia armador muito técnico, cadencia o jogo com maestria na segunda etapa.', 
    'everton_preto.jpg', 208, 'TARDE', 'ATIVO'
);
SET @atleta_id = LAST_INSERT_ID();
INSERT INTO `tbl_atleta_time` (`id_time`, `id_atleta`, `camisa_atleta_time`, `posicao_atleta_time`, `jogos_atleta_time`, `convocacao_atleta_time`, `gols_atleta_time`, `defesas_atleta_time`)
VALUES (4, @atleta_id, 17, 'AM', 5, 10, 1, 0);

ALTER TABLE tbl_atleta_time 
ADD COLUMN status_atleta_time VARCHAR(20) NOT NULL DEFAULT 'TITULAR';

