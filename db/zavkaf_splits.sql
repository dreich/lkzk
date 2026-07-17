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

-- Дамп структуры для таблица lkzk.zavkaf_splits
CREATE TABLE IF NOT EXISTS `zavkaf_splits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content_of_load_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'в целях отладки',
  `base_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_uid2` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_uid2_new` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `LoadType` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `StudentAmount` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Amount` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lecturer_login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lecturer_person_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lecturer_fio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lecturer_uid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chair_uid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'лектора, соотв. lecturer_uid',
  `zavkaf_login` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'поле только для удобства; не всегда заполяется..',
  `zavkaf_fio` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zavkaf_chair_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'для псевдо-кафедр здесь кафедра, а не факультет. Если заполняет рук. аспирантуры aspirantura_itog_exam, здесь пусто.',
  `delete` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `base_uid2` (`base_uid2`) USING BTREE,
  KEY `base_uid` (`base_uid`) USING BTREE,
  KEY `lecturer_person_id` (`lecturer_person_id`) USING BTREE,
  KEY `chair_uid` (`chair_uid`) USING BTREE,
  KEY `zavkaf_chair_uid` (`zavkaf_chair_uid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=215049 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
