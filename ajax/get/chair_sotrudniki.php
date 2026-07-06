<?php

session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}

include '../../functions.php';
// include '../../connect/sotrudnik.php';

$c_roles = ExplodePalki($_SESSION['c_roles'], true);


// TODO проверить, что правильные коды для псевдо-кафедр (должны быть 888, 999...)
// TODO совмещение dean & zavkaf [через GET ?]
if ($c_roles['uoup'] || $c_roles['dean'])
{
  $chair_id = quote_smart($_GET['chair_id']);
  // EchoLog($chair_id);

  // защита
  if ($c_roles['dean'])
  {
    // декан+завкаф смотрит список сотрудников без параметра кафедры
    if (!$chair_id && $_roles['zavkaf'])
    {
      $chair_id = $_SESSION['c_chair_id'];
    }

    $dean_department_sql = " AND `department_id` = '$_SESSION[c_department_id]'";
  }
}
// Завкаф
else
{
  $chair_id = $_SESSION['c_chair_id'];
}

$XMLChairByCode = GetTable('xml_chair', "", "", "Code");
$chair_uid = $XMLChairByCode[$chair_id]['UID'];

// до подмены
$xml_chair = GetRow('xml_chair', ['Code' => $chair_id]);



// Авторизован зав. псевдо-кафедрой, сотрудников будем брать по коду псевдо-факультета, который у них в sotrudniki.chair_id
// т.к. берём теперь по selected_chairs_ids, там подмена не нужна
if ($_pseudo_chairs[$chair_id])
{
  // подменяем на код родителя
  $chair_id_substituted = $_pseudo_chairs[$chair_id];
}
else
{
  $chair_id_substituted = $chair_id;
}


// else
// {
//   $chair_id = $_SESSION['c_chair_id'];
// }

// TO FIX? сейчас здесь фак. завкафа
$department_id = $_SESSION['c_department_id'];
// получим режим работы системы
$ModeRow = GetRow('params', ['param' => 'system_mode']);
$_mode = $ModeRow['value'];

// $position_table_name = "position" . date('Y');

// $Sotrudniki = GetSQL("
//                   SELECT person.`id`, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`
//                   FROM `$position_table_name`
//                   JOIN `person` ON `$position_table_name`.person_id = `person`.id
//                   WHERE $position_table_name.`podrazdelenia_chain` LIKE('%|$chair_id|%')
//                 ");

// $Sotrudniki = GetRows('sotrudniki', ['chair_id' => $chair_id]);

// TypeWorkload Тип нагрузки:
// 0 — аудиторная
// 1 — неаудиторная
// Amount - кол-во часов нагрузки

// Т.к. сотрудники ГПХ в таблице sotrudniki привязаны не к кафедре, а факультету, то будем их брать по факультету авторизованного завкафа,
// а не ГПХ-шников будем искать по кафедре


// 1. Подключение к БД и проверка авторизации

// В режиме редактирования нагрузку ИК-КСРО нужно брать из таблицы ksro
if ($_mode == 'mode_filling')
{
  $_ksro_sql = "AND x.`nagruzka_type` <> 'ksro'";
  // EchoLog($chair_id);
  // TODO проверить
  // if ($_pseudo_chairs[$chair_id])
  // {
  //   // подменяем на код родителя
  //   $ksro_chair_id = $_pseudo_chairs[$chair_id];
  // }

  $KSRO = GetTable('ksro', "`chair_id` = '$chair_id'");
  // EchoLog($KSRO);
  $KSROByPersonID = [];

  if ($KSRO)
  {
    foreach ($KSRO as $ksro)
    {
      if (!$KSROByPersonID[$ksro['lecturer_person_id']][$ksro['UID_Language']])
      {
        $KSROByPersonID[$ksro['lecturer_person_id']][$ksro['UID_Language']] = $ksro;
      }
      else
      {
        safeAdd($KSROByPersonID[$ksro['lecturer_person_id']][$ksro['UID_Language']]['Amount'], $ksro['Amount']);
      }
    }
  }


  $AspiranturaKandExam = GetTable('aspirantura_kand_exam', "`deleted` <> '1'");
  $AspiranturaKandExamByPersonID = [];

  if ($AspiranturaKandExam)
  {
    foreach ($AspiranturaKandExam as $row)
    {
      $AspiranturaKandExamByPersonID[$row['lecturer_person_id']][] = $row;
    }
  }


  $AspiranturaRukAsp = GetTable('aspirantura_ruk_asp', "`deleted` <> '1'");
  $AspiranturaRukAspByPersonID = [];

  if ($AspiranturaRukAsp)
  {
    foreach ($AspiranturaRukAsp as $row)
    {
      $AspiranturaRukAspByPersonID[$row['lecturer_person_id']][] = $row;
    }
  }


  $AspiranturaRukSoisk = GetTable('aspirantura_ruk_soisk', "`deleted` <> '1'");
  $AspiranturaRukSoiskByPersonID = [];

  if ($AspiranturaRukSoisk)
  {
    foreach ($AspiranturaRukSoisk as $row)
    {
      $AspiranturaRukSoiskByPersonID[$row['lecturer_person_id']][] = $row;
    }
  }

}
// в других режимах - из Галактики
else
{
  $_ksro_sql = "AND x.`nagruzka_type` <> ''";
}

// 2. Получаем нагрузку из Галактики (русскую и английскую)
$originalLoads = [];
$query = "SELECT 
    xml_lecturer.Tab_number,
    x.UID_Lecturer,
    x.base_uid2,
    x.Amount,
    x.TypeWorkload
    -- n.type
FROM nagruzka n
JOIN xml_content_of_load x ON n.load_base_UID2 = x.base_uid2
JOIN xml_lecturer ON x.UID_Lecturer = xml_lecturer.UID
WHERE xml_lecturer.Tab_number IS NOT NULL # AND x.UID_Lecturer = '26115.281474976862936'
$_ksro_sql
";

$rows = GetSQL($query) ?: [];
foreach ($rows as $row) 
{
  $originalLoads[$row['Tab_number']][$row['base_uid2']] = 
  [
    'UID_Lecturer' => $row['UID_Lecturer'],
    'amount' => (float)$row['Amount'],
    'type_workload' => $row['TypeWorkload'],
  ];
}

// EchoLog($originalLoads);

// 3. Получаем английскую нагрузку из Галактики
$englishLoads = [];
$query = "SELECT 
    xml_lecturer.Tab_number,
    x.UID_Lecturer,
    x.base_uid2,
    x.Amount,
    x.TypeWorkload
FROM nagruzka n
JOIN xml_content_of_load x ON n.load_base_UID2 = x.base_uid2
#JOIN xml_content_of_load_staff s ON x.base_uid2 = s.base_uid2
JOIN xml_lecturer ON x.UID_Lecturer = xml_lecturer.UID
WHERE xml_lecturer.Tab_number IS NOT NULL AND x.UID_Language = '25031.945'# AND xml_lecturer.Tab_number = '1129'
$_ksro_sql
";
 
$rows = GetSQL($query) ?: [];
// EchoLog($query);
foreach ($rows as $row) 
{
  // if (!isset($englishLoads[$row['Tab_number']])) {
  //     $englishLoads[$row['Tab_number']] = [];
  // }
  // $englishLoads[$row['Tab_number']][] = [
  //     'UID_Lecturer' => $row['UID_Lecturer'],
  //     'amount' => (float)$row['Amount'],
  //     'type_workload' => $row['TypeWorkload']
  // ];

  $englishLoads[$row['Tab_number']][$row['base_uid2']] = 
  [
    'UID_Lecturer' => $row['UID_Lecturer'],
    'amount' => (float)$row['Amount'],
    'type_workload' => $row['TypeWorkload'],
  ];
}

// EchoLog($englishLoads);

// 4. Получаем переопределенную нагрузку из zavkaf_splits
$splitsLoads = [];
$query = "SELECT 
    zs.lecturer_person_id,
    zs.base_uid2,
    zs.base_uid2_new,
    zs.Amount,
    zs.LoadType,
    zs.`delete`,
    x.TypeWorkload,
    zs.lecturer_uid as UID_Lecturer
FROM zavkaf_splits zs
#JOIN xml_content_of_load x ON zs.content_of_load_uid = x.UID
JOIN xml_content_of_load x ON zs.base_uid2 = x.base_uid2
WHERE zs.`delete` = 0 AND `zavkaf_chair_uid` = '$chair_uid'";

// Возможно,  AND `zavkaf_chair_uid` = '$chair_uid' не нужно

// Debug: Log the query
// EchoLog("Executing query: " . $query);

$splitsLoads = [];
// признак того, что оригинальная нагрузка переразбита в сплитах, такие оригинальные часы плюсовать не будем
$splitsLoadsByBaseUID2 = [];

$rows = GetSQL($query) ?: [];

foreach ($rows as $row) 
{
  $splitsLoadsByBaseUID2[$row['base_uid2']] = true;

  $splitsLoads[$row['lecturer_person_id']][$row['base_uid2_new']] = 
  [
    'UID_Lecturer' => $row['UID_Lecturer'],
    'amount' => (float)$row['Amount'],
    'base_uid2_new' => $row['base_uid2_new'],
    'type_workload' => $row['TypeWorkload']
  ];
}

// EchoLog($splitsLoads);

// 5. Получаем английскую нагрузку из zavkaf_splits
$query = "SELECT 
    zs.lecturer_person_id,
    zs.lecturer_uid as UID_Lecturer,
    zs.Amount,
    zs.base_uid2,
    zs.base_uid2_new,
    x.TypeWorkload
FROM zavkaf_splits zs
#JOIN xml_content_of_load x ON zs.content_of_load_uid = x.UID
JOIN xml_content_of_load x ON zs.base_uid2 = x.base_uid2
#JOIN xml_lecturer ON x.UID_Lecturer = xml_lecturer.UID
WHERE zs.delete = 0  AND `zavkaf_chair_uid` = '$chair_uid'
  AND x.base_uid2 IN (
      SELECT DISTINCT base_uid2 
      FROM xml_content_of_load_staff 
      WHERE UID_Language = '25031.945'
  )";

// Возможно,  AND `zavkaf_chair_uid` = '$chair_uid' не нужно

$englishSplits = [];
$rows = GetSQL($query) ?: [];

foreach ($rows as $row) 
{
  $splitsLoadsByBaseUID2[$row['base_uid2']] = true;

  $englishSplits[$row['lecturer_person_id']][$row['base_uid2_new']] = 
  [
    'UID_Lecturer' => $row['UID_Lecturer'],
    'amount' => (float)$row['Amount'],
    'base_uid2_new' => $row['base_uid2_new'],
    'type_workload' => $row['TypeWorkload']
  ];

  // if (!isset($englishSplits[$row['lecturer_person_id']])) {
  //     $englishSplits[$row['lecturer_person_id']] = [];
  // }

  // $englishSplits[$row['lecturer_person_id']][] = [
  //     'UID_Lecturer' => $row['UID_Lecturer'],
  //     'amount' => (float)$row['Amount'],
  //     'type_workload' => $row['TypeWorkload']
  // ];
}
// EchoLog($englishSplits);

// 6. Получаем список сотрудников
$employees = [];

$query = "SELECT * FROM `sotrudniki` 
WHERE ((`type` <> 'gph' AND `chair_id` = ?) 
       OR (`type` = 'gph' AND `department_id` = ?))
  $dean_department_sql
  AND `date_remove` IS NULL
  ORDER BY `type` ASC # для того, чтобы (если получили одного сотрудника и ГПХ, и не ГПХ, то возьмётся не ГПХ)
  ";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('ss', $chair_id_substituted, $department_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) 
{
  // $row['person_id']
  $employees[$row['person_id']] = $row;
}

$DopDean = GetRow('dop_deans', ['chair_id' => $chair_id_substituted]);

if ($DopDean)
{
  $employees[$DopDean['person_id']] = GetRow('sotrudniki', ['person_id' => $DopDean['person_id'], 'dolzhnost' => 'декан факультета']);
}

// EchoLog($chair_id);
// EchoLog($department_id);
// EchoLog($employees);

// Батаков
// EchoLog($splitsLoads[51586]);
// EchoLog($originalLoads[51586]);
// Суворов
// EchoLog($splitsLoads[51972]);
// EchoLog($originalLoads[51972]);
// EchoLog($englishSplits[51972]);
// EchoLog($englishLoads[51972]);
// Фомина
// EchoLog($splitsLoads[70297]);
// EchoLog($originalLoads[70297]);
// EchoLog($englishSplits[70297]);
// EchoLog($englishLoads[1129]);

// 7. Объединяем данные
foreach ($employees as &$employee) 
{
    $personId = $employee['person_id'];
    
    // EchoLog($employee['lecturer_uid']);

    // Инициализируем счетчики
    $totalAmount = 0;
    $auditoriumAmount = 0;
    $engAmount = 0;
    $typeWorkload = '';
    
    // Обрабатываем английскую нагрузку из zavkaf_splits
    if (isset($englishSplits[$personId])) 
    {
      foreach ($englishSplits[$personId] as $engLoad) 
      {
        if (empty($engLoad['UID_Lecturer'])) 
        {
            // EchoLog($engLoad);
            continue;
        }
        if ($engLoad['UID_Lecturer'] != $employee['lecturer_uid']) 
        {
            continue;
        }
        
        $engAmount += $engLoad['amount'];
        // Не добавляем в общую аудиторную нагрузку здесь, т.к. это будет сделано в основном цикле
      }
    }

    // Оригинальную английскую нагрузку
    if (isset($englishLoads[$personId]) && $_mode != 'mode_filling') 
    {
      // foreach ($englishLoads[$personId] as $engLoad) 
      foreach ($englishLoads[$personId] as $base_uid2 => $engLoad) 
      {
        if (empty($engLoad['UID_Lecturer'])) {
            // EchoLog($engLoad);
            continue;
        }
        if ($engLoad['UID_Lecturer'] != $employee['lecturer_uid']) {
            continue;
        }

        // Если оригинальная (Галактика) нагрузка была перераспределена
        if ($splitsLoadsByBaseUID2[$base_uid2]) continue;
        
        $engAmount += $engLoad['amount'];
        // Не добавляем в общую аудиторную нагрузку здесь, т.к. это будет сделано в основном цикле
      }
    }

    // EchoLog($splitsLoads[50665]);
    
    // Обрабатываем переопределенную нагрузку
    if (isset($splitsLoads[$personId])) 
    {
        foreach ($splitsLoads[$personId] as $baseUid2 => $load) 
        {
            if (empty($load['UID_Lecturer'])) 
            {
                // EchoLog($load);
                continue;
            }

            if ($load['UID_Lecturer'] != $employee['lecturer_uid']) {
                continue;
            }
            
            // Проверяем, что это не английская нагрузка
            $isEnglish = false;
            if (isset($englishSplits[$personId])) {
                foreach ($englishSplits[$personId] as $engLoad) 
                {
                    if ($engLoad['UID_Lecturer'] == $load['UID_Lecturer'] && 
                        $engLoad['amount'] == $load['amount']) {
                        $isEnglish = true;
                        break;
                    }
                }
            }

            if ($personId == 50665)
            {
              // EchoLog($load);
            }
            
            // if (!$isEnglish) 
            {
              $totalAmount += $load['amount'];
              if ($load['type_workload'] == '0') 
              {
                  $auditoriumAmount += $load['amount'];
              }
            }
            
            $typeWorkload = $load['type_workload'] ?: $typeWorkload;
            
            // Удаляем из оригинальной нагрузки, если была переопределена
            if (isset($originalLoads[$personId][$baseUid2])) 
            {
              unset($originalLoads[$personId][$baseUid2]);
            }
        }
    }
    
    // Добавляем оставшуюся оригинальную нагрузку
    if (isset($originalLoads[$personId]) && $_mode != 'mode_filling') 
    {
        foreach ($originalLoads[$personId] as $base_uid2 => $load) 
        {
          // if (empty($load['UID_Lecturer'])) EchoLog($load);
          if ($load['UID_Lecturer'] != $employee['lecturer_uid']) continue;

          // Если оригинальная (Галактика) нагрузка была перераспределена
          if ($splitsLoadsByBaseUID2[$base_uid2]) continue;

          $totalAmount += $load['amount'];
          // if ($personId == 70297)
          // {
          //   EchoLog($load['amount']);
          // }
          if ($load['type_workload'] == '0') {
              $auditoriumAmount += $load['amount'];
          }
          $typeWorkload = $load['type_workload'] ?: $typeWorkload;
        }
    }

    
    if ($_mode == 'mode_filling')
    {
      // В режиме заполнения нагрузку ИК-КСРО добавим из таблицы `ksro`

      if ($KSROByPersonID[$personId])
      {
        foreach ($KSROByPersonID[$personId] as $lang_uid => $ksro_language_row)
        {
          safeAdd($totalAmount, $ksro_language_row['Amount']);

          if ($lang_uid === $_language_eng_uid)
          {
            safeAdd($engAmount, $ksro_language_row['Amount']);
          }
        }
      }


      if ($AspiranturaKandExamByPersonID[$personId])
      {
        foreach ($AspiranturaKandExamByPersonID[$personId] as $row)
        {
          safeAdd($totalAmount, $row['students_num'] * $_aspirantura_hours_per_student);
        }
      }

      if ($AspiranturaRukAspByPersonID[$personId])
      {
        foreach ($AspiranturaRukAspByPersonID[$personId] as $row)
        {
          safeAdd($totalAmount, $_aspirantura_ruk_asp_hours);
        }
      }

      if ($AspiranturaRukSoiskByPersonID[$personId])
      {
        foreach ($AspiranturaRukSoiskByPersonID[$personId] as $row)
        {
          safeAdd($totalAmount, $_aspirantura_ruk_soisk_hours);
        }
      }

    }
    
    // Добавляем результаты к данным сотрудника
    $employee['amount_sum'] = round($totalAmount, 2);
    $employee['amount_sum_auditorium'] = round($auditoriumAmount, 2);
    $employee['amount_sum_eng'] = round($engAmount, 2);
    $employee['TypeWorkload'] = $typeWorkload ?: '';
}




if ($employees)
foreach ($employees as &$sotrudnik)
{
  // if ($sotrudnik['type'] == 'sotrudnik')
  // {
  //   $sotrudnik['selected'] = true;
  // }

  if ($sotrudnik['type'] == 'gph')
  {
    $sotrudnik['stavka'] = '-';
  }
  
  // $sotrudnik['selected'] = (boolean) $sotrudnik['selected'];

  $selected_chairs_ids_arr = ExplodePalki($sotrudnik['selected_chairs_ids'], true);

  $sotrudnik['selected'] = !!$selected_chairs_ids_arr[$chair_id];
}

// $c_roles = ExplodePalki($_SESSION['c_roles'], true);

usort($employees, function($a, $b) 
{
  // Define custom order for types
  $typeOrder = [
    'sotrudnik' => 1,
    'kandidat' => 2,
    'worked' => 3,
    'gph' => 4
  ];

  // По selected (убывающе) - true/false или 1/0
  if ($a['selected'] > $b['selected']) return -1;
  if ($a['selected'] < $b['selected']) return 1;

  // Sort by type using custom order
  $aType = isset($typeOrder[$a['type']]) ? $typeOrder[$a['type']] : PHP_INT_MAX;
  $bType = isset($typeOrder[$b['type']]) ? $typeOrder[$b['type']] : PHP_INT_MAX;

  if ($aType !== $bType) return $aType - $bType;

  // По fio (возрастающе)
  return mb_strcasecmp($a['fio'], $b['fio']);
});


$SotrudnikiChairNagruzkaVisibility = GetRow('sotrudnik_chair_nagruzka_visibility', ['chair_id' => $chair_id]);

if (!$SotrudnikiChairNagruzkaVisibility)
{
  // default
  $SotrudnikiChairNagruzkaVisibility = ['visible' => '0'];
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(['sotrudniki' => array_values($employees), 'sotrudnik_chair_nagruzka_visibility' => $SotrudnikiChairNagruzkaVisibility['visible'], 'chair_name' => $xml_chair['Name']]);

?>