-- MySQL dump 10.13  Distrib 8.0.44, for Linux (x86_64)
--
-- Host: localhost    Database: nlabims
-- ------------------------------------------------------
-- Server version	8.0.44

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

--
-- Table structure for table `assessment_types`
--

USE `pixelfor_bims_db`;
DROP TABLE IF EXISTS `assessment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_types`
--

LOCK TABLES `assessment_types` WRITE;
/*!40000 ALTER TABLE `assessment_types` DISABLE KEYS */;
INSERT INTO `assessment_types` VALUES (1,'Opener',1),(2,'Mid-Term',2),(3,'End-Term',3);
/*!40000 ALTER TABLE `assessment_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessments`
--

DROP TABLE IF EXISTS `assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curriculum_type_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `term_id` int NOT NULL,
  `assessment_type` varchar(50) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `max_score` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  KEY `subject_id` (`subject_id`),
  KEY `term_id` (`term_id`),
  CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`),
  CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects_learning_areas` (`id`),
  CONSTRAINT `assessments_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessments`
--

LOCK TABLES `assessments` WRITE;
/*!40000 ALTER TABLE `assessments` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cbe_grading_scale`
--

DROP TABLE IF EXISTS `cbe_grading_scale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cbe_grading_scale` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(10) NOT NULL,
  `grade_name` varchar(100) NOT NULL,
  `points` int NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_code` (`grade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cbe_grading_scale`
--

LOCK TABLES `cbe_grading_scale` WRITE;
/*!40000 ALTER TABLE `cbe_grading_scale` DISABLE KEYS */;
INSERT INTO `cbe_grading_scale` VALUES (1,'EE1','Exceeding Expectation 1',8,1),(2,'EE2','Exceeding Expectation 2',7,2),(3,'ME1','Meeting Expectation 1',6,3),(4,'ME2','Meeting Expectation 2',5,4),(5,'AE1','Approaching Expectation 1',4,5),(6,'AE2','Approaching Expectation 2',3,6),(7,'BE1','Below Expectation 1',2,7),(8,'BE2','Below Expectation 2',1,8);
/*!40000 ALTER TABLE `cbe_grading_scale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes_levels`
--

DROP TABLE IF EXISTS `classes_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curriculum_type_id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci
 NOT NULL,
  `level_order` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  CONSTRAINT `classes_levels_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes_levels`
--

LOCK TABLES `classes_levels` WRITE;
/*!40000 ALTER TABLE `classes_levels` DISABLE KEYS */;
INSERT INTO `classes_levels` VALUES (1,1,'Grade 4',1),(2,1,'Grade 5',2),(3,1,'Grade 6',3),(4,1,'Grade 7',4),(5,1,'Grade 8',5),(6,1,'Grade 9',6),(7,2,'Form 3',1),(8,2,'Form 4',2),(9,3,'Year 9',1),(10,3,'Year 10',2),(11,3,'Year 11',3);
/*!40000 ALTER TABLE `classes_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_subjects`
--

DROP TABLE IF EXISTS `curriculum_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curriculum_type_id` int NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `is_core` tinyint(1) DEFAULT '0' COMMENT '1 = compulsory, 0 = optional',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_curriculum_subject` (`curriculum_type_id`,`subject_name`),
  CONSTRAINT `curriculum_subjects_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_subjects`
--

LOCK TABLES `curriculum_subjects` WRITE;
/*!40000 ALTER TABLE `curriculum_subjects` DISABLE KEYS */;
INSERT INTO `curriculum_subjects` VALUES (1,1,'Mathematics',1),(2,1,'English',1),(3,1,'Kiswahili',1),(4,1,'Science & Technology',1),(5,1,'Social Studies',1),(6,1,'Religious Education (CRE/IRE/HRE)',0),(7,1,'Creative Arts',0),(8,1,'Physical & Health Education',0),(9,1,'Agriculture',0),(10,1,'Home Science',0),(11,2,'Mathematics',1),(12,2,'English',1),(13,2,'Kiswahili',1),(14,2,'Biology',0),(15,2,'Chemistry',0),(16,2,'Physics',0),(17,2,'Geography',0),(18,2,'History',0),(19,2,'CRE',0),(20,2,'IRE',0),(21,2,'Business Studies',0),(22,2,'Agriculture',0),(23,2,'Home Science',0),(24,2,'Computer Studies',0),(25,3,'English Language',1),(26,3,'Mathematics',1),(27,3,'Biology',0),(28,3,'Chemistry',0),(29,3,'Physics',0),(30,3,'Combined Science',0),(31,3,'Geography',0),(32,3,'History',0),(33,3,'Business Studies',0),(34,3,'Economics',0),(35,3,'Computer Science',0),(36,3,'Art & Design',0),(37,3,'Physical Education',0);
/*!40000 ALTER TABLE `curriculum_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curriculum_types`
--

DROP TABLE IF EXISTS `curriculum_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `curriculum_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci
 NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curriculum_types`
--

LOCK TABLES `curriculum_types` WRITE;
/*!40000 ALTER TABLE `curriculum_types` DISABLE KEYS */;
INSERT INTO `curriculum_types` VALUES (1,'CBE',NULL),(2,'8-4-4',NULL),(3,'IGCSE',NULL);
/*!40000 ALTER TABLE `curriculum_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_submissions`
--

DROP TABLE IF EXISTS `grade_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `academic_year` int NOT NULL,
  `term` varchar(20) NOT NULL,
  `assessment_type` varchar(50) DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `unlocked_by` int DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `class_teacher_comment` text,
  `principal_comment` text,
  `parent_comment` text,
  `status` enum('PENDING','AWAITING_PRINCIPAL','RELEASED') DEFAULT 'PENDING',
  `submitted_to_principal_at` timestamp NULL DEFAULT NULL,
  `released_to_students_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`student_id`,`academic_year`,`term`,`assessment_type`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_at` (`submitted_to_principal_at`),
  CONSTRAINT `grade_submissions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_submissions_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_submissions`
--

LOCK TABLES `grade_submissions` WRITE;
/*!40000 ALTER TABLE `grade_submissions` DISABLE KEYS */;
INSERT INTO `grade_submissions` VALUES (1,1,2,2023,'Term 1','Opener',1,'2026-01-21 09:01:00',NULL,NULL,NULL,NULL,NULL,'PENDING',NULL,NULL),(2,1,2,2026,'Term 1','Opener',1,'2026-02-02 16:33:47',1,'2026-02-02 16:32:42','You have performed exceptionally well! keep up the good work.','keep it up james!',NULL,'RELEASED','2026-02-02 16:33:47','2026-02-02 16:34:43'),(3,2,2,2026,'Term 1','Opener',1,'2026-02-03 08:17:31',1,'2026-02-03 08:15:16','Well done, Amanda!','keep up the good work',NULL,'RELEASED','2026-02-03 08:17:31','2026-02-03 08:17:54'),(4,4,6,2026,'Term 1','Opener',1,'2026-02-03 15:41:39',1,'2026-02-03 15:40:59','well done','you can do better in maths and geography',NULL,'RELEASED','2026-02-03 15:41:39','2026-02-03 15:42:30'),(6,2,2,2026,'Term 1','End-Term',1,'2026-02-06 08:41:15',NULL,NULL,'wonderful work. keep it up!',NULL,NULL,'AWAITING_PRINCIPAL','2026-02-06 08:41:15',NULL);
/*!40000 ALTER TABLE `grade_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade_upload_permissions`
--

DROP TABLE IF EXISTS `grade_upload_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade_upload_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `academic_year` int NOT NULL,
  `term` varchar(20) NOT NULL,
  `assessment_type` varchar(50) DEFAULT NULL,
  `curriculum_name` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission` (`academic_year`,`term`,`assessment_type`,`curriculum_name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade_upload_permissions`
--

LOCK TABLES `grade_upload_permissions` WRITE;
/*!40000 ALTER TABLE `grade_upload_permissions` DISABLE KEYS */;
INSERT INTO `grade_upload_permissions` VALUES (1,2026,'Term 1','Opener','CBC',1,'2026-01-21 08:01:06','2026-01-21 08:01:31'),(2,2023,'Term 1','Opener','CBC',1,'2026-01-21 08:01:20','2026-01-21 08:01:20'),(3,2023,'Term 1','Mid-Term','CBC',1,'2026-01-21 08:01:25','2026-01-21 08:01:25'),(4,2026,'Term 1','Mid-Term','CBC',1,'2026-01-21 08:10:24','2026-01-21 08:10:24'),(5,2026,'Term 1','Opener','CBE',1,'2026-01-21 08:59:24','2026-01-21 08:59:24'),(6,2026,'Term 1','Mid-Term','CBE',1,'2026-01-21 08:59:25','2026-01-21 08:59:25'),(7,2023,'Term 1','Opener','CBE',1,'2026-01-21 08:59:29','2026-01-21 08:59:29'),(8,2024,'Term 1','Opener','CBE',1,'2026-01-22 08:26:56','2026-01-22 08:26:56'),(9,2024,'Term 1','Mid-Term','CBE',1,'2026-01-22 08:26:58','2026-01-22 08:26:58'),(10,2024,'Term 1','End-Term','CBE',1,'2026-01-22 08:27:00','2026-01-22 08:27:00'),(11,2026,'Term 1','Opener','8-4-4',1,'2026-01-28 08:06:36','2026-01-28 08:06:36'),(12,2026,'Term 1','Mid-Term','8-4-4',1,'2026-01-28 08:06:37','2026-01-28 08:06:37'),(13,2026,'Term 1','Opener','IGCSE',1,'2026-01-29 11:33:46','2026-01-29 11:33:46'),(14,2026,'Term 1','Mid-Term','IGCSE',1,'2026-01-29 11:33:47','2026-01-29 11:33:47');
/*!40000 ALTER TABLE `grade_upload_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `score` int DEFAULT NULL COMMENT 'Actual score out of 100',
  `rats_score` int DEFAULT NULL COMMENT 'RATs score out of 20 (for Mid-Term and End-Term)',
  `final_score` int DEFAULT NULL COMMENT 'Final calculated score (score + rats for Mid/End-Term, just score for Opener)',
  `grade_points` int DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT '0',
  `teacher_comment` text,
  `term` enum('Term 1','Term 2','Term 3') NOT NULL,
  `assessment_type` varchar(50) DEFAULT NULL,
  `academic_year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teacher_id` int NOT NULL,
  `paper_scores` json DEFAULT NULL,
  `exam_type` varchar(20) DEFAULT 'full_papers',
  `grade_boundaries` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_year_term` (`student_id`,`academic_year`,`term`),
  KEY `idx_student_year_term_assessment` (`student_id`,`academic_year`,`term`,`assessment_type`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grades`
--

LOCK TABLES `grades` WRITE;
/*!40000 ALTER TABLE `grades` DISABLE KEYS */;
INSERT INTO `grades` VALUES (1,1,'Agriculture','EE1',NULL,NULL,NULL,8,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(2,1,'Creative Arts','EE1',NULL,NULL,NULL,8,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(3,1,'English','EE2',NULL,NULL,NULL,7,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(4,1,'Home Science','EE2',NULL,NULL,NULL,7,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(5,1,'Kiswahili','EE2',NULL,NULL,NULL,7,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(6,1,'Mathematics','ME1',NULL,NULL,NULL,6,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(7,1,'Physical & Health Education','ME1',NULL,NULL,NULL,6,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(8,1,'Religious Education (CRE/IRE/HRE)','EE1',NULL,NULL,NULL,8,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(9,1,'Science & Technology','EE2',NULL,NULL,NULL,7,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(10,1,'Social Studies','ME1',NULL,NULL,NULL,6,1,NULL,'Term 1','Opener',2023,'2026-01-21 09:01:00','2026-01-21 09:01:00',2,NULL,'full_papers',NULL),(11,1,'Agriculture','ME1',55,16,71,6,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(12,1,'Creative Arts','EE2',67,14,81,7,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(13,1,'English','EE1',78,19,97,8,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(14,1,'Home Science','EE1',79,12,91,8,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(15,1,'Kiswahili','EE2',67,13,80,7,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(16,1,'Mathematics','ME1',52,12,64,6,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(17,1,'Physical & Health Education','EE1',77,15,92,8,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(18,1,'Religious Education (CRE/IRE/HRE)','EE1',80,19,99,8,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(19,1,'Science & Technology','ME1',50,12,62,6,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(20,1,'Social Studies','EE2',73,12,85,7,0,NULL,'Term 1','Mid-Term',2024,'2026-01-22 08:37:32','2026-01-22 20:56:29',2,NULL,'full_papers',NULL),(21,4,'Biology','A-',76,NULL,76,11,1,'excellent performance','Term 1','Opener',2026,'2026-01-29 16:44:15','2026-02-03 15:41:39',1,'{\"SinglePaper\": 86}','single_paper',NULL),(22,4,'English','A',81,NULL,81,12,1,'Keep up the good work','Term 1','Opener',2026,'2026-01-29 16:44:15','2026-02-03 15:41:39',1,'{\"SinglePaper\": 91}','single_paper',NULL),(23,1,'Agriculture','EE1',96,NULL,96,8,1,'good work','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(24,1,'English','ME2',57,NULL,57,5,1,'You can do better','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(25,1,'Home Science','EE1',92,NULL,92,8,1,'Wonderful performance','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(26,1,'Kiswahili','EE2',76,NULL,76,7,1,'good attempt','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(27,1,'Mathematics','EE2',81,NULL,81,7,1,'good work','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(28,1,'Physical & Health Education','EE1',97,NULL,97,8,1,'Excellent','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(29,1,'Religious Education (CRE/IRE/HRE)','EE1',92,NULL,92,8,1,'Great work','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(30,1,'Science & Technology','EE1',99,NULL,99,8,1,'Keep up the good work','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(31,1,'Social Studies','ME1',67,NULL,67,6,1,'You can do better','Term 1','Opener',2026,'2026-01-29 16:58:24','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(38,2,'English','EE2',88,NULL,88,7,1,'good work','Term 1','Opener',2026,'2026-01-30 08:57:14','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(39,2,'Home Science','EE1',98,NULL,98,8,1,'excellent','Term 1','Opener',2026,'2026-01-30 08:57:14','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(40,2,'Mathematics','EE2',76,NULL,76,7,1,'aim higher','Term 1','Opener',2026,'2026-01-30 09:17:11','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(41,2,'Physical & Health Education','EE1',96,NULL,96,8,1,'good work','Term 1','Opener',2026,'2026-01-30 09:17:11','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(42,2,'Agriculture','EE2',83,NULL,83,7,1,'great work','Term 1','Opener',2026,'2026-01-30 09:18:18','2026-02-03 08:17:31',4,NULL,'full_papers',NULL),(43,2,'Creative Arts','EE2',76,NULL,76,7,1,'good attempt','Term 1','Opener',2026,'2026-01-30 09:18:18','2026-02-03 08:17:31',4,NULL,'full_papers',NULL),(44,2,'Science & Technology','EE2',75,NULL,75,7,1,'great work','Term 1','Opener',2026,'2026-01-30 09:20:17','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(45,2,'Social Studies','EE2',88,NULL,88,7,1,'wonderful work','Term 1','Opener',2026,'2026-01-30 09:20:17','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(46,2,'Kiswahili','ME1',66,NULL,66,6,1,'you can do better','Term 1','Opener',2026,'2026-01-30 09:22:11','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(47,2,'Religious Education (CRE/IRE/HRE)','EE1',95,NULL,95,8,1,'excellent','Term 1','Opener',2026,'2026-01-30 09:22:11','2026-02-03 08:17:31',2,NULL,'full_papers',NULL),(48,1,'Creative Arts','EE2',78,NULL,78,7,1,'','Term 1','Opener',2026,'2026-02-02 16:33:47','2026-02-02 16:33:47',2,NULL,'full_papers',NULL),(51,4,'Geography','C+',56,NULL,56,7,1,'Aim higher','Term 1','Opener',2026,'2026-02-03 08:34:48','2026-02-03 15:41:39',1,NULL,'full_papers',NULL),(52,4,'Mathematics','C+',56,NULL,56,7,1,'try harder','Term 1','Opener',2026,'2026-02-03 08:39:09','2026-02-03 15:41:39',5,NULL,'full_papers',NULL),(53,4,'Physics','B',67,NULL,67,9,1,'aim higher','Term 1','Opener',2026,'2026-02-03 08:39:09','2026-02-03 15:41:39',5,NULL,'full_papers',NULL),(54,4,'Chemistry','A',82,NULL,82,12,1,'good job','Term 1','Opener',2026,'2026-02-03 08:41:01','2026-02-03 15:41:39',6,NULL,'full_papers',NULL),(55,4,'Kiswahili','A-',78,NULL,78,11,1,'aim for an A','Term 1','Opener',2026,'2026-02-03 08:41:01','2026-02-03 15:41:39',6,NULL,'full_papers',NULL),(66,2,'Agriculture','EE2',70,18,88,7,1,'well done','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(67,2,'Creative Arts','EE1',80,18,98,8,1,'well done','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(68,2,'English','EE2',67,12,79,7,1,'well done','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(69,2,'Home Science','EE2',56,19,75,7,1,'aim higher','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(70,2,'Kiswahili','EE1',80,15,95,8,1,'well done','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(71,2,'Mathematics','EE2',70,12,82,7,1,'well done','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(72,2,'Physical & Health Education','EE2',76,12,88,7,1,'good work','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(73,2,'Religious Education (CRE/IRE/HRE)','EE1',78,17,95,8,1,'good work','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(74,2,'Science & Technology','ME1',56,10,66,6,1,'aim higher','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL),(75,2,'Social Studies','EE2',64,18,82,7,1,'good attempt','Term 1','End-Term',2026,'2026-02-06 08:41:15','2026-02-06 08:41:15',2,NULL,'full_papers',NULL);
/*!40000 ALTER TABLE `grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `igcse_grading_scale`
--

DROP TABLE IF EXISTS `igcse_grading_scale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `igcse_grading_scale` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(10) NOT NULL,
  `min_score` int NOT NULL,
  `max_score` int NOT NULL,
  `points` int NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_code` (`grade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `igcse_grading_scale`
--

LOCK TABLES `igcse_grading_scale` WRITE;
/*!40000 ALTER TABLE `igcse_grading_scale` DISABLE KEYS */;
INSERT INTO `igcse_grading_scale` VALUES (1,'A*',90,100,9,1),(2,'A',80,89,8,2),(3,'B',70,79,7,3),(4,'C',60,69,6,4),(5,'D',50,59,5,5),(6,'E',40,49,4,6),(7,'F',30,39,3,7),(8,'G',20,29,2,8),(9,'U',0,19,1,9);
/*!40000 ALTER TABLE `igcse_grading_scale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parent_student`
--

DROP TABLE IF EXISTS `parent_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parent_student` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `student_id` int NOT NULL,
  `relationship` varchar(50) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent_id` (`parent_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`),
  CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parent_student`
--

LOCK TABLES `parent_student` WRITE;
/*!40000 ALTER TABLE `parent_student` DISABLE KEYS */;
/*!40000 ALTER TABLE `parent_student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parents`
--

DROP TABLE IF EXISTS `parents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `linked_students` text COLLATE utf8mb4_general_ci
 COMMENT 'Comma-separated admission numbers',
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `residential_area` varchar(255) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `relationship` varchar(50) COLLATE utf8mb4_general_ci
 DEFAULT NULL COMMENT 'Father, Mother, Guardian, etc.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parents`
--

LOCK TABLES `parents` WRITE;
/*!40000 ALTER TABLE `parents` DISABLE KEYS */;
INSERT INTO `parents` VALUES (1,5,'Sarah','Adam',NULL,'1234,4321,7890','','','Mother');
/*!40000 ALTER TABLE `parents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pci_assessments`
--

DROP TABLE IF EXISTS `pci_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pci_assessments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `academic_year` int NOT NULL,
  `term` enum('Term 1','Term 2','Term 3') NOT NULL,
  `communication_collaboration` varchar(10) DEFAULT NULL COMMENT 'CC',
  `self_efficacy` varchar(10) DEFAULT NULL COMMENT 'SE',
  `critical_thinking` varchar(10) DEFAULT NULL COMMENT 'CT',
  `creativity_imagination` varchar(10) DEFAULT NULL COMMENT 'CI',
  `citizenship` varchar(10) DEFAULT NULL COMMENT 'CZ',
  `digital_literacy` varchar(10) DEFAULT NULL COMMENT 'DL',
  `learning_to_learn` varchar(10) DEFAULT NULL COMMENT 'L&L',
  `love` varchar(10) DEFAULT NULL,
  `respect` varchar(10) DEFAULT NULL COMMENT 'RST',
  `responsibility` varchar(10) DEFAULT NULL COMMENT 'RTY',
  `unity` varchar(10) DEFAULT NULL,
  `peace` varchar(10) DEFAULT NULL COMMENT 'PC',
  `integrity` varchar(10) DEFAULT NULL COMMENT 'ITY',
  `discipline` varchar(10) DEFAULT NULL COMMENT 'DNE',
  `organization` varchar(10) DEFAULT NULL COMMENT 'ORG',
  `tidiness` varchar(10) DEFAULT NULL COMMENT 'TID',
  `projects_manipulative_skills` varchar(10) DEFAULT NULL COMMENT 'P & M S',
  `extended_activities` varchar(10) DEFAULT NULL COMMENT 'EA',
  `teacher_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pci` (`student_id`,`academic_year`,`term`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_student_year_term` (`student_id`,`academic_year`,`term`),
  CONSTRAINT `pci_assessments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pci_assessments_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pci_assessments`
--

LOCK TABLES `pci_assessments` WRITE;
/*!40000 ALTER TABLE `pci_assessments` DISABLE KEYS */;
INSERT INTO `pci_assessments` VALUES (1,2,2026,'Term 1','EE2','EE1','ME1','ME2','EE1','EE1','EE2','EE1','EE2','ME1','EE2','EE1','EE2','EE2','EE2','ME1','ME2','EE2',2,'2026-02-06 08:41:15','2026-02-06 08:41:15');
/*!40000 ALTER TABLE `pci_assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `assessment_id` int NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(10) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`,`assessment_id`),
  KEY `assessment_id` (`assessment_id`),
  CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `results_ibfk_2` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `results`
--

LOCK TABLES `results` WRITE;
/*!40000 ALTER TABLE `results` DISABLE KEYS */;
/*!40000 ALTER TABLE `results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci
 NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','System administrator'),(2,'Teacher','Teacher role'),(3,'Student','Student role'),(4,'Parent','Parent role');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_subjects`
--

DROP TABLE IF EXISTS `student_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_subject` (`student_id`,`subject_name`),
  CONSTRAINT `student_subjects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_subjects`
--

LOCK TABLES `student_subjects` WRITE;
/*!40000 ALTER TABLE `student_subjects` DISABLE KEYS */;
INSERT INTO `student_subjects` VALUES (11,1,'English','2026-01-18 17:52:02'),(12,1,'Kiswahili','2026-01-18 17:52:02'),(13,1,'Mathematics','2026-01-18 17:52:02'),(14,1,'Science & Technology','2026-01-18 17:52:02'),(15,1,'Social Studies','2026-01-18 17:52:02'),(16,1,'Agriculture','2026-01-18 17:52:02'),(17,1,'Creative Arts','2026-01-18 17:52:02'),(18,1,'Home Science','2026-01-18 17:52:02'),(19,1,'Physical & Health Education','2026-01-18 17:52:02'),(20,1,'Religious Education (CRE/IRE/HRE)','2026-01-18 17:52:02'),(21,2,'English','2026-01-19 13:24:12'),(22,2,'Kiswahili','2026-01-19 13:24:12'),(23,2,'Mathematics','2026-01-19 13:24:12'),(24,2,'Science & Technology','2026-01-19 13:24:12'),(25,2,'Social Studies','2026-01-19 13:24:12'),(26,2,'Agriculture','2026-01-19 13:24:12'),(27,2,'Creative Arts','2026-01-19 13:24:12'),(28,2,'Home Science','2026-01-19 13:24:12'),(29,2,'Physical & Health Education','2026-01-19 13:24:13'),(30,2,'Religious Education (CRE/IRE/HRE)','2026-01-19 13:24:13'),(31,4,'English','2026-01-27 09:10:08'),(32,4,'Kiswahili','2026-01-27 09:10:08'),(33,4,'Mathematics','2026-01-27 09:10:08'),(34,4,'Biology','2026-01-27 09:10:08'),(35,4,'Chemistry','2026-01-27 09:10:08'),(36,4,'Computer Studies','2026-01-27 09:10:08'),(37,4,'Geography','2026-01-27 09:10:08'),(38,4,'Physics','2026-01-27 09:10:08'),(39,5,'English Language','2026-01-29 11:28:55'),(40,5,'Mathematics','2026-01-29 11:28:55'),(41,5,'Biology','2026-01-29 11:28:55'),(42,5,'Business Studies','2026-01-29 11:28:55'),(43,5,'Chemistry','2026-01-29 11:28:55'),(44,5,'Computer Science','2026-01-29 11:28:55'),(45,5,'History','2026-01-29 11:28:55'),(46,5,'Physics','2026-01-29 11:28:55');
/*!40000 ALTER TABLE `student_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `admission_number` varchar(50) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `gender` enum('Male','Female') COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `curriculum_type_id` int NOT NULL,
  `class_level_id` int NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci
 DEFAULT 'active',
  `year_of_enrollment` int DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `residential_area` varchar(255) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `parent_phone` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `parent_email` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `admission_number` (`admission_number`),
  UNIQUE KEY `unique_admission` (`admission_number`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  KEY `class_level_id` (`class_level_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`),
  CONSTRAINT `students_ibfk_3` FOREIGN KEY (`class_level_id`) REFERENCES `classes_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,2,'1234','james','paul','Male','2014-07-17',1,4,'active',2023,'+254741234562','South C','+254712345678','parent@school.com'),(2,9,'4321','Amanda ','Adams','Female','2013-08-14',1,4,'active',2024,'+254766432121','Westlands','+254755443311','parent@school.com'),(4,8,'5678','Fatma','Ahmed','Female','2006-02-08',2,8,'active',2023,'+254784321256','South B','+254768889543','parent@school.com'),(5,6,'7890','Ahmed','Issa','Male','2007-02-06',3,11,'active',2025,'+254798989898','Kilimani','+254768889543','parent@school.com');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects_learning_areas`
--

DROP TABLE IF EXISTS `subjects_learning_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects_learning_areas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `curriculum_type_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci
 NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  CONSTRAINT `subjects_learning_areas_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects_learning_areas`
--

LOCK TABLES `subjects_learning_areas` WRITE;
/*!40000 ALTER TABLE `subjects_learning_areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `subjects_learning_areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_844_grading_scale`
--

DROP TABLE IF EXISTS `system_844_grading_scale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_844_grading_scale` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(10) NOT NULL,
  `min_score` int NOT NULL,
  `max_score` int NOT NULL,
  `points` int NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_code` (`grade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_844_grading_scale`
--

LOCK TABLES `system_844_grading_scale` WRITE;
/*!40000 ALTER TABLE `system_844_grading_scale` DISABLE KEYS */;
INSERT INTO `system_844_grading_scale` VALUES (1,'A',80,100,12,1),(2,'A-',75,79,11,2),(3,'B+',70,74,10,3),(4,'B',65,69,9,4),(5,'B-',60,64,8,5),(6,'C+',55,59,7,6),(7,'C',50,54,6,7),(8,'C-',45,49,5,8),(9,'D+',40,44,4,9),(10,'D',35,39,3,10),(11,'D-',30,34,2,11),(12,'E',0,29,1,12);
/*!40000 ALTER TABLE `system_844_grading_scale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_subjects`
--

DROP TABLE IF EXISTS `teacher_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `curriculum_type_id` int NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_curriculum_subject` (`teacher_id`,`curriculum_type_id`,`subject_name`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  CONSTRAINT `teacher_subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_subjects_ibfk_2` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_subjects`
--

LOCK TABLES `teacher_subjects` WRITE;
/*!40000 ALTER TABLE `teacher_subjects` DISABLE KEYS */;
INSERT INTO `teacher_subjects` VALUES (23,1,1,'English','2026-02-03 08:30:25'),(24,1,1,'Home Science','2026-02-03 08:30:25'),(25,1,2,'English','2026-02-03 08:30:25'),(26,1,2,'Geography','2026-02-03 08:30:25'),(27,1,3,'Biology','2026-02-03 08:30:25'),(28,2,1,'Kiswahili','2026-02-03 08:30:43'),(29,2,1,'Religious Education (CRE/IRE/HRE)','2026-02-03 08:30:43'),(30,2,2,'Kiswahili','2026-02-03 08:30:43'),(31,2,2,'Computer Studies','2026-02-03 08:30:43'),(32,2,2,'CRE','2026-02-03 08:30:43'),(33,4,1,'Agriculture','2026-02-03 08:31:29'),(34,4,1,'Creative Arts','2026-02-03 08:31:29'),(35,4,2,'History','2026-02-03 08:31:29'),(36,4,2,'IRE','2026-02-03 08:31:29'),(37,5,1,'Mathematics','2026-02-03 08:32:01'),(38,5,1,'Physical & Health Education','2026-02-03 08:32:01'),(39,5,2,'Mathematics','2026-02-03 08:32:01'),(40,5,2,'Business Studies','2026-02-03 08:32:01'),(41,5,2,'Physics','2026-02-03 08:32:01'),(46,6,1,'Science & Technology','2026-02-03 08:33:17'),(47,6,1,'Social Studies','2026-02-03 08:33:17'),(48,6,2,'Biology','2026-02-03 08:33:17'),(49,6,2,'Chemistry','2026-02-03 08:33:17');
/*!40000 ALTER TABLE `teacher_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `category` enum('Subject Teacher','Class Teacher','Head Teacher') COLLATE utf8mb4_general_ci
 DEFAULT 'Subject Teacher',
  `assigned_class_id` int DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `residential_area` varchar(255) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(50) COLLATE utf8mb4_general_ci
 DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `assigned_class_id` (`assigned_class_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`assigned_class_id`) REFERENCES `classes_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,4,'Mellisa','Adams',NULL,NULL,'Subject Teacher',NULL,'+25467567654','Westlands','1988-06-07','23453423'),(2,10,'Muthoni','Mala',NULL,NULL,'Class Teacher',4,'+254755672134','Kasarani','1994-02-01','45324512'),(3,11,'Moreen','Awiti',NULL,NULL,'Head Teacher',NULL,'+254765447890','Ongata Rongai','1987-08-14','34213412'),(4,12,'Stacy','Bahati',NULL,NULL,'Subject Teacher',NULL,'+254721447654','Westlands','1985-09-26','8976654'),(5,13,'James','Awiti',NULL,NULL,'Subject Teacher',NULL,'+254755432112','Rongai','1994-12-06','3422134'),(6,14,'Allan','Bii',NULL,NULL,'Class Teacher',8,'+254765432165','South C','1990-05-16','45445654');
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `terms`
--

DROP TABLE IF EXISTS `terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `terms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_general_ci
 NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_general_ci
 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `terms`
--

LOCK TABLES `terms` WRITE;
/*!40000 ALTER TABLE `terms` DISABLE KEYS */;
/*!40000 ALTER TABLE `terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) COLLATE utf8mb4_general_ci
 NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci
 NOT NULL,
  `role_id` int NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci
 DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@school.com','$2y$10$Tc0R/lGfDi46G.UNdFHG.OlfOsVLM9/ekbb02EnmAvk41PDmMiZCm',1,'active','2026-01-08 13:07:15'),(2,'student@school.com','$2y$10$97o47nUNxoXqXfC5lVKXhOxqxJveNd6e68rbrUsGczwUu0/TayHhi',3,'active','2026-01-12 08:40:04'),(4,'teacher@school.com','$2y$10$Ck.NG6KDNM.1azZfVoXLce5N11ixvsyoAyz4q9NJCuW/93Yc235vm',2,'active','2026-01-12 08:45:37'),(5,'parent@school.com','$2y$10$.u2HwSRAkNfhnW4tkw/eruqKGJU3nAO1YM/I8s7RZWuBJHtSFVB4W',4,'active','2026-01-12 08:46:18'),(6,'igcse@school.com','$2y$10$SswvrmcGgsabo60bhj91QuPo4VZNLLKZFzLIgmN9IRCZN.w/3RAvO',3,'active','2026-01-15 19:40:11'),(8,'844@school.com','$2y$10$zAhTnFgCgLaIlcQNKTnrPu.vfivdm7KrGz3kyQdt/mvgioqJXoi/C',3,'active','2026-01-15 19:40:58'),(9,'cbe@school.com','$2y$10$BN/bcXVFRu3q0RZpkBZr1.VeSvpZNQvWa536VXEMbUXG4SFbHAaai',3,'active','2026-01-15 19:44:33'),(10,'c.teacher@school.com','$2y$10$YXw0EIVYJOfXfa/laTngeOLJn35Nwf2ayizweABJ2NrCPHJyuY1Si',2,'active','2026-01-19 12:38:29'),(11,'h.teacher@school.com','$2y$10$R9hHCHNe09oE2sxCZbHOwOzM5Br8ocbFv/0m5inHv8TBrE1/JPo5O',2,'active','2026-01-29 16:49:02'),(12,'st1@school.com','$2y$10$G10jLTVnLkRe15fVRpeoqOT6Az2PL7t6/D8dmXmc/K.gEoORmB.2C',2,'active','2026-01-30 09:04:29'),(13,'st2@school.com','$2y$10$GTjceVhAILpcgjmRoHe7WuAQOtMUBsglewbd/mwv5KCFqciqDElHC',2,'active','2026-01-30 09:04:48'),(14,'st3@school.com','$2y$10$og6cTulA3yjFG6THLW38kOTVt1hoPbifSaKrocaZj5osESZMhSL4O',2,'active','2026-01-30 09:05:06');
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

-- Dump completed on 2026-02-06 12:46:35
