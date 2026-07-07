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

$data = quote_smart($data);

$common_sql = 
"

`lecturer_login` = '$data[lecturer_login]',
`lecturer_person_id` = '$data[lecturer_person_id]',
`lecturer_uid` = '$data[lecturer_uid]',
`lecturer_fio` = '$data[lecturer_fio]',
`lecturer_chair_id` = '$data[lecturer_chair_id]',
`lecturer_chair_name` = '$data[lecturer_chair_name]',
`lecturer_department_id` = '$data[lecturer_department_id]',
`lecturer_department_name` = '$data[lecturer_department_name]',
`deleted` = '0',
";

$adding_ids = [];

// Добавление
if (!$data['id'])
{
  $load_id = uniq(16);

  $query = "INSERT INTO `aspirantura_ruk_soisk` 
            SET
              `load_id` = '$load_id',
              `fio` = '$data[fio]',
              `prikaz` = '$data[prikaz]',
              `UID_Semester` = '1',
              $common_sql
              `date` = NOW()
            ";

  $Result = $mysqli->query($query);

  $adding_ids[] = $mysqli->insert_id;

  $load_id = uniq(16);

  $query = "INSERT INTO `aspirantura_ruk_soisk` 
            SET
              `load_id` = '$load_id',
              `fio` = '$data[fio]',
              `prikaz` = '$data[prikaz]',
              `UID_Semester` = '2',
              $common_sql
              `date` = NOW()
            ";

  $Result = $mysqli->query($query);

  $adding_ids[] = $mysqli->insert_id;
}
// Обновление
else
{
  // $data['ids'];

  $query = "UPDATE `aspirantura_ruk_soisk` 
            SET 
              $common_sql
              `UID_Semester` = '1',
              `fio` = '$data[fio]',
              `prikaz` = '$data[prikaz]',
              `date_update` = NOW()
              WHERE  `id` = {$data['ids'][0]}
            ";

  $Result = $mysqli->query($query);


  $query = "UPDATE `aspirantura_ruk_soisk` 
            SET 
              $common_sql
              `UID_Semester` = '2',
              `fio` = '$data[fio]',
              `prikaz` = '$data[prikaz]',
              `date_update` = NOW()
              WHERE  `id` = {$data['ids'][1]}
            ";

  $Result = $mysqli->query($query);
}



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

if ($Result !== false) 
{
    echo json_encode(['result' => 'success', 'id' => $id, 'ids' => $adding_ids]);
} else 
{
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
