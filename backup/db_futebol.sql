-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: db_futebol
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tbl_atleta_responsavel`
--

DROP TABLE IF EXISTS `tbl_atleta_responsavel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_atleta_responsavel` (
  `id_atleta_responsavel` int(11) NOT NULL AUTO_INCREMENT,
  `id_responsavel` int(11) NOT NULL,
  `id_atleta` int(11) NOT NULL,
  `grau_parentesco_responsavel` varchar(20) NOT NULL,
  PRIMARY KEY (`id_atleta_responsavel`),
  KEY `fk_ar_atleta` (`id_atleta`),
  KEY `fk_ar_responsavel` (`id_responsavel`),
  CONSTRAINT `fk_ar_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_ar_responsavel` FOREIGN KEY (`id_responsavel`) REFERENCES `tbl_responsavel` (`id_responsavel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_atleta_responsavel`
--

LOCK TABLES `tbl_atleta_responsavel` WRITE;
/*!40000 ALTER TABLE `tbl_atleta_responsavel` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_atleta_responsavel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_atleta_time`
--

DROP TABLE IF EXISTS `tbl_atleta_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_atleta_time` (
  `id_atleta_time` int(11) NOT NULL AUTO_INCREMENT,
  `id_time` int(11) NOT NULL,
  `id_atleta` int(11) NOT NULL,
  `camisa_atleta_time` int(11) NOT NULL,
  `posicao_atleta_time` varchar(20) NOT NULL,
  `jogos_atleta_time` int(11) NOT NULL DEFAULT 0,
  `convocacao_atleta_time` int(11) NOT NULL DEFAULT 0,
  `gols_atleta_time` int(11) NOT NULL DEFAULT 0,
  `defesas_atleta_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_atleta_time`),
  KEY `fk_atleta_time_atleta` (`id_atleta`),
  KEY `fk_atleta_time_time` (`id_time`),
  CONSTRAINT `fk_atleta_time_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_atleta_time_time` FOREIGN KEY (`id_time`) REFERENCES `tbl_time` (`id_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_atleta_time`
--

LOCK TABLES `tbl_atleta_time` WRITE;
/*!40000 ALTER TABLE `tbl_atleta_time` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_atleta_time` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_atletas`
--

DROP TABLE IF EXISTS `tbl_atletas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_atletas` (
  `id_atleta` int(11) NOT NULL AUTO_INCREMENT,
  `id_endereco` int(11) NOT NULL,
  `nome_atleta` varchar(100) NOT NULL,
  `numero_atleta` varchar(20) NOT NULL,
  `data_nasc_atleta` date NOT NULL,
  `cpf_atleta` varchar(14) NOT NULL,
  `rg_atleta` varchar(11) NOT NULL,
  `peso_atleta` decimal(5,2) NOT NULL,
  `altura_atleta` decimal(5,2) NOT NULL,
  `sexo_atleta` varchar(1) NOT NULL,
  `escola_atleta` varchar(100) NOT NULL,
  `serie_atleta` varchar(20) NOT NULL,
  `descricao_atleta` text NOT NULL,
  `foto_atleta` varchar(255) NOT NULL,
  `sala_atleta` int(11) NOT NULL,
  `periodo_escolar_atleta` varchar(20) NOT NULL,
  `status_atleta` varchar(11) NOT NULL DEFAULT 'ATIVO',
  PRIMARY KEY (`id_atleta`),
  UNIQUE KEY `numero_atleta` (`numero_atleta`),
  KEY `fk_atleta_endereco` (`id_endereco`),
  CONSTRAINT `fk_atleta_endereco` FOREIGN KEY (`id_endereco`) REFERENCES `tbl_endereco` (`id_endereco`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_atletas`
--

LOCK TABLES `tbl_atletas` WRITE;
/*!40000 ALTER TABLE `tbl_atletas` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_atletas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_autorizacoes`
--

DROP TABLE IF EXISTS `tbl_autorizacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_autorizacoes` (
  `id_autorizacao` int(11) NOT NULL AUTO_INCREMENT,
  `id_atleta` int(11) NOT NULL,
  `id_responsavel` int(11) NOT NULL,
  `data_assinatura_autorizacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_autorizacao`),
  KEY `fk_aut_atleta` (`id_atleta`),
  KEY `fk_aut_responsavel` (`id_responsavel`),
  CONSTRAINT `fk_aut_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_aut_responsavel` FOREIGN KEY (`id_responsavel`) REFERENCES `tbl_responsavel` (`id_responsavel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_autorizacoes`
--

LOCK TABLES `tbl_autorizacoes` WRITE;
/*!40000 ALTER TABLE `tbl_autorizacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_autorizacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_campeonato`
--

DROP TABLE IF EXISTS `tbl_campeonato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_campeonato` (
  `id_campeonato` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `logo_evento` varchar(255) NOT NULL,
  `banner_evento` varchar(255) NOT NULL,
  `nome_campeonato` varchar(100) NOT NULL,
  `organizador_campeonato` varchar(100) NOT NULL,
  `descricao_campeonato` text DEFAULT NULL,
  `tipo_campeonato` varchar(20) NOT NULL,
  `data_inicio_campeonato` datetime NOT NULL,
  `data_fim_campeonato` datetime NOT NULL,
  `local_evento` varchar(100) NOT NULL,
  PRIMARY KEY (`id_campeonato`),
  KEY `fk_campeonato_categoria` (`id_categoria`),
  CONSTRAINT `fk_campeonato_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_campeonato`
--

LOCK TABLES `tbl_campeonato` WRITE;
/*!40000 ALTER TABLE `tbl_campeonato` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_campeonato` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_campeonato_time`
--

DROP TABLE IF EXISTS `tbl_campeonato_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_campeonato_time` (
  `id_campeonato_time` int(11) NOT NULL AUTO_INCREMENT,
  `id_time` int(11) NOT NULL,
  `id_campeonato` int(11) NOT NULL,
  PRIMARY KEY (`id_campeonato_time`),
  KEY `fk_ct_time` (`id_time`),
  KEY `fk_ct_campeonato` (`id_campeonato`),
  CONSTRAINT `fk_ct_campeonato` FOREIGN KEY (`id_campeonato`) REFERENCES `tbl_campeonato` (`id_campeonato`),
  CONSTRAINT `fk_ct_time` FOREIGN KEY (`id_time`) REFERENCES `tbl_time` (`id_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_campeonato_time`
--

LOCK TABLES `tbl_campeonato_time` WRITE;
/*!40000 ALTER TABLE `tbl_campeonato_time` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_campeonato_time` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_cartoes`
--

DROP TABLE IF EXISTS `tbl_cartoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cartoes` (
  `id_cartao` int(11) NOT NULL AUTO_INCREMENT,
  `id_atleta` int(11) NOT NULL,
  `id_campeonato` int(11) NOT NULL,
  `id_jogo` int(11) NOT NULL,
  `tipo_cartao` varchar(10) NOT NULL,
  `data_cartao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cartao`),
  KEY `fk_cartao_atleta` (`id_atleta`),
  KEY `fk_cartao_jogo` (`id_jogo`),
  CONSTRAINT `fk_cartao_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_cartao_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `tbl_jogos` (`id_jogo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_cartoes`
--

LOCK TABLES `tbl_cartoes` WRITE;
/*!40000 ALTER TABLE `tbl_cartoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_cartoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_categoria`
--

DROP TABLE IF EXISTS `tbl_categoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_categoria` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nome_categoria` varchar(50) NOT NULL,
  `idade_min_categoria` int(11) NOT NULL,
  `idade_max_categoria` int(11) NOT NULL,
  `sexo_categoria` varchar(1) NOT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_categoria`
--

LOCK TABLES `tbl_categoria` WRITE;
/*!40000 ALTER TABLE `tbl_categoria` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_categoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_categoria_atleta`
--

DROP TABLE IF EXISTS `tbl_categoria_atleta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_categoria_atleta` (
  `id_categoria_atleta` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `id_atleta` int(11) NOT NULL,
  `data_inicio_categoria_atleta` datetime NOT NULL,
  `data_fim_categoria_atleta` datetime DEFAULT NULL,
  `data_atualizacao_categoria_atleta` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_categoria_atleta` varchar(20) NOT NULL DEFAULT 'ATIVO',
  `observacao_categoria_atleta` text DEFAULT NULL,
  PRIMARY KEY (`id_categoria_atleta`),
  KEY `fk_ca_atleta` (`id_atleta`),
  KEY `fk_ca_categoria` (`id_categoria`),
  CONSTRAINT `fk_ca_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_ca_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_categoria_atleta`
--

LOCK TABLES `tbl_categoria_atleta` WRITE;
/*!40000 ALTER TABLE `tbl_categoria_atleta` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_categoria_atleta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_endereco`
--

DROP TABLE IF EXISTS `tbl_endereco`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_endereco` (
  `id_endereco` int(11) NOT NULL AUTO_INCREMENT,
  `rua_endereco` varchar(100) NOT NULL,
  `numero_endereco` varchar(6) NOT NULL,
  `bairro_endereco` varchar(50) NOT NULL,
  `complemento_endereco` varchar(100) DEFAULT NULL,
  `cep_endereco` varchar(10) NOT NULL,
  `cidade_endereco` varchar(50) NOT NULL,
  `estado_endereco` varchar(2) NOT NULL,
  PRIMARY KEY (`id_endereco`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_endereco`
--

LOCK TABLES `tbl_endereco` WRITE;
/*!40000 ALTER TABLE `tbl_endereco` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_endereco` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_inscricao`
--

DROP TABLE IF EXISTS `tbl_inscricao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inscricao` (
  `id_inscricao` int(11) NOT NULL AUTO_INCREMENT,
  `id_atleta` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `data_inscricao` datetime NOT NULL DEFAULT current_timestamp(),
  `status_inscricao` varchar(11) NOT NULL DEFAULT 'ATIVO',
  PRIMARY KEY (`id_inscricao`),
  KEY `fk_inscricao_atleta` (`id_atleta`),
  KEY `fk_inscricao_categoria` (`id_categoria`),
  CONSTRAINT `fk_inscricao_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_inscricao_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_inscricao`
--

LOCK TABLES `tbl_inscricao` WRITE;
/*!40000 ALTER TABLE `tbl_inscricao` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_inscricao` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_jogos`
--

DROP TABLE IF EXISTS `tbl_jogos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_jogos` (
  `id_jogo` int(11) NOT NULL AUTO_INCREMENT,
  `id_campeonato` int(11) NOT NULL,
  `id_time_casa` int(11) NOT NULL,
  `id_time_visitante` int(11) NOT NULL,
  `placar_time_casa_jogos` int(11) NOT NULL DEFAULT 0,
  `placar_time_visitante_jogos` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_jogo`),
  KEY `fk_jogo_campeonato` (`id_campeonato`),
  KEY `fk_jogo_time_visitante` (`id_time_visitante`),
  KEY `fk_jogo_time_casa` (`id_time_casa`),
  CONSTRAINT `fk_jogo_campeonato` FOREIGN KEY (`id_campeonato`) REFERENCES `tbl_campeonato` (`id_campeonato`),
  CONSTRAINT `fk_jogo_time_casa` FOREIGN KEY (`id_time_casa`) REFERENCES `tbl_time` (`id_time`),
  CONSTRAINT `fk_jogo_time_visitante` FOREIGN KEY (`id_time_visitante`) REFERENCES `tbl_time` (`id_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_jogos`
--

LOCK TABLES `tbl_jogos` WRITE;
/*!40000 ALTER TABLE `tbl_jogos` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_jogos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_noticias`
--

DROP TABLE IF EXISTS `tbl_noticias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_noticias` (
  `id_noticia` int(11) NOT NULL AUTO_INCREMENT,
  `titulo_noticia` varchar(150) NOT NULL,
  `conteudo_noticia` text NOT NULL,
  `data_publicacao_noticia` datetime NOT NULL DEFAULT current_timestamp(),
  `autor_noticia` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_noticia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_noticias`
--

LOCK TABLES `tbl_noticias` WRITE;
/*!40000 ALTER TABLE `tbl_noticias` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_noticias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_responsavel`
--

DROP TABLE IF EXISTS `tbl_responsavel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_responsavel` (
  `id_responsavel` int(11) NOT NULL AUTO_INCREMENT,
  `id_endereco` int(11) NOT NULL,
  `nome_responsavel` varchar(100) NOT NULL,
  `cpf_responsavel` varchar(14) NOT NULL,
  `rg_responsavel` varchar(11) NOT NULL,
  `telefone_responsavel` varchar(20) DEFAULT NULL,
  `whatsapp_responsavel` varchar(20) NOT NULL,
  `assinatura_responsavel` varchar(255) NOT NULL,
  `aceite_responsavel` varchar(1) NOT NULL DEFAULT 'S',
  PRIMARY KEY (`id_responsavel`),
  KEY `fk_responsavel_endereco` (`id_endereco`),
  CONSTRAINT `fk_responsavel_endereco` FOREIGN KEY (`id_endereco`) REFERENCES `tbl_endereco` (`id_endereco`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_responsavel`
--

LOCK TABLES `tbl_responsavel` WRITE;
/*!40000 ALTER TABLE `tbl_responsavel` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_responsavel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_time`
--

DROP TABLE IF EXISTS `tbl_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_time` (
  `id_time` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `logo_time` varchar(255) NOT NULL,
  `nome_time` varchar(50) NOT NULL,
  `tipo_mando_time` varchar(10) NOT NULL,
  PRIMARY KEY (`id_time`),
  KEY `fk_time_categoria` (`id_categoria`),
  CONSTRAINT `fk_time_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_time`
--

LOCK TABLES `tbl_time` WRITE;
/*!40000 ALTER TABLE `tbl_time` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_time` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-06 11:29:54
