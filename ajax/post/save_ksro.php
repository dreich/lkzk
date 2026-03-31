<?php

// Сохранить сотрудника в разделе КСРО
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

$XmlChairByCode = GetTable('xml_chair', "", "", "Code");

$ksro_kind_uid = '26003.281474976710751';
$ik_kind_uid = '26003.281474976710750';
$ksro_discipline_uid = '26006.281474976727808';
$ik_discipline_uid = '26006.281474976727807';

$c_chair_id = $_SESSION['c_chair_id'];
$c_department_id = $_SESSION['c_department_id'];

$UID_Chair = $XmlChairByCode[$c_chair_id]['UID'];
$UID_FacultyPerformer = $XmlChairByCode[$c_chair_id]['UID_Faculty'];

$id = isset($data['id']) ? $data['id'] : null;

$result = [];

// Общие поля для обоих запросов
$fields = "
    `chair_id` = '$c_chair_id',
    `lecturer_person_id` = '$data[lecturer_person_id]',
    `UID_Language` = '$data[UID_Language]',
    `lecturer_fio` = '$data[lecturer_fio]',
    `uid` = '$data[uid]',
    `login` = '$data[login]',
    `dolzhnost` = '$data[dolzhnost]',
    `stavka` = '$data[stavka]',
    `UID_Chair` = '$UID_Chair',
    `UID_FacultyPerformer` = '$UID_FacultyPerformer',
    
    `department_id` = '$c_department_id'";

    // `ik_osen` = '$data[ik_osen]',
    // `ik_vesna` = '$data[ik_vesna]',
    // `ksro_osen` = '$data[ksro_osen]',
    // `ksro_vesna` = '$data[ksro_vesna]'

$Result = $mysqli->query("
    REPLACE INTO `ksro` 
    SET $fields, 
    `UID_KindOfWork` = '$ik_kind_uid',
    `UID_Discipline` = '$ik_discipline_uid',
    `UID_Semester` = '1', 
    `Amount` = '$data[ik_osen]'");

$Result = $mysqli->query("
    REPLACE INTO `ksro` 
    SET $fields, 
    `UID_KindOfWork` = '$ik_kind_uid',
    `UID_Discipline` = '$ik_discipline_uid',
    `UID_Semester` = '2', 
    `Amount` = '$data[ik_vesna]'");

$Result = $mysqli->query("
    REPLACE INTO `ksro` 
    SET $fields, 
    `UID_KindOfWork` = '$ksro_kind_uid',
    `UID_Discipline` = '$ksro_discipline_uid',
    `UID_Semester` = '1', 
    `Amount` = '$data[ksro_osen]'");

$Result = $mysqli->query("
    REPLACE INTO `ksro` 
    SET $fields, 
    `UID_KindOfWork` = '$ksro_kind_uid',
    `UID_Discipline` = '$ksro_discipline_uid',
    `UID_Semester` = '2', 
    `Amount` = '$data[ksro_vesna]'");

// if ($id) 
// {
//   // Обновляем существующую запись
//   $Result = $mysqli->query("UPDATE `ksro` SET $fields WHERE `id` = '$id'");
// } 
// else 
// {
//   // Добавляем новую запись
//   $Result = $mysqli->query("INSERT INTO `ksro` SET $fields, `chair_id` = '$c_chair_id', `department_id` = '$c_department_id'");
  
//   $id = $mysqli->insert_id;
// }

if ($Result)
{
  $result['result'] = 'success';
  $result['id'] = $id;
}
else
{
  $result['result'] = 'error';
  EchoLog($mysqli->error);
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo array2json($result);

?>