<?php

include '../../functions.php';

session_name('lkzk');
session_start();

header('Content-Type: application/json');

if (!$_SESSION['c_login']) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chair_id = $_SESSION['c_chair_id'] ?? 0;

$column_order_file = 'data/nagruzka_column_order_' . $chair_id . '.json';

if (file_exists($column_order_file)) {
    $json_data = file_get_contents($column_order_file);
    echo $json_data;
} else {
    echo json_encode(['columns' => []]);
}
