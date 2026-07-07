<?php

// Удалить выбранного преподавателя для строки нагрузки Аспирантура рук-во аспирантом
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
  $Result = $mysqli->query("
        UPDATE `aspirantura_ruk_asp`
        SET `lecturer_login` = '',
            `lecturer_person_id` = '',
            `lecturer_uid` = '',
            `lecturer_fio` = '',
            `lecturer_chair_id` = '',
            `lecturer_chair_name` = '',
            `lecturer_department_id` = '',
            `lecturer_department_name` = '',
            `date_update` = NOW()
        WHERE `uid` = '$data[uid]'
      ");
}

if (!$Result)
{
  EchoLog("Error in delete_nagruzka_aspirant_ruk_asp.php: " . $mysqli->error, "file mail");
  EchoLog($query);
}


if ($Result !== false) {
    echo json_encode(['result' => 'success', 'delete' => $delete]);
} else {
    echo json_encode(['result' => 'error']);
}
