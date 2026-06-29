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
