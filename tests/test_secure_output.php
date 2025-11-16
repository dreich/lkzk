<?php

include 'functions.php';

// Тесты для функции SecureOutput

// Тест 1: Простая строка
$input1 = '<script>alert("XSS")</script>';
$expected1 = htmlspecialchars($input1, ENT_QUOTES);
$result1 = SecureOutput($input1);
assert($result1 === $expected1, "Тест 1 провален: простая строка");
echo "Тест 1 пройден: простая строка\n";

// Тест 2: Простой массив строк
$input2 = ['<b>bold</b>', '"quotes"', "'single'"];
$expected2 = [htmlspecialchars('<b>bold</b>', ENT_QUOTES), htmlspecialchars('"quotes"', ENT_QUOTES), htmlspecialchars("'single'", ENT_QUOTES)];
$result2 = SecureOutput($input2);
assert($result2 === $expected2, "Тест 2 провален: массив строк");
echo "Тест 2 пройден: массив строк\n";

// Тест 3: Вложенный массив
$input3 = ['level1' => ['level2' => '<img src="x">', 'safe' => 'ok']];
$expected3 = ['level1' => ['level2' => htmlspecialchars('<img src="x">', ENT_QUOTES), 'safe' => htmlspecialchars('ok', ENT_QUOTES)]];
$result3 = SecureOutput($input3);
assert($result3 === $expected3, "Тест 3 провален: вложенный массив");
echo "Тест 3 пройден: вложенный массив\n";

// Тест 4: Смешанный массив
$input4 = ['string' => '<div>', 'array' => ['nested' => '<span>']];
$expected4 = ['string' => htmlspecialchars('<div>', ENT_QUOTES), 'array' => ['nested' => htmlspecialchars('<span>', ENT_QUOTES)]];
$result4 = SecureOutput($input4);
assert($result4 === $expected4, "Тест 4 провален: смешанный массив");
echo "Тест 4 пройден: смешанный массив\n";

// Тест 5: Не массив (число)
$input5 = 123;
$expected5 = htmlspecialchars('123', ENT_QUOTES);
$result5 = SecureOutput($input5);
assert($result5 === $expected5, "Тест 5 провален: число");
echo "Тест 5 пройден: число\n";

// Тест 6: Пустой массив
$input6 = [];
$expected6 = [];
$result6 = SecureOutput($input6);
assert($result6 === $expected6, "Тест 6 провален: пустой массив");
echo "Тест 6 пройден: пустой массив\n";

echo "Все тесты пройдены!\n";

?>
