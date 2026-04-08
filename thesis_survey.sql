-- MySQL dump 10.13  Distrib 9.6.0, for macos14.8 (x86_64)
--
-- Host: 127.0.0.1    Database: thesis_survey
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'd69d4d9a-3373-11f1-8e01-27893c8d12b2:1-332';

--
-- Table structure for table `document_events`
--

DROP TABLE IF EXISTS `document_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` int unsigned NOT NULL,
  `task_number` int unsigned NOT NULL,
  `document_key` varchar(100) NOT NULL,
  `event_type` varchar(20) NOT NULL,
  `event_time` datetime NOT NULL,
  `view_ms` int unsigned DEFAULT NULL,
  `event_order` int unsigned DEFAULT NULL,
  `display_order` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_document_events_participant` (`participant_id`),
  KEY `idx_document_events_task` (`task_number`),
  CONSTRAINT `fk_document_events_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_events`
--

LOCK TABLES `document_events` WRITE;
/*!40000 ALTER TABLE `document_events` DISABLE KEYS */;
INSERT INTO `document_events` VALUES (1,1,1,'refund_policy','open','2026-04-08 18:21:08',NULL,1,NULL),(2,1,1,'refund_policy','close','2026-04-08 18:21:09',568,1,NULL),(3,1,2,'shipping_policy','open','2026-04-08 18:21:15',NULL,1,NULL),(4,1,2,'shipping_policy','close','2026-04-08 18:21:16',628,1,NULL),(5,1,2,'tracking_faq','open','2026-04-08 18:21:16',NULL,2,NULL),(6,1,2,'tracking_faq','close','2026-04-08 18:21:17',452,2,NULL),(7,1,2,'tracking_faq','open','2026-04-08 18:21:17',NULL,3,NULL),(8,1,2,'tracking_faq','close','2026-04-08 18:21:17',445,3,NULL),(9,1,2,'brand_voice','open','2026-04-08 18:21:18',NULL,4,NULL),(10,1,2,'brand_voice','close','2026-04-08 18:21:18',251,4,NULL),(11,1,4,'subscription_terms','open','2026-04-08 18:21:31',NULL,1,NULL),(12,1,4,'subscription_terms','close','2026-04-08 18:21:32',1028,1,NULL),(13,1,1,'refund_policy','open','2026-04-08 18:24:00',NULL,1,NULL),(14,1,1,'refund_policy','close','2026-04-08 18:24:00',536,1,NULL),(15,1,1,'refund_policy','open','2026-04-08 18:24:01',NULL,2,NULL),(16,1,1,'refund_policy','close','2026-04-08 18:24:01',424,2,NULL),(17,1,1,'support_sop','open','2026-04-08 18:24:02',NULL,3,NULL),(18,1,1,'support_sop','close','2026-04-08 18:24:02',417,3,NULL),(19,1,1,'support_sop','open','2026-04-08 18:24:03',NULL,4,NULL),(20,1,1,'support_sop','close','2026-04-08 18:24:03',333,4,NULL),(21,1,1,'tone_guidelines','open','2026-04-08 18:24:03',NULL,5,NULL),(22,1,1,'tone_guidelines','close','2026-04-08 18:24:04',373,5,NULL),(23,1,2,'shipping_policy','open','2026-04-08 18:24:09',NULL,1,NULL),(24,1,2,'shipping_policy','close','2026-04-08 18:24:09',402,1,NULL),(25,1,2,'shipping_policy','open','2026-04-08 18:24:10',NULL,2,NULL),(26,1,2,'shipping_policy','close','2026-04-08 18:24:10',421,2,NULL),(27,1,2,'tracking_faq','open','2026-04-08 18:24:11',NULL,3,NULL),(28,1,2,'tracking_faq','close','2026-04-08 18:24:11',207,3,NULL),(29,1,2,'brand_voice','open','2026-04-08 18:24:12',NULL,4,NULL),(30,1,2,'brand_voice','close','2026-04-08 18:24:12',137,4,NULL),(31,1,2,'brand_voice','open','2026-04-08 18:24:13',NULL,5,NULL),(32,1,2,'brand_voice','close','2026-04-08 18:24:13',150,5,NULL),(33,1,2,'brand_voice','open','2026-04-08 18:24:13',NULL,6,NULL),(34,1,2,'brand_voice','close','2026-04-08 18:24:13',133,6,NULL),(35,1,3,'privacy_policy','open','2026-04-08 18:24:19',NULL,1,NULL),(36,1,3,'privacy_policy','close','2026-04-08 18:24:19',537,1,NULL),(37,1,3,'deletion_process','open','2026-04-08 18:24:20',NULL,2,NULL),(38,1,3,'deletion_process','close','2026-04-08 18:24:20',387,2,NULL),(39,1,3,'communication_style','open','2026-04-08 18:24:21',NULL,3,NULL),(40,1,3,'communication_style','close','2026-04-08 18:24:21',338,3,NULL),(41,1,4,'subscription_terms','open','2026-04-08 18:24:30',NULL,1,NULL),(42,1,4,'subscription_terms','close','2026-04-08 18:24:30',217,1,NULL),(43,1,4,'reply_templates','open','2026-04-08 18:24:31',NULL,2,NULL),(44,1,4,'reply_templates','close','2026-04-08 18:24:32',238,2,NULL),(45,2,1,'refund_policy','open','2026-04-08 18:25:59',NULL,1,NULL),(46,2,1,'refund_policy','close','2026-04-08 18:25:59',113,1,NULL),(47,2,1,'support_sop','open','2026-04-08 18:25:59',NULL,2,NULL),(48,2,1,'support_sop','close','2026-04-08 18:26:00',116,2,NULL),(49,2,1,'tone_guidelines','open','2026-04-08 18:26:00',NULL,3,NULL),(50,2,1,'tone_guidelines','close','2026-04-08 18:26:00',118,3,NULL),(51,2,2,'shipping_policy','open','2026-04-08 18:26:08',NULL,1,NULL),(52,2,2,'shipping_policy','close','2026-04-08 18:26:09',455,1,NULL),(53,2,2,'tracking_faq','open','2026-04-08 18:26:09',NULL,2,NULL),(54,2,2,'tracking_faq','close','2026-04-08 18:26:09',276,2,NULL),(55,2,2,'brand_voice','open','2026-04-08 18:26:10',NULL,3,NULL),(56,2,2,'brand_voice','close','2026-04-08 18:26:10',134,3,NULL),(57,2,2,'brand_voice','open','2026-04-08 18:26:10',NULL,4,NULL),(58,2,2,'brand_voice','close','2026-04-08 18:26:10',123,4,NULL),(59,2,2,'brand_voice','open','2026-04-08 18:26:10',NULL,5,NULL),(60,2,2,'brand_voice','close','2026-04-08 18:26:10',121,5,NULL),(61,2,2,'brand_voice','open','2026-04-08 18:26:10',NULL,6,NULL),(62,2,2,'brand_voice','close','2026-04-08 18:26:11',106,6,NULL),(63,2,3,'deletion_process','open','2026-04-08 18:26:17',NULL,1,NULL),(64,2,3,'deletion_process','close','2026-04-08 18:26:17',140,1,NULL),(65,4,1,'support_sop','open','2026-04-08 18:27:06',NULL,1,NULL),(66,4,1,'support_sop','close','2026-04-08 18:27:06',294,1,NULL),(67,4,1,'tone_guidelines','open','2026-04-08 18:27:07',NULL,2,NULL),(68,4,1,'tone_guidelines','close','2026-04-08 18:27:07',295,2,NULL),(69,4,2,'tracking_faq','open','2026-04-08 18:27:12',NULL,1,NULL),(70,4,2,'tracking_faq','close','2026-04-08 18:27:12',506,1,NULL),(71,4,2,'brand_voice','open','2026-04-08 18:27:12',NULL,2,NULL),(72,4,2,'brand_voice','close','2026-04-08 18:27:13',493,2,NULL),(73,4,3,'communication_style','open','2026-04-08 18:27:19',NULL,1,NULL),(74,4,3,'communication_style','close','2026-04-08 18:27:20',361,1,NULL),(75,4,3,'privacy_policy','open','2026-04-08 18:27:20',NULL,2,NULL),(76,4,3,'privacy_policy','close','2026-04-08 18:27:21',142,2,NULL),(77,4,4,'subscription_terms','open','2026-04-08 18:27:26',NULL,1,NULL),(78,4,4,'subscription_terms','close','2026-04-08 18:27:27',868,1,NULL),(79,4,4,'reply_templates','open','2026-04-08 18:27:28',NULL,2,NULL),(80,4,4,'reply_templates','close','2026-04-08 18:27:29',500,2,NULL),(81,4,4,'billing_rules','open','2026-04-08 18:27:29',NULL,3,NULL),(82,4,4,'billing_rules','close','2026-04-08 18:27:30',280,3,NULL),(83,16,1,'support_sop','open','2026-04-08 19:10:14',NULL,1,1),(84,16,1,'support_sop','close','2026-04-08 19:10:15',912,1,1),(85,16,2,'tracking_faq','open','2026-04-08 19:10:21',NULL,1,2),(86,16,2,'tracking_faq','close','2026-04-08 19:10:22',572,1,2),(87,16,2,'shipping_policy','open','2026-04-08 19:10:24',NULL,2,3),(88,16,2,'shipping_policy','close','2026-04-08 19:10:24',172,2,3),(89,16,2,'shipping_policy','open','2026-04-08 19:10:24',NULL,3,3),(90,16,2,'shipping_policy','close','2026-04-08 19:10:24',237,3,3),(91,16,2,'brand_voice','open','2026-04-08 19:10:25',NULL,4,1),(92,16,2,'brand_voice','close','2026-04-08 19:10:25',142,4,1),(93,16,2,'brand_voice','open','2026-04-08 19:10:25',NULL,5,1),(94,16,2,'brand_voice','close','2026-04-08 19:10:25',139,5,1),(95,16,3,'privacy_policy','open','2026-04-08 19:10:30',NULL,1,2),(96,16,3,'privacy_policy','close','2026-04-08 19:10:31',501,1,2),(97,16,3,'deletion_process','open','2026-04-08 19:10:31',NULL,2,1),(98,16,3,'deletion_process','close','2026-04-08 19:10:31',441,2,1),(99,16,4,'reply_templates','open','2026-04-08 19:10:37',NULL,1,1),(100,16,4,'reply_templates','close','2026-04-08 19:10:37',395,1,1),(101,16,1,'refund_policy','open','2026-04-08 19:15:43',NULL,2,2),(102,16,1,'refund_policy','close','2026-04-08 19:15:44',850,2,2),(103,16,1,'tone_guidelines','open','2026-04-08 19:15:44',NULL,3,3),(104,16,1,'tone_guidelines','close','2026-04-08 19:15:45',606,3,3),(105,16,1,'refund_policy','open','2026-04-08 19:15:45',NULL,4,2),(106,16,1,'refund_policy','close','2026-04-08 19:15:46',762,4,2),(107,16,1,'tone_guidelines','open','2026-04-08 19:15:47',NULL,5,3),(108,16,1,'tone_guidelines','close','2026-04-08 19:15:47',643,5,3),(109,16,1,'tone_guidelines','open','2026-04-08 19:15:48',NULL,6,3),(110,16,1,'tone_guidelines','close','2026-04-08 19:15:48',388,6,3),(111,16,1,'tone_guidelines','open','2026-04-08 19:15:48',NULL,7,3),(112,16,1,'tone_guidelines','close','2026-04-08 19:15:49',289,7,3),(113,16,1,'support_sop','open','2026-04-08 19:15:49',NULL,8,1),(114,16,1,'support_sop','close','2026-04-08 19:15:50',377,8,1),(115,16,1,'support_sop','open','2026-04-08 19:15:50',NULL,9,1),(116,16,1,'support_sop','close','2026-04-08 19:15:51',369,9,1),(117,16,1,'refund_policy','open','2026-04-08 19:15:51',NULL,10,2),(118,16,1,'refund_policy','close','2026-04-08 19:15:51',322,10,2),(119,16,1,'support_sop','open','2026-04-08 19:15:52',NULL,11,1),(120,16,1,'support_sop','close','2026-04-08 19:15:52',307,11,1),(121,1,1,'refund_policy','open','2026-04-08 19:22:00',NULL,1,3),(122,1,1,'refund_policy','close','2026-04-08 19:22:00',370,1,3),(123,1,1,'support_sop','open','2026-04-08 19:22:01',NULL,2,2),(124,1,1,'support_sop','close','2026-04-08 19:22:01',677,2,2),(125,1,2,'shipping_policy','open','2026-04-08 19:22:05',NULL,1,1),(126,1,2,'shipping_policy','close','2026-04-08 19:22:06',1332,1,1),(127,1,2,'brand_voice','open','2026-04-08 19:22:06',NULL,2,2),(128,1,2,'brand_voice','close','2026-04-08 19:22:07',255,2,2),(129,1,3,'communication_style','open','2026-04-08 19:22:12',NULL,1,1),(130,1,3,'communication_style','close','2026-04-08 19:22:12',135,1,1),(131,1,3,'deletion_process','open','2026-04-08 19:22:13',NULL,2,2),(132,1,3,'deletion_process','close','2026-04-08 19:22:13',119,2,2),(133,1,4,'reply_templates','open','2026-04-08 19:22:19',NULL,1,2),(134,1,4,'reply_templates','close','2026-04-08 19:22:20',385,1,2),(135,1,1,'refund_policy','open','2026-04-08 19:29:25',NULL,3,3),(136,1,1,'refund_policy','close','2026-04-08 19:29:26',553,3,3),(137,1,1,'support_sop','open','2026-04-08 19:29:26',NULL,4,2),(138,1,1,'support_sop','close','2026-04-08 19:29:27',422,4,2),(139,17,1,'tone_guidelines','open','2026-04-08 19:30:42',NULL,1,1),(140,17,1,'tone_guidelines','close','2026-04-08 19:30:43',429,1,1),(141,1,2,'brand_voice','open','2026-04-08 19:30:57',NULL,3,2),(142,1,2,'brand_voice','close','2026-04-08 19:30:57',189,3,2),(143,1,3,'communication_style','open','2026-04-08 19:31:03',NULL,3,1),(144,1,3,'communication_style','close','2026-04-08 19:31:04',725,3,1),(145,1,4,'billing_rules','open','2026-04-08 19:31:08',NULL,2,3),(146,1,4,'billing_rules','close','2026-04-08 19:31:09',260,2,3),(147,18,1,'support_sop','open','2026-04-08 19:33:25',NULL,1,1),(148,18,1,'support_sop','close','2026-04-08 19:33:25',139,1,1),(149,18,1,'refund_policy','open','2026-04-08 19:33:26',NULL,2,2),(150,18,1,'refund_policy','close','2026-04-08 19:33:26',122,2,2),(151,18,2,'shipping_policy','open','2026-04-08 19:33:32',NULL,1,2),(152,18,2,'shipping_policy','close','2026-04-08 19:33:32',122,1,2),(153,18,2,'brand_voice','open','2026-04-08 19:33:32',NULL,2,3),(154,18,2,'brand_voice','close','2026-04-08 19:33:32',134,2,3),(155,18,3,'communication_style','open','2026-04-08 19:33:38',NULL,1,1),(156,18,3,'communication_style','close','2026-04-08 19:33:38',226,1,1),(157,18,3,'privacy_policy','open','2026-04-08 19:33:38',NULL,2,2),(158,18,3,'privacy_policy','close','2026-04-08 19:33:38',122,2,2),(159,18,4,'reply_templates','open','2026-04-08 19:33:44',NULL,1,1),(160,18,4,'reply_templates','close','2026-04-08 19:33:45',274,1,1),(161,18,4,'subscription_terms','open','2026-04-08 19:33:45',NULL,2,2),(162,18,4,'subscription_terms','close','2026-04-08 19:33:46',274,2,2),(163,1,2,'tracking_faq','open','2026-04-08 20:57:40',NULL,4,3),(164,1,2,'tracking_faq','close','2026-04-08 20:57:41',671,4,3),(165,1,2,'tracking_faq','open','2026-04-08 20:57:41',NULL,5,3),(166,1,2,'tracking_faq','close','2026-04-08 20:57:41',306,5,3),(167,1,2,'brand_voice','open','2026-04-08 20:57:42',NULL,6,2),(168,1,2,'brand_voice','close','2026-04-08 20:57:43',1277,6,2),(169,1,2,'shipping_policy','open','2026-04-08 20:57:43',NULL,7,1),(170,1,2,'shipping_policy','close','2026-04-08 20:57:44',493,7,1),(171,1,2,'tracking_faq','open','2026-04-08 20:57:45',NULL,8,3),(172,1,2,'tracking_faq','close','2026-04-08 20:57:46',1043,8,3),(173,1,2,'shipping_policy','open','2026-04-08 20:57:46',NULL,9,1),(174,1,2,'shipping_policy','close','2026-04-08 20:57:47',791,9,1),(175,1,2,'shipping_policy','open','2026-04-08 20:57:49',NULL,10,1),(176,1,2,'shipping_policy','close','2026-04-08 20:57:50',708,10,1),(177,1,1,'tone_guidelines','open','2026-04-08 21:25:55',NULL,5,1),(178,1,1,'tone_guidelines','close','2026-04-08 21:25:57',1153,5,1),(179,1,1,'support_sop','open','2026-04-08 21:25:57',NULL,6,2),(180,1,1,'support_sop','close','2026-04-08 21:25:57',320,6,2),(181,1,1,'tone_guidelines','open','2026-04-08 21:28:22',NULL,7,1),(182,1,1,'tone_guidelines','close','2026-04-08 21:28:22',537,7,1),(183,20,1,'refund_policy','open','2026-04-08 21:51:34',NULL,1,1),(184,20,1,'refund_policy','close','2026-04-08 21:51:37',2499,1,1),(185,20,1,'tone_guidelines','open','2026-04-08 21:51:38',NULL,2,2),(186,20,1,'tone_guidelines','close','2026-04-08 21:51:38',445,2,2),(187,20,1,'tone_guidelines','open','2026-04-08 21:51:50',NULL,3,2),(188,20,1,'tone_guidelines','close','2026-04-08 21:51:51',489,3,2),(189,20,2,'tracking_faq','open','2026-04-08 21:52:02',NULL,1,1),(190,20,2,'tracking_faq','close','2026-04-08 21:52:02',555,1,1),(191,20,3,'privacy_policy','open','2026-04-08 21:52:11',NULL,1,1),(192,20,3,'privacy_policy','close','2026-04-08 21:52:11',558,1,1),(193,20,1,'refund_policy','open','2026-04-08 21:55:06',NULL,4,1),(194,20,1,'refund_policy','close','2026-04-08 21:55:07',785,4,1),(195,20,1,'refund_policy','open','2026-04-08 21:55:09',NULL,5,1),(196,20,1,'refund_policy','close','2026-04-08 21:55:16',6644,5,1),(197,20,1,'refund_policy','open','2026-04-08 21:55:43',NULL,6,1),(198,20,1,'refund_policy','close','2026-04-08 21:55:48',4735,6,1),(199,20,1,'refund_policy','open','2026-04-08 21:55:53',NULL,7,1),(200,20,1,'refund_policy','close','2026-04-08 21:55:54',1001,7,1),(201,20,1,'refund_policy','open','2026-04-08 21:56:44',NULL,8,1),(202,20,1,'refund_policy','close','2026-04-08 21:56:46',1433,8,1),(203,20,1,'refund_policy','open','2026-04-08 21:56:48',NULL,9,1),(204,20,1,'refund_policy','close','2026-04-08 21:56:55',7652,9,1),(205,20,1,'refund_policy','open','2026-04-08 21:56:57',NULL,10,1),(206,20,1,'refund_policy','close','2026-04-08 21:56:59',1860,10,1),(207,20,1,'tone_guidelines','open','2026-04-08 21:56:59',NULL,11,2),(208,20,1,'tone_guidelines','close','2026-04-08 21:57:00',765,11,2),(209,20,1,'support_sop','open','2026-04-08 21:57:00',NULL,12,3),(210,20,1,'support_sop','close','2026-04-08 21:57:01',578,12,3),(211,20,1,'refund_policy','open','2026-04-08 21:57:11',NULL,13,1),(212,20,1,'refund_policy','close','2026-04-08 21:57:21',9775,13,1),(213,1,1,'support_sop','open','2026-04-08 22:05:46',NULL,8,2),(214,1,1,'support_sop','close','2026-04-08 22:05:46',637,8,2),(215,1,1,'support_sop','open','2026-04-08 22:05:48',NULL,9,2),(216,1,1,'support_sop','close','2026-04-08 22:05:52',4472,9,2),(217,1,1,'tone_guidelines','open','2026-04-08 22:07:00',NULL,10,1),(218,1,1,'tone_guidelines','close','2026-04-08 22:07:01',1634,10,1),(219,1,1,'support_sop','open','2026-04-08 22:07:04',NULL,11,2),(220,1,1,'support_sop','close','2026-04-08 22:07:05',688,11,2),(221,1,1,'tone_guidelines','open','2026-04-08 22:17:21',NULL,12,1),(222,1,1,'tone_guidelines','close','2026-04-08 22:17:23',1454,12,1),(223,1,1,'support_sop','open','2026-04-08 22:17:23',NULL,13,2),(224,1,1,'support_sop','close','2026-04-08 22:17:26',2771,13,2),(225,1,1,'refund_policy','open','2026-04-08 22:17:28',NULL,14,3),(226,1,1,'refund_policy','close','2026-04-08 22:17:30',2124,14,3);
/*!40000 ALTER TABLE `document_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `participants`
--

DROP TABLE IF EXISTS `participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `participant_code` varchar(50) NOT NULL,
  `condition_name` varchar(50) NOT NULL,
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `participant_code` (`participant_code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `participants`
--

LOCK TABLES `participants` WRITE;
/*!40000 ALTER TABLE `participants` DISABLE KEYS */;
INSERT INTO `participants` VALUES (1,'P-1412DDAA','passive','2026-04-08 18:09:13','2026-04-08 19:31:34'),(2,'P-B340B6B0','passive','2026-04-08 18:25:56',NULL),(3,'P-6379FD95','passive','2026-04-08 18:26:41',NULL),(4,'P-15A96AB3','control','2026-04-08 18:26:58','2026-04-08 18:27:49'),(5,'P-F138BB90','passive','2026-04-08 18:28:38',NULL),(6,'P-3FBA4DC1','control','2026-04-08 18:28:51',NULL),(7,'P-BD4F7E9E','control','2026-04-08 18:29:04',NULL),(8,'P-887654D8','control','2026-04-08 18:29:15',NULL),(9,'P-32BD4492','active','2026-04-08 18:29:28',NULL),(10,'P-E2536D86','passive','2026-04-08 18:53:03',NULL),(11,'P-F9DE3930','passive','2026-04-08 18:53:10','2026-04-08 18:55:27'),(12,'P-DB6A5935','active','2026-04-08 18:53:22',NULL),(13,'P-E34469A4','active','2026-04-08 18:54:03',NULL),(14,'P-F1F46DB7','control','2026-04-08 19:01:02',NULL),(15,'P-39C126F6','control','2026-04-08 19:06:09','2026-04-08 19:07:08'),(16,'P-8EEBD4E9','control','2026-04-08 19:10:12','2026-04-08 19:10:59'),(17,'P-7F34F869','control','2026-04-08 19:30:39',NULL),(18,'P-B42A8DA9','passive','2026-04-08 19:33:23','2026-04-08 19:34:05'),(19,'P-B38DEDAC','active','2026-04-08 20:51:57',NULL),(20,'P-41FB2270','active','2026-04-08 21:08:22',NULL);
/*!40000 ALTER TABLE `participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `postsurvey_responses`
--

DROP TABLE IF EXISTS `postsurvey_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `postsurvey_responses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` int unsigned NOT NULL,
  `ai_lit_1` tinyint unsigned NOT NULL,
  `ai_lit_2` tinyint unsigned NOT NULL,
  `ai_lit_3` tinyint unsigned NOT NULL,
  `ai_lit_4` tinyint unsigned NOT NULL,
  `ai_lit_5` tinyint unsigned NOT NULL,
  `crt_1` decimal(10,2) NOT NULL,
  `crt_2` decimal(10,2) NOT NULL,
  `crt_3` decimal(10,2) NOT NULL,
  `ai_experience` varchar(50) NOT NULL,
  `age` smallint unsigned NOT NULL,
  `gender` varchar(50) NOT NULL,
  `education` varchar(50) NOT NULL,
  `submitted_at` datetime NOT NULL,
  `duration_seconds` int unsigned DEFAULT NULL,
  `short_time_flag` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_postsurvey_participant` (`participant_id`),
  CONSTRAINT `fk_postsurvey_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `postsurvey_responses`
--

LOCK TABLES `postsurvey_responses` WRITE;
/*!40000 ALTER TABLE `postsurvey_responses` DISABLE KEYS */;
INSERT INTO `postsurvey_responses` VALUES (1,1,5,4,5,3,7,0.03,67.00,76.00,'regularly',21,'male','high_school','2026-04-08 18:14:57',NULL,0),(2,1,2,7,7,7,7,12.00,234.00,234.00,'never',24,'male','bachelors','2026-04-08 18:21:56',NULL,0),(3,1,5,3,3,5,3,2.00,3.00,3.99,'regularly',24,'male','high_school','2026-04-08 18:24:54',NULL,0),(4,4,6,3,4,4,5,2.00,2.00,2.00,'regularly',23,'male','phd','2026-04-08 18:27:49',NULL,0),(5,11,7,7,3,4,3,2.00,3.00,4.00,'never',24,'non_binary','bachelors','2026-04-08 18:55:27',NULL,0),(6,15,7,6,5,6,5,34.00,34.00,34.00,'occasionally',34,'male','bachelors','2026-04-08 19:07:08',NULL,0),(7,16,6,5,6,1,3,3.00,5.00,6.00,'occasionally',23,'female','masters','2026-04-08 19:10:59',NULL,0),(8,1,7,7,7,7,7,5.00,6.00,6.98,'regularly',20,'female','bachelors','2026-04-08 19:22:59',NULL,0),(9,1,7,4,6,6,4,2.00,3.00,3.98,'daily',45,'female','masters','2026-04-08 19:31:34',NULL,0),(10,18,3,4,5,3,5,6.00,4.00,5.00,'occasionally',23,'female','masters','2026-04-08 19:34:05',15,1);
/*!40000 ALTER TABLE `postsurvey_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_responses`
--

DROP TABLE IF EXISTS `task_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_responses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` int unsigned NOT NULL,
  `task_number` int unsigned NOT NULL,
  `ai_correct` tinyint(1) NOT NULL,
  `reliance_choice` varchar(50) NOT NULL,
  `final_response` text NOT NULL,
  `confidence` tinyint unsigned NOT NULL,
  `active_reflection` text,
  `verification_intention` varchar(60) DEFAULT NULL,
  `task_started_at` datetime DEFAULT NULL,
  `task_submitted_at` datetime DEFAULT NULL,
  `duration_seconds` int unsigned DEFAULT NULL,
  `short_time_flag` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_task_responses_participant` (`participant_id`),
  KEY `idx_task_responses_task` (`task_number`),
  CONSTRAINT `fk_task_responses_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_responses`
--

LOCK TABLES `task_responses` WRITE;
/*!40000 ALTER TABLE `task_responses` DISABLE KEYS */;
INSERT INTO `task_responses` VALUES (1,1,1,0,'major_edits','fr6ytgbuhjbghvcfytgu',3,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 18:14:06',NULL,0),(2,1,1,0,'discarded','cfyvgbhjknmjknjb',4,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 18:21:14',NULL,0),(3,1,2,1,'major_edits','vyfgbhjkn',2,NULL,NULL,'2026-04-08 18:21:14','2026-04-08 18:21:25',NULL,0),(4,1,3,0,'minor_edits','n',4,NULL,NULL,'2026-04-08 18:21:25','2026-04-08 18:21:30',NULL,0),(5,1,4,1,'minor_edits','hbijo',4,NULL,NULL,'2026-04-08 18:21:30','2026-04-08 18:21:36',NULL,0),(6,1,1,0,'minor_edits','cADFSb',4,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 18:24:08',NULL,0),(7,1,2,1,'major_edits','vsDfz',4,NULL,NULL,'2026-04-08 18:21:14','2026-04-08 18:24:18',NULL,0),(8,1,3,0,'minor_edits','VSBF DZfbSV',4,NULL,NULL,'2026-04-08 18:21:25','2026-04-08 18:24:28',NULL,0),(9,1,4,1,'discarded','vSDz fvsDFDVz fvsdfv',3,NULL,NULL,'2026-04-08 18:21:30','2026-04-08 18:24:37',NULL,0),(10,2,1,0,'major_edits','fwABESRGWEFQR3',3,NULL,NULL,'2026-04-08 18:25:57','2026-04-08 18:26:07',NULL,0),(11,2,2,1,'as_is','AGERSBFDAREGWFR3',3,NULL,NULL,'2026-04-08 18:26:07','2026-04-08 18:26:16',NULL,0),(12,2,3,0,'as_is','AFWGEVBS',3,NULL,NULL,'2026-04-08 18:26:16','2026-04-08 18:26:25',NULL,0),(13,4,1,0,'major_edits','VSABFD',3,NULL,NULL,'2026-04-08 18:27:00','2026-04-08 18:27:11',NULL,0),(14,4,2,1,'discarded','avbfgs',3,NULL,NULL,'2026-04-08 18:27:11','2026-04-08 18:27:18',NULL,0),(15,4,3,0,'major_edits','awfbe',3,NULL,NULL,'2026-04-08 18:27:18','2026-04-08 18:27:26',NULL,0),(16,4,4,1,'minor_edits','fwgaRSF',3,NULL,NULL,'2026-04-08 18:27:26','2026-04-08 18:27:34',NULL,0),(17,11,1,0,'major_edits','dwqefwesd',2,NULL,NULL,'2026-04-08 18:53:11','2026-04-08 18:54:38',NULL,0),(18,11,2,1,'major_edits','dwqCSV',3,NULL,NULL,'2026-04-08 18:54:38','2026-04-08 18:54:49',NULL,0),(19,11,3,0,'major_edits','Sdvabfgndh',2,NULL,NULL,'2026-04-08 18:54:49','2026-04-08 18:54:58',NULL,0),(20,11,4,1,'minor_edits','SVDbfzx',3,NULL,NULL,'2026-04-08 18:54:58','2026-04-08 18:55:04',NULL,0),(21,15,1,0,'minor_edits','yfg',4,NULL,NULL,'2026-04-08 19:06:09','2026-04-08 19:06:24',NULL,0),(22,15,2,1,'major_edits','hvguhbijnl',4,NULL,NULL,'2026-04-08 19:06:24','2026-04-08 19:06:32',NULL,0),(23,15,3,0,'major_edits','cfygvubhijnklkm',3,NULL,NULL,'2026-04-08 19:06:32','2026-04-08 19:06:43',NULL,0),(24,15,4,1,'discarded','nkjlkm',4,NULL,NULL,'2026-04-08 19:06:43','2026-04-08 19:06:50',NULL,0),(25,16,1,0,'minor_edits','http://localhost:8000/thankyou.php',3,NULL,NULL,'2026-04-08 19:10:13','2026-04-08 19:10:20',NULL,0),(26,16,2,1,'discarded','cyvugbhk',3,NULL,NULL,'2026-04-08 19:10:20','2026-04-08 19:10:29',NULL,0),(27,16,3,0,'discarded','jhbjn',3,NULL,NULL,'2026-04-08 19:10:29','2026-04-08 19:10:36',NULL,0),(28,16,4,1,'as_is','byugihn',2,NULL,NULL,'2026-04-08 19:10:36','2026-04-08 19:10:41',NULL,0),(29,1,1,0,'discarded','uvtybihjol',3,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 19:22:04',NULL,0),(30,1,2,1,'discarded','ctfygvhbjkjnl',3,NULL,NULL,'2026-04-08 18:21:14','2026-04-08 19:22:11',NULL,0),(31,1,3,0,'major_edits','vfyugbhnj',4,NULL,NULL,'2026-04-08 18:21:25','2026-04-08 19:22:18',NULL,0),(32,1,4,1,'major_edits','cfgvjbhk',3,NULL,NULL,'2026-04-08 18:21:30','2026-04-08 19:22:25',NULL,0),(33,17,1,0,'discarded','zdvsbf',5,NULL,NULL,'2026-04-08 19:30:41','2026-04-08 19:30:50',NULL,0),(34,1,1,0,'major_edits','ugyihjgcfhgvjhbkjnlm',3,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 19:30:55',NULL,0),(35,1,2,1,'major_edits','VSDFFSBZdgn',4,NULL,NULL,'2026-04-08 18:21:14','2026-04-08 19:31:01',NULL,0),(36,1,3,0,'minor_edits','BFDZgnbds',3,NULL,NULL,'2026-04-08 18:21:25','2026-04-08 19:31:07',NULL,0),(37,1,4,1,'major_edits','BFZDgncfbdzvd',3,NULL,NULL,'2026-04-08 18:21:30','2026-04-08 19:31:13',NULL,0),(38,18,1,0,'discarded','hgvbhkjnlm',2,NULL,NULL,'2026-04-08 19:33:24','2026-04-08 19:33:30',6,1),(39,18,2,1,'discarded','cgfvgbhn',3,NULL,NULL,'2026-04-08 19:33:30','2026-04-08 19:33:37',7,1),(40,18,3,0,'discarded','c vhbjknj',5,NULL,NULL,'2026-04-08 19:33:37','2026-04-08 19:33:43',6,1),(41,18,4,1,'discarded','tfcygvubhjnk',4,NULL,NULL,'2026-04-08 19:33:44','2026-04-08 19:33:50',6,1),(42,1,1,0,'did_not_use','ugbuhj',2,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 20:32:02',8568,0),(43,1,1,0,'use_small_changes','ghbkjnlk',3,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 21:30:18',12064,0),(44,1,1,0,'use_small_changes','ghbkjnlk',3,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 21:30:42',12088,0),(45,1,1,0,'use_substantial_changes','jn',4,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 21:34:17',12303,0),(46,1,1,0,'use_substantial_changes','jn',4,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 21:34:35',12321,0),(47,20,1,0,'use_substantial_changes','yfcvgbjh',4,'verification_intention=overall_recommendation',NULL,'2026-04-08 21:08:23','2026-04-08 21:51:57',2614,0),(48,20,2,1,'use_small_changes','hvgjhbkjnlkm;',3,'verification_intention=policy_rule_or_requirement',NULL,'2026-04-08 21:51:57','2026-04-08 21:52:08',11,1),(49,20,3,0,'use_exact','knlmk;ljnkbhjgvh',5,'verification_intention=overall_recommendation',NULL,'2026-04-08 21:52:08','2026-04-08 21:52:19',11,1),(50,1,1,0,'use_substantial_changes','jvhbkjnlkm',3,NULL,NULL,'2026-04-08 18:09:14','2026-04-08 22:03:53',14079,0);
/*!40000 ALTER TABLE `task_responses` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-09  0:50:19
