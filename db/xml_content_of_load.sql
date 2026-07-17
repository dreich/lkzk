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

-- Дамп структуры для таблица lkzk.xml_content_of_load
CREATE TABLE IF NOT EXISTS `xml_content_of_load` (
  `UID` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_uid` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_uid2` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `LoadId` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `YearOfEducation` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DateFrom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `DateTo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Amount` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `AmountInUnit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TypeOfContingent` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_SubGroup` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_Stream` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_KindOfWork` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PackageNumber` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ID_Auditorium` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_Discipline` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Lecturer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Lecturer_UID_Chair` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'временно, может быть не пригодится',
  `UID_Chair` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `UID_Semester` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TypeWorkload` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Course` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DisciplineTypeLoad` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `LoadType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'если LoadType=0 то число студентов, LoadType=1 то часы',
  `StudentAmount` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nagruzka_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '$_nagruzka_types',
  `hash` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`UID`),
  KEY `UID_Chair` (`UID_Chair`),
  KEY `UID_Discipline` (`UID_Discipline`),
  KEY `UID_KindOfWork` (`UID_KindOfWork`),
  KEY `UID_Lecturer` (`UID_Lecturer`),
  KEY `base_uid` (`base_uid`),
  KEY `base_uid2` (`base_uid2`),
  KEY `gia` (`nagruzka_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
