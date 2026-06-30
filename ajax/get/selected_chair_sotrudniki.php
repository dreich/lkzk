<?php

// Для раздела Нагрузка поиск сотрудников кафедры 

session_name('lkzk');
session_start();

include '../../functions.php';
// include '../../connect/sotrudnik.php';

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}

$XMLLecturerByUID = GetTable('xml_lecturer', "", "", "UID");

if ($c_roles['zavkaf'])
{
  $chair_id = $_SESSION['c_chair_id'];
  $department_id = $_SESSION['c_department_id'];
}
// видимо, сделано для рук-ля аспирантуры
else
{
  $chair_id = quote_smart($_GET['chair_id']);
  $xml_chair = GetRow('xml_chair', ['Code' => $chair_id]);

  $xml_faculty = GetRow('xml_faculty', ['UID' => $xml_chair['UID_Faculty']]);

  $department_id = $xml_faculty['Code'];
}

$s = quote_smart($_GET['s']);

// Если один препод в распределении, предложим его удалить
if ($_GET['lectors_num'] == 1 && $s == '-')
{
  $Sotrudniki = [['fio' => '-', 'lecturer_person_id' => '-', 'lecturer_uid' => '-']];
}
else
{
  $Sotrudniki = [];
}

// Т.к. сотрудники ГПХ в таблице sotrudniki привязаны не к кафедре, а факультету, то будем их брать по факультету авторизованного завкафа,
// а не ГПХ-шников будем искать по кафедре

if ($c_roles['ruk_aspirantura'])
{
  $chair_dep_sql = '1';
}
else
{
  // if ($_pseudo_chairs[$chair_id])
  // {
  //   $chair_id = $department_id;
  // }

  // $chair_dep_sql = "((`type` <> 'gph' AND `chair_id` = '$chair_id') OR (`type` = 'gph' AND `department_id` = '$department_id'))";
  $chair_dep_sql = "`selected_chairs_ids` LIKE('%|$chair_id|%')";
}

$additional = GetTable('sotrudniki', "$chair_dep_sql AND `date_remove` IS NULL AND `fio` LIKE ('$s%') AND `lecturer_uid` <> ''");

$Sotrudniki = array_merge($Sotrudniki, $additional);

if (mb_stripos($s, 'Вак') === 0)
{
  $VacancyLecturer = GetRow('xml_lecturer', ['Tab_number' => '000000']);

  $Sotrudniki[] = ['fio' => 'Вакансия', 'lecturer_person_id' => '000000', 'lecturer_uid' => $VacancyLecturer['UID']];
}

$filteredSotrudniki = [];

foreach ($Sotrudniki as $sotrudnik) 
{
  // Заполняем dolzhnost_hint
  $sotrudnik['dolzhnost_hint'] = ($sotrudnik['type'] == 'gph') 
      ? 'ГПХ' 
      : $sotrudnik['dolzhnost'];
  
  // Оставляем только тех, кто есть в справочнике xml_lecturer для подстраховки
  if (isset($XMLLecturerByUID[$sotrudnik['lecturer_uid']]) || $sotrudnik['lecturer_uid'] == '-' || $sotrudnik['lecturer_person_id'] == '000000') 
  {
    $filteredSotrudniki[] = $sotrudnik;
  }
}

// Приоритеты типов: чем меньше число, тем выше приоритет
$priority = [
    'sotrudnik' => 1,
    'kandidat'  => 2,
    'worked'    => 3,
    'gph'       => 4,
];

$unique = [];

foreach ($filteredSotrudniki as $person) 
{
  $id = $person['person_id'];
  $type = $person['type'];
  
  
  // Если person_id ещё нет в результате
  if (!isset($unique[$id])) 
  {
    $unique[$id] = $person;
    continue;
  }
  
  // Сравниваем приоритеты типов
  if ($priority[$type] < $priority[$unique[$id]['type']]) 
  {
    // Текущий тип приоритетнее — заменяем
    $unique[$id] = $person;
  } 
  elseif ($priority[$type] == $priority[$unique[$id]['type']]) 
  {
    // Типы одинаковые — выбираем того, у кого непустое selected_chairs_ids
    $currentHasSelected = !empty($unique[$id]['selected_chairs_ids']);
    $newHasSelected = !empty($person['selected_chairs_ids']);
    
    if (!$currentHasSelected && $newHasSelected) 
    {
        // У текущего лучшего пусто, а у нового непусто — заменяем
        $unique[$id] = $person;
    }
    // Если у обоих одинаково — оставляем первого
  }
}

// Сбросить ключи, чтобы получить обычный индексированный массив
$Sotrudniki = array_values($unique);


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode($Sotrudniki);

?>

