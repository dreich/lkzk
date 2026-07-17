-- --------------------------------------------------------
-- Хост:                         msite.unn.ru
-- Версия сервера:               5.7.44-log - MySQL Community Server (GPL)
-- Операционная система:         Linux
-- HeidiSQL Версия:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Дамп структуры для таблица lkzk.aspirantura_ruk_asp
CREATE TABLE IF NOT EXISTS `aspirantura_ruk_asp` (
  `uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Semester` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `load_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `base_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fio` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `napravlenie_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `napravlenie_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_person_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_fio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_chair_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_chair_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_department_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_department_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `deleted` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `date_update` datetime DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`uid`,`UID_Semester`),
  KEY `deleted` (`deleted`),
  KEY `lecturer_chair_id` (`lecturer_chair_id`),
  KEY `lecturer_uid` (`lecturer_uid`),
  KEY `load_id` (`load_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
