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

  $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);

  $XMLContentOfLoad = GetRows('xml_content_of_load', ['UID_Chair' => $XMLChair['UID']]);

  $dop_sql = "AND `status` IN ('require_admin_change', 'done_change')
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
    $History = GetSQL("SELECT * FROM `log` WHERE `action_name` = 'require_admin_change' AND `load_base_UID2` = '$nagruzka[base_uid2]' ORDER BY `id` DESC LIMIT 1");

    // EchoLog($nagruzka['base_uid2']);
    // EchoLog($History);

    $nagruzka['require_admin_change_message'] = $History[0]['message'];
    $nagruzka['require_admin_change_date'] = $History[0]['datetime']; //date('Y-m-d', strtotime($History[0]['datetime']));

  }
}




// ==========================================
// ЛОГИКА ЭКСПОРТА В EXCEL (XLSX)
// ==========================================
if ($is_export) 
{
    require '../../vendor/autoload.php'; 
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->getDefaultRowDimension()->setRowHeight(DEFAULT_ROW_HEIGHT);

    // 1. Заголовки столбцов (названия последних изменены под контекст)
    $headers = [
        'Факультет исполнитель', 'Кафедра исполнитель', 'Факультет владелец', 'Аббр', 'Дисциплина', 'Группа', 
        'Уровень образования', 'Направление подготовки', 'Язык программы', 
        'Форма обучения', 'Семестр', 'Количество студентов', 'Вид работ', 
        'Профиль/направленность программы', 'Курс', 'Количество часов', 
        'Сообщение об изменении', 'Дата изменения'
    ];
    
    // Ключи массива $Nagruzka
    $keys = [
        'department_name', 'chair_name', 'department_owner_name', 'Abbr', 'discipline_name', 'group_name',
        'education_level', 'napravlenie', 'language', 'form_obuchenia',
        'UID_Semester', 'StudentAmount', 'kind_of_work', 'napravlennost',
        'UID_Course', 'Amount', 'require_admin_change_message', 'require_admin_change_date'
    ];

    // Записываем заголовки
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    // 2. Настройка ширины столбцов (адаптировано под второй скриншот)
    $widths = [
        18, // Факультет исполнитель
        30, // Кафедра исполнитель
        18, // Факультет владелец
        16, // Аббр
        20, // Дисциплина
        15, // Группа
        16, // Уровень образования
        22, // Направление подготовки
        12, // Язык
        15, // Форма обучения
        10, // Семестр
        12, // Кол-во студентов
        18, // Вид работ
        25, // Профиль/направленность
        8,  // Курс
        12, // Кол-во часов
        35, // Сообщение об изменении
        18  // Дата изменения
    ];

    // Автоматически превращаем индекс массива (0, 1, 2...) в буквы Excel (A, B, C...)
    foreach ($widths as $index => $widthValue) {
        $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
        $sheet->getColumnDimension($colName)->setWidth($widthValue);
    }

    // 3. Заполнение данными
    $rowNum = 2;
    foreach ($Nagruzka as $row) {
        $colNum = 'A';
        foreach ($keys as $key) {
            $value = '';
            if (isset($row[$key])) {
                // Сначала меняем любые <br>, <br/>, <br > на перенос строки Excel (\n)
                $value = preg_replace('/<br\s*\/?>/i', "\n", $row[$key]);
                // Затем удаляем все остальные HTML-теги
                $value = strip_tags($value);
            }
            
            $sheet->setCellValue($colNum . $rowNum, $value);
            $colNum++;
        }
        $rowNum++;
    }

    // 4. Стилизация для красивого отображения переносов
    $lastRow = $rowNum > 2 ? $rowNum - 1 : 2;

    // Включаем перенос строк. 
    // Добавлены столбцы K, L, O, так как на втором скрине видно, что даже шапки там переносятся.
    // $wrapColumns = ['A', 'B', 'D', 'G', 'K', 'L', 'M', 'O', 'P'];
    // foreach ($wrapColumns as $colName) {
    //     $sheet->getStyle($colName . '1:' . $colName . $lastRow) // Начинаем с 1 строки, чтобы шапки тоже переносились
    //           ->getAlignment()
    //           ->setWrapText(true);
    // }

    // Динамически получаем букву последнего столбца (в нашем случае вернет 'R')
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($keys));

    // Применяем перенос и выравнивание ко ВСЕМ столбцам от A до R (или сколько их там будет)
    $sheet->getStyle('A1:' . $lastCol . $lastRow)
          ->getAlignment()
          ->setWrapText(true)
          ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

    // Стилизация шапки
    $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
    $sheet->getStyle('A1:' . $lastCol . '1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    
    // Высоту первой строки можно не задавать жестко, если мы включили WrapText для шапок (строка 1)
    // Excel сам подберет высоту под перенесенный текст заголовков.

    // Отдаем файл в браузер с новым именем
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="nagruzka_changes.xlsx"');
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