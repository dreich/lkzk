<?php

// Удалить коммент из `log` по id
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

$id = intval($data['id']);

$Result = $mysqli->query("DELETE FROM `log` WHERE `action_name` = 'write_admin_comment' AND `id` = '$id'");

if ($Result !== false) {
    echo json_encode(['result' => 'success']);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
