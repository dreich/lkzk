# Changelog

## 2025-11-19 — «Правка багов, создание тестов, частичная наладка зелёных таблиц»
- Скорректированы серверные расчёты статистики и выборок по нагрузке для ролей УОУП и завкаф (`ajax/get/get_nagruzka_uoup_stat.php`, `ajax/get/get_nagruzka_zavkaf_stat.php`, `ajax/get/nagruzka_discipline.php`, `ajax/get/selected_chair_sotrudniki.php`).
- В интерфейсе УОУП переработана работа зелёных таблиц: добавлены кастомные фильтры, обновлены диалоги действий и обработка ответов (`app.js.php`, `nagruzka.tpl.html`, `uoup_chairs_refused.tpl.html`, `uoup_nagruzka_to_change.tpl.html`).
- Запущена новая инфраструктура UI‑тестов на Playwright с отдельными сценариями для ролей ЗавКаф и администратора УОУП (`tests/tests.js`, `tests/ui/test_zavkaf.js`, `tests/ui/test_uoup.js`).

## 2025-11-19 — «Мелкие баги. Скрипт экспорта выбранных нагрузок в XML»
- Исправлены подсказки и фильтры на зелёных таблицах и формах отказа/выполнения нагрузок (`app.js.php`, `nagruzka_history.tpl.html`).
- Добавлен скрипт `once/export_selected_nagruzka.php`, формирующий XML по выбранным нагрузкам на основе `nagruzka` и `xml_content_of_load`.
- UI-тесты обновлены под новую инфраструктуру логов и headless-настройку (`tests/tests.js`, `tests/ui/test_uoup.js`, `tests/ui/test_zavkaf.js`).
