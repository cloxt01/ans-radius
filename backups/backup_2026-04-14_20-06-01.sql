/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.10-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: ans_radius
-- ------------------------------------------------------
-- Server version	10.11.10-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(1000) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES
(1,'admin','$2y$10$r0Ugbrb5WUXi023xevKYIeydjKnkXlUm951GsnkbrmK8mI/L.JY4.','',NULL,NULL,NULL,'2026-03-30 07:09:52','2026-04-01 03:42:02');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `available_services`
--

DROP TABLE IF EXISTS `available_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `available_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_key` varchar(80) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `service_type` varchar(50) NOT NULL DEFAULT 'general',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_key` (`service_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `available_services`
--

LOCK TABLES `available_services` WRITE;
/*!40000 ALTER TABLE `available_services` DISABLE KEYS */;
INSERT INTO `available_services` VALUES
(1,'r2','2 Router Mikrotik','router',1,1,'2026-04-02 13:45:34','2026-04-02 13:45:34'),
(2,'r10','10 Router MikroTik','router',1,2,'2026-04-02 13:45:47','2026-04-02 13:45:47'),
(3,'pppoe200','200 PPPoE/DHCP/Member','pppoe',1,3,'2026-04-02 13:46:07','2026-04-02 13:46:07'),
(4,'pppoe500','500 PPPoE/DHCP/Member','pppoe',1,4,'2026-04-02 13:46:28','2026-04-02 13:46:28'),
(5,'voucher5000','5.000 Voucher','voucher',1,5,'2026-04-02 13:46:47','2026-04-02 13:46:47'),
(6,'voucher10000','10000 Voucher','voucher',1,6,'2026-04-02 13:47:02','2026-04-02 13:50:44'),
(7,'r3','3 Router Mikrotik','router',1,7,'2026-04-03 02:48:28','2026-04-03 02:48:28');
/*!40000 ALTER TABLE `available_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cron_logs`
--

DROP TABLE IF EXISTS `cron_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) DEFAULT NULL,
  `status` enum('success','failed','started') DEFAULT NULL,
  `output` text DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `execution_time` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `schedule_id` (`schedule_id`),
  CONSTRAINT `cron_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `cron_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_logs`
--

LOCK TABLES `cron_logs` WRITE;
/*!40000 ALTER TABLE `cron_logs` DISABLE KEYS */;
INSERT INTO `cron_logs` VALUES
(1,1,'success',NULL,NULL,0,'2026-04-11 09:29:40'),
(2,2,'success',NULL,NULL,0,'2026-04-11 09:29:40'),
(3,3,'success',NULL,NULL,0,'2026-04-11 09:29:40'),
(4,NULL,'failed',NULL,NULL,0,'2026-04-11 09:50:24'),
(5,1,'success',NULL,NULL,0,'2026-04-11 17:00:01'),
(6,2,'success',NULL,NULL,0,'2026-04-11 17:00:01');
/*!40000 ALTER TABLE `cron_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cron_schedules`
--

DROP TABLE IF EXISTS `cron_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `task_type` varchar(50) DEFAULT NULL,
  `custom_script_path` varchar(255) DEFAULT NULL,
  `custom_script_args` text DEFAULT NULL,
  `schedule_time` time DEFAULT NULL,
  `schedule_days` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `last_status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_schedules`
--

LOCK TABLES `cron_schedules` WRITE;
/*!40000 ALTER TABLE `cron_schedules` DISABLE KEYS */;
INSERT INTO `cron_schedules` VALUES
(1,'Auto Invoice','auto_invoice',NULL,NULL,'00:00:00','monthly',1,'2026-04-12 01:00:01','2026-04-13 00:00:00','success','2026-03-30 07:09:52','2026-04-11 17:00:01'),
(2,'Auto Isolir','auto_isolir',NULL,NULL,'00:00:00','daily',1,'2026-04-12 01:00:01','2026-04-13 00:00:00','success','2026-03-30 07:09:52','2026-04-11 17:00:01'),
(3,'Payment Reminder','send_reminders',NULL,NULL,'08:00:00','daily',1,'2026-04-11 17:29:40','2026-04-12 08:00:00','success','2026-03-30 07:09:52','2026-04-11 09:29:40'),
(9,'Backup DB','backup_db',NULL,NULL,'00:00:00','daily',1,NULL,'2026-04-12 00:00:00',NULL,'2026-04-11 11:14:32','2026-04-11 11:14:32');
/*!40000 ALTER TABLE `cron_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `pppoe_username` varchar(50) NOT NULL,
  `package_id` int(11) DEFAULT NULL,
  `router_id` int(11) DEFAULT 0,
  `status` enum('active','isolated') DEFAULT 'active',
  `auto_isolate` tinyint(1) NOT NULL DEFAULT 1,
  `isolation_date` int(11) DEFAULT 20,
  `address` text DEFAULT NULL,
  `lat` decimal(11,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `portal_password` varchar(255) DEFAULT NULL,
  `installed_by` int(11) DEFAULT NULL,
  `installation_date` datetime DEFAULT NULL,
  `installation_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `pppoe_username` (`pppoe_username`),
  KEY `package_id` (`package_id`),
  KEY `installed_by` (`installed_by`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`installed_by`) REFERENCES `technician_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES
(2,'Adzka Payment','082101000848','boyka',11,0,'active',1,11,'X47M+C65, Jl. Tonjong-Terate, Pamengkang, Kec. Kramatwatu, Kabupaten Serang, Banten 42616',-6.33008400,106.39498500,'$2y$10$DVbqsf6EQBiribNrFES0deWpZbWHXsf9TWpZSDjIyES8R10CJ6cI6',NULL,NULL,NULL,'2026-04-03 05:14:18','2026-04-11 14:41:27');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faqs`
--

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
INSERT INTO `faqs` VALUES
(1,'Apa itu ANS Radius ?','RL Radius adalah Aplikasi Billing MikroTik PPPoE, DHCP dan Hotspot yang dikembangkan dengan PHP dan Radius.\r\n\r\nBilling RL Radius sangat cocok digunakan sebagai billing mikrotik rtrwnet maupun ISP yang memiliki banyak MikroTik serta lokasi yang berbeda beda, tetapi dengan database terpusat pada satu server, klien anda hanya butuh 1 akun saja agar bisa terhubung ke jaringan yang anda miliki, bebas dari router mana saja, asalkan mikrotik sudah terhubung ke server RL Radius.\r\n\r\nPengembangan Billing RL Radius dimulai pada tanggal 11 April 2020 dan di gunakan untuk server produksi pertama kali pada tanggal 26 Mei 2020\r\n\r\nSaat ini RL Radius sudah teruji stabil, serta semakin banyak digunakan oleh pengusaha Internet dari berbagai kalangan seperti ISP, usaha Hotspot voucher dan RTRWNET se Indonesia',0,1,'2026-04-02 14:43:55','2026-04-02 14:43:55'),
(2,'Apakah jika ada permasalahan akan di bantu?','Iya, semua permasalahan yang berkaitan penggunaan aplikasi RL Radius akan kami layani melalui telpon dan remote desktop.',0,1,'2026-04-02 14:44:23','2026-04-02 14:44:23'),
(3,'Bagaimana caranya berlangganan?','Anda dapat berlangganan melalui situs resmi kami ansradius.id lalu memilih paket yang ada atau daftar pada bagian pojok kanan atas',0,1,'2026-04-02 14:44:38','2026-04-02 14:44:38'),
(4,'Berapa koneksi internet yang ditawarkan?','Berapa koneksi internet yang ditawarkan?\r\nKami menawarkan koneksi internet yang cukup tinggi sekitar 100/MBps',0,1,'2026-04-02 14:44:48','2026-04-02 14:44:48');
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hotspot_sales`
--

DROP TABLE IF EXISTS `hotspot_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hotspot_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `profile` varchar(100) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `selling_price` decimal(15,2) DEFAULT NULL,
  `prefix` varchar(20) DEFAULT NULL,
  `sales_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sales_user_id` (`sales_user_id`),
  CONSTRAINT `hotspot_sales_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `sales_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hotspot_sales`
--

LOCK TABLES `hotspot_sales` WRITE;
/*!40000 ALTER TABLE `hotspot_sales` DISABLE KEYS */;
INSERT INTO `hotspot_sales` VALUES
(1,'ABC-8KxOLA','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(2,'ABC-EiKlHR','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(3,'ABC-t4fhWg','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(4,'ABC-JdzKXl','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(5,'ABC-6P9LOb','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(6,'ABC-cxleBV','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(7,'ABC-TH55Gv','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(8,'ABC-hO1hHf','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(9,'ABC-uEXoXW','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(10,'ABC-iDekYT','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 17:51:34','2026-04-10 16:51:34'),
(11,'ABC-tZDcq0','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(12,'ABC-1GskYw','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(13,'ABC-W5NT5g','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(14,'ABC-QSILe8','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(15,'ABC-s7nzpY','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(16,'ABC-p4giyP','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(17,'ABC-6Mcvgl','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(18,'ABC-DtFeJa','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(19,'ABC-B8aZjF','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(20,'ABC-531GaR','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:53:44','2026-04-10 16:53:44'),
(21,'ABC-LN8Pei','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(22,'ABC-K59oM4','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(23,'ABC-C93kJi','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(24,'ABC-pkPAZR','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(25,'ABC-7WZNBQ','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(26,'ABC-Q80Bd3','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(27,'ABC-Utid1a','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(28,'ABC-Sn78Vi','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(29,'ABC-qezhqh','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(30,'ABC-ZPxCn5','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-10 17:55:52','2026-04-10 16:55:52'),
(31,'ABC-9u7b4L','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(32,'ABC-iQhVaT','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(33,'ABC-ax9cOQ','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(34,'ABC-l6xIur','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(35,'ABC-QNqbyY','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(36,'ABC-E9dmHt','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(37,'ABC-eWhZ6o','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(38,'ABC-A6rnQ2','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(39,'ABC-py4TZm','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(40,'ABC-IIZC6O','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-10 18:06:58','2026-04-10 17:06:58'),
(41,'ABC-OQ6GEX','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(42,'ABC-lh1NNC','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(43,'ABC-tR6JdU','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(44,'ABC-iVF4Aj','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(45,'ABC-6iO12x','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(46,'ABC-LJUQuS','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(47,'ABC-eRnY1Q','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(48,'ABC-ggRJWr','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(49,'ABC-9R4O83','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(50,'ABC-Dp0IvA','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 08:31:40','2026-04-11 07:31:40'),
(51,'ABC-q6cPLb','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:28:41','2026-04-11 11:28:41'),
(52,'ABC-dUxBSQ','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:28:41','2026-04-11 11:28:41'),
(53,'ABC-NgwcdM','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:28:41','2026-04-11 11:28:41'),
(54,'ABC-oPsOMX','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:28:41','2026-04-11 11:28:41'),
(55,'ABC-a0tSRL','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:28:42','2026-04-11 11:28:42'),
(56,'ABC-Tlzmh','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:29:37','2026-04-11 11:29:37'),
(57,'ABC-wrYux','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:29:37','2026-04-11 11:29:37'),
(58,'ABC-K1Z69','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 12:29:37','2026-04-11 11:29:37'),
(59,'ABC-fvtk2M','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(60,'ABC-7gIpHS','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(61,'ABC-xNZXAi','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(62,'ABC-Scyq1s','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(63,'ABC-nrVgS4','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(64,'ABC-DW2St4','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(65,'ABC-PyMqiB','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(66,'ABC-fepZWp','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(67,'ABC-LY9vID','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(68,'ABC-aAeyrg','Voucher 12 Jam',2000.00,3000.00,'ABC-',NULL,'2026-04-11 12:38:01','2026-04-11 11:38:01'),
(69,'ABC-rikppq','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(70,'ABC-j0Urn4','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(71,'ABC-vxj721','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(72,'ABC-WJYugF','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(73,'ABC-k28dU2','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(74,'ABC-aS0ZsO','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(75,'ABC-WIZVoe','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(76,'ABC-e5aVSP','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(77,'ABC-rgZ8s8','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(78,'ABC-OVYFF8','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:30:03','2026-04-11 12:30:03'),
(79,'ABC-bFdXrs','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(80,'ABC-TB8Z3s','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(81,'ABC-PbNMEB','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(82,'ABC-jUlBkq','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(83,'ABC-QXGXI1','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(84,'ABC-8ao0u9','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(85,'ABC-ZMx1Pz','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(86,'ABC-8a4LfH','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(87,'ABC-9woW9u','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(88,'ABC-tpjjmR','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:39:22','2026-04-11 12:39:22'),
(89,'ABC-0jS0FR','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(90,'ABC-N7yLGq','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(91,'ABC-mFB1sN','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(92,'ABC-CB2YIt','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(93,'ABC-iCUjIv','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(94,'ABC-UIbVhT','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(95,'ABC-DHa6aS','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(96,'ABC-4b6XOz','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(97,'ABC-bypmjw','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(98,'ABC-LnhSpg','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:51:48','2026-04-11 12:51:48'),
(99,'ABC-Qcr1H9','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(100,'ABC-tvibZE','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(101,'ABC-8zUMfC','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(102,'ABC-oMs3aa','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(103,'ABC-6jFhUX','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(104,'ABC-MCnY7K','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(105,'ABC-5Pta3s','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(106,'ABC-xt2CdS','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(107,'ABC-vix9RZ','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(108,'ABC-2WiPk4','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 13:54:02','2026-04-11 12:54:02'),
(109,'ABC-FHf1WG','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(110,'ABC-9nJF6k','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(111,'ABC-eEfjyf','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(112,'ABC-r5LMP4','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(113,'ABC-erKf6r','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(114,'ABC-nYkB6d','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(115,'ABC-gTgy0L','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(116,'ABC-yOGTfV','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(117,'ABC-l3vLxB','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(118,'ABC-JqjMWf','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 15:45:32','2026-04-11 14:45:32'),
(119,'ABC-qEONRC','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(120,'ABC-rNIQhf','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(121,'ABC-Hvtsx0','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(122,'ABC-JKfl8w','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(123,'ABC-Udu1QS','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(124,'ABC-8n1LLR','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(125,'ABC-Hjyldi','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(126,'ABC-e6AlU7','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(127,'ABC-P95tKk','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(128,'ABC-BudyBa','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 16:44:48','2026-04-11 15:44:48'),
(129,'ABC-JtTFv2','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(130,'ABC-8yEk01','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(131,'ABC-NYtedP','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(132,'ABC-AON77C','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(133,'ABC-p2fmoz','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(134,'ABC-YtH50V','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(135,'ABC-hAlaIp','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(136,'ABC-RPXFAm','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(137,'ABC-h3CMJk','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(138,'ABC-p8Amt8','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:38:22','2026-04-11 19:38:22'),
(139,'ABC-wYNzRg','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(140,'ABC-yJAvQa','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(141,'ABC-VBlE3M','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(142,'ABC-Tv90Rh','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(143,'ABC-IscAua','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(144,'ABC-TqL907','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(145,'ABC-Yr7shP','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(146,'ABC-MVv7V6','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(147,'ABC-WGrybe','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(148,'ABC-SXXrLm','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:58:17','2026-04-11 19:58:17'),
(149,'ABC-8rbqOY','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(150,'ABC-FoyODl','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(151,'ABC-7vDLPj','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(152,'ABC-iECMkB','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(153,'ABC-1eUnBt','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(154,'ABC-0a3zyX','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(155,'ABC-5Yf2Di','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(156,'ABC-dJcS10','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(157,'ABC-yI9qxP','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(158,'ABC-te3780','Voucher 12 Jam',3000.00,2000.00,'ABC-',NULL,'2026-04-11 20:59:52','2026-04-11 19:59:52'),
(159,'sGrt3S','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(160,'2TPm0J','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(161,'BYahaQ','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(162,'ZBv4Ch','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(163,'fnfP3Q','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(164,'AjP7Mw','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(165,'2e0LNj','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(166,'6IYoqL','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(167,'eE4Inu','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04'),
(168,'lMCRRq','Voucher 12 Jam',3000.00,2000.00,'',NULL,'2026-04-11 21:18:04','2026-04-11 20:18:04');
/*!40000 ALTER TABLE `hotspot_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hotspot_voucher_orders`
--

DROP TABLE IF EXISTS `hotspot_voucher_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hotspot_voucher_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_gateway` varchar(20) NOT NULL DEFAULT 'tripay',
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_link` text DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_payload` longtext DEFAULT NULL,
  `status` enum('pending','paid','failed','expired') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `voucher_username` varchar(100) DEFAULT NULL,
  `voucher_password` varchar(100) DEFAULT NULL,
  `voucher_generated_at` datetime DEFAULT NULL,
  `fulfillment_status` enum('pending','success','failed') DEFAULT 'pending',
  `fulfillment_error` text DEFAULT NULL,
  `whatsapp_status` enum('pending','sent','failed') DEFAULT 'pending',
  `whatsapp_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hotspot_voucher_orders`
--

LOCK TABLES `hotspot_voucher_orders` WRITE;
/*!40000 ALTER TABLE `hotspot_voucher_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `hotspot_voucher_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','paid','cancelled') DEFAULT 'unpaid',
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_ref` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES
(1,'INV000001',2,290000.00,'paid','2026-04-20','2026-04-11 21:27:37','manual_admin',NULL,'2026-04-03 05:14:41','2026-04-11 14:27:37');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `odp_links`
--

DROP TABLE IF EXISTS `odp_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odp_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_odp_id` int(11) NOT NULL,
  `to_odp_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `from_odp_id` (`from_odp_id`),
  KEY `to_odp_id` (`to_odp_id`),
  CONSTRAINT `odp_links_ibfk_1` FOREIGN KEY (`from_odp_id`) REFERENCES `odps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `odp_links_ibfk_2` FOREIGN KEY (`to_odp_id`) REFERENCES `odps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `odp_links`
--

LOCK TABLES `odp_links` WRITE;
/*!40000 ALTER TABLE `odp_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `odp_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `odps`
--

DROP TABLE IF EXISTS `odps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `lat` decimal(11,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `odps`
--

LOCK TABLES `odps` WRITE;
/*!40000 ALTER TABLE `odps` DISABLE KEYS */;
/*!40000 ALTER TABLE `odps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `onu_locations`
--

DROP TABLE IF EXISTS `onu_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `onu_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `lat` decimal(11,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `odp_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `odp_id` (`odp_id`),
  CONSTRAINT `onu_locations_ibfk_1` FOREIGN KEY (`odp_id`) REFERENCES `odps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `onu_locations`
--

LOCK TABLES `onu_locations` WRITE;
/*!40000 ALTER TABLE `onu_locations` DISABLE KEYS */;
INSERT INTO `onu_locations` VALUES
(1,'Adzka Payment','boy',-6.18886100,106.72129200,NULL,'2026-03-31 00:53:57','2026-03-31 01:01:19'),
(2,'Adzka Payment','boyka',-6.33008400,106.39498500,NULL,'2026-04-03 05:14:18','2026-04-11 14:41:27');
/*!40000 ALTER TABLE `onu_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `product_type` varchar(50) NOT NULL DEFAULT 'general',
  `price` decimal(10,2) NOT NULL,
  `profile_normal` varchar(50) NOT NULL,
  `profile_isolir` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `package_services` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES
(11,'ANSCloud Basic','general',100000.00,'default','default','','[\"r2\",\"pppoe200\"]','2026-04-02 14:47:29','2026-04-02 14:50:21'),
(12,'ANSCloud Premium','general',290000.00,'default','default','','[\"r2\",\"pppoe500\",\"voucher10000\"]','2026-04-02 14:48:03','2026-04-02 14:48:26'),
(13,'ANSCloud Ultimate','general',475000.00,'50Mbps','50Mbps','','[\"r10\",\"pppoe500\",\"voucher10000\"]','2026-04-02 14:49:08','2026-04-02 13:49:08'),
(14,'ANS Bussiness','general',798000.00,'default','default','','[\"r10\",\"pppoe500\",\"voucher10000\"]','2026-04-03 03:48:59','2026-04-03 02:48:59');
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `routers`
--

DROP TABLE IF EXISTS `routers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `routers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `host` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `port` int(11) DEFAULT 8728,
  `is_active` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `routers`
--

LOCK TABLES `routers` WRITE;
/*!40000 ALTER TABLE `routers` DISABLE KEYS */;
/*!40000 ALTER TABLE `routers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_profile_prices`
--

DROP TABLE IF EXISTS `sales_profile_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_profile_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_user_id` int(11) NOT NULL,
  `profile_name` varchar(100) NOT NULL,
  `base_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `voucher_length` int(11) DEFAULT 6,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sales_user_id` (`sales_user_id`),
  CONSTRAINT `sales_profile_prices_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `sales_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_profile_prices`
--

LOCK TABLES `sales_profile_prices` WRITE;
/*!40000 ALTER TABLE `sales_profile_prices` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_profile_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_transactions`
--

DROP TABLE IF EXISTS `sales_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `related_username` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sales_user_id` (`sales_user_id`),
  CONSTRAINT `sales_transactions_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `sales_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_transactions`
--

LOCK TABLES `sales_transactions` WRITE;
/*!40000 ALTER TABLE `sales_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_users`
--

DROP TABLE IF EXISTS `sales_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `deposit_balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `voucher_mode` varchar(20) DEFAULT 'mix',
  `voucher_length` int(11) DEFAULT 6,
  `voucher_type` varchar(20) DEFAULT 'upp',
  `bill_discount` decimal(15,2) DEFAULT 2000.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_users`
--

LOCK TABLES `sales_users` WRITE;
/*!40000 ALTER TABLE `sales_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(1,'app_name','ANS Radius','2026-03-30 07:09:52','2026-03-30 08:46:01'),
(2,'app_version','2.0.0','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(3,'currency','IDR','2026-03-30 07:09:52','2026-03-30 08:46:13'),
(4,'CURRENCY_SYMBOL','Rp','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(5,'timezone','Asia/Jakarta','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(6,'invoice_prefix','INV','2026-03-30 07:09:52','2026-03-31 05:14:25'),
(7,'invoice_start','1','2026-03-30 07:09:52','2026-03-30 13:18:41'),
(8,'PUBLIC_VOUCHER_PREFIX','VCH-','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(9,'PUBLIC_VOUCHER_LENGTH','6','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(10,'BACKUP_RETENTION_DAYS','3','2026-03-30 07:36:33','2026-03-30 07:36:33'),
(11,'MIKROTIK_HOST','10.7.0.2','2026-03-31 04:57:21','2026-04-11 20:49:22'),
(12,'MIKROTIK_USER','ADZ','2026-03-31 04:57:21','2026-04-09 12:02:11'),
(13,'MIKROTIK_PASS','Adzka@172','2026-03-31 04:57:21','2026-04-09 12:02:11'),
(14,'MIKROTIK_PORT','8728','2026-03-31 04:57:21','2026-04-11 20:39:43'),
(15,'GENIEACS_URL','','2026-03-31 04:57:27','2026-04-01 11:53:54'),
(16,'GENIEACS_USERNAME','','2026-03-31 04:57:27','2026-04-01 11:53:54'),
(17,'GENIEACS_PASSWORD','','2026-03-31 04:57:27','2026-04-01 11:53:54'),
(18,'TELEGRAM_BOT_TOKEN','123456','2026-03-31 04:57:31','2026-03-31 04:57:31'),
(19,'TELEGRAM_ADMIN_CHAT_ID','admin','2026-03-31 04:57:31','2026-03-31 04:57:31'),
(20,'DEFAULT_WHATSAPP_GATEWAY','mpwa','2026-03-31 05:32:14','2026-04-03 03:22:29'),
(21,'FONNTE_API_TOKEN','','2026-03-31 05:32:14','2026-03-31 05:32:14'),
(22,'WABLAS_API_TOKEN','','2026-03-31 05:32:14','2026-03-31 05:32:14'),
(23,'MPWA_API_KEY','yOfCtXolJ9qUIniymaZ75fCkf6T6eO','2026-03-31 05:32:14','2026-04-03 03:22:29'),
(24,'MPWA_SENDER','628891500003','2026-03-31 05:32:14','2026-04-03 03:22:29'),
(25,'MPWA_API_URL','https://wabeta.m-pedia.my.id/send-message','2026-03-31 05:32:14','2026-04-03 03:22:29'),
(26,'WHATSAPP_ADMIN_NUMBER','6285863409811','2026-03-31 05:32:14','2026-04-03 03:22:29'),
(27,'TRIPAY_API_KEY','admin','2026-03-31 05:32:37','2026-03-31 05:32:37'),
(28,'TRIPAY_PRIVATE_KEY','123456','2026-03-31 05:32:37','2026-03-31 05:32:37'),
(29,'TRIPAY_MERCHANT_CODE','','2026-03-31 05:32:37','2026-03-31 05:32:37'),
(30,'TRIPAY_MODE','','2026-03-31 05:32:37','2026-04-03 04:18:50'),
(31,'MIDTRANS_API_KEY','SB-Mid-server-ej09ndXz8fs9589JQyYBTalH','2026-03-31 05:32:37','2026-04-03 03:41:06'),
(32,'MIDTRANS_MERCHANT_CODE','G268995147','2026-03-31 05:32:37','2026-04-03 03:41:06'),
(33,'DEFAULT_PAYMENT_GATEWAY','midtrans','2026-03-31 05:32:37','2026-04-03 03:41:06'),
(34,'CRON_TOKEN','4524855864dc9ad3181806d6fd911806','2026-03-31 05:32:43','2026-03-31 05:32:43'),
(35,'AVAILABLE_SERVICES_SEEDED','1','2026-04-01 14:40:08','2026-04-01 14:40:08'),
(36,'voucher_template','default.php','2026-04-09 15:10:29','2026-04-09 16:50:54');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES
(1,'hero_title','Internet Cepat Tanpa Batas','2026-03-30 07:09:52','2026-03-30 10:48:31'),
(2,'theme_color','ocean','2026-03-30 07:09:52','2026-03-31 09:37:26'),
(3,'hero_description','Nikmati koneksi internet fiber optic super cepat, stabil, dan unlimited untuk kebutuhan rumah maupun bisnis Anda. Gabung sekarang!','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(4,'contact_phone','+62 812-3456-7890','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(5,'contact_email','info@ansradius.id','2026-03-30 07:09:52','2026-03-31 08:04:31'),
(6,'contact_address','Serang, Indonesia','2026-03-30 07:09:52','2026-03-31 08:04:31'),
(7,'footer_about','Penyedia layanan internet terpercaya dengan jaringan fiber optic berkualitas untuk menunjang aktivitas digital Anda.','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(8,'feature_1_title','Kecepatan Tinggi','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(9,'feature_1_desc','Koneksi fiber optic dengan kecepatan simetris upload dan download.','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(10,'feature_2_title','Unlimited Quota','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(11,'feature_2_desc','Akses internet sepuasnya tanpa batasan kuota (FUP).','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(12,'feature_3_title','Support 24/7','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(13,'feature_3_desc','Tim teknis kami siap membantu Anda kapanpun jika terjadi gangguan.','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(14,'social_facebook','#','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(15,'social_instagram','#','2026-03-30 07:09:52','2026-03-30 10:46:55'),
(16,'social_twitter','#','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(17,'social_youtube','#','2026-03-30 07:09:52','2026-03-30 07:09:52'),
(18,'landing_template','modern_ultra','2026-03-30 10:43:13','2026-03-31 09:37:26');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `technician_users`
--

DROP TABLE IF EXISTS `technician_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `technician_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technician_users`
--

LOCK TABLES `technician_users` WRITE;
/*!40000 ALTER TABLE `technician_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `technician_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trouble_tickets`
--

DROP TABLE IF EXISTS `trouble_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trouble_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','resolved') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `photo_proof` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `technician_id` (`technician_id`),
  CONSTRAINT `trouble_tickets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trouble_tickets_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technician_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trouble_tickets`
--

LOCK TABLES `trouble_tickets` WRITE;
/*!40000 ALTER TABLE `trouble_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `trouble_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webhook_logs`
--

DROP TABLE IF EXISTS `webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(50) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `status_code` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webhook_logs`
--

LOCK TABLES `webhook_logs` WRITE;
/*!40000 ALTER TABLE `webhook_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `webhook_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-14 19:06:01
