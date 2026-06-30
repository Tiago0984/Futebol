-- Adiciona colunas
ALTER TABLE tbl_time      ADD COLUMN status_time       VARCHAR(10) NOT NULL DEFAULT 'ATIVO';
ALTER TABLE tbl_campeonato ADD COLUMN status_campeonato VARCHAR(10) NOT NULL DEFAULT 'ATIVO';
ALTER TABLE tbl_categoria  ADD COLUMN status_categoria  VARCHAR(10) NOT NULL DEFAULT 'ATIVO';
ALTER TABLE tbl_jogos      ADD COLUMN status_jogo       VARCHAR(10) NOT NULL DEFAULT 'ATIVO';

-- Desabilita o safe mode só para esta sessão
SET SQL_SAFE_UPDATES = 0;

-- Atualiza existentes
UPDATE tbl_time       SET status_time       = 'ATIVO';
UPDATE tbl_campeonato SET status_campeonato = 'ATIVO';
UPDATE tbl_categoria  SET status_categoria  = 'ATIVO';
UPDATE tbl_jogos      SET status_jogo       = 'ATIVO';

-- Padroniza maiúsculo nas tabelas que já tinham status
UPDATE tbl_evento_calendario SET status_evento_calendario = UPPER(status_evento_calendario);
UPDATE tbl_grade_treino      SET status_grade_treino      = UPPER(status_grade_treino);

-- Reabilita o safe mode
SET SQL_SAFE_UPDATES = 1;

UPDATE `db_futebol`.`tbl_time` SET `logo_time` = 'time-azul.png' WHERE (`id_time` = '1');
UPDATE `db_futebol`.`tbl_time` SET `logo_time` = 'time-verde.png' WHERE (`id_time` = '2');
UPDATE `db_futebol`.`tbl_time` SET `logo_time` = 'time-visitante.png' WHERE (`id_time` = '3');

-- Corrige o status dos atletas nos times (ATIVO → TITULAR)
SET SQL_SAFE_UPDATES = 0;

UPDATE tbl_atleta_time
SET status_atleta_time = 'TITULAR'
WHERE status_atleta_time = 'ATIVO';



-- Normaliza posicao_atleta_time para maiúsculo padronizado
UPDATE tbl_atleta_time SET posicao_atleta_time = 'ATACANTE' WHERE UPPER(posicao_atleta_time) IN ('ATAQUE','ATACANTE','AT','FW','CF');
UPDATE tbl_atleta_time SET posicao_atleta_time = 'MEIA'     WHERE UPPER(posicao_atleta_time) IN ('MEIO','MEIA','AM','MC','MEI');
UPDATE tbl_atleta_time SET posicao_atleta_time = 'GOLEIRO'  WHERE UPPER(posicao_atleta_time) IN ('GOLEIRO','GR','GK','GL');
UPDATE tbl_atleta_time SET posicao_atleta_time = 'ZAGUEIRO' WHERE UPPER(posicao_atleta_time) IN ('ZAGUEIRO','ZAG','CB');
UPDATE tbl_atleta_time SET posicao_atleta_time = 'LATERAL'  WHERE UPPER(posicao_atleta_time) IN ('LATERAL','LAT','LB','RB');
UPDATE tbl_atleta_time SET posicao_atleta_time = 'VOLANTE'  WHERE UPPER(posicao_atleta_time) IN ('VOLANTE','VO','DM');

-- Normaliza posicao_atleta para maiúsculo padronizado
UPDATE tbl_atletas SET posicao_atleta = 'ATACANTE' WHERE UPPER(posicao_atleta) IN ('ATAQUE','ATACANTE','AT','FW','CF');
UPDATE tbl_atletas SET posicao_atleta = 'MEIA'     WHERE UPPER(posicao_atleta) IN ('MEIO','MEIA','AM','MC','MEI');
UPDATE tbl_atletas SET posicao_atleta = 'GOLEIRO'  WHERE UPPER(posicao_atleta) IN ('GOLEIRO','GR','GK','GL');
UPDATE tbl_atletas SET posicao_atleta = 'ZAGUEIRO' WHERE UPPER(posicao_atleta) IN ('ZAGUEIRO','ZAG','CB');
UPDATE tbl_atletas SET posicao_atleta = 'LATERAL'  WHERE UPPER(posicao_atleta) IN ('LATERAL','LAT','LB','RB');
UPDATE tbl_atletas SET posicao_atleta = 'VOLANTE'  WHERE UPPER(posicao_atleta) IN ('VOLANTE','VO','DM');

SET SQL_SAFE_UPDATES = 1;


CREATE TABLE `tbl_videos` (
  `id_video`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `titulo_video`      VARCHAR(255) NOT NULL,
  `url_video`         VARCHAR(500) NOT NULL,
  `descricao_video`   TEXT         NULL,
  `status_video`      VARCHAR(20)  NOT NULL DEFAULT 'ATIVO',
  `criado_em_video`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em_video` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tbl_videos ADD COLUMN ao_vivo_video INT(1) NOT NULL DEFAULT 0 AFTER status_video;


ALTER TABLE tbl_cartoes
  MODIFY COLUMN id_campeonato INT NULL,
  MODIFY COLUMN id_jogo INT NULL;



