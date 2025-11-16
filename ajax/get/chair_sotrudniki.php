<?php


session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}

include '../../functions.php';
// include '../../connect/sotrudnik.php';


$chair_id = $_SESSION['c_chair_id'];

// $position_table_name = "position" . date('Y');

// $Sotrudniki = GetSQL("
//                   SELECT person.`id`, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`
//                   FROM `$position_table_name`
//                   JOIN `person` ON `$position_table_name`.person_id = `person`.id
//                   WHERE $position_table_name.`podrazdelenia_chain` LIKE('%|$chair_id|%')
//                 ");

// $Sotrudniki = GetRows('sotrudniki', ['chair_id' => $chair_id]);

$Sotrudniki = GetSQL("
        SELECT sotrudniki.*, SUM(xml_content_of_load.Amount) as amount_sum
        FROM `sotrudniki`
        LEFT JOIN nagruzka ON sotrudniki.person_id = nagruzka.lecturer_person_id
        LEFT JOIN `xml_content_of_load` ON nagruzka.`load_base_UID` = xml_content_of_load.`base_uid`
        WHERE sotrudniki.`chair_id` = '$chair_id'
        GROUP BY sotrudniki.person_id
        "); 


if ($Sotrudniki)
foreach ($Sotrudniki as &$sotrudnik)
{
  $sotrudnik['selected'] = (boolean) $sotrudnik['selected'];
}

// $c_roles = ExplodePalki($_SESSION['c_roles'], true);

usort($Sotrudniki, function($a, $b) {
    // По selected (убывающе) - true/false или 1/0
    if ($a['selected'] > $b['selected']) return -1;
    if ($a['selected'] < $b['selected']) return 1;
    
    // По type (возрастающе)
    if ($a['type'] !== $b['type']) {
        return strcmp($a['type'], $b['type']);
    }
    
    // По fio (возрастающе)
    return strcmp($a['fio'], $b['fio']);
});


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Sotrudniki));

?>

