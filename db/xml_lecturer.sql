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

-- Дамп структуры для таблица lkzk.xml_lecturer
CREATE TABLE IF NOT EXISTS `xml_lecturer` (
  `UID` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CustomUID` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Person` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FIO` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Post` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `UID_Chair` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Rate` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `YearLoadNormaMin` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `YearLoadNormaMax` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DateContractBeg` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `DateContractEnd` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Availability` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Priority` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Tab_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Archive` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`UID`),
  KEY `Tab_number` (`Tab_number`),
  KEY `Archive` (`Archive`),
  KEY `UID_Post` (`UID_Post`),
  KEY `UID_Chair` (`UID_Chair`),
  KEY `DateContractEnd` (`DateContractEnd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Экспортируемые данные не выделены.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
