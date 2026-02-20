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
$department_id = $_SESSION['c_department_id'];

// $position_table_name = "position" . date('Y');

// $Sotrudniki = GetSQL("
//                   SELECT person.`id`, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`
//                   FROM `$position_table_name`
//                   JOIN `person` ON `$position_table_name`.person_id = `person`.id
//                   WHERE $position_table_name.`podrazdelenia_chain` LIKE('%|$chair_id|%')
//                 ");

// $Sotrudniki = GetRows('sotrudniki', ['chair_id' => $chair_id]);

// TypeWorkload Тип нагрузки:
// 0 — аудиторная
// 1 — неаудиторная
// Amount - кол-во часов нагрузки

// Т.к. сотрудники ГПХ в таблице sotrudniki привязаны не к кафедре, а факультету, то будем их брать по факультету авторизованного завкафа,
// а не ГПХ-шников будем искать по кафедре

$query = "
        SELECT sotrudniki.*, 
        ROUND(SUM(xml_content_of_load.Amount), 2) as amount_sum, 
        ROUND(SUM(CASE WHEN xml_content_of_load.TypeWorkload = '0' 
              THEN xml_content_of_load.Amount ELSE 0 END), 2) as amount_sum_auditorium,
        xml_content_of_load.TypeWorkload
        FROM `sotrudniki`
        LEFT JOIN nagruzka ON sotrudniki.person_id = nagruzka.lecturer_person_id
        LEFT JOIN `xml_content_of_load` ON nagruzka.`load_base_UID2` = xml_content_of_load.`base_uid2`
        -- LEFT JOIN `xml_content_of_load_staff` ON nagruzka.`load_base_UID2` = xml_content_of_load_staff.`base_uid2`
        WHERE 
        ((sotrudniki.`type` <> 'gph' AND sotrudniki.`chair_id` = '$chair_id') OR (sotrudniki.`type` = 'gph' AND sotrudniki.`department_id` = '$department_id'))
        # sotrudniki.`chair_id` = '$chair_id' 
        AND `date_remove` IS NULL
        GROUP BY sotrudniki.person_id
        ";


// Запрос от ИИ, чтобы считать и английскую нагрузку, которая указана во 2й таблице
 $query = 
 "
SELECT 
    sotrudniki.*, 
    ROUND(SUM(x.Amount), 2) as amount_sum, 
    ROUND(SUM(CASE WHEN x.TypeWorkload = '0' THEN x.Amount ELSE 0 END), 2) as amount_sum_auditorium,
    -- Для eng используем отдельную логику
    ROUND((
        SELECT SUM(CASE WHEN s.UID_Language = '25031.945' THEN x2.Amount ELSE 0 END)
        FROM nagruzka n2
        JOIN xml_content_of_load x2 ON n2.load_base_UID2 = x2.base_uid2
        LEFT JOIN xml_content_of_load_staff s ON x2.base_uid2 = s.base_uid2
        WHERE n2.lecturer_person_id = sotrudniki.person_id
          AND n2.load_base_UID2 = n.load_base_UID2
    ), 2) as amount_sum_eng,
    x.TypeWorkload
FROM `sotrudniki`
LEFT JOIN nagruzka n ON sotrudniki.person_id = n.lecturer_person_id
LEFT JOIN `xml_content_of_load` x ON n.`load_base_UID2` = x.`base_uid2`
WHERE 
    ((sotrudniki.`type` <> 'gph' AND sotrudniki.`chair_id` = '$chair_id') 
     OR (sotrudniki.`type` = 'gph' AND sotrudniki.`department_id` = '$department_id'))
    AND `date_remove` IS NULL
GROUP BY sotrudniki.person_id


";

$query = "SELECT 
    s.*,
    COALESCE(loads.total_amount, 0) as amount_sum,
    COALESCE(loads.auditorium_amount, 0) as amount_sum_auditorium,
    COALESCE(loads.eng_amount, 0) as amount_sum_eng,
    loads.TypeWorkload
FROM `sotrudniki` s
LEFT JOIN (
    SELECT 
        n.lecturer_person_id,
        SUM(x.Amount) as total_amount,
        SUM(CASE WHEN x.TypeWorkload = '0' THEN x.Amount ELSE 0 END) as auditorium_amount,
        SUM(CASE WHEN s.UID_Language = '25031.945' THEN x.Amount ELSE 0 END) as eng_amount,
        MAX(x.TypeWorkload) as TypeWorkload
    FROM nagruzka n
    JOIN xml_content_of_load x ON n.load_base_UID2 = x.base_uid2
    LEFT JOIN xml_content_of_load_staff s ON x.base_uid2 = s.base_uid2
    GROUP BY n.lecturer_person_id
) loads ON s.person_id = loads.lecturer_person_id
WHERE 
    ((s.`type` <> 'gph' AND s.`chair_id` = '$chair_id') 
     OR (s.`type` = 'gph' AND s.`department_id` = '$department_id'))
    AND s.`date_remove` IS NULL";


$query = "SELECT 
    s.*, 
    ROUND(COALESCE(loads.total_amount, 0), 2) as amount_sum, 
    ROUND(COALESCE(loads.auditorium_amount, 0), 2) as amount_sum_auditorium,
    ROUND(COALESCE(eng.eng_amount, 0), 2) as amount_sum_eng,
    COALESCE(loads.TypeWorkload, '') as TypeWorkload
FROM `sotrudniki` s
LEFT JOIN (
    -- ТОЛЬКО xml_content_of_load (без staff!)
    SELECT 
        n.lecturer_person_id,
        SUM(x.Amount) as total_amount,
        SUM(CASE WHEN x.TypeWorkload = '0' THEN x.Amount ELSE 0 END) as auditorium_amount,
        MAX(x.TypeWorkload) as TypeWorkload
    FROM nagruzka n
    JOIN xml_content_of_load x ON n.load_base_UID2 = x.base_uid2
    GROUP BY n.lecturer_person_id
) loads ON s.person_id = loads.lecturer_person_id
LEFT JOIN (
    -- ОТДЕЛЬНО для английской нагрузки
    SELECT 
        n.lecturer_person_id,
        SUM(x.Amount) as eng_amount
    FROM nagruzka n
    JOIN xml_content_of_load x ON n.load_base_UID2 = x.base_uid2
    JOIN xml_content_of_load_staff s ON x.base_uid2 = s.base_uid2
    WHERE s.UID_Language = '25031.945'  -- фильтр здесь
    GROUP BY n.lecturer_person_id
) eng ON s.person_id = eng.lecturer_person_id
WHERE 
    ((s.`type` <> 'gph' AND s.`chair_id` = '$chair_id') 
     OR (s.`type` = 'gph' AND s.`department_id` = '$department_id'))
    AND s.`date_remove` IS NULL";


// Без этого ругается версия MySQL на GROUP BY
$mysqli->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

$Sotrudniki = GetSQL($query); 


if ($Sotrudniki)
foreach ($Sotrudniki as &$sotrudnik)
{
  if ($sotrudnik['type'] == 'sotrudnik')
  {
    $sotrudnik['selected'] = true;
  }
  
  $sotrudnik['selected'] = (boolean) $sotrudnik['selected'];
}

// $c_roles = ExplodePalki($_SESSION['c_roles'], true);

usort($Sotrudniki, function($a, $b) 
{
  // Define custom order for types
  $typeOrder = [
    'sotrudnik' => 1,
    'kandidat' => 2,
    'worked' => 3,
    'gph' => 4
  ];

  // По selected (убывающе) - true/false или 1/0
  if ($a['selected'] > $b['selected']) return -1;
  if ($a['selected'] < $b['selected']) return 1;

  // Sort by type using custom order
  $aType = isset($typeOrder[$a['type']]) ? $typeOrder[$a['type']] : PHP_INT_MAX;
  $bType = isset($typeOrder[$b['type']]) ? $typeOrder[$b['type']] : PHP_INT_MAX;

  if ($aType !== $bType) return $aType - $bType;

  // По fio (возрастающе)
  return mb_strcasecmp($a['fio'], $b['fio']);
});


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Sotrudniki));

?>

