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
  if ($_pseudo_chairs[$chair_id])
  {
    $chair_id = $department_id;
  }

  $chair_dep_sql = "((`type` <> 'gph' AND `chair_id` = '$chair_id') OR (`type` = 'gph' AND `department_id` = '$department_id'))";
}

$additional = GetTable('sotrudniki', "$chair_dep_sql AND `selected` = '1' AND `date_remove` IS NULL AND `fio` LIKE ('$s%') AND `lecturer_uid` <> ''");

$Sotrudniki = array_merge($Sotrudniki, $additional);

if (mb_stripos($s, 'Вак') === 0)
{
  $VacancyLecturer = GetRow('xml_lecturer', ['Tab_number' => '000000']);

  $Sotrudniki[] = ['fio' => 'Вакансия', 'lecturer_person_id' => '000000', 'lecturer_uid' => $VacancyLecturer['UID']];
}

$filteredSotrudniki = [];

foreach ($Sotrudniki as $sotrudnik) {
    // Заполняем dolzhnost_hint
    $sotrudnik['dolzhnost_hint'] = ($sotrudnik['type'] == 'gph') 
        ? 'ГПХ' 
        : $sotrudnik['dolzhnost'];
    
    // Оставляем только тех, кто есть в справочнике xml_lecturer для подстраховки
    if (isset($XMLLecturerByUID[$sotrudnik['lecturer_uid']])) 
    {
      $filteredSotrudniki[] = $sotrudnik;
    }
}

$Sotrudniki = $filteredSotrudniki;


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Sotrudniki));

?>

