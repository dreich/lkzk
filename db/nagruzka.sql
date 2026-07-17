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

-- Дамп структуры для таблица lkzk.nagruzka
CREATE TABLE IF NOT EXISTS `nagruzka` (
  `load_base_UID2` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'UID нагрузки без последних .1 .2 ...',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initial',
  `prev_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initial',
  `chair_id` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'больше для справки',
  `chair_name` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_name` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zavkaf_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zavkaf_login` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zavkaf_fio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disciplines_UIDs_chain_str` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disciplines_Names_chain_str` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment_to_admin` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'comment_to_admin',
  `date` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_update` datetime DEFAULT NULL,
  `valid` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '0 - если кафедра, которой уже нет (из Галактики), либо пустая кафедра.',
  UNIQUE KEY `load_base_UID` (`load_base_UID2`) USING BTREE,
  KEY `chair_id` (`chair_id`),
  KEY `valid` (`valid`),
  KEY `status` (`status`),
  KEY `zavkaf_login` (`zavkaf_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
