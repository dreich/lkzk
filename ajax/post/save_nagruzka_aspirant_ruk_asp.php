<?php

// Сохранить выбранного преподавателя для строки нагрузки Аспирантура рук-во аспирантам
include '../../functions.php';

session_name('lkzk');
session_start();

header('Content-Type: application/json');

if (!$_SESSION['c_login']) {
    echo json_encode(['result' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

// если есть id, то это обновление, иначе - добавление новой строки

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  echo json_encode(['result' => 'error', 'message' => 'Invalid data']);
  exit;
}

{
  $query = "UPDATE `aspirantura_ruk_asp` 
          SET 
              `lecturer_login` = '$data[lecturer_login]',
              `lecturer_person_id` = '$data[lecturer_person_id]',
              `lecturer_uid` = '$data[lecturer_uid]',
              `lecturer_fio` = '$data[lecturer_fio]',
              `lecturer_chair_id` = '$data[lecturer_chair_id]',
              `lecturer_chair_name` = '$data[lecturer_chair_name]',
              `lecturer_department_id` = '$data[lecturer_department_id]',
              `lecturer_department_name` = '$data[lecturer_department_name]'

              WHERE `uid` = '$data[uid]'
            ";
}

$Result = $mysqli->query($query);

if (!$Result)
{
  EchoLog("Error in save_nagruzka_aspirant_ruk_asp.php: " . $mysqli->error, "file mail");
  EchoLog($query);
}
// else
// {
//   if (!$data['id'])
//   {
//     $id = $mysqli->insert_id;
//   }
// }

if ($Result !== false) {
    echo json_encode(['result' => 'success', 'id' => $id]);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
