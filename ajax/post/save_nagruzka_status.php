<?php

// Сохранить статус нагрузки (+ комментарий, лог)
include '../../functions.php';

$SEND_REAL_MAILS = false;

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

// EchoLog($data);

// от зав. кафа, который авторизован
$chair_id = $_SESSION['c_chair_id'];

$result = [];

$logs = ['refused' => $_statuses['refused'], 'require_admin_change' => $_statuses['require_admin_change'], 'write_admin_comment' => 'Написать комментарий администратору'];

if ($data['status'] && $data['status'] != 'write_admin_comment')
{
  $Nagruzka = GetRow('nagruzka', ['load_base_UID' => $data['load_base_UID']]);

  if ($data['status'] == 'require_admin_change')
  {
    $lecturer_sql = ", `lecturer_fio` = NULL, `lecturer_uid` = NULL, `lecturer_person_id` = NULL";
  }

  $Result = $mysqli->query("
                    UPDATE `nagruzka` 
                    SET `status` = '$data[status]'
                    $lecturer_sql
                    WHERE `load_base_UID` = '$data[load_base_UID]'");
  // `chair_id` = '$chair_id' AND 

  if ($Nagruzka['status'] != $data['status'])
  {
    $status_change = 1;
  }
  else
  {
    $status_change = 0;
  }

  $log = $logs[$data['status']];
}
// Если действие write_admin_comment, то статус не меняется
// статус пустой, тогда только сохраняем комментарий в историю, отправляем письмо
elseif ($data['message'] && $data['status'] == 'write_admin_comment')
{
  $Result = true;
  $status_change = 0;
  // $data['status'] пуст
  $log = $logs['write_admin_comment'];

  $AdminsUOUP = GetTable('users', "`roles` LIKE('%|uoup|%')");

  if ($AdminsUOUP)
  {
    $dop_sql = "AND `base_uid` = '$data[load_base_UID]'";
    $nagruzka_query = GetNagruzkaBaseQuery($dop_sql);

    $NagruzkaRows = GetSQL($nagruzka_query);

    $Nagruzka = array_values(PrepareNagruzka($NagruzkaRows));

    // EchoLog($Nagruzka);

    // по логике одна строка д.б.
    $nagruzka = $Nagruzka ? $Nagruzka[0]: null;

    if (!$nagruzka)
    {
      EchoLog("Не найдена нагрузка в save_nagruzka_status.php", 'file mail');
    }

    if ($nagruzka)
    {
      // EchoLog($nagruzka);


      $message_subject = "Заведующий кафедрой ($_SESSION[c_fio]) оставил комментарий к нагрузке";
      $message_text = "Заведующий кафедрой ($_SESSION[c_fio]) оставил комментарий к нагрузке.<br>";

      $message_text .= GetNagruzkaFieldsForMail($nagruzka);

      $message_text .= "<br>Комментарий: " . nl2br($data['message']);

      if ($SEND_REAL_MAILS)
      foreach ($AdminsUOUP as $uoup)
      {
        $User = GetLdapAttrsByAdmin($uoup['login'], ['unnmail']);
        
        mail_utf8($User['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text);
      }

      mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text);
    }
  }
}

if ($Result)
{
  $result['result'] = 'success';
  // $result['id'] = $id;

  $mysqli->query("UPDATE `nagruzka` SET `comment_to_admin` = '$data[message]' WHERE `load_base_UID` = '$data[load_base_UID]'");

  ActivityLog($data['load_base_UID'], $log, $data['message'], $data['status'], 0, $status_change);
}
else
{
  EchoLog($mysqli->error);
  $result['result'] = 'error';
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo array2json($result);


?>