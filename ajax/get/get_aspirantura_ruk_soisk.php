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
                    FROM `aspirantura_ruk_soisk`
                    WHERE `deleted` <> 1 $filter_sql
                  ");

// if ($Chairs)
// {
//   foreach ($Chairs as &$chair)
//   {
//     $chair['visible'] = !!$chair['visible'];
//   }
// }

echo json_encode($Nagruzka);
?>