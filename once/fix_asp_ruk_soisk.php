<?php

// Поиск неправильных привязок преподавателей в aspirantura_ruk_soisk
// Проверяем, что lecturer_uid соответствует наиболее приоритетному типу сотрудника

header("Content-type: text/plain; charset=utf-8");

include '../functions.php';

// Приоритеты типов (как в автокомплите)
$priority = [
    'sotrudnik' => 1,
    'kandidat'  => 2,
    'worked'    => 3,
    'gph'       => 4,
];

// Подгружаем справочники для расшифровки кафедр и факультетов
$XMLChairByCode   = GetTable('xml_chair',   '', '', 'Code');
$XMLFacultyByCode = GetTable('xml_faculty', '', '', 'Code');

// Загружаем всех активных сотрудников, у которых есть lecturer_uid
$allSotrudniki = GetTable('sotrudniki', "`date_remove` IS NULL AND `lecturer_uid` <> ''");

// Группируем по person_id, для каждого находим наивысший приоритет и соответствующий lecturer_uid
$bestByPersonId = [];

foreach ($allSotrudniki as $s) {
    $pid = $s['person_id'];
    $type = $s['type'];
    
    // Если тип неизвестен — пропускаем
    if (!isset($priority[$type])) {
        continue;
    }
    
    // Если person_id ещё не встречался
    if (!isset($bestByPersonId[$pid])) {
        $bestByPersonId[$pid] = $s;
        continue;
    }
    
    $currentBest = $bestByPersonId[$pid];
    
    // Сравниваем приоритеты типов (чем меньше число, тем выше приоритет)
    if ($priority[$type] < $priority[$currentBest['type']]) {
        // Текущий тип приоритетнее — заменяем
        $bestByPersonId[$pid] = $s;
    } elseif ($priority[$type] == $priority[$currentBest['type']]) {
        // Типы одинаковые — выбираем того, у кого непустое selected_chairs_ids
        $currentHasSelected = !empty($currentBest['selected_chairs_ids']);
        $newHasSelected = !empty($s['selected_chairs_ids']);
        
        if (!$currentHasSelected && $newHasSelected) {
            // У текущего лучшего пусто, а у нового непусто — заменяем
            $bestByPersonId[$pid] = $s;
        }
        // Если у обоих одинаково (оба пустые или оба непустые) — оставляем первого
    }
    // Иначе текущий тип менее приоритетный — ничего не делаем
}

echo "Всего уникальных person_id с приоритетным выбором: " . count($bestByPersonId) . "\n\n";

// Загружаем все записи из aspirantura_ruk_soisk (только активные)
$rukSoiskRows = GetTable('aspirantura_ruk_soisk', "`deleted` = '0'");

$errors = [];
$totalChecked = 0;

foreach ($rukSoiskRows as $row) {
    $pid = $row['lecturer_person_id'];
    $currentUid = $row['lecturer_uid'];
    
    // Пропускаем пустые person_id
    if (empty($pid) || $pid == '-') {
        continue;
    }
    
    $totalChecked++;
    
    // Если person_id нет в sotrudniki (странная ситуация)
    if (!isset($bestByPersonId[$pid])) {
        $errors[] = [
            'soisk_id'          => $row['id'],
            'soisk_fio'         => $row['fio'],
            'prikaz'            => $row['prikaz'],
            'person_id'         => $pid,
            'current_uid'       => $currentUid,
            'current_fio'       => $row['lecturer_fio'],
            'current_type'      => 'НЕ НАЙДЕН В sotrudniki',
            'best_uid'          => '—',
            'best_type'         => '—',
            'best_chair_name'   => '—',
            'best_faculty_name' => '—',
            'problem'           => 'person_id отсутствует в таблице sotrudniki'
        ];
        continue;
    }
    
    $best = $bestByPersonId[$pid];
    $bestUid = $best['lecturer_uid'];
    
    // Если текущий uid не совпадает с лучшим — это ошибка
    if ($currentUid != $bestUid) {
        // Находим текущий тип сотрудника (какой uid реально выбран)
        $currentType = '';
        foreach ($allSotrudniki as $s) {
            if ($s['lecturer_uid'] == $currentUid && $s['person_id'] == $pid) {
                $currentType = $s['type'];
                break;
            }
        }
        
        // Получаем названия кафедры и факультета для лучшего варианта
        $bestChairName   = isset($XMLChairByCode[$best['chair_id']])   ? $XMLChairByCode[$best['chair_id']]['Name']   : '—';
        $bestFacultyName = isset($XMLFacultyByCode[$best['department_id']]) ? $XMLFacultyByCode[$best['department_id']]['Name'] : '—';
        
        $errors[] = [
            'soisk_id'          => $row['id'],
            'soisk_fio'         => $row['fio'],
            'prikaz'            => $row['prikaz'],
            'person_id'         => $pid,
            'current_uid'       => $currentUid,
            'current_fio'       => $row['lecturer_fio'],
            'current_type'      => $currentType ?: 'неизвестно',
            'best_uid'          => $bestUid,
            'best_type'         => $best['type'],
            'best_chair_id'     => $best['chair_id'],
            'best_chair_name'   => $bestChairName,
            'best_faculty_id'   => $best['department_id'],
            'best_faculty_name' => $bestFacultyName,
            'problem'           => 'Выбран не самый приоритетный тип'
        ];
    }
}

// Вывод результатов
echo "Проверено записей: $totalChecked\n";
echo "Найдено ошибок: " . count($errors) . "\n\n";

if (count($errors) > 0) {
    echo "=== НАЙДЕННЫЕ НЕСООТВЕТСТВИЯ ===\n\n";
    
    foreach ($errors as $i => $err) {
        echo ($i + 1) . ". aspirantura_ruk_soisk.id: {$err['soisk_id']}\n";
        echo "   Соискатель: {$err['soisk_fio']}\n";
        echo "   Приказ: {$err['prikaz']}\n";
        echo "   person_id: {$err['person_id']}\n";
        echo "   ВЫБРАН: lecturer_uid = {$err['current_uid']} (type: {$err['current_type']}), ФИО: {$err['current_fio']}\n";
        echo "   ДОЛЖЕН БЫТЬ: lecturer_uid = {$err['best_uid']} (type: {$err['best_type']})\n";
        echo "   Кафедра правильного: {$err['best_chair_name']} (chair_id: {$err['best_chair_id']})\n";
        echo "   Факультет правильного: {$err['best_faculty_name']} (department_id: {$err['best_faculty_id']})\n";
        echo "   Проблема: {$err['problem']}\n\n";
    }
    
    // Также выводим в виде списка id для удобного копирования
    echo "=== ID записей для исправления ===\n";
    echo implode("\n", array_column($errors, 'soisk_id')) . "\n\n";
    
    // Статистика по типам ошибок
    echo "=== СТАТИСТИКА ПО ТИПАМ ОШИБОК ===\n";
    
    $byCurrentType = [];
    $byBestType = [];
    foreach ($errors as $err) {
        $byCurrentType[$err['current_type']] = ($byCurrentType[$err['current_type']] ?? 0) + 1;
        $byBestType[$err['best_type']] = ($byBestType[$err['best_type']] ?? 0) + 1;
    }
    
    echo "\nВыбранный тип (ошибочный):\n";
    foreach ($byCurrentType as $type => $count) {
        echo "  $type: $count\n";
    }
    
    echo "\nДолжен быть тип (правильный):\n";
    foreach ($byBestType as $type => $count) {
        echo "  $type: $count\n";
    }
}

?>