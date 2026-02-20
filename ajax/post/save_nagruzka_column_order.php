<?php

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

if (!$data || !isset($data['columns'])) {
    echo json_encode(['result' => 'error', 'message' => 'Invalid data']);
    exit;
}

$columns = $data['columns'];

$column_order_file = 'data/nagruzka_column_order_' . $chair_id . '.json';

$result = file_put_contents($column_order_file, json_encode(['columns' => $columns]));

if ($result !== false) {
    echo json_encode(['result' => 'success']);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
