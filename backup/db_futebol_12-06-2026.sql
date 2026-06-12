-- Normalizar aliases internacionais para nomes em português
UPDATE tbl_atletas SET posicao_atleta = 'Goleiro'      WHERE LOWER(TRIM(posicao_atleta)) IN ('gk','gr')            AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'Zagueiro'     WHERE LOWER(TRIM(posicao_atleta)) IN ('cb','zag','z')       AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'Lateral'      WHERE LOWER(TRIM(posicao_atleta)) IN ('lb','rb','ld','le')   AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'Volante'      WHERE LOWER(TRIM(posicao_atleta)) IN ('dm','cdm','vo','vol') AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'Meia'         WHERE LOWER(TRIM(posicao_atleta)) IN ('am','mc','mf','mei')  AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'Centroavante' WHERE LOWER(TRIM(posicao_atleta)) IN ('at','fw','cf','ata','atacante') AND id_atleta > 0;


ALTER TABLE `tbl_atletas`
ADD COLUMN `posicao_atleta` VARCHAR(20) NULL DEFAULT NULL AFTER `sexo_atleta`;

-- ============================================================
-- NORMALIZAR tbl_atletas.posicao_atleta
-- ============================================================
UPDATE tbl_atletas SET posicao_atleta = 'GOLEIRO'      WHERE LOWER(TRIM(posicao_atleta)) IN ('gk','gr','goleiro','gol')             AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'ZAGUEIRO'     WHERE LOWER(TRIM(posicao_atleta)) IN ('cb','zag','z','zagueiro')             AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'LATERAL'      WHERE LOWER(TRIM(posicao_atleta)) IN ('lb','rb','ld','le','lat','lateral')    AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'VOLANTE'      WHERE LOWER(TRIM(posicao_atleta)) IN ('dm','cdm','vo','vol','volante')        AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'MEIA'         WHERE LOWER(TRIM(posicao_atleta)) IN ('am','mc','mf','mei','meia')           AND id_atleta > 0;
UPDATE tbl_atletas SET posicao_atleta = 'CENTROAVANTE' WHERE LOWER(TRIM(posicao_atleta)) IN ('at','fw','cf','ata','atacante','centroavante') AND id_atleta > 0;

-- ============================================================
-- NORMALIZAR tbl_atleta_time.posicao_atleta_time
-- ============================================================
UPDATE tbl_atleta_time SET posicao_atleta_time = 'GOLEIRO'      WHERE LOWER(TRIM(posicao_atleta_time)) IN ('gk','gr','goleiro','gol')              AND id_atleta > 0;
UPDATE tbl_atleta_time SET posicao_atleta_time = 'ZAGUEIRO'     WHERE LOWER(TRIM(posicao_atleta_time)) IN ('cb','zag','z','zagueiro')              AND id_atleta > 0;
UPDATE tbl_atleta_time SET posicao_atleta_time = 'LATERAL'      WHERE LOWER(TRIM(posicao_atleta_time)) IN ('lb','rb','ld','le','lat','lateral')     AND id_atleta > 0;
UPDATE tbl_atleta_time SET posicao_atleta_time = 'VOLANTE'      WHERE LOWER(TRIM(posicao_atleta_time)) IN ('dm','cdm','vo','vol','volante')         AND id_atleta > 0;
UPDATE tbl_atleta_time SET posicao_atleta_time = 'MEIA'         WHERE LOWER(TRIM(posicao_atleta_time)) IN ('am','mc','mf','mei','meia')            AND id_atleta > 0;
UPDATE tbl_atleta_time SET posicao_atleta_time = 'CENTROAVANTE' WHERE LOWER(TRIM(posicao_atleta_time)) IN ('at','fw','cf','ata','atacante','centroavante') AND id_atleta > 0;
