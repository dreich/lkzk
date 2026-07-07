<?php

// Удалить строку нагрузки Аспирантура рук-во соискателем
include '../../functions.php';

session_name('lkzk');
session_start();

header('Content-Type: application/json');

if (!$_SESSION['c_login']) {
    echo json_encode(['result' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  echo json_encode(['result' => 'error', 'message' => 'Invalid data']);
  exit;
}

{
  $Result = $mysqli->query("
        DELETE FROM `aspirantura_ruk_soisk`
        WHERE `id` = '{$data['ids'][0]}'
      ");

  $Result = $mysqli->query("
        DELETE FROM `aspirantura_ruk_soisk`
        WHERE `id` = '{$data['ids'][1]}'
      ");
}

if (!$Result)
{
  EchoLog("Error in delete_nagruzka_aspirant_ruk_soisk.php: " . $mysqli->error, "file mail");
  EchoLog($query);
}


if ($Result !== false) {
    echo json_encode(['result' => 'success', 'delete' => $delete]);
} else {
    echo json_encode(['result' => 'error']);
}


?>