ALTER TABLE tbl_jogos ADD COLUMN data_jogo datetime NULL;

UPDATE tbl_jogos SET data_jogo = '2025-06-01 19:00:00' WHERE id_jogo = 1;
UPDATE tbl_jogos SET data_jogo = '2025-06-06 19:00:00' WHERE id_jogo = 2;


CREATE TABLE tbl_galeria (
    id_galeria INT AUTO_INCREMENT PRIMARY KEY,
    titulo_galeria VARCHAR(100),
    foto_galeria VARCHAR(255) NOT NULL,
    ordem_galeria INT DEFAULT 0,
    status_galeria VARCHAR(10) DEFAULT 'ATIVO',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tbl_banner (
    id_banner INT AUTO_INCREMENT PRIMARY KEY,
    titulo_banner VARCHAR(100),
    subtitulo_banner VARCHAR(255),
    foto_banner VARCHAR(255) NOT NULL,
    ordem_banner INT DEFAULT 0,
    status_banner VARCHAR(10) DEFAULT 'ATIVO',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);