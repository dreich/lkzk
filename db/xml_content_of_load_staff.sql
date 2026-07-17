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

-- Дамп структуры для таблица lkzk.xml_content_of_load_staff
CREATE TABLE IF NOT EXISTS `xml_content_of_load_staff` (
  `UID` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_ContentOfLoad` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_uid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_uid2` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TypeOfContingent` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ContentOfLoadUID` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_SubGroup` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Abbr` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_FormOfEducation` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Speciality` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Specialization` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_FacultyOwner` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_FacultyPerformer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`UID`),
  KEY `UID_ContentOfLoad` (`UID_ContentOfLoad`(191)),
  KEY `UID_Group` (`UID_Group`),
  KEY `UID_FacultyOwner` (`UID_FacultyOwner`),
  KEY `UID_Speciality` (`UID_Speciality`),
  KEY `UID_Specialization` (`UID_Specialization`),
  KEY `UID_Language` (`UID_Language`),
  KEY `Abbr` (`Abbr`),
  KEY `base_uid` (`base_uid`),
  KEY `base_uid2` (`base_uid2`),
  KEY `UID_SubGroup` (`UID_SubGroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
