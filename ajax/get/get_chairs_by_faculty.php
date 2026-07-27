<?php
session_name('lkzk');
session_start();
require_once '../../functions.php';

if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

global $mysqli;

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

$faculty_uid = '';

if (!empty($c_roles['uoup']) && isset($_GET['faculty'])) {
    $faculty_uid = $mysqli->real_escape_string($_GET['faculty']);
} elseif (!empty($c_roles['dean'])) {
    $dean_dep_id = $_SESSION['c_department_id']; // Code in xml_faculty
    $faculty = GetRow('xml_faculty', ['Code' => $dean_dep_id]);
    if ($faculty) {
        $faculty_uid = $faculty['UID'];
    }
}

if (!$faculty_uid) {
    echo json_encode([]);
    die();
}

$chairs = GetSQL("SELECT UID, Name, Abbr, Code FROM xml_chair WHERE UID_Faculty = '$faculty_uid' ORDER BY Name");

echo json_encode(array_values($chairs ? $chairs : []));
