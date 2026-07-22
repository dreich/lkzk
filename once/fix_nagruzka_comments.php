<?

include '../functions.php';


// Используем существующее подключение $mysqli
// $mysqli уже должно быть создано и подключено к БД

// Режим работы: false = только просмотр (readonly), true = выполнить обновления
$executeUpdates = true;

echo "========================================\n";
echo "РЕЖИМ: " . ($executeUpdates ? "ВЫПОЛНЕНИЕ ОБНОВЛЕНИЙ" : "ТОЛЬКО ПРОСМОТР (READONLY)") . "\n";
echo "========================================\n\n";

// 1. Находим все "базовые" UID (ровно 2 числа через точку) с непустым комментарием
$sqlBase = "SELECT load_base_UID2, comment_to_admin 
            FROM nagruzka 
            WHERE comment_to_admin IS NOT NULL 
              AND comment_to_admin != '' 
              AND load_base_UID2 REGEXP '^[0-9]+\\.[0-9]+$'
            ORDER BY load_base_UID2";

$resultBase = $mysqli->query($sqlBase);

if (!$resultBase) {
    die("Ошибка запроса базовых записей: " . $mysqli->error . "\n");
}

$baseRecords = [];
while ($row = $resultBase->fetch_assoc()) {
    $baseRecords[] = $row;
}
$resultBase->free();

echo "Найдено базовых записей с комментариями: " . count($baseRecords) . "\n\n";

$totalChildRecords = 0;
$totalUpdates = 0;

// 2. Для каждой базовой записи ищем дочерние
foreach ($baseRecords as $index => $baseRecord) {
    $baseUid = $baseRecord['load_base_UID2'];
    $comment = $baseRecord['comment_to_admin'];
    
    $pattern = $baseUid . '.%';
    
    // Проверяем ВСЕ дочерние записи (и с комментарием, и без)
    $stmtCheck = $mysqli->prepare("SELECT load_base_UID2, comment_to_admin, status
                                    FROM nagruzka 
                                    WHERE load_base_UID2 LIKE ? 
                                      AND load_base_UID2 != ?
                                    ORDER BY load_base_UID2");
    
    if (!$stmtCheck) {
        echo "Ошибка подготовки запроса: " . $mysqli->error . "\n";
        continue;
    }
    
    $stmtCheck->bind_param("ss", $pattern, $baseUid);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    $allChildren = [];
    while ($row = $resultCheck->fetch_assoc()) {
        $allChildren[] = $row;
    }
    $stmtCheck->close();
    
    // Разделяем на те, где комментарий пустой (будем обновлять) и где уже есть (пропустим)
    $toUpdate = [];
    $alreadyHasComment = [];
    
    foreach ($allChildren as $child) {
        if (empty($child['comment_to_admin'])) {
            $toUpdate[] = $child;
        } else {
            $alreadyHasComment[] = $child;
        }
    }
    
    if (count($allChildren) > 0) {
        echo "[" . ($index + 1) . "] Базовый UID: {$baseUid}\n";
        echo "    Комментарий: " . mb_substr($comment, 0, 150) . (mb_strlen($comment) > 150 ? '...' : '') . "\n";
        echo "    Всего дочерних записей: " . count($allChildren) . "\n";
        echo "    ├─ Уже с комментарием: " . count($alreadyHasComment) . " (пропускаем)\n";
        echo "    └─ Без комментария: " . count($toUpdate) . " (будут обновлены)\n";
        
        // Показываем дочерние записи, которые будут обновлены
        if (count($toUpdate) > 0) {
            echo "    Записи для обновления:\n";
            foreach ($toUpdate as $i => $child) {
                $prefix = ($i === count($toUpdate) - 1) ? "    └─ " : "    ├─ ";
                echo "{$prefix}{$child['load_base_UID2']} [status: {$child['status']}]\n";
            }
        }
        
        // Показываем дочерние записи с существующими комментариями
        if (count($alreadyHasComment) > 0) {
            echo "    Записи с существующим комментарием (НЕ обновляются):\n";
            foreach ($alreadyHasComment as $i => $child) {
                $prefix = ($i === count($alreadyHasComment) - 1) ? "    └─ " : "    ├─ ";
                $existingComment = mb_substr($child['comment_to_admin'], 0, 80);
                echo "{$prefix}{$child['load_base_UID2']} [status: {$child['status']}] \"{$existingComment}...\"\n";
            }
        }
        
        echo "\n";
        
        // 3. Выполняем обновления, если разрешено
        if ($executeUpdates && count($toUpdate) > 0) {
            $stmtUpdate = $mysqli->prepare("UPDATE nagruzka 
                                           SET comment_to_admin = ? 
                                           WHERE load_base_UID2 = ?");
            
            if (!$stmtUpdate) {
                echo "Ошибка подготовки запроса обновления: " . $mysqli->error . "\n";
                continue;
            }
            
            foreach ($toUpdate as $child) {
                $stmtUpdate->bind_param("ss", $comment, $child['load_base_UID2']);
                if ($stmtUpdate->execute()) {
                    $totalUpdates++;
                } else {
                    echo "    Ошибка при обновлении {$child['load_base_UID2']}: " . $stmtUpdate->error . "\n";
                }
            }
            $stmtUpdate->close();
        }
        
        $totalChildRecords += count($toUpdate);
    }
    
    // Для отладки можно ограничить вывод первых N записей
    // if ($index >= 4) break; // Раскомментировать для теста на первых 5 записях
}

echo "========================================\n";
echo "ИТОГО:\n";
echo "Базовых записей с комментарием: " . count($baseRecords) . "\n";
echo "Дочерних записей без комментария: {$totalChildRecords}\n";

if ($executeUpdates) {
    echo "ВЫПОЛНЕНО обновлений: {$totalUpdates}\n";
} else {
    echo "РЕЖИМ READONLY - обновления НЕ выполнялись\n";
    echo "\nДля выполнения обновлений измените \$executeUpdates = true;\n";
}
echo "========================================\n";

?>