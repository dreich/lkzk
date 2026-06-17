<?php

// Аналогичные действия, поэтому один скрипт
// 1) Админ УОУП отклоняет отказ зав. кафедрой от нагрузки
// 2) Админ УОУП отклоняет запрос зав. кафедрой на изменение нагрузки
include '../../functions.php';

session_name('lkzk');
session_start();

$request = file_get_contents('php://input');
$data = (array) json_decode($request);

$not_quoted_message = $data['message'];
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
    UPDATE `nagruzka` SET `prev_status` = `status`, `status` = 'initial', `comment_to_admin` = '$data[message]'
    WHERE `load_base_UID2` = '$data[load_base_UID2]'");

  if (!$nagruzka)
  {
    EchoLog("nagruzka пустая в uoup_cancel.php", 'file mail');
  }

  // EchoLog($nagruzka);

  if ($Result && $nagruzka)
  {
    $result['result'] = 'success';
    
    ActivityLog($data['load_base_UID2'], [$data['action'], $nagruzka['chair_id'], $nagruzka['chair_name'], $nagruzka['zavkaf_fio']], $data['message'], 'initial', 0, 1);

    $message_subject = $data['action'];
    $message_text = GetNagruzkaFieldsForMail($nagruzka);

    $message_text .= "<br>Комментарий: " . nl2br($not_quoted_message);

    $User = GetLdapAttrsByAdmin($nagruzka['zavkaf_login'], ['unnmail']);

    if ($User['unnmail'])
    {
      mail_utf8($User['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text);
    }
    else
    {
      EchoLog("Пустой unnmail зав.кафедрой (UID2: $data[load_base_UID2], логин: $nagruzka[zavkaf_login]) в uoup_cancel.php", 'file mail');
    }

    // TMP
    // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text);
  }
  else
  {
    $result['result'] = 'error';
    EchoLog($mysqli->error);
    EchoLog("Ошибка в uoup_cancel.php", 'file mail');
  }
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo array2json($result);


?>