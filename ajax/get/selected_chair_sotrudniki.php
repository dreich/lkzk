<?php

// Для раздела Нагрузка поиск сотрудников кафедры 

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


$chair_id = $_SESSION['c_chair_id'];
$s = quote_smart($_GET['s']);
// $fio_array = explode(' ', $s);
// $fio_array = quote_smart($fio_array);

// if ($fio_array[0]) $surname_sql = "AND `surname` LIKE ('%$fio_array[0]%')";
// if ($fio_array[1]) $name_sql = "AND `name` LIKE ('%$fio_array[1]%')";
// if ($fio_array[2]) $patronymic_sql = "AND `patronymic` LIKE ('%$fio_array[2]%')";

// $position_table_name = "position" . date('Y');

// $Sotrudniki = GetSQL("
//                   SELECT person.`id`, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`
//                   FROM `$position_table_name`
//                   JOIN `person` ON `$position_table_name`.person_id = `person`.id
//                   WHERE $position_table_name.`podrazdelenia_chain` LIKE('%|$chair_id|%')
//                 ");

$Sotrudniki = GetTable('sotrudniki', "`chair_id` = '$chair_id' AND  `selected` = '1' AND `fio` LIKE ('%$s%')");

if (mb_stripos('Вак', $s) === 0)
{
  $Sotrudniki[] = ['fio' => 'Вакансия', 'lecturer_person_id' => '000000'];
}


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Sotrudniki));

?>

