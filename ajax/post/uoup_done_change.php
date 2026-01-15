<?php

// Зав. каф. делает запрос админу на внесение изменений, УОУП на этот запрос отвечает положительно ("Выполнено"), 
// тогда до следующей выгрузки нагрузка становится в этом статусе done_change
include '../../functions.php';

session_name('lkzk');
session_start();

$request = file_get_contents('php://input');
$data = (array) json_decode($request);

$data = quote_smart($data);

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}

if ($data['load_base_UID2'])
{
  $nagruzka = GetFullNagruzkaRow($data['load_base_UID2']);

  $Result = $mysqli->query("
    UPDATE `nagruzka` SET `prev_status` = `status`, `status` = 'done_change'
    WHERE `load_base_UID2` = '$data[load_base_UID2]'");

  if (!$nagruzka)
  {
    EchoLog("nagruzka пустая в uoup_done_change.php", 'file mail');
  }

  // EchoLog($nagruzka);

  if ($Result && $nagruzka)
  {
    $result['result'] = 'success';
    
    ActivityLog($data['load_base_UID2'], ['Админ УОУП выполнил запрос кафедры на внесение изменений', $nagruzka['chair_id'], $nagruzka['chair_name'], $nagruzka['zavkaf_fio']], $data['message'], 'done_change', 1, 1);
  }
  else
  {
    $result['result'] = 'error';
    EchoLog($mysqli->error);
    EchoLog("Ошибка в uoup_done_change.php", 'file mail');
  }
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo array2json($result);


?>