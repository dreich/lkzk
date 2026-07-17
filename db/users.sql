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

-- Дамп структуры для таблица lkzk.users
CREATE TABLE IF NOT EXISTS `users` (
  `login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Дамп данных таблицы lkzk.users: ~14 rows (приблизительно)
INSERT INTO `users` (`login`, `roles`, `fio`) VALUES
	('gubanikhina', '|uoup|', 'Губанихина Татьяна Николаевна'),
	('kolchina', '|uoup|', 'Колчина Юлия Валерьевна'),
	('konstantin.suslov', '|uoup|', 'Суслов Константин Михайлович'),
	('lashina', '|uoup|', 'Лашина Любовь Николаевна'),
	('marina.rozova', '|uoup|', 'Розова Марина Юрьевна'),
	('nadezhda.semenova', '|ruk_aspirantura|', 'Семенова Надежда Леонидовна'),
	('nikolay.rybakov', '|ruk_aspirantura|', 'Рыбаков Николай Валерьевич'),
	('salakhetdinova', '|ruk_aspirantura|', 'Салахетдинова Татьяна Владимировна'),
	('sbornova', '|uoup|', 'Сборнова Наталья Владимировна'),
	('sergey.gorokhov', '|uoup|ruk_aspirantura|', 'Горохов Сергей Владимирович'),
	('tatyana.safonova', '|ruk_aspirantura|', 'Сафонова Татьяна Алексеевна'),
	('trykina', '|ruk_aspirantura|', 'Трыкина Надежда Дмитриевна'),
	('vladimir.ivanov', '|uoup|', 'Иванов Владимир Анатольевич'),
	('vorobev', '|uoup|', 'Воробьев Андрей Андреевич');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
