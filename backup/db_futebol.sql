-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: db_futebol
-- ------------------------------------------------------
-- Server version	8.0.45

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
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('iaiMSzxcq2qWvkLdXSBsWu9wvVdxMVv1G0GDlgIt',NULL,'172.21.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJZS2FLOVBrVHN1c2VOeTllUXJRNklCanpyTlJFVWh2c1BBYU5vWEcwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDgwXC9ldmVudG8iLCJyb3V0ZSI6ImV2ZW50byJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1777906540),('PB53Prl7oYeWZtJoWfVtSm8HlHZWu0l35Qoaj7OB',NULL,'172.21.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJQUlc0S0VDdjAxWHlTZkFZc0dGN25PMmxvenFBRXdtQlFJdjZOWDZKIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDgwXC9wYXJjZXJpYXMiLCJyb3V0ZSI6InBhcmNlcmlhcyJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1778160099);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_atleta_responsavel`
--

DROP TABLE IF EXISTS `tbl_atleta_responsavel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_atleta_responsavel` (
  `id_atleta_responsavel` int NOT NULL AUTO_INCREMENT,
  `id_responsavel` int NOT NULL,
  `id_atleta` int NOT NULL,
  `grau_parentesco_responsavel` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_atleta_responsavel`),
  KEY `fk_ar_atleta` (`id_atleta`),
  KEY `fk_ar_responsavel` (`id_responsavel`),
  CONSTRAINT `fk_ar_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_ar_responsavel` FOREIGN KEY (`id_responsavel`) REFERENCES `tbl_responsavel` (`id_responsavel`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_atleta_responsavel`
--

LOCK TABLES `tbl_atleta_responsavel` WRITE;
/*!40000 ALTER TABLE `tbl_atleta_responsavel` DISABLE KEYS */;
INSERT INTO `tbl_atleta_responsavel` VALUES (1,1,1,'PAI'),(2,2,1,'MAE'),(3,2,2,'MAE'),(4,3,3,'PAI');
/*!40000 ALTER TABLE `tbl_atleta_responsavel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_atleta_time`
--

DROP TABLE IF EXISTS `tbl_atleta_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_atleta_time` (
  `id_atleta_time` int NOT NULL AUTO_INCREMENT,
  `id_time` int NOT NULL,
  `id_atleta` int NOT NULL,
  `camisa_atleta_time` int NOT NULL,
  `posicao_atleta_time` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `jogos_atleta_time` int NOT NULL DEFAULT '0',
  `convocacao_atleta_time` int NOT NULL DEFAULT '0',
  `gols_atleta_time` int NOT NULL DEFAULT '0',
  `defesas_atleta_time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_atleta_time`),
  KEY `fk_atleta_time_atleta` (`id_atleta`),
  KEY `fk_atleta_time_time` (`id_time`),
  CONSTRAINT `fk_atleta_time_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_atleta_time_time` FOREIGN KEY (`id_time`) REFERENCES `tbl_time` (`id_time`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_atleta_time`
--

LOCK TABLES `tbl_atleta_time` WRITE;
/*!40000 ALTER TABLE `tbl_atleta_time` DISABLE KEYS */;
INSERT INTO `tbl_atleta_time` VALUES (1,1,1,10,'ATAQUE',5,0,3,0),(2,1,2,8,'MEIO',5,0,1,0),(3,2,3,1,'GOLEIRO',5,0,0,10);
/*!40000 ALTER TABLE `tbl_atleta_time` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_atletas`
--

DROP TABLE IF EXISTS `tbl_atletas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_atletas` (
  `id_atleta` int NOT NULL AUTO_INCREMENT,
  `id_endereco` int NOT NULL,
  `nome_atleta` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `numero_atleta` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `data_nasc_atleta` date NOT NULL,
  `cpf_atleta` varchar(14) COLLATE utf8mb4_general_ci NOT NULL,
  `rg_atleta` varchar(11) COLLATE utf8mb4_general_ci NOT NULL,
  `peso_atleta` decimal(5,2) NOT NULL,
  `altura_atleta` decimal(5,2) NOT NULL,
  `sexo_atleta` varchar(1) COLLATE utf8mb4_general_ci NOT NULL,
  `escola_atleta` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `serie_atleta` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `descricao_atleta` text COLLATE utf8mb4_general_ci NOT NULL,
  `foto_atleta` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `sala_atleta` int NOT NULL,
  `periodo_escolar_atleta` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `status_atleta` varchar(11) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'ATIVO',
  PRIMARY KEY (`id_atleta`),
  UNIQUE KEY `numero_atleta` (`numero_atleta`),
  KEY `fk_atleta_endereco` (`id_endereco`),
  CONSTRAINT `fk_atleta_endereco` FOREIGN KEY (`id_endereco`) REFERENCES `tbl_endereco` (`id_endereco`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_atletas`
--

LOCK TABLES `tbl_atletas` WRITE;
/*!40000 ALTER TABLE `tbl_atletas` DISABLE KEYS */;
INSERT INTO `tbl_atletas` VALUES (1,1,'João Silva','A001','2012-05-10','111','111',40.00,1.50,'M','Escola A','7º','Aluno','foto.jpg',101,'MANHA','ATIVO'),(2,2,'Lucas Souza','A002','2011-03-22','222','222',42.00,1.55,'M','Escola A','8º','Aluno','foto.jpg',102,'TARDE','ATIVO'),(3,3,'Pedro Lima','A003','2010-07-15','333','333',45.00,1.60,'M','Escola A','9º','Aluno','foto.jpg',103,'MANHA','ATIVO');
/*!40000 ALTER TABLE `tbl_atletas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_autorizacoes`
--

DROP TABLE IF EXISTS `tbl_autorizacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_autorizacoes` (
  `id_autorizacao` int NOT NULL AUTO_INCREMENT,
  `id_atleta` int NOT NULL,
  `id_responsavel` int NOT NULL,
  `data_assinatura_autorizacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_autorizacao`),
  KEY `fk_aut_atleta` (`id_atleta`),
  KEY `fk_aut_responsavel` (`id_responsavel`),
  CONSTRAINT `fk_aut_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_aut_responsavel` FOREIGN KEY (`id_responsavel`) REFERENCES `tbl_responsavel` (`id_responsavel`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_autorizacoes`
--

LOCK TABLES `tbl_autorizacoes` WRITE;
/*!40000 ALTER TABLE `tbl_autorizacoes` DISABLE KEYS */;
INSERT INTO `tbl_autorizacoes` VALUES (1,1,1,'2026-05-07 00:00:00'),(2,2,2,'2026-05-07 00:00:00'),(3,3,3,'2026-05-07 00:00:00');
/*!40000 ALTER TABLE `tbl_autorizacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_campeonato`
--

DROP TABLE IF EXISTS `tbl_campeonato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_campeonato` (
  `id_campeonato` int NOT NULL AUTO_INCREMENT,
  `id_categoria` int NOT NULL,
  `logo_evento` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `banner_evento` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nome_campeonato` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `organizador_campeonato` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `descricao_campeonato` text COLLATE utf8mb4_general_ci,
  `tipo_campeonato` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `data_inicio_campeonato` datetime NOT NULL,
  `data_fim_campeonato` datetime NOT NULL,
  `local_evento` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_campeonato`),
  KEY `fk_campeonato_categoria` (`id_categoria`),
  CONSTRAINT `fk_campeonato_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_campeonato`
--

LOCK TABLES `tbl_campeonato` WRITE;
/*!40000 ALTER TABLE `tbl_campeonato` DISABLE KEYS */;
INSERT INTO `tbl_campeonato` VALUES (1,1,'logo.png','banner.png','Copa Escola','Escola A','Campeonato interno','MATA-MATA','2025-01-01 00:00:00','2025-02-01 00:00:00','Quadra A');
/*!40000 ALTER TABLE `tbl_campeonato` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_campeonato_time`
--

DROP TABLE IF EXISTS `tbl_campeonato_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_campeonato_time` (
  `id_campeonato_time` int NOT NULL AUTO_INCREMENT,
  `id_time` int NOT NULL,
  `id_campeonato` int NOT NULL,
  PRIMARY KEY (`id_campeonato_time`),
  KEY `fk_ct_time` (`id_time`),
  KEY `fk_ct_campeonato` (`id_campeonato`),
  CONSTRAINT `fk_ct_campeonato` FOREIGN KEY (`id_campeonato`) REFERENCES `tbl_campeonato` (`id_campeonato`),
  CONSTRAINT `fk_ct_time` FOREIGN KEY (`id_time`) REFERENCES `tbl_time` (`id_time`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_campeonato_time`
--

LOCK TABLES `tbl_campeonato_time` WRITE;
/*!40000 ALTER TABLE `tbl_campeonato_time` DISABLE KEYS */;
INSERT INTO `tbl_campeonato_time` VALUES (1,1,1),(2,2,1),(3,3,1);
/*!40000 ALTER TABLE `tbl_campeonato_time` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_cartoes`
--

DROP TABLE IF EXISTS `tbl_cartoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cartoes` (
  `id_cartao` int NOT NULL AUTO_INCREMENT,
  `id_atleta` int NOT NULL,
  `id_campeonato` int NOT NULL,
  `id_jogo` int NOT NULL,
  `tipo_cartao` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `data_cartao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cartao`),
  KEY `fk_cartao_atleta` (`id_atleta`),
  KEY `fk_cartao_jogo` (`id_jogo`),
  CONSTRAINT `fk_cartao_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_cartao_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `tbl_jogos` (`id_jogo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_cartoes`
--

LOCK TABLES `tbl_cartoes` WRITE;
/*!40000 ALTER TABLE `tbl_cartoes` DISABLE KEYS */;
INSERT INTO `tbl_cartoes` VALUES (1,1,1,1,'AMARELO','2026-05-07 13:20:34'),(2,2,1,1,'AMARELO','2026-05-07 13:20:34'),(3,2,1,1,'AMARELO','2026-05-07 13:20:34'),(4,3,1,2,'VERMELHO','2026-05-07 13:20:34');
/*!40000 ALTER TABLE `tbl_cartoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_categoria`
--

DROP TABLE IF EXISTS `tbl_categoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_categoria` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nome_categoria` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `idade_min_categoria` int NOT NULL,
  `idade_max_categoria` int NOT NULL,
  `sexo_categoria` varchar(1) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_categoria`
--

LOCK TABLES `tbl_categoria` WRITE;
/*!40000 ALTER TABLE `tbl_categoria` DISABLE KEYS */;
INSERT INTO `tbl_categoria` VALUES (1,'Sub-12',10,12,'M'),(2,'Sub-15',13,15,'M');
/*!40000 ALTER TABLE `tbl_categoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_categoria_atleta`
--

DROP TABLE IF EXISTS `tbl_categoria_atleta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_categoria_atleta` (
  `id_categoria_atleta` int NOT NULL AUTO_INCREMENT,
  `id_categoria` int NOT NULL,
  `id_atleta` int NOT NULL,
  `data_inicio_categoria_atleta` datetime NOT NULL,
  `data_fim_categoria_atleta` datetime DEFAULT NULL,
  `data_atualizacao_categoria_atleta` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status_categoria_atleta` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'ATIVO',
  `observacao_categoria_atleta` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_categoria_atleta`),
  KEY `fk_ca_atleta` (`id_atleta`),
  KEY `fk_ca_categoria` (`id_categoria`),
  CONSTRAINT `fk_ca_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_ca_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_categoria_atleta`
--

LOCK TABLES `tbl_categoria_atleta` WRITE;
/*!40000 ALTER TABLE `tbl_categoria_atleta` DISABLE KEYS */;
INSERT INTO `tbl_categoria_atleta` VALUES (1,1,1,'2026-05-07 13:16:27',NULL,'2026-05-07 13:16:27','ATIVO',NULL),(2,1,2,'2026-05-07 13:16:27',NULL,'2026-05-07 13:16:27','ATIVO',NULL),(3,1,3,'2026-05-07 13:16:27',NULL,'2026-05-07 13:16:27','ATIVO',NULL);
/*!40000 ALTER TABLE `tbl_categoria_atleta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_endereco`
--

DROP TABLE IF EXISTS `tbl_endereco`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_endereco` (
  `id_endereco` int NOT NULL AUTO_INCREMENT,
  `rua_endereco` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `numero_endereco` varchar(6) COLLATE utf8mb4_general_ci NOT NULL,
  `bairro_endereco` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `complemento_endereco` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cep_endereco` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `cidade_endereco` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `estado_endereco` varchar(2) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_endereco`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_endereco`
--

LOCK TABLES `tbl_endereco` WRITE;
/*!40000 ALTER TABLE `tbl_endereco` DISABLE KEYS */;
INSERT INTO `tbl_endereco` VALUES (1,'Rua A','123','Centro','Casa','01000-000','São Paulo','SP'),(2,'Rua B','456','Zona Sul','Apto 12','02000-000','São Paulo','SP'),(3,'Rua C','789','Zona Norte','Casa','03000-000','São Paulo','SP');
/*!40000 ALTER TABLE `tbl_endereco` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_inscricao`
--

DROP TABLE IF EXISTS `tbl_inscricao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_inscricao` (
  `id_inscricao` int NOT NULL AUTO_INCREMENT,
  `id_atleta` int NOT NULL,
  `id_categoria` int NOT NULL,
  `data_inscricao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_inscricao` varchar(11) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'ATIVO',
  PRIMARY KEY (`id_inscricao`),
  KEY `fk_inscricao_atleta` (`id_atleta`),
  KEY `fk_inscricao_categoria` (`id_categoria`),
  CONSTRAINT `fk_inscricao_atleta` FOREIGN KEY (`id_atleta`) REFERENCES `tbl_atletas` (`id_atleta`),
  CONSTRAINT `fk_inscricao_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_inscricao`
--

LOCK TABLES `tbl_inscricao` WRITE;
/*!40000 ALTER TABLE `tbl_inscricao` DISABLE KEYS */;
INSERT INTO `tbl_inscricao` VALUES (1,1,1,'2026-05-07 13:16:45','ATIVO'),(2,2,1,'2026-05-07 13:16:45','ATIVO'),(3,3,1,'2026-05-07 13:16:45','ATIVO');
/*!40000 ALTER TABLE `tbl_inscricao` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_jogos`
--

DROP TABLE IF EXISTS `tbl_jogos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_jogos` (
  `id_jogo` int NOT NULL AUTO_INCREMENT,
  `id_campeonato` int NOT NULL,
  `id_time_casa` int NOT NULL,
  `id_time_visitante` int NOT NULL,
  `placar_time_casa_jogos` int NOT NULL DEFAULT '0',
  `placar_time_visitante_jogos` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_jogo`),
  KEY `fk_jogo_campeonato` (`id_campeonato`),
  KEY `fk_jogo_time_visitante` (`id_time_visitante`),
  KEY `fk_jogo_time_casa` (`id_time_casa`),
  CONSTRAINT `fk_jogo_campeonato` FOREIGN KEY (`id_campeonato`) REFERENCES `tbl_campeonato` (`id_campeonato`),
  CONSTRAINT `fk_jogo_time_casa` FOREIGN KEY (`id_time_casa`) REFERENCES `tbl_time` (`id_time`),
  CONSTRAINT `fk_jogo_time_visitante` FOREIGN KEY (`id_time_visitante`) REFERENCES `tbl_time` (`id_time`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_jogos`
--

LOCK TABLES `tbl_jogos` WRITE;
/*!40000 ALTER TABLE `tbl_jogos` DISABLE KEYS */;
INSERT INTO `tbl_jogos` VALUES (1,1,1,3,2,1),(2,1,2,3,1,1);
/*!40000 ALTER TABLE `tbl_jogos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_noticias`
--

DROP TABLE IF EXISTS `tbl_noticias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_noticias` (
  `id_noticia` int NOT NULL AUTO_INCREMENT,
  `titulo_noticia` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `conteudo_noticia` text COLLATE utf8mb4_general_ci NOT NULL,
  `data_publicacao_noticia` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `autor_noticia` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_noticia`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_noticias`
--

LOCK TABLES `tbl_noticias` WRITE;
/*!40000 ALTER TABLE `tbl_noticias` DISABLE KEYS */;
INSERT INTO `tbl_noticias` VALUES (1,'Início do campeonato','Começou hoje','2025-01-01 00:00:00','Admin');
/*!40000 ALTER TABLE `tbl_noticias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_responsavel`
--

DROP TABLE IF EXISTS `tbl_responsavel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_responsavel` (
  `id_responsavel` int NOT NULL AUTO_INCREMENT,
  `id_endereco` int NOT NULL,
  `nome_responsavel` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `cpf_responsavel` varchar(14) COLLATE utf8mb4_general_ci NOT NULL,
  `rg_responsavel` varchar(11) COLLATE utf8mb4_general_ci NOT NULL,
  `telefone_responsavel` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `whatsapp_responsavel` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `assinatura_responsavel` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `aceite_responsavel` varchar(1) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'S',
  PRIMARY KEY (`id_responsavel`),
  KEY `fk_responsavel_endereco` (`id_endereco`),
  CONSTRAINT `fk_responsavel_endereco` FOREIGN KEY (`id_endereco`) REFERENCES `tbl_endereco` (`id_endereco`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_responsavel`
--

LOCK TABLES `tbl_responsavel` WRITE;
/*!40000 ALTER TABLE `tbl_responsavel` DISABLE KEYS */;
INSERT INTO `tbl_responsavel` VALUES (1,1,'Carlos Silva','111','111','1199999','1199999','assinatura.png','S'),(2,2,'Maria Souza','222','222','1198888','1198888','assinatura.png','S'),(3,3,'José Lima','333','333','1197777','1197777','assinatura.png','S');
/*!40000 ALTER TABLE `tbl_responsavel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_time`
--

DROP TABLE IF EXISTS `tbl_time`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_time` (
  `id_time` int NOT NULL AUTO_INCREMENT,
  `id_categoria` int NOT NULL,
  `logo_time` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nome_time` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_time` enum('INTERNO','EXTERNO') COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_time`),
  KEY `fk_time_categoria` (`id_categoria`),
  CONSTRAINT `fk_time_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `tbl_categoria` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_time`
--

LOCK TABLES `tbl_time` WRITE;
/*!40000 ALTER TABLE `tbl_time` DISABLE KEYS */;
INSERT INTO `tbl_time` VALUES (1,1,'logo.png','Time Azul','INTERNO'),(2,1,'logo.png','Time Verde','INTERNO'),(3,1,'logo.png','Time Visitante','EXTERNO');
/*!40000 ALTER TABLE `tbl_time` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-07 10:24:19
