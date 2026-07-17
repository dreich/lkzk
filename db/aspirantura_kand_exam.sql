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

-- Дамп структуры для таблица lkzk.aspirantura_kand_exam
CREATE TABLE IF NOT EXISTS `aspirantura_kand_exam` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `load_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `base_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bup_nrec` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bup_department_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bup_language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `disc_nrec` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disc_abr` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disc_title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `exam_semester` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `groups` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `groups_uid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `students_num` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lecturer_login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_person_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lecturer_fio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `chair_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `chair_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `department_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `department_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `deleted` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `date_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted` (`deleted`),
  KEY `bup_nrec_disc_nrec_disc_abr` (`bup_nrec`,`disc_nrec`,`disc_abr`) USING BTREE,
  KEY `lecturer_person_id` (`lecturer_person_id`),
  KEY `chair_id` (`chair_id`),
  KEY `load_id` (`load_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=362 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
