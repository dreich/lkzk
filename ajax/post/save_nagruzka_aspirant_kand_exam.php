<?php

// Сохранить выбранного преподавателя для строки нагрузки Аспирантура канд. экзамен
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


if (!$data['id'])
{
  $query = "INSERT INTO `aspirantura_kand_exam` 
            SET 
              `bup_nrec` = '$data[bup_nrec]',
              `disc_nrec` = '$data[disc_nrec]',
              `disc_abr` = '$data[disc_abr]',
              `disc_title` = '$data[disc_title]',
              `exam_semester` = '$data[exam_semester]',
              `groups` = '$data[groups]',
              `students_num` = '$data[students_num]',
              `lecturer_login` = '$data[lecturer_login]',
              `lecturer_person_id` = '$data[lecturer_person_id]',
              `lecturer_uid` = '$data[lecturer_uid]',
              `lecturer_fio` = '$data[lecturer_fio]',
              `chair_id` = '$data[chair_id]',
              `chair_name` = '$data[chair_name]',
              `department_id` = '$data[department_id]',
              `department_name` = '$data[department_name]',
              `date` = NOW()
            ";

  
}
else
{
  $query = "UPDATE `aspirantura_kand_exam` 
          SET 
              `lecturer_login` = '$data[lecturer_login]',
              `lecturer_person_id` = '$data[lecturer_person_id]',
              `lecturer_uid` = '$data[lecturer_uid]',
              `lecturer_fio` = '$data[lecturer_fio]',
              `chair_id` = '$data[chair_id]',
              `chair_name` = '$data[chair_name]',
              `department_id` = '$data[department_id]',
              `department_name` = '$data[department_name]'

              WHERE `id` = '$data[id]'
            ";
}

$Result = $mysqli->query($query);

if (!$Result)
{
  EchoLog("Error in save_nagruzka_aspirant_kand_exam.php: " . $mysqli->error, "file mail");
  EchoLog($query);
}
else
{
  if (!$data['id'])
  {
    $id = $mysqli->insert_id;
  }
}

if ($Result !== false) {
    echo json_encode(['result' => 'success', 'id' => $id]);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
