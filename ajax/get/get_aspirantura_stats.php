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
$lecturer_uid = quote_smart($_GET['lecturer_uid']);

$stats = ['total' => ['sum' => 0], 'assigned' => ['sum' => 0], 'not_assigned' => ['sum' => 0]];

$aspirantura_kand_exam_params = [];

if ($chair_id)
{
  $aspirantura_kand_exam_params['chair_id'] = $chair_id;
}

if ($lecturer_uid)
{
  $aspirantura_kand_exam_params['lecturer_uid'] = $lecturer_uid;
}


$AspiranturaKandExam = GetRows('aspirantura_kand_exam', $aspirantura_kand_exam_params, null, null, "lecturer_uid, students_num");

if ($AspiranturaKandExam)
{
  foreach ($AspiranturaKandExam as $row)
  {
    $hours = round($_aspirantura_hours_per_student * $row['students_num'], 1);

    safeAdd($stats['total']['sum'], $hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats['assigned']['sum'], $hours);
    }
    else
    {
      safeAdd($stats['not_assigned']['sum'], $hours);
    }
  }
}


$aspirantura_ruk_asp_params = [];

if ($chair_id)
{
  $aspirantura_ruk_asp_params['lecturer_chair_id'] = $chair_id;
}

if ($lecturer_uid)
{
  $aspirantura_ruk_asp_params['lecturer_uid'] = $lecturer_uid;
}


$AspiranturaRukAsp = GetRows('aspirantura_ruk_asp', $aspirantura_ruk_asp_params, null, null, 'lecturer_uid');

if ($AspiranturaRukAsp)
{
  foreach ($AspiranturaRukAsp as $row)
  {
    safeAdd($stats['total']['sum'], $_aspirantura_ruk_asp_hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats['assigned']['sum'], $_aspirantura_ruk_asp_hours);
    }
    else
    {
      safeAdd($stats['not_assigned']['sum'], $_aspirantura_ruk_asp_hours);
    }
  }
}


$aspirantura_ruk_soisk_params = [];


if ($chair_id)
{
  $aspirantura_ruk_soisk_params['lecturer_chair_id'] = $chair_id;
}

if ($lecturer_uid)
{
  $aspirantura_ruk_soisk_params['lecturer_uid'] = $lecturer_uid;
}


$AspiranturaRukSoisk = GetRows('aspirantura_ruk_soisk', $aspirantura_ruk_soisk_params, null, null, 'lecturer_uid');

if ($AspiranturaRukSoisk)
{
  foreach ($AspiranturaRukSoisk as $row)
  {
    safeAdd($stats['total']['sum'], $_aspirantura_ruk_soisk_hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats['assigned']['sum'], $_aspirantura_ruk_soisk_hours);
    }
    else
    {
      safeAdd($stats['not_assigned']['sum'], $_aspirantura_ruk_soisk_hours);
    }
  }
}


echo json_encode(['stat' => $stats]);
?>