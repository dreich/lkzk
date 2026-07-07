<?php

// Удалить выбранного преподавателя для строки нагрузки Аспирантура канд. экзамен
// Как это работает: Если это последняя строка с ключом bup_nrec + disc_nrec + disc_abr, то просто очистим препода, строку оставляем
// Если не последняя, то можем удалить всю строку
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

// Посмотрим, сколько строк в таблице с этим ключом
$Rows = GetRows('aspirantura_kand_exam', ['bup_nrec' => $data['bup_nrec'], 'disc_nrec' => $data['disc_nrec'], 'disc_abr' => $data['disc_abr']]);

// можно удалить всю строку
if (sizeof($Rows) > 1)
{
  $Result = $mysqli->query("DELETE FROM `aspirantura_kand_exam` WHERE `id` = '$data[id]'");
  $delete = true;
}
// только очистим препода
else
{
  $Result = $mysqli->query("
        UPDATE `aspirantura_kand_exam`
        SET `lecturer_login` = '',
            `lecturer_person_id` = '',
            `lecturer_uid` = '',
            `lecturer_fio` = '',
            `chair_id` = '',
            `chair_name` = '',
            `department_id` = '',
            `department_name` = '',
            `date_update` = NOW()
        WHERE `id` = '$data[id]'
      ");
}

if (!$Result)
{
  EchoLog("Error in delete_nagruzka_aspirant_kand_exam.php: " . $mysqli->error, "file mail");
  EchoLog($query);
}


if ($Result !== false) {
    echo json_encode(['result' => 'success', 'delete' => $delete]);
} else {
    echo json_encode(['result' => 'error']);
}
