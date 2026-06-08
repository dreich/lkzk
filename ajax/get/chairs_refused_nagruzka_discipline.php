<?

// Получить данные в таблицу отказов факультетов
session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}

// Флаг экспорта
$is_export = (isset($_GET['export']));

// 1. Задаем константу с нужной высотой (например, 40)
define('DEFAULT_ROW_HEIGHT', 40);

// Проверяем, что запрос пришел через AJAX, ИЛИ это запрос на скачивание файла
if (!$is_export && (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest')) {
    http_response_code(403);
    exit('Forbidden');
}

include '../../functions.php';

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

if ($c_roles['uoup'])
{
  $c_chair_id = $_SESSION['c_chair_id'];

  // $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);

  // $XMLContentOfLoad = GetRows('xml_content_of_load', ['UID_Chair' => $XMLChair['UID']]);

  $dop_sql = "AND `status` IN ('refused', 'done_refused')
              #AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'
              #AND `base_uid` = '26589.281474976773927'
              ORDER BY `original_uid`
              # TMP
              #AND `UID_Discipline` = '26006.281474976725278'
              #LIMIT 15
  ";

  $Nagruzka = PrepareNagruzka(GetSQL(GetNagruzkaBaseQuery($dop_sql, 'all', true)));

  
}


// Получим из лога последнее собщения action_name = 'require_admin_change'
if ($Nagruzka)
{
  foreach ($Nagruzka as &$nagruzka)
  {
    $History = GetSQL("SELECT * FROM `log` WHERE `action_name` = 'refused' AND `load_base_UID2` = '$nagruzka[base_uid2]' ORDER BY `id` DESC LIMIT 1");

    $nagruzka['refused_change_message'] = $History[0]['message'];
    // оставить дату с временем
    $nagruzka['refused_date'] = $History[0]['datetime']; // date('Y-m-d', strtotime($History[0]['datetime']));

  }
}




// ==========================================
// ЛОГИКА ЭКСПОРТА В EXCEL (XLSX)
// ==========================================
if ($is_export) {
    require '../../vendor/autoload.php'; 
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->getDefaultRowDimension()->setRowHeight(DEFAULT_ROW_HEIGHT);

    // 1. Заголовки столбцов
    $headers = [
        'Факультет исполнитель', 'Кафедра исполнитель', 'Факультет владелец', 'Аббр', 'Дисциплина', 'Группа', 
        'Уровень образования', 'Направление подготовки', 'Язык программы', 
        'Форма обучения', 'Семестр', 'Количество студентов', 'Вид работ', 
        'Профиль/направленность программы', 'Курс', 'Количество часов', 
        'Сообщение об отказе', 'Дата отказа'
    ];
    
    $keys = [
        'department_name', 'chair_name', 'department_owner_name', 'Abbr', 'discipline_name', 'group_name',
        'education_level', 'napravlenie', 'language', 'form_obuchenia',
        'UID_Semester', 'StudentAmount', 'kind_of_work', 'napravlennost',
        'UID_Course', 'Amount', 'refused_change_message', 'refused_date'
    ];

    // Записываем заголовки
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    // ==========================================
    // 2. Настройка ширины столбцов (динамический плоский массив)
    // ==========================================
    $widths = [
        18, // Факультет исполнитель
        30, // Кафедра исполнитель
        18, // Факультет владелец
        10, // Аббр
        35, // Дисциплина
        14, // Группа
        18, // Уровень образования
        30, // Направление подготовки
        15, // Язык
        15, // Форма обучения
        10, // Семестр
        14, // Количество студентов
        20, // Вид работ
        35, // Профиль/направленность программы
        8,  // Курс
        12, // Количество часов
        35, // Сообщение об отказе
        18  // Дата отказа
    ];

    // Автоматически переводим порядковый индекс (0, 1, 2...) в имя столбца Excel (A, B, C...)
    foreach ($widths as $index => $widthValue) {
        $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $sheet->getColumnDimension($colName)->setWidth($widthValue);
    }

    // ==========================================
    // 3. Заполнение данными (без жестких букв для $colNum)
    // ==========================================
    $rowNum = 2;
    foreach ($Nagruzka as $row) {
        // Начинаем с 1-го столбца (что соответствует 'A')
        $colIndex = 1; 
        
        foreach ($keys as $key) {
            $value = '';
            if (isset($row[$key])) {
                // Сначала меняем любые <br>, <br/>, <br > на перенос строки Excel (\n)
                $value = preg_replace('/<br\s*\/?>/i', "\n", $row[$key]);
                // Затем удаляем все остальные HTML-теги
                $value = strip_tags($value);
            }
            
            // Динамически получаем букву текущего столбца из его числового индекса
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($colLetter . $rowNum, $value);
            $colIndex++;
        }
        $rowNum++;
    }

    // ==========================================
    // 4. Стилизация для красивого отображения переносов
    // ==========================================
    $lastRow = $rowNum > 2 ? $rowNum - 1 : 2;

    // Динамически вычисляем букву самой последней колонки на основе количества ключей
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($keys));

    // Включаем перенос текста и выравнивание по верху сразу для ВСЕХ ячеек (динамический диапазон, например, A1:Q5)
    $sheet->getStyle('A1:' . $lastCol . $lastRow)
          ->getAlignment()
          ->setWrapText(true)
          ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

    // Стилизация шапки (жирный шрифт и центрирование по вертикали)
    $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
    $sheet->getStyle('A1:' . $lastCol . '1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

    // Отдаем файл в браузер
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="nagruzka_refused.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}




header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Nagruzka));


?>