-- ============================================================
-- HISTÓRICO DE ATUALIZAÇÕES DO BANCO DE DADOS
-- Projeto: Escolinha de Futebol
-- Aplicar na ordem em que estão registradas
-- ============================================================

-- ============================================================
-- Atualização: campo de data na tabela tbl_jogos
-- ============================================================

-- Adiciona coluna de data/hora do jogo
ALTER TABLE tbl_jogos ADD COLUMN data_jogo datetime NULL;

-- Popula datas dos jogos já existentes
UPDATE tbl_jogos SET data_jogo = '2025-06-01 19:00:00' WHERE id_jogo = 1;
UPDATE tbl_jogos SET data_jogo = '2025-06-06 19:00:00' WHERE id_jogo = 2;

-- ============================================================
-- Atualização: criação das tabelas de galeria e banners
-- ============================================================

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

-- Dados iniciais dos banners
INSERT INTO tbl_banner (titulo_banner, subtitulo_banner, foto_banner, ordem_banner) VALUES
('BEM VINDO AO PRO SOCCER', 'Descrição do banner 1', 'banner1.jpg', 1),
('SOMOS UM CLUBE PROFISSIONAL DE FUTEBOL', 'Descrição do banner 2', 'banner2.jpg', 2),
('SOMOS O CLUBE DOS SONHOS', 'Descrição do banner 3', 'banner3.jpg', 3);

-- ============================================================
-- Atualização: novos campos e dados na tabela tbl_noticias
-- ============================================================

-- Adiciona foto e categoria às notícias
ALTER TABLE `tbl_noticias`
ADD COLUMN `foto_noticia` VARCHAR(255) NULL AFTER `conteudo_noticia`,
ADD COLUMN `categoria_noticia` VARCHAR(50) NOT NULL DEFAULT 'Avisos Oficiais' AFTER `foto_noticia`;

-- Limpa registros antigos e insere notícias de exemplo
TRUNCATE TABLE `tbl_noticias`;

INSERT INTO `tbl_noticias` (`titulo_noticia`, `conteudo_noticia`, `foto_noticia`, `categoria_noticia`, `data_publicacao_noticia`, `autor_noticia`) VALUES
('SUB-15 GOLEIA NA ESTREIA DA COPA BASE REGIONAL COM SHOW TÁTICO', 'Nossa categoria de base sub-15 entrou em campo na manhã deste sábado e aplicou um placar elástico de 4 a 0 contra o rival. O destaque da partida foi o coletivo e a forte pressão na saída de bola, o que garantiu os primeiros 3 pontos na tabela...', 'sub15_goleada.jpg', 'Campeonatos', '2026-05-28 09:00:00', 'Prof. Fábio'),
('Dicas de nutrição para jovens atletas antes de competições', 'Manter uma alimentação balanceada e rica em carboidratos complexos nos dias que antecedem o confronto é vital para o rendimento tático e vigor físico dos nossos atletas da base.', 'nutricao_base.jpg', 'Nutrição & Saúde', '2026-05-25 14:20:00', 'Nutricionista Julia'),
('Galeria de fotos: Álbum completo do torneio de integração interna', 'Confira os melhores cliques e momentos marcantes do nosso último torneio interno que reuniu familiares, atletas e toda a comissão técnica em um dia de celebração esportiva.', 'galeria_torneio.jpg', 'Treinamentos', '2026-05-20 11:15:00', 'Admin'),
('Reforma do gramado sintético da quadra central é concluída', 'O departamento de infraestrutura finalizou a manutenção preventiva e aplicação de novos compostos amortecedores no nosso complexo sintético, elevando a segurança dos treinos diários.', 'reforma_quadra.jpg', 'Avisos Oficiais', '2026-05-15 16:00:00', 'Diretoria Executiva');

-- ============================================================
-- Atualização: campos de autenticação na tabela tbl_atletas
-- Executar no banco de dados do projeto Futebol
-- ============================================================

-- 1. Adiciona e-mail do atleta (único, pode ser NULL se ainda não cadastrado)
ALTER TABLE `tbl_atletas`
    ADD COLUMN `email_atleta` VARCHAR(255) NULL UNIQUE AFTER `status_atleta`;

-- 2. Adiciona senha (obrigatório pelo Laravel para autenticação)
ALTER TABLE `tbl_atletas`
    ADD COLUMN `password` VARCHAR(255) NULL AFTER `email_atleta`;

-- 3. Adiciona remember_token (usado pelo Laravel para "lembrar sessão")
ALTER TABLE `tbl_atletas`
    ADD COLUMN `remember_token` VARCHAR(100) NULL AFTER `password`;

-- ============================================================
-- Após rodar esse SQL, o atleta poderá logar com CPF ou e-mail
-- O campo 'password' será preenchido pelo sistema (hash bcrypt)
-- ============================================================

-- ============================================================
-- Atualização: fluxo de cadastro público + assinatura digital
-- ============================================================

-- 4. Token único para o link de assinatura enviado ao responsável
ALTER TABLE `tbl_atletas`
    ADD COLUMN `token_cadastro` VARCHAR(100) NULL UNIQUE AFTER `remember_token`;

-- 5. Status do atleta passa a suportar PENDENTE (aguardando assinatura/aprovação)
ALTER TABLE `tbl_atletas`
    MODIFY COLUMN `status_atleta` VARCHAR(20) NOT NULL DEFAULT 'PENDENTE';

-- ============================================================
-- Atualização: fluxo de assinatura na tbl_autorizacoes
-- ============================================================

-- 6. Token do link enviado ao responsável para assinar
ALTER TABLE `tbl_autorizacoes`
    ADD COLUMN `token_assinatura` VARCHAR(100) NULL UNIQUE AFTER `data_assinatura_autorizacao`;

-- 7. Status da autorização (PENDENTE = link enviado, ASSINADO = responsável assinou)
ALTER TABLE `tbl_autorizacoes`
    ADD COLUMN `status_autorizacao` VARCHAR(20) NOT NULL DEFAULT 'PENDENTE' AFTER `token_assinatura`;

-- ============================================================
-- Resumo dos status possíveis:
-- tbl_atletas.status_atleta: PENDENTE | ATIVO | INATIVO
-- tbl_autorizacoes.status_autorizacao: PENDENTE | ASSINADO
-- ============================================================

-- ============================================================
-- Criação da tabela de usuários admin (tbl_usuarios)
-- Substitui a tabela padrão 'users' do Laravel
-- ============================================================

CREATE TABLE `tbl_usuarios` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `nome_usuario`             VARCHAR(255) NOT NULL,
    `email_usuario`            VARCHAR(255) NOT NULL UNIQUE,
    `senha_usuario`            VARCHAR(255) NOT NULL,
    `remember_token_usuario`   VARCHAR(100) NULL,
    `criado_em_usuarios`       TIMESTAMP NULL DEFAULT NULL,
    `atualizado_em_usuarios`   TIMESTAMP NULL DEFAULT NULL
);

-- Inserir usuário admin (senha: 1234567)
-- Gere o hash via: docker compose exec php php artisan tinker
-- App\Models\User::create(['nome_usuario'=>'Admin','email_usuario'=>'seu@email.com','senha_usuario'=>bcrypt('suasenha')]);
-- ============================================================

-- ============================================================
-- Atualização: foto de perfil do usuário admin (tbl_usuarios)
-- 2026-06-08
-- ============================================================

-- 8. Adiciona campo de foto para o usuário admin
--    Armazena apenas o nome do arquivo (ex: avatar5.png)
--    Os arquivos ficam em public/dash/assets/img/user/
ALTER TABLE `tbl_usuarios`
    ADD COLUMN `foto_usuario` VARCHAR(255) NULL AFTER `email_usuario`;

-- ============================================================
-- Atualização: campos nullable em tbl_atletas
-- Campos atribuídos pelo admin após o cadastro público não
-- devem ser obrigatórios no insert inicial do formulário
-- 2026-06-08
-- ============================================================

-- 9. Torna nullable os campos preenchidos pelo admin depois
ALTER TABLE `tbl_atletas`
    MODIFY COLUMN `numero_atleta`  VARCHAR(20)    NULL,
    MODIFY COLUMN `sala_atleta`    VARCHAR(20)    NULL,
    MODIFY COLUMN `peso_atleta`    DECIMAL(5,2)   NULL,
    MODIFY COLUMN `altura_atleta`  DECIMAL(4,2)   NULL,
    MODIFY COLUMN `descricao_atleta` TEXT         NULL,
    MODIFY COLUMN `foto_atleta`    VARCHAR(255)   NULL;

-- ============================================================
-- Atualização: campos nullable em tbl_responsavel
-- assinatura_responsavel é preenchida quando o responsável
-- clica no link e assina digitalmente — não existe no cadastro
-- telefone_responsavel é opcional no formulário público
-- 2026-06-08
-- ============================================================

-- 10. Torna nullable os campos da tabela de responsáveis
ALTER TABLE `tbl_responsavel`
    MODIFY COLUMN `assinatura_responsavel` TEXT         NULL,
    MODIFY COLUMN `telefone_responsavel`   VARCHAR(20)  NULL;

-- ============================================================
