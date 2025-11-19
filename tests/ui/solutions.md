### [19.11.2025, 08:49:00] [Администратор УОУП vorobev] Ошибка в пункте меню "Нагрузка":
- **Тест:** [tests/ui/test_uoup.js](./tests/ui/test_uoup.js)
- **Файл:** [app.js.php:577](../../app.js.php#L577)
- **URL:** http://lkzk/#/nagruzka/all/02678
- **Ошибка:** TypeError: Cannot read properties of null (reading 'data')
- **Путь:** меню "Нагрузка"
- **Шаги:** Переход: Нагрузка; Клик по строке второй таблицы
- **Решение:** Убедитесь, что resolve для маршрута "Нагрузка" возвращает данные (ajax/get/nagruzka_*.php) и что контроллер обрабатывает пустые ответы перед обращением к response.data.
