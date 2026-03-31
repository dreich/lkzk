<?php
include '../connect.php'; // подключаемся к БД

// Проверяем, что есть таблицы
$result = $mysqli->query("SHOW TABLES LIKE 'zavkaf_splits'");
if ($result->num_rows == 0) {
    die("Таблица zavkaf_splits не найдена");
}

$result = $mysqli->query("SHOW TABLES LIKE 'xml_lecturer'");
if ($result->num_rows == 0) {
    die("Таблица xml_lecturer не найдена");
}

// 1. Получаем все строки из zavkaf_splits, где lecturer_uid не пуст и не '-1'
$query = "
    SELECT id, lecturer_uid, chair_uid 
    FROM zavkaf_splits 
    WHERE lecturer_uid IS NOT NULL 
      AND lecturer_uid != '' 
      AND lecturer_uid != '-1'
";

$result = $mysqli->query($query);

if (!$result) {
    die("Ошибка запроса: " . $mysqli->error);
}

echo "Найдено строк для обработки: " . $result->num_rows . "\n";

$updated = 0;
$not_found = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $lecturer_uid = $row['lecturer_uid'];
    
    // 2. Ищем в xml_lecturer запись с таким UID
    $stmt = $mysqli->prepare("SELECT UID_Chair FROM xml_lecturer WHERE UID = ?");
    $stmt->bind_param('s', $lecturer_uid);
    $stmt->execute();
    $lecturer_result = $stmt->get_result();
    
    if ($lecturer_row = $lecturer_result->fetch_assoc()) {
        $chair_uid = $lecturer_row['UID_Chair'];
        
        // 3. Обновляем chair_uid в zavkaf_splits
        $update_stmt = $mysqli->prepare("
            UPDATE zavkaf_splits 
            SET chair_uid = ? 
            WHERE id = ?
        ");
        $update_stmt->bind_param('si', $chair_uid, $id);
        
        if ($update_stmt->execute()) {
            $updated++;
            echo "✓ ID $id: lecturer_uid = $lecturer_uid → chair_uid = $chair_uid\n";
        } else {
            echo "✗ Ошибка обновления ID $id: " . $update_stmt->error . "\n";
        }
        $update_stmt->close();
        
    } else {
        $not_found++;
        echo "✗ ID $id: lecturer_uid = $lecturer_uid - не найден в xml_lecturer\n";
    }
    
    $stmt->close();
}

echo "\n=== ИТОГО ===\n";
echo "Обновлено: $updated\n";
echo "Не найдено в xml_lecturer: $not_found\n";

$result->free();
?>

