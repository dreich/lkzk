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

// получим режим работы системы
$ModeRow = GetRow('params', ['param' => 'system_mode']);
$_mode = $ModeRow['value'];

$ksro_kind_uid = '26003.281474976710751';
$ksro_discipline_uid = '26006.281474976727808';
$ik_kind_uid = '26003.281474976710750';
$ik_discipline_uid = '26006.281474976727807';

// редактирует только в этом режиме, поэтому берём из таблицы, где храним "редактуру"
if ($_mode == 'mode_filling')
{
  $Rows = GetTable('ksro', "`chair_id` = '$c_chair_id'");
}
// в других режимах берём из Галактики
else
{
  $nagruzka_query = GetNagruzkaBaseQuery("AND `xml_kind_of_work`.Name IN ('$ksro_kind_uid', '$ik_kind_uid') OR `xml_discipline`.UID IN ('$ksro_discipline_uid', '$ik_discipline_uid')", 'all');

  // EchoLog($nagruzka_query);
  $Rows = GetSQL($nagruzka_query);

  EchoLog($Rows);
}


$RowsByKey = [];
$Result = [];


if ($Rows)
{
  foreach ($Rows as $row)
  {
    // Когда берём из галактики, немного не совпадают имена полей таблиц
    // if ($row['lecturer_person_id'] && !$row['person_id'])
    // {
    //   $row['person_id'] = $row['lecturer_person_id'];
    // }

    // if ($row['UID_Language'] && !$row['UID_Language'])
    // {
    //   $row['UID_Language'] = $row['UID_Language'];
    // }

    // if ($row['UID_Semester'] && !$row['semester'])
    // {
    //   $row['semester'] = $row['UID_Semester'];
    // }

    // if ($row['lecturer_fio'] && !$row['fio'])
    // {
    //   $row['fio'] = $row['lecturer_fio'];
    // }


    if (!$RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"])
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"] = $row;
    }

    if ($row['UID_KindOfWork'] === $ksro_kind_uid && $row['UID_Semester'] == 1)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ksro_osen'] = $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ksro_kind_uid && $row['UID_Semester'] == 2)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ksro_vesna'] = $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ik_kind_uid && $row['UID_Semester'] == 1)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ik_osen'] = $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ik_kind_uid && $row['UID_Semester'] == 2)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ik_vesna'] = $row['Amount'];
    }
  }
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($RowsByKey));
?>