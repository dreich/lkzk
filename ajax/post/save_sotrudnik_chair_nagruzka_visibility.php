<?php

// Из раздела Сотрудники завкаф сохраняет для всех сотрудников кафедры "режим" - можно ли им видеть нагрузку
include '../../functions.php';

session_name('lkzk');
session_start();

header('Content-Type: application/json');

if (!$_SESSION['c_login']) {
    echo json_encode(['result' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$chair_id = $_SESSION['c_chair_id']; // ?? 0;

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$visible = $data['visible'] ? '1' : '0';

$Result = $mysqli->query("REPLACE INTO `sotrudnik_chair_nagruzka_visibility` SET `chair_id` = '$chair_id', `visible` = '$visible'");

if ($Result !== false) {
    echo json_encode(['result' => 'success']);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
