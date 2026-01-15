<?php

// На нагрузку назначен преподаватель
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

// EchoLog($data);

// от зав. кафа, который авторизован
$chair_id = $_SESSION['c_chair_id'];

$result = [];

$Result = $mysqli->query("
                  UPDATE `nagruzka` 
                  SET `lecturer_fio` = '$data[lecturer_fio]', `lecturer_uid` = '$data[lecturer_uid]' , `lecturer_person_id` = '$data[lecturer_person_id]', `disciplines_UIDs_chain_str` = '$data[disciplines_UIDs_chain_str]', `disciplines_Names_chain_str` = '$data[disciplines_Names_chain_str]', `date_update` = NOW()
                  WHERE `chair_id` = '$chair_id' AND `load_base_UID2` = '$data[load_base_UID2]'");

                  // `chair_id` = '$chair_id' AND 

if ($Result)
{
  $result['result'] = 'success';
  // $result['id'] = $id;

  if (mb_strcasecmp($data['lecturer_fio'], 'Вакансия') == 0)
  {
    ActivityLog($data['load_base_UID2'], ["Нагрузка назначена на вакансию", $chair_id, "Вакансия"], "", "nagruzka_vacancy_assign", 0, 0);
  }
  else
  {
    ActivityLog($data['load_base_UID2'], ["На нагрузку назначен $data[lecturer_fio]", $chair_id, $data['lecturer_fio'], $data['lecturer_uid'], $data['lecturer_person_id']], "", "nagruzka_lecturer_assign", 0, 0);
  }
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