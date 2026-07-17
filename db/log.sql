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

-- Дамп структуры для таблица lkzk.log
CREATE TABLE IF NOT EXISTS `log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datetime` datetime NOT NULL,
  `log` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_dop1` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_dop2` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_dop3` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `log_dop4` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_login` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chair_id` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `load_base_UID1` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `load_base_UID2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `status_change` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_src_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `datetime` (`datetime`),
  KEY `person_id` (`user_login`),
  KEY `action_name` (`action_name`),
  KEY `internal` (`internal`),
  KEY `status_change` (`status_change`),
  KEY `file_name` (`file_name`(191)),
  KEY `load_base_UID` (`load_base_UID2`) USING BTREE,
  KEY `load_base_UID1` (`load_base_UID1`)
) ENGINE=InnoDB AUTO_INCREMENT=683549 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
