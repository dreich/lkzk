<?php
// Получение текущего режима работы системы
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

// Проверка доступа (только для УОУП)
$c_roles = ExplodePalki($_SESSION['c_roles'], true);

if(empty($c_roles['uoup'])) {
    EchoLog('Попытка получения режима без прав УОУП: '.$_SESSION['c_login']);
    die(json_encode(['result' => 'error', 'message' => 'Доступ запрещен']));
}


$CurrentModeRow = GetRow('params', ['param' => 'system_mode']);


echo json_encode([
    'mode' => $CurrentModeRow['value'],
]);
?>