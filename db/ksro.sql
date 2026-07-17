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

-- Дамп структуры для таблица lkzk.ksro
CREATE TABLE IF NOT EXISTS `ksro` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `load_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `base_uid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chair_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lecturer_person_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'фейковое поле, чтобы понимать, что в системе он может меняться; правильный юид хранится в sotrudniki',
  `lecturer_fio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stavka` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dolzhnost` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Semester` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Amount` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_KindOfWork` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Discipline` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Chair` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_FacultyPerformer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chair_id_person_id_language_uid_UID_KindOfWork` (`chair_id`,`lecturer_person_id`,`UID_Language`,`UID_KindOfWork`,`UID_Semester`) USING BTREE,
  KEY `chair_id` (`chair_id`),
  KEY `uid` (`uid`),
  KEY `UID_Chair` (`UID_Chair`),
  KEY `department_id` (`department_id`),
  KEY `load_id` (`load_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6042 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
