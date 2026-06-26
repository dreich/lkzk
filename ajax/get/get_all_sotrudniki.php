<?php

// Для раздела Аспирантура получить всех ППС

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
$XMLChairByCode = GetTable('xml_chair', '', '', 'Code');
$XMLFacultyByCode = GetTable('xml_faculty', '', '', 'Code');

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



$add = GetTable('sotrudniki', "`date_remove` IS NULL AND `fio` LIKE ('$s%') AND `lecturer_uid` <> ''");

$Sotrudniki = array_merge($Sotrudniki, $add);

// if (mb_stripos($s, 'Вак') === 0)
// {
//   $VacancyLecturer = GetRow('xml_lecturer', ['Tab_number' => '000000']);

//   $Sotrudniki[] = ['fio' => 'Вакансия', 'lecturer_person_id' => '000000', 'lecturer_uid' => $VacancyLecturer['UID']];
// }

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
      $sotrudnik['department_name'] = $XMLFacultyByCode[$sotrudnik['department_id']]['Name'];
      $sotrudnik['chair_name'] = $XMLChairByCode[$sotrudnik['chair_id']]['Name'];
      $filteredSotrudniki["$sotrudnik[person_id]-$sotrudnik[type]"] = $sotrudnik;
    }
}

// Приоритеты: чем меньше число, тем выше приоритет
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

  // Если person_id ещё нет в результате, или текущий type приоритетнее
  if (!isset($unique[$id]) || $priority[$type] < $priority[$unique[$id]['type']])
  {
      $unique[$id] = $person;
  }
}

// Сбросить ключи, чтобы получить обычный индексированный массив
$filteredSotrudniki = array_values($unique);
// $Sotrudniki = $filteredSotrudniki;


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($filteredSotrudniki));

?>

