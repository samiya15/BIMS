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

DROP TABLE IF EXISTS `assessment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
  `assessment_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `max_score` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  KEY `subject_id` (`subject_id`),
  KEY `term_id` (`term_id`),
  CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`),
  CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects_learning_areas` (`id`),
  CONSTRAINT `assessments_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessments`
--

LOCK TABLES `assessments` WRITE;
/*!40000 ALTER TABLE `assessments` DISABLE KEYS */;
/*!40000 ALTER TABLE `assessments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cbc_grading_scale`
--

DROP TABLE IF EXISTS `cbc_grading_scale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cbc_grading_scale` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(10) NOT NULL,
  `grade_name` varchar(100) NOT NULL,
  `points` int NOT NULL,
  `display_order` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_code` (`grade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cbc_grading_scale`
--

LOCK TABLES `cbc_grading_scale` WRITE;
/*!40000 ALTER TABLE `cbc_grading_scale` DISABLE KEYS */;
INSERT INTO `cbc_grading_scale` VALUES (1,'EE1','Exceeding Expectation 1',8,1),(2,'EE2','Exceeding Expectation 2',7,2),(3,'ME1','Meeting Expectation 1',6,3),(4,'ME2','Meeting Expectation 2',5,4),(5,'AE1','Approaching Expectation 1',4,5),(6,'AE2','Approaching Expectation 2',3,6),(7,'BE1','Below Expectation 1',2,7),(8,'BE2','Below Expectation 2',1,8);
/*!40000 ALTER TABLE `cbc_grading_scale` ENABLE KEYS */;
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
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `level_order` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  CONSTRAINT `classes_levels_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `grade_points` int DEFAULT NULL,
  `term` enum('Term 1','Term 2','Term 3') NOT NULL,
  `assessment_type` varchar(50) DEFAULT NULL,
  `academic_year` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `teacher_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_year_term` (`student_id`,`academic_year`,`term`),
  KEY `idx_student_year_term_assessment` (`student_id`,`academic_year`,`term`,`assessment_type`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grades`
--

LOCK TABLES `grades` WRITE;
/*!40000 ALTER TABLE `grades` DISABLE KEYS */;
/*!40000 ALTER TABLE `grades` ENABLE KEYS */;
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
  `relationship` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent_id` (`parent_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`),
  CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `first_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `linked_students` text COLLATE utf8mb4_general_ci COMMENT 'Comma-separated admission numbers',
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `residential_area` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `relationship` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Father, Mother, Guardian, etc.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parents`
--

LOCK TABLES `parents` WRITE;
/*!40000 ALTER TABLE `parents` DISABLE KEYS */;
INSERT INTO `parents` VALUES (1,5,'Sarah','Adam',NULL,'1234','','','Mother');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
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
  `grade` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`,`assessment_id`),
  KEY `assessment_id` (`assessment_id`),
  CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `results_ibfk_2` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_subjects`
--

LOCK TABLES `student_subjects` WRITE;
/*!40000 ALTER TABLE `student_subjects` DISABLE KEYS */;
INSERT INTO `student_subjects` VALUES (11,1,'English','2026-01-18 17:52:02'),(12,1,'Kiswahili','2026-01-18 17:52:02'),(13,1,'Mathematics','2026-01-18 17:52:02'),(14,1,'Science & Technology','2026-01-18 17:52:02'),(15,1,'Social Studies','2026-01-18 17:52:02'),(16,1,'Agriculture','2026-01-18 17:52:02'),(17,1,'Creative Arts','2026-01-18 17:52:02'),(18,1,'Home Science','2026-01-18 17:52:02'),(19,1,'Physical & Health Education','2026-01-18 17:52:02'),(20,1,'Religious Education (CRE/IRE/HRE)','2026-01-18 17:52:02'),(21,2,'English','2026-01-19 13:24:12'),(22,2,'Kiswahili','2026-01-19 13:24:12'),(23,2,'Mathematics','2026-01-19 13:24:12'),(24,2,'Science & Technology','2026-01-19 13:24:12'),(25,2,'Social Studies','2026-01-19 13:24:12'),(26,2,'Agriculture','2026-01-19 13:24:12'),(27,2,'Creative Arts','2026-01-19 13:24:12'),(28,2,'Home Science','2026-01-19 13:24:12'),(29,2,'Physical & Health Education','2026-01-19 13:24:13'),(30,2,'Religious Education (CRE/IRE/HRE)','2026-01-19 13:24:13');
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
  `admission_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gender` enum('Male','Female') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `curriculum_type_id` int NOT NULL,
  `class_level_id` int NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `year_of_enrollment` int DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `residential_area` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parent_phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parent_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `admission_number` (`admission_number`),
  UNIQUE KEY `unique_admission` (`admission_number`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  KEY `class_level_id` (`class_level_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`),
  CONSTRAINT `students_ibfk_3` FOREIGN KEY (`class_level_id`) REFERENCES `classes_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,2,'1234','james','paul','Male','2014-07-17',1,4,'active',2023,'+254741234562','South C','+254712345678','parent@school.com'),(2,9,'4321','Amanda ','Adams','Female','2013-08-14',1,4,'active',2024,'+254766432121','Westlands','+254755443311','parent@school.com');
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
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `curriculum_type_id` (`curriculum_type_id`),
  CONSTRAINT `subjects_learning_areas_ibfk_1` FOREIGN KEY (`curriculum_type_id`) REFERENCES `curriculum_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects_learning_areas`
--

LOCK TABLES `subjects_learning_areas` WRITE;
/*!40000 ALTER TABLE `subjects_learning_areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `subjects_learning_areas` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_subjects`
--

LOCK TABLES `teacher_subjects` WRITE;
/*!40000 ALTER TABLE `teacher_subjects` DISABLE KEYS */;
INSERT INTO `teacher_subjects` VALUES (1,1,1,'English','2026-01-16 09:15:57'),(2,1,1,'Home Science','2026-01-16 09:15:57'),(3,1,2,'English','2026-01-16 09:15:57'),(4,1,2,'CRE','2026-01-16 09:15:57'),(5,2,1,'Kiswahili','2026-01-19 12:43:05'),(6,2,1,'Religious Education (CRE/IRE/HRE)','2026-01-19 12:43:05'),(7,2,2,'CRE','2026-01-19 12:43:05');
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
  `first_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` enum('Subject Teacher','Class Teacher','Head Teacher') COLLATE utf8mb4_general_ci DEFAULT 'Subject Teacher',
  `assigned_class_id` int DEFAULT NULL,
  `phone_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `residential_area` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `assigned_class_id` (`assigned_class_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`assigned_class_id`) REFERENCES `classes_levels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,4,'Mellisa','Adams',NULL,NULL,'Subject Teacher',NULL,'+25467567654','Westlands','1988-06-07','23453423'),(2,10,'Muthoni','Mala',NULL,NULL,'Class Teacher',4,'+254755672134','Kasarani','1994-02-01','45324512');
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
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role_id` int NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@school.com','$2y$10$Tc0R/lGfDi46G.UNdFHG.OlfOsVLM9/ekbb02EnmAvk41PDmMiZCm',1,'active','2026-01-08 13:07:15'),(2,'student@school.com','$2y$10$97o47nUNxoXqXfC5lVKXhOxqxJveNd6e68rbrUsGczwUu0/TayHhi',3,'active','2026-01-12 08:40:04'),(4,'teacher@school.com','$2y$10$Ck.NG6KDNM.1azZfVoXLce5N11ixvsyoAyz4q9NJCuW/93Yc235vm',2,'active','2026-01-12 08:45:37'),(5,'parent@school.com','$2y$10$.u2HwSRAkNfhnW4tkw/eruqKGJU3nAO1YM/I8s7RZWuBJHtSFVB4W',4,'active','2026-01-12 08:46:18'),(6,'igcse@school.com','$2y$10$SswvrmcGgsabo60bhj91QuPo4VZNLLKZFzLIgmN9IRCZN.w/3RAvO',3,'active','2026-01-15 19:40:11'),(8,'844@school.com','$2y$10$zAhTnFgCgLaIlcQNKTnrPu.vfivdm7KrGz3kyQdt/mvgioqJXoi/C',3,'active','2026-01-15 19:40:58'),(9,'cbe@school.com','$2y$10$BN/bcXVFRu3q0RZpkBZr1.VeSvpZNQvWa536VXEMbUXG4SFbHAaai',3,'active','2026-01-15 19:44:33'),(10,'c.teacher@school.com','$2y$10$YXw0EIVYJOfXfa/laTngeOLJn35Nwf2ayizweABJ2NrCPHJyuY1Si',2,'active','2026-01-19 12:38:29');
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

-- Dump completed on 2026-01-20 11:34:52
