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

-- Дамп структуры для таблица lkzk.xml_group
CREATE TABLE IF NOT EXISTS `xml_group` (
  `UID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Course` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Faculty` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Chair` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_FormOfEducation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `PlannedAmount` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Amount` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `YearOfEducation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `YearOfEntry` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `KindEducation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Speciality` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Specialization` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Kind` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Archive` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`UID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
