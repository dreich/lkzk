<?php
session_name('lkzk');
session_start();
require_once '../../functions.php';

if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

$c_roles = ExplodePalki($_SESSION['c_roles'], true);
if (empty($c_roles['uoup'])) {
    die(json_encode([]));
}

$faculties = GetSQL("SELECT UID, Name, Abbr, Code FROM xml_faculty ORDER BY Name");
echo json_encode(array_values($faculties));
