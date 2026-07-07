<?php

// Получение нагрузки типа Аспирантура руководство соискателями
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

$filter = $_COOKIE['global_nagruzka_filter'];

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

// $Chairs = GetTable('xml_chair', "", "", 'Code', 'Code, Name');

if ($filter == 'assigned')
{
  $filter_sql = "AND `lecturer_uid` <> ''";
}
elseif ($filter == 'not_assigned')
{
  $filter_sql = "AND `lecturer_uid` = ''";
}

if ($c_roles['zavkaf'])
{
  $chair_sql = "AND `lecturer_chair_id` = '$_SESSION[c_chair_id]'";
}

if ($_GET['chair_id'])
{
  $chair_id = quote_smart($_GET['chair_id']);

  $chair_sql = "AND `lecturer_chair_id` = '$chair_id'";
}

if ($_GET['lecturer_uid'])
{
  $lecturer_uid = quote_smart($_GET['lecturer_uid']);
  
  $lecturer_sql = "AND `lecturer_uid` = '$lecturer_uid'";
}


$Nagruzka = GetSQL("SELECT *
                    FROM `aspirantura_ruk_soisk`
                    WHERE `deleted` <> 1 
                    $filter_sql
                    $chair_sql
                    $lecturer_sql
                  ");

$NagruzkaByPerson = [];

if ($Nagruzka)
{
  foreach ($Nagruzka as $row)
  {
    if (!$NagruzkaByPerson["$row[fio]-$row[prikaz]"])
    {
      $NagruzkaByPerson["$row[fio]-$row[prikaz]"] = $row;
    }
    
    $NagruzkaByPerson["$row[fio]-$row[prikaz]"]['ids'][] = $row['id'];
  }
}

echo json_encode(array_values($NagruzkaByPerson));
?>