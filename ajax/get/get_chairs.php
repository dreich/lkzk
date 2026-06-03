<?php

// Получение справочника кафедр
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}


// $Chairs = GetTable('xml_chair', "", "", 'Code', 'Code, Name');

$Chairs = GetSQL("SELECT xml_chair.Code, xml_chair.Name, sotrudnik_chair_nagruzka_visibility.visible
                  FROM `xml_chair`
                  LEFT JOIN sotrudnik_chair_nagruzka_visibility ON xml_chair.Code = sotrudnik_chair_nagruzka_visibility.chair_id
                ", "Code");

if ($Chairs)
{
  foreach ($Chairs as &$chair)
  {
    $chair['visible'] = !!$chair['visible'];
  }
}

echo json_encode($Chairs);
?>