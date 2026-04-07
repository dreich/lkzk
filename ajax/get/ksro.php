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

// получим режим работы системы
$ModeRow = GetRow('params', ['param' => 'system_mode']);
$_mode = $ModeRow['value'];
// Флаг только у УОУП, чтобы не грузить очень много данных
$_only_stat = $_GET['only_stat'];


$c_roles = ExplodePalki($_SESSION['c_roles'], true);
$lecturer_uid = isset($_GET['lecturer_uid']) ? quote_smart($_GET['lecturer_uid']) : '';


// EchoLog($c_roles);

// редактирует только в этом режиме, поэтому берём из таблицы `ksro`, где храним "редактуру"
if ($_mode == 'mode_filling')
{
  if ($c_roles['zavkaf'])
  {
    $c_chair_id = $_SESSION['c_chair_id'];
    $_chair_sql = "`chair_id` = '$c_chair_id'";

    if ($lecturer_uid)
    {
      $_lecturer_uid_sql = "AND `uid` = '$lecturer_uid'";
    }
  }
  elseif ($c_roles['uoup'])
  {
    $_chair_id = quote_smart($_GET['chair_id']);
    if ($_chair_id)
    {
      $_chair_sql = "`chair_id` = '$_chair_id'";
    }
  }
  

  $Rows = GetTable('ksro', "$_chair_sql $_lecturer_uid_sql");
  // EchoLog($Rows);
}
// в других режимах берём из Галактики
else
{
  if ($c_roles['zavkaf'])
  {
    $c_chair_id = $_SESSION['c_chair_id'];
    $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);
    $chair_id_sql = "AND xml_content_of_load.`UID_Chair` = '$XMLChair[UID]'";
  }

  // УОУП просматривает нагрузку кафедры
  if ($c_roles['uoup'] && $_GET['chair_id'])
  {
    $_chair_id = quote_smart($_GET['chair_id']);
    $XMLChair = GetRow('xml_chair', ['Code' => $_chair_id]);
    $chair_id_sql = "AND `UID_Chair` = '$XMLChair[UID]'";
  }

  if ($lecturer_uid)
  {
    $lecturer_uid_sql = "AND `UID_Lecturer` = '$lecturer_uid'";
  }

  $nagruzka_query = GetNagruzkaBaseQuery("
    AND (`xml_kind_of_work`.Name IN ('$ksro_kind_uid', '$ik_kind_uid') OR `xml_discipline`.UID IN ('$ksro_discipline_uid', '$ik_discipline_uid'))
    $chair_id_sql
    $lecturer_uid_sql
    ", 'all');

  // EchoLog($nagruzka_query);
  $Rows = GetSQL($nagruzka_query);

  // EchoLog($Rows);
}


$RowsByKey = [];
$Result = [];
$Stat = [];

if ($Rows)
{
  foreach ($Rows as $row)
  {
    if (!$RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"])
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"] = $row;
    }

    if ($row['UID_KindOfWork'] === $ksro_kind_uid && $row['UID_Semester'] == 1)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ksro_osen'] = $row['Amount'];
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ids']['id_ksro_osen'] = $row['id'];
      $Stat['assigned']['sum'] = $Stat['total']['sum'] += (float) $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ksro_kind_uid && $row['UID_Semester'] == 2)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ksro_vesna'] = $row['Amount'];
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ids']['id_ksro_vesna'] = $row['id'];
      $Stat['assigned']['sum'] = $Stat['total']['sum'] += (float) $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ik_kind_uid && $row['UID_Semester'] == 1)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ik_osen'] = $row['Amount'];
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ids']['id_ik_osen'] = $row['id'];
      $Stat['assigned']['sum'] = $Stat['total']['sum'] += (float) $row['Amount'];
    }
    elseif ($row['UID_KindOfWork'] === $ik_kind_uid && $row['UID_Semester'] == 2)
    {
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ik_vesna'] = $row['Amount'];
      $RowsByKey["$row[lecturer_person_id]-$row[UID_Language]"]['ids']['id_ik_vesna'] = $row['id'];
      $Stat['assigned']['sum'] = $Stat['total']['sum'] += (float) $row['Amount'];
    }
  }
}

// для скорости, где достаточно только статистики
if ($_only_stat) $RowsByKey = [];

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
// echo json_encode(array_values($RowsByKey));
$ret_arr = ['nagruzka' => array_values($RowsByKey), 'stat' => $Stat ? $Stat : new stdClass];
echo json_encode($ret_arr);
?>