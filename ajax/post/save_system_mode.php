<?php
// УОУП сохраняет режим работы системы
session_name('lkzk');
session_start();
require_once '../../functions.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

// Проверка доступа УОУП
$c_roles = ExplodePalki($_SESSION['c_roles'], true);

if(empty($c_roles['uoup'])) {
    EchoLog('Попытка изменения режима без прав УОУП: '.$_SESSION['c_login']);
    die(json_encode(['result' => 'error', 'message' => 'Доступ запрещен']));
}

// Получение и проверка данных
$data = json_decode(file_get_contents('php://input'), true);
$mode = isset($data['mode']) ? quote_smart($data['mode']) : '';

// Проверка режима через массив $_system_modes из data.php
if(empty($mode) || !array_key_exists($mode, $_system_modes)) {
    EchoLog('Неверный режим работы: '.$mode);
    die(json_encode(['result' => 'error', 'message' => 'Неверный режим работы']));
}

// TODO: Реализовать сохранение режима в БД
// $sql = "UPDATE system_settings SET mode = $mode WHERE id = 1";
// mysql_query($sql);

EchoLog("Изменен режим работы на: {$_system_modes[$mode]} (УОУП: {$_SESSION['c_login']})");

echo json_encode(['result' => 'success']);
?>