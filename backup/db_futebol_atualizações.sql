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

INSERT INTO tbl_banner (titulo_banner, subtitulo_banner, foto_banner, ordem_banner) VALUES
('WELCOME TO PRO SOCCER', 'Descrição do banner 1', 'banner1.jpg', 1),
('WE ARE PROFESSIONAL FOOTBALL CLUB', 'Descrição do banner 2', 'banner2.jpg', 2),
('WE ARE DREAM CLUB', 'Descrição do banner 3', 'banner3.jpg', 3);

UPDATE tbl_banner SET titulo_banner = 'BEM VINDO AO PRO SOCCER' WHERE id_banner = 1;
UPDATE tbl_banner SET titulo_banner = 'SOMOS UM CLUBE PROFISSIONAL DE FUTEBOL' WHERE id_banner = 2;
UPDATE tbl_banner SET titulo_banner = 'SOMOS O CLUBE DOS SONHOS' WHERE id_banner = 3;

ALTER TABLE tbl_noticias ADD COLUMN foto_noticia VARCHAR(255) DEFAULT NULL;