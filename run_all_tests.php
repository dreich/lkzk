<?php

// Скрипт для запуска всех тестов из папки tests/

echo "Запуск всех тестов...\n\n";

$testFiles = glob('tests/*.php');

if (empty($testFiles)) {
    echo "Тесты не найдены в папке tests/.\n";
    exit(1);
}

$passed = 0;
$total = count($testFiles);

foreach ($testFiles as $testFile) {
    echo "=== Запуск теста: " . basename($testFile) . " ===\n";
    try {
        include $testFile;
        echo "✓ Тест завершен успешно\n\n";
        $passed++;
    } catch (Exception $e) {
        echo "✗ Ошибка в тесте: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Результаты ===\n";
echo "Пройдено тестов: $passed из $total\n";

if ($passed === $total) {
    echo "Все тесты пройдены! 🎉\n";
    exit(0);
} else {
    echo "Некоторые тесты провалены.\n";
    exit(1);
}

?>
