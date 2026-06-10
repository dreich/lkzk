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

//  кафедра завкафа
// для псевдо здесь псевдо-код 888, 999
$c_chair_id = $_SESSION['c_chair_id'];

$selected = $data['selected'] ? true : false;

$result = [];

$sotrudnik = GetRow('sotrudniki', ['person_id' => $data['person_id']]);

$chairs_ids_arr = ExplodePalki($sotrudnik['selected_chairs_ids'], true);

if ($selected)
{
  // выбрала кафедра, которой ещё нет
  if (!$chairs_ids_arr[$c_chair_id])
  {
    $chairs_ids_arr[] = $c_chair_id;
  }
}
// снял галку
else
{
  if ($chairs_ids_arr[$c_chair_id])
  {
    unset($chairs_ids_arr[$c_chair_id]);
  }
}

$new_selected_chairs_ids = ImplodePalki($chairs_ids_arr);

$selected = $new_selected_chairs_ids ? '1' : '0';

$Result = $mysqli->query("
    UPDATE `sotrudniki` SET `selected` = '$selected', `selected_chairs_ids` = '$new_selected_chairs_ids' 
    WHERE `person_id` = '$data[person_id]' AND `chair_id` = '$data[chair_id]'
  ");

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