<?php

// Обработка чекбокса в Сотрудники
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


$selected = $data['selected'] ? '1' : '0';

$result = [];

$Result = $mysqli->query("UPDATE `sotrudniki` SET `selected` = '$selected' WHERE `person_id` = '$data[person_id]'");

if ($Result)
{
  $result['result'] = 'success';
  // $result['id'] = $id;
}
else
{
  $result['result'] = 'error';
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo array2json($result);


?>