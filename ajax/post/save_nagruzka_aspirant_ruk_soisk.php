<?php

// Сохранить строку нагрузки Аспирантура рук-во соискателем
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

$common_sql = 
"
`fio` = '$data[fio]',
`prikaz` = '$data[prikaz]',
`lecturer_login` = '$data[lecturer_login]',
`lecturer_person_id` = '$data[lecturer_person_id]',
`lecturer_uid` = '$data[lecturer_uid]',
`lecturer_fio` = '$data[lecturer_fio]',
`lecturer_chair_id` = '$data[lecturer_chair_id]',
`lecturer_chair_name` = '$data[lecturer_chair_name]',
`lecturer_department_id` = '$data[lecturer_department_id]',
`lecturer_department_name` = '$data[lecturer_department_name]',
";

if (!$data['id'])
{
  $load_id = uniq(16);

  $query = "INSERT INTO `aspirantura_ruk_soisk` 
            SET
              `load_id` = '$load_id',
              $common_sql
              `date` = NOW()
            ";
}
else
{
  $query = "UPDATE `aspirantura_ruk_soisk` 
            SET 
              $common_sql
              `date_update` = NOW()
              WHERE `id` = '$data[id]'
            ";
}

$Result = $mysqli->query($query);

if (!$Result)
{
  EchoLog("Error in save_nagruzka_aspirant_ruk_soisk.php: " . $mysqli->error, "file mail");
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
