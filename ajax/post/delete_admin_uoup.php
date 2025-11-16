<?php

// Удалить администратора УОУП
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
$User = GetRow('users', ['login' => $data['login']]);

if ($User)
{
  // EchoLog($User);

  $roles_arr = ExplodePalki($User['roles'], true);

  // уже есть такая роль
  if ($roles_arr['uoup'])
  {
    unset($roles_arr['uoup']);

    if (sizeof($roles_arr))
    {
      $roles_str = ImplodePalki($roles_arr);
      $Result = $mysqli->query("UPDATE `users` SET `roles` = '$roles_str' WHERE `login` = '$data[login]'");
    }
    else
    {
      $Result = $mysqli->query("DELETE FROM `users` WHERE `login` = '$data[login]'");
    }


  }

}


$result = [];

if ($Result)
{
  $result['result'] = 'success';
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