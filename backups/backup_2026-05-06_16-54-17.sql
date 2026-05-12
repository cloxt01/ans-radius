-- MySQL dump 10.13  Distrib 5.7.43, for Linux (x86_64)
--
-- Host: localhost    Database: ans_radius
-- ------------------------------------------------------
-- Server version	5.7.43-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
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
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$bVZVUWMYePiWt2/nbaBp3u3sFU4IfSQxo8tWL5mXRonv4MQRY3cN6','',NULL,NULL,NULL,'2026-05-04 12:04:24','2026-05-04 12:04:24');
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
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_key` (`service_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `available_services`
--

LOCK TABLES `available_services` WRITE;
/*!40000 ALTER TABLE `available_services` DISABLE KEYS */;
INSERT INTO `available_services` VALUES (1,'dsa','dsda','general',1,1,'2026-05-04 12:14:51','2026-05-04 12:14:51');
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
  `output` text,
  `error_message` text,
  `execution_time` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `schedule_id` (`schedule_id`),
  CONSTRAINT `cron_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `cron_schedules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_logs`
--

LOCK TABLES `cron_logs` WRITE;
/*!40000 ALTER TABLE `cron_logs` DISABLE KEYS */;
INSERT INTO `cron_logs` VALUES (7,2,'success',NULL,NULL,0.08,'2026-05-05 09:02:56'),(8,2,'success',NULL,NULL,0.02,'2026-05-05 09:03:57');
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
  `schedule_time` time DEFAULT NULL,
  `schedule_days` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_run` datetime DEFAULT NULL,
  `next_run` datetime DEFAULT NULL,
  `last_status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cron_schedules`
--

LOCK TABLES `cron_schedules` WRITE;
/*!40000 ALTER TABLE `cron_schedules` DISABLE KEYS */;
INSERT INTO `cron_schedules` VALUES (1,'Auto Invoice','auto_invoice','00:00:00','monthly',1,'2026-05-05 00:55:49','2026-05-06 00:00:00','success','2026-05-04 12:04:24','2026-05-05 09:02:13'),(2,'Auto Isolir','auto_isolir','00:00:00','daily',1,'2026-05-05 11:03:57','2026-05-06 00:00:00','success','2026-05-04 12:04:24','2026-05-05 09:03:57'),(3,'Payment Reminder','send_reminders','08:00:00','daily',1,'2026-05-05 10:55:49','2026-05-06 08:00:00','success','2026-05-04 12:04:24','2026-05-05 08:55:49'),(7,'Auto Invoice','auto_invoice','00:00:00','monthly',1,NULL,NULL,NULL,'2026-05-06 04:33:07','2026-05-06 04:33:07'),(8,'Auto Isolir','auto_isolir','00:00:00','daily',1,NULL,NULL,NULL,'2026-05-06 04:33:07','2026-05-06 04:33:07'),(9,'Payment Reminder','send_reminders','08:00:00','daily',1,NULL,NULL,NULL,'2026-05-06 04:33:07','2026-05-06 04:33:07');
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
  `router_id` int(11) DEFAULT '0',
  `status` enum('active','isolated') DEFAULT 'active',
  `auto_isolate` tinyint(1) NOT NULL DEFAULT '1',
  `isolation_date` int(11) DEFAULT '20',
  `address` text,
  `lat` decimal(11,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `portal_password` varchar(255) DEFAULT NULL,
  `installed_by` int(11) DEFAULT NULL,
  `installation_date` datetime DEFAULT NULL,
  `installation_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pppoe_username` (`pppoe_username`),
  KEY `package_id` (`package_id`),
  KEY `installed_by` (`installed_by`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`installed_by`) REFERENCES `technician_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (2,'Admin','083110542910','admin',2,0,'active',1,20,'Jl.Djohar Atmaja, Maja, Maja, Rt04/01, No. 40,Lebak, Banten 42381',-6.17361500,106.59683700,'$2y$10$nY6w17FPKn5O7chALMFGtOwPF2hHhZTyeLnLZtAJLAxQTzCJoBlzu',NULL,NULL,NULL,'2026-05-05 03:59:55','2026-05-05 08:59:55');
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
  `sort_order` int(11) DEFAULT '0',
  `is_active` tinyint(4) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faqs`
--

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sales_user_id` (`sales_user_id`),
  CONSTRAINT `hotspot_sales_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `sales_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hotspot_sales`
--

LOCK TABLES `hotspot_sales` WRITE;
/*!40000 ALTER TABLE `hotspot_sales` DISABLE KEYS */;
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
  `payment_link` text,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_payload` longtext,
  `status` enum('pending','paid','failed','expired') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `voucher_username` varchar(100) DEFAULT NULL,
  `voucher_password` varchar(100) DEFAULT NULL,
  `voucher_generated_at` datetime DEFAULT NULL,
  `fulfillment_status` enum('pending','success','failed') DEFAULT 'pending',
  `fulfillment_error` text,
  `whatsapp_status` enum('pending','sent','failed') DEFAULT 'pending',
  `whatsapp_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (2,'INV000001',2,125000.00,'unpaid','2026-05-20',NULL,NULL,NULL,'2026-05-05 04:03:24','2026-05-05 09:03:24');
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `from_odp_id` (`from_odp_id`),
  KEY `to_odp_id` (`to_odp_id`),
  CONSTRAINT `odp_links_ibfk_1` FOREIGN KEY (`from_odp_id`) REFERENCES `odps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `odp_links_ibfk_2` FOREIGN KEY (`to_odp_id`) REFERENCES `odps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `odp_id` (`odp_id`),
  CONSTRAINT `onu_locations_ibfk_1` FOREIGN KEY (`odp_id`) REFERENCES `odps` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `onu_locations`
--

LOCK TABLES `onu_locations` WRITE;
/*!40000 ALTER TABLE `onu_locations` DISABLE KEYS */;
INSERT INTO `onu_locations` VALUES (1,'Cuki','cuki00',NULL,NULL,NULL,'2026-05-04 07:15:12','2026-05-05 02:54:56'),(2,'Admin','admin',-6.17361500,106.59683700,NULL,'2026-05-05 03:59:55','2026-05-05 03:59:55');
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
  `description` text,
  `package_services` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (2,'Paket Stater','general',125000.00,'PAKET STAR LEGEND','ANS - ISOLIR','',NULL,'2026-05-05 02:54:39','2026-05-05 03:47:58');
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
  `port` int(11) DEFAULT '8728',
  `is_active` tinyint(1) DEFAULT '0',
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
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
  `base_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `voucher_length` int(11) DEFAULT '6',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sales_user_id` (`sales_user_id`),
  CONSTRAINT `sales_profile_prices_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `sales_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `description` text,
  `related_username` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sales_user_id` (`sales_user_id`),
  CONSTRAINT `sales_transactions_ibfk_1` FOREIGN KEY (`sales_user_id`) REFERENCES `sales_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `deposit_balance` decimal(15,2) DEFAULT '0.00',
  `status` enum('active','inactive') DEFAULT 'active',
  `voucher_mode` varchar(20) DEFAULT 'mix',
  `voucher_length` int(11) DEFAULT '6',
  `voucher_type` varchar(20) DEFAULT 'upp',
  `bill_discount` decimal(15,2) DEFAULT '2000.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_users`
--

LOCK TABLES `sales_users` WRITE;
/*!40000 ALTER TABLE `sales_users` DISABLE KEYS */;
INSERT INTO `sales_users` VALUES (1,'salam','082101000848','salam12','$2y$10$ikJ84gfMDKUypCX58aqCdOXXYDwzmg7zKTaCBgSiQwaMfh3Oxlil2',0.00,'active','mix',6,'upp',0.00,'2026-05-04 12:37:51','2026-05-04 12:37:51');
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
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'app_name','ANS Radius','2026-05-04 12:04:24','2026-05-04 12:38:52'),(2,'app_version','2.0.0','2026-05-04 12:04:24','2026-05-04 12:04:24'),(3,'currency','IDR','2026-05-04 12:04:24','2026-05-04 12:04:24'),(4,'CURRENCY_SYMBOL','Rp','2026-05-04 12:04:24','2026-05-04 12:04:24'),(5,'timezone','Asia/Jakarta','2026-05-04 12:04:24','2026-05-04 12:04:24'),(6,'voucher_template','default.php','2026-05-04 12:04:24','2026-05-04 12:04:24'),(7,'invoice_prefix','INV','2026-05-04 12:04:24','2026-05-04 12:04:24'),(8,'invoice_start','1','2026-05-04 12:04:24','2026-05-04 12:04:24'),(9,'PUBLIC_VOUCHER_PREFIX','VCH-','2026-05-04 12:04:24','2026-05-04 12:04:24'),(10,'PUBLIC_VOUCHER_LENGTH','6','2026-05-04 12:04:24','2026-05-04 12:04:24'),(11,'AVAILABLE_SERVICES_SEEDED','1','2026-05-04 12:06:58','2026-05-04 12:06:58'),(12,'MIKROTIK_HOST','10.7.0.2','2026-05-04 12:12:51','2026-05-06 08:52:11'),(13,'MIKROTIK_USER','ADZ','2026-05-04 12:12:51','2026-05-04 12:12:51'),(14,'MIKROTIK_PASS','Adzka@172','2026-05-04 12:12:51','2026-05-04 12:12:51'),(15,'MIKROTIK_PORT','8728','2026-05-04 12:12:51','2026-05-06 08:52:01'),(16,'TRIPAY_API_KEY','','2026-05-04 12:17:24','2026-05-04 12:17:24'),(17,'TRIPAY_PRIVATE_KEY','','2026-05-04 12:17:24','2026-05-04 12:17:24'),(18,'TRIPAY_MERCHANT_CODE','','2026-05-04 12:17:24','2026-05-04 12:17:24'),(19,'TRIPAY_MODE','','2026-05-04 12:17:24','2026-05-04 12:17:24'),(20,'MIDTRANS_API_KEY','Mid-server-dSsGJhHdYrSDwI7LHuclvIvj','2026-05-04 12:17:24','2026-05-04 12:19:35'),(21,'MIDTRANS_MERCHANT_CODE','G268995147','2026-05-04 12:17:24','2026-05-04 12:17:24'),(22,'DEFAULT_PAYMENT_GATEWAY','midtrans','2026-05-04 12:17:24','2026-05-04 12:17:54'),(33,'CRON_TOKEN','efcd06c38fb2040d5056d557bf3d57ee','2026-05-05 08:55:22','2026-05-05 08:55:22');
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
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'hero_title','Internet Cepat <br>Tanpa Batas','2026-05-04 12:04:24','2026-05-04 12:04:24'),(2,'theme_color','neon','2026-05-04 12:04:24','2026-05-04 12:04:24'),(3,'hero_description','Nikmati koneksi internet fiber optic super cepat, stabil, dan unlimited untuk kebutuhan rumah maupun bisnis Anda. Gabung sekarang!','2026-05-04 12:04:24','2026-05-04 12:04:24'),(4,'contact_phone','+62 812-3456-7890','2026-05-04 12:04:24','2026-05-04 12:04:24'),(5,'contact_email','info@gembok.net','2026-05-04 12:04:24','2026-05-04 12:04:24'),(6,'contact_address','Jakarta, Indonesia','2026-05-04 12:04:24','2026-05-04 12:04:24'),(7,'footer_about','Penyedia layanan internet terpercaya dengan jaringan fiber optic berkualitas untuk menunjang aktivitas digital Anda.','2026-05-04 12:04:24','2026-05-04 12:04:24'),(8,'feature_1_title','Kecepatan Tinggi','2026-05-04 12:04:24','2026-05-04 12:04:24'),(9,'feature_1_desc','Koneksi fiber optic dengan kecepatan simetris upload dan download.','2026-05-04 12:04:24','2026-05-04 12:04:24'),(10,'feature_2_title','Unlimited Quota','2026-05-04 12:04:24','2026-05-04 12:04:24'),(11,'feature_2_desc','Akses internet sepuasnya tanpa batasan kuota (FUP).','2026-05-04 12:04:24','2026-05-04 12:04:24'),(12,'feature_3_title','Support 24/7','2026-05-04 12:04:24','2026-05-04 12:04:24'),(13,'feature_3_desc','Tim teknis kami siap membantu Anda kapanpun jika terjadi gangguan.','2026-05-04 12:04:24','2026-05-04 12:04:24'),(14,'social_facebook','#','2026-05-04 12:04:24','2026-05-04 12:04:24'),(15,'social_instagram','#','2026-05-04 12:04:24','2026-05-04 12:04:24'),(16,'social_twitter','#','2026-05-04 12:04:24','2026-05-04 12:04:24'),(17,'social_youtube','#','2026-05-04 12:04:24','2026-05-04 12:04:24');
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `description` text,
  `status` enum('pending','in_progress','resolved') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `notes` text,
  `resolved_at` datetime DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `photo_proof` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `technician_id` (`technician_id`),
  CONSTRAINT `trouble_tickets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `trouble_tickets_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technician_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status_code` int(11) DEFAULT NULL,
  `response` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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

-- Dump completed on 2026-05-06  8:54:17
