<?php

// Получение нагрузки КСРО по кафедре
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

$c_chair_id = $_SESSION['c_chair_id'];

$Rows = GetTable('ksro', "`chair_id` = '$c_chair_id'");

$RowsByKey = [];
$Result = [];

$ksro_kind_uid = '26003.281474976710751';
$ksro_discipline_uid = '26006.281474976727808';
$ik_kind_uid = '26003.281474976710750';
$ik_discipline_uid = '26006.281474976727807';

if ($Rows)
{
  foreach ($Rows as $row)
  {
    if (!$RowsByKey["$row[person_id]-$row[language_uid]"])
    {
      $RowsByKey["$row[person_id]-$row[language_uid]"] = $row;
    }

    if ($row['UID_KindOfWork'] === $ksro_kind_uid && $row['semester'] == 1)
    {
      $RowsByKey["$row[person_id]-$row[language_uid]"]['ksro_osen'] = $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ksro_kind_uid && $row['semester'] == 2)
    {
      $RowsByKey["$row[person_id]-$row[language_uid]"]['ksro_vesna'] = $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ik_kind_uid && $row['semester'] == 1)
    {
      $RowsByKey["$row[person_id]-$row[language_uid]"]['ik_osen'] = $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ik_kind_uid && $row['semester'] == 2)
    {
      $RowsByKey["$row[person_id]-$row[language_uid]"]['ik_vesna'] = $row['Amount'];
    }
  }
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($RowsByKey));
?>