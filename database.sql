-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: church_finance
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Current Database: `church_finance`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `church_finance` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `church_finance`;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('no_activity','performance_drop','approval_delay','high_expense','payment_gap','low_stock','target_achieved','target_missed') NOT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `department` enum('lmat','nibret','both') DEFAULT 'both',
  `is_read` tinyint(1) DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_department_read` (`department`,`is_read`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alerts`
--

LOCK TABLES `alerts` WRITE;
/*!40000 ALTER TABLE `alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approval_history`
--

DROP TABLE IF EXISTS `approval_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `approval_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `action` enum('APPROVE','REJECT') NOT NULL,
  `role` enum('Collector','Deputy','Secretary') NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `approval_history_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approval_history`
--

LOCK TABLES `approval_history` WRITE;
/*!40000 ALTER TABLE `approval_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `approval_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT','APPROVE','REJECT') NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'INSERT','sales',3,NULL,'{\"total\":250,\"receipt\":\"SALE-20260419-2073\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-19 13:39:22');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contributions`
--

DROP TABLE IF EXISTS `contributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `due_date` date DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('paid','late','partial') DEFAULT 'paid',
  `receipt_number` varchar(50) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_month` (`student_id`,`year`,`month`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `contributions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contributions_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contributions`
--

LOCK TABLES `contributions` WRITE;
/*!40000 ALTER TABLE `contributions` DISABLE KEYS */;
INSERT INTO `contributions` VALUES (1,2,2026,1,'2026-01-10','2026-04-05',50.00,'late','CONT-20260405-5575',4,'2026-04-05 13:16:28'),(2,3,2026,2,'2026-02-10','2026-04-05',100.00,'late','CONT-20260405-7858',4,'2026-04-05 13:16:32');
/*!40000 ALTER TABLE `contributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_snapshots`
--

DROP TABLE IF EXISTS `daily_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_date` date NOT NULL,
  `total_income` decimal(12,2) DEFAULT 0.00,
  `total_expense` decimal(12,2) DEFAULT 0.00,
  `total_contributions` decimal(12,2) DEFAULT 0.00,
  `lmat_sales` decimal(12,2) DEFAULT 0.00,
  `manual_income` decimal(12,2) DEFAULT 0.00,
  `withdrawals` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(12,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_snapshot_date` (`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_snapshots`
--

LOCK TABLES `daily_snapshots` WRITE;
/*!40000 ALTER TABLE `daily_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT 0.00,
  `status` enum('planned','active','completed','cancelled') DEFAULT 'planned',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES (1,'ßï¿ßëáßîï ßîëßëúßèñ','ßï¿ßëáßîï ßïêßëàßë╡ ßï¿ßêÜßè½ßêäßï╡ ßë╡ßêìßëà ßîëßëúßèñ','2026-05-19','2026-05-24',0.00,'planned',1,'2026-04-19 13:27:23'),(2,'ßï¿ßìïßê▓ßè½ ßëáßïôßêì','ßï¿ßìïßê▓ßè½ ßëáßïôßêì ßèáßè¿ßëúßëáßê¡','2026-04-29','2026-05-01',0.00,'planned',1,'2026-04-19 13:27:23');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_approvals`
--

DROP TABLE IF EXISTS `expense_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `approver_role` enum('Collector','Deputy','Secretary') NOT NULL,
  `weight_value` int(11) NOT NULL COMMENT '50, 30, or 20',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_expense_role` (`expense_id`,`approver_role`),
  KEY `approver_id` (`approver_id`),
  CONSTRAINT `expense_approvals_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_approvals`
--

LOCK TABLES `expense_approvals` WRITE;
/*!40000 ALTER TABLE `expense_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `expense_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('PENDING','COLLECTOR_APPROVED','DEPUTY_APPROVED','SECRETARY_APPROVED','FULLY_APPROVED','REJECTED') DEFAULT 'PENDING',
  `approval_weight_total` int(11) DEFAULT 0,
  `collector_approved` tinyint(4) DEFAULT 0,
  `deputy_approved` tinyint(4) DEFAULT 0,
  `secretary_approved` tinyint(4) DEFAULT 0,
  `collector_id` int(11) DEFAULT NULL,
  `deputy_id` int(11) DEFAULT NULL,
  `secretary_id` int(11) DEFAULT NULL,
  `collector_approved_at` datetime DEFAULT NULL,
  `deputy_approved_at` datetime DEFAULT NULL,
  `secretary_approved_at` datetime DEFAULT NULL,
  `fully_approved_at` datetime DEFAULT NULL,
  `approval_deadline` datetime DEFAULT NULL,
  `delay_notified` tinyint(1) DEFAULT 0,
  `rejected_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `event_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requested_by` (`requested_by`),
  KEY `collector_id` (`collector_id`),
  KEY `deputy_id` (`deputy_id`),
  KEY `secretary_id` (`secretary_id`),
  KEY `rejected_by` (`rejected_by`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`deputy_id`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`secretary_id`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_ibfk_5` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_ibfk_6` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `income`
--

DROP TABLE IF EXISTS `income`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `income` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source` enum('sale','contribution','manual','expense') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_date` date NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `income_ibfk_1` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `income_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `income`
--

LOCK TABLES `income` WRITE;
/*!40000 ALTER TABLE `income` DISABLE KEYS */;
INSERT INTO `income` VALUES (1,'sale',1,250.00,'ßê╜ßï½ßî¡: ßêÿßê╡ßëÇßêì (Cross) ßë╡ßêìßëà',2,'2026-04-05',NULL,'2026-04-05 12:39:37'),(2,'sale',2,85.00,'ßê╜ßï½ßî¡: ßêÿßê╡ßëÇßêì (Cross) ßë╡ßèòßê╜',2,'2026-04-05',NULL,'2026-04-05 12:40:05'),(3,'contribution',1,50.00,'ßïêßê¡ßêâßïè ßêÿßïïßî« - January 2026',4,'2026-04-05',NULL,'2026-04-05 13:16:28'),(4,'contribution',2,100.00,'ßïêßê¡ßêâßïè ßêÿßïïßî« - February 2026',4,'2026-04-05',NULL,'2026-04-05 13:16:32'),(5,'sale',3,250.00,'ßê╜ßï½ßî¡: ßêÿßê╡ßëÇßêì (Cross) ßë╡ßêìßëà',1,'2026-04-19',NULL,'2026-04-19 13:39:22');
/*!40000 ALTER TABLE `income` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(20) DEFAULT 'pcs',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'ßîºßìì (Candle)',25.00,200.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(2,'ßèÉßîáßêï (Single)',15.00,150.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(3,'ßêÿßê╡ßëÇßêì (Cross) ßë╡ßèòßê╜',85.00,49.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(4,'ßêÿßê╡ßëÇßêì (Cross) ßë╡ßêìßëà',250.00,35.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(5,'ßêÿßìàßêÉßìì ßëàßï▒ßê╡',350.00,40.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(6,'ßèÑßèòßëüßêïßêì (Incense)',30.00,300.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(7,'ßè⌐ßëúßèòßï½ (Kubanya)',200.00,25.00,'pcs',NULL,NULL,'2026-04-05 12:09:28'),(8,'ßê¢ßê¡ (Honey)',120.00,80.00,'kg',NULL,NULL,'2026-04-05 12:09:28');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Lmat_Admin','ßêìßê¢ßë╡ ßèáßê╡ßë░ßï│ßï│ßê¬ - Can add products and view reports'),(2,'Seller','ßê╗ßî¡ - Can only record sales'),(3,'Collector','ßê░ßëÑßê│ßëó - First expense approver'),(4,'Deputy','ßê¥ßè¡ßë╡ßêì ßê░ßëÑßê│ßëó - Second expense approver'),(5,'Secretary','ßìÇßêÇßìè - Third expense approver'),(6,'Nibret_Admin','ßèòßëÑßê¿ßë╡ ßèáßê╡ßë░ßï│ßï│ßê¬ - Full finance access');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `payment_method` enum('cash','telebirr','cbe','abyssinia') NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `receipt_number` varchar(50) DEFAULT NULL,
  `sale_date` date NOT NULL,
  `cycle_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `seller_id` (`seller_id`),
  KEY `cycle_id` (`cycle_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`),
  CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`cycle_id`) REFERENCES `sales_cycles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,4,1.00,250.00,250.00,2,'abyssinia',0.00,0.00,'SALE-20260405-5346','2026-04-05',NULL,'2026-04-05 12:39:37'),(2,3,1.00,85.00,85.00,2,'cbe',0.00,0.00,'SALE-20260405-6246','2026-04-05',NULL,'2026-04-05 12:40:05'),(3,4,1.00,250.00,250.00,1,'cash',0.00,0.00,'SALE-20260419-2073','2026-04-19',2,'2026-04-19 13:39:22');
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_cycles`
--

DROP TABLE IF EXISTS `sales_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cycle_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_cycles`
--

LOCK TABLES `sales_cycles` WRITE;
/*!40000 ALTER TABLE `sales_cycles` DISABLE KEYS */;
INSERT INTO `sales_cycles` VALUES (1,'ßïæßï░ßë╡ 1','2026-04-14','2026-04-16','closed',0.00,'2026-04-19 13:27:23'),(2,'ßïæßï░ßë╡ 2','2026-04-17','2026-04-19','active',250.00,'2026-04-19 13:27:23');
/*!40000 ALTER TABLE `sales_cycles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seller_activity_log`
--

DROP TABLE IF EXISTS `seller_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `activity_date` date NOT NULL,
  `last_activity` datetime DEFAULT NULL,
  `sales_count` int(11) DEFAULT 0,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `performance_score` int(11) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_seller_date` (`seller_id`,`activity_date`),
  CONSTRAINT `seller_activity_log_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seller_activity_log`
--

LOCK TABLES `seller_activity_log` WRITE;
/*!40000 ALTER TABLE `seller_activity_log` DISABLE KEYS */;
INSERT INTO `seller_activity_log` VALUES (1,1,'2026-04-19','2026-04-19 13:46:02',1,250.00,0,'2026-04-19 13:46:02'),(10,2,'2026-04-19','2026-04-19 13:46:18',0,0.00,0,'2026-04-19 13:46:18');
/*!40000 ALTER TABLE `seller_activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seller_targets`
--

DROP TABLE IF EXISTS `seller_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seller_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `target_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `period_type` enum('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `seller_id` (`seller_id`),
  CONSTRAINT `seller_targets_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seller_targets`
--

LOCK TABLES `seller_targets` WRITE;
/*!40000 ALTER TABLE `seller_targets` DISABLE KEYS */;
INSERT INTO `seller_targets` VALUES (1,2,500.00,'daily','2026-04-19','2026-04-19',NULL,'2026-04-19 13:27:22'),(2,2,2500.00,'weekly','2026-04-13','2026-04-19',NULL,'2026-04-19 13:27:22');
/*!40000 ALTER TABLE `seller_targets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_payment_scores`
--

DROP TABLE IF EXISTS `student_payment_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_payment_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `consistency_score` int(11) DEFAULT 100 COMMENT '0-100',
  `total_months_paid` int(11) DEFAULT 0,
  `total_months_expected` int(11) DEFAULT 0,
  `on_time_payments` int(11) DEFAULT 0,
  `late_payments` int(11) DEFAULT 0,
  `missed_payments` int(11) DEFAULT 0,
  `last_payment_date` date DEFAULT NULL,
  `longest_gap_days` int(11) DEFAULT 0,
  `current_streak` int(11) DEFAULT 0,
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_score` (`student_id`),
  CONSTRAINT `student_payment_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_payment_scores`
--

LOCK TABLES `student_payment_scores` WRITE;
/*!40000 ALTER TABLE `student_payment_scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_payment_scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `grade` varchar(20) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'ßèáßëáßëá ßëáßëÇßêê','0911111111','Grade 10',1,'2026-04-05 12:09:30'),(2,'ßëÑßê¡ßë▒ßè½ßèò ßèáßêêßêÖ','0922222222','Grade 11',1,'2026-04-05 12:09:30'),(3,'ßë╗ßêï ßë░ßê╡ßìïßï¼','0933333333','Grade 9',1,'2026-04-05 12:09:30'),(4,'ßï│ßïèßë╡ ßêÿßè«ßèòßèò','0944444444','Grade 12',1,'2026-04-05 12:09:30');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_undo_log`
--

DROP TABLE IF EXISTS `transaction_undo_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_undo_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_transaction_type` enum('sale','expense','income','contribution','withdrawal') NOT NULL,
  `original_id` int(11) NOT NULL,
  `undo_reason` text NOT NULL,
  `original_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`original_data`)),
  `undone_by` int(11) NOT NULL,
  `undone_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `undone_by` (`undone_by`),
  CONSTRAINT `transaction_undo_log_ibfk_1` FOREIGN KEY (`undone_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_undo_log`
--

LOCK TABLES `transaction_undo_log` WRITE;
/*!40000 ALTER TABLE `transaction_undo_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaction_undo_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transparency_settings`
--

DROP TABLE IF EXISTS `transparency_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transparency_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `public_view_enabled` tinyint(1) DEFAULT 1,
  `show_income_total` tinyint(1) DEFAULT 1,
  `show_expense_total` tinyint(1) DEFAULT 1,
  `show_balance` tinyint(1) DEFAULT 1,
  `show_monthly_comparison` tinyint(1) DEFAULT 1,
  `access_code` varchar(50) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `transparency_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transparency_settings`
--

LOCK TABLES `transparency_settings` WRITE;
/*!40000 ALTER TABLE `transparency_settings` DISABLE KEYS */;
INSERT INTO `transparency_settings` VALUES (1,1,1,1,1,1,NULL,NULL,'2026-04-19 13:27:21');
/*!40000 ALTER TABLE `transparency_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `department` enum('lmat','nibret') NOT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ßèáßêêßê¢ßï¿ßêü ßë░ßê╡ßìïßï¼','alemayehu@church.com','$2y$10$FplRrz7qYw5Nsewa4xG3v.TFe45FRR.DbajnmSaFUruQ1HCCfAz2i',1,'lmat',1,'2026-04-05 12:09:28'),(2,'ßêäßêêßèò ßèáßëáßëá','helen@church.com','$2y$10$FplRrz7qYw5Nsewa4xG3v.TFe45FRR.DbajnmSaFUruQ1HCCfAz2i',2,'lmat',1,'2026-04-05 12:09:28'),(3,'ßêÿßê╡ßììßèò ßëáßëÇßêê','mesfin@church.com','$2y$10$FplRrz7qYw5Nsewa4xG3v.TFe45FRR.DbajnmSaFUruQ1HCCfAz2i',3,'nibret',1,'2026-04-05 12:09:28'),(4,'ßê╡ßêêßê║ ßè¿ßëáßï░','abdu@church.com','$2y$10$FplRrz7qYw5Nsewa4xG3v.TFe45FRR.DbajnmSaFUruQ1HCCfAz2i',4,'nibret',1,'2026-04-05 12:09:28'),(5,'ßê¢ßê₧ ßïêßèòßï╡ßêÖ','mamo@church.com','$2y$10$FplRrz7qYw5Nsewa4xG3v.TFe45FRR.DbajnmSaFUruQ1HCCfAz2i',5,'nibret',1,'2026-04-05 12:09:28'),(6,'ßë░ßê╡ßìïßï¼ ßèáßêêßêÖ','tesfaye@church.com','$2y$10$FplRrz7qYw5Nsewa4xG3v.TFe45FRR.DbajnmSaFUruQ1HCCfAz2i',6,'nibret',1,'2026-04-05 12:09:28');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdrawals`
--

DROP TABLE IF EXISTS `withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `withdrawn_by` int(11) NOT NULL,
  `withdrawn_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `withdrawn_by` (`withdrawn_by`),
  CONSTRAINT `withdrawals_ibfk_1` FOREIGN KEY (`withdrawn_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdrawals`
--

LOCK TABLES `withdrawals` WRITE;
/*!40000 ALTER TABLE `withdrawals` DISABLE KEYS */;
/*!40000 ALTER TABLE `withdrawals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'church_finance'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-25  2:06:15
