<?php

// Получение нагрузки КСРО по кафедре
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

$c_chair_id = $_SESSION['c_chair_id'];

$Result = GetTable('ksro');



header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode($Result);
?>