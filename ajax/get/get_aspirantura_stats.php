<?php

// Получение статистики по нагрузке типа Аспирантура (кроме Нагрузка по итоговому экзамену (она берётся, как другие нагрузки из ajax/nagruzka/))
session_name('lkzk');
session_start();
require_once '../../functions.php';
require_once '../../data.php';

// Проверка AJAX запроса
if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['result' => 'error', 'message' => 'Только AJAX запросы']));
}

$chair_id = quote_smart($_GET['chair_id']);

$stats = ['total' => 0, 'assigned' => 0, 'not_assigned' => 0];

if ($chair_id)
{
  $chair_id_param = ['chair_id' => $chair_id];
}
else
{
  $chair_id_param = null;
}


$AspiranturaKandExam = GetRows('aspirantura_kand_exam', $chair_id_param, null, null, "lecturer_uid, students_num");

if ($AspiranturaKandExam)
{
  foreach ($AspiranturaKandExam as $row)
  {
    $hours = round($_aspirantura_hours_per_student * $row['students_num'], 1);

    safeAdd($stats['total'], $hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats['assigned'], $hours);
    }
    else
    {
      safeAdd($stats['not_assigned'], $hours);
    }
  }
}


if ($chair_id)
{
  $chair_id_param = ['lecturer_chair_id' => $chair_id];
}
else
{
  $chair_id_param = null;
}

$AspiranturaRukAsp = GetRows('aspirantura_ruk_asp', $chair_id_param, null, null, 'lecturer_uid');

if ($AspiranturaRukAsp)
{
  foreach ($AspiranturaRukAsp as $row)
  {
    safeAdd($stats['total'], $_aspirantura_ruk_asp_hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats['assigned'], $_aspirantura_ruk_asp_hours);
    }
    else
    {
      safeAdd($stats['not_assigned'], $_aspirantura_ruk_asp_hours);
    }
  }
}


if ($chair_id)
{
  $chair_id_param = ['lecturer_chair_id' => $chair_id];
}
else
{
  $chair_id_param = null;
}


$AspiranturaRukSoisk = GetRows('aspirantura_ruk_soisk', $chair_id_param, null, null, 'lecturer_uid');

if ($AspiranturaRukSoisk)
{
  foreach ($AspiranturaRukSoisk as $row)
  {
    safeAdd($stats['total'], $_aspirantura_ruk_soisk_hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats['assigned'], $_aspirantura_ruk_soisk_hours);
    }
    else
    {
      safeAdd($stats['not_assigned'], $_aspirantura_ruk_soisk_hours);
    }
  }
}


echo json_encode($stats);
?>