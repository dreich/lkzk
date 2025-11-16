<?php

// Зав. каф. делает запрос админу на внесение изменений, УОУП этот запрос отклоняет, тогда до следующей выгрузке нагрузка становится в этом статусе cancelling_to_change
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

if ($data['base_uid'])
{
  $nagruzka = GetFullNagruzkaRow($data['base_uid']);

  $Result = $mysqli->query("
    UPDATE `nagruzka` SET `prev_status` = `status`, `status` = 'cancelling_to_change'
    WHERE `load_base_UID` = '$data[base_uid]'");

  if (!$nagruzka)
  {
    EchoLog("nagruzka пустая в uoup_cancel_to_change.php", 'file mail');
  }

  // EchoLog($nagruzka);

  if ($Result && $nagruzka)
  {
    $result['result'] = 'success';
    
    ActivityLog($data['load_base_UID'], ['Админ УОУП отклонил запрос кафедры на внесение изменений', $data['chair_id'], $data['chair_name'], $data['zavkaf_fio']], $data['message'], 'cancelling_to_change', 1, 0);
  }
  else
  {
    $result['result'] = 'error';
    EchoLog($mysqli->error);
    EchoLog("Ошибка в uoup_cancel_to_change.php", 'file mail');
  }
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo array2json($result);


?>