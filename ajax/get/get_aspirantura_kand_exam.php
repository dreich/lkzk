<?php

// Получение нагрузки типа Аспирантура кандидатские экзамены
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

$filter = $_COOKIE['global_nagruzka_filter'];

// $Chairs = GetTable('xml_chair', "", "", 'Code', 'Code, Name');

if ($filter == 'assigned')
{
  $filter_sql = "AND `lecturer_uid` <> ''";
}
elseif ($filter == 'not_assigned')
{
  $filter_sql = "AND `lecturer_uid` = ''";
}

$Nagruzka = GetSQL("SELECT *
                    FROM `aspirantura_kand_exam`
                    WHERE `deleted` <> 1 $filter_sql
                  ");

if ($Nagruzka)
{
  foreach ($Nagruzka as &$nagruzka)
  {
    $nagruzka['chair_name'] = $nagruzka['chair_name'] ? $nagruzka['chair_name'] : $nagruzka['department_name'];
  }
}

echo json_encode($Nagruzka);
?>