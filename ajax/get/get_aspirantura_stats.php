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

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

$chair_id = quote_smart($_GET['chair_id']);
$lecturer_uid = quote_smart($_GET['lecturer_uid']);

if ($lecturer_uid)
{
  $Lecturer = GetRow('xml_lecturer', ['UID' => $lecturer_uid]);
  $lecturer_fio = $Lecturer['FIO'];
}

if ($c_roles['dean'])
{
  $c_department_id = $_SESSION['c_department_id'];
}

$stats = ['total' => ['sum' => 0], 'assigned' => ['sum' => 0], 'not_assigned' => ['sum' => 0]];
// только assigned
$stats_by_type = ['aspirantura_kand_exam' => 0, 'aspirantura_ruk_asp' => 0, 'aspirantura_ruk_soisk' => 0];

$aspirantura_kand_exam_params = ['deleted' => 0];

// all - для УОУП и рук-ля аспирантуры
if ($chair_id && $chair_id != 'all')
{
  $aspirantura_kand_exam_params['chair_id'] = $chair_id;
}

if ($lecturer_uid)
{
  $aspirantura_kand_exam_params['lecturer_uid'] = $lecturer_uid;
}

if (!$aspirantura_kand_exam_params['chair_id'])
{
  if ($c_roles['dean'] && $c_department_id)
  {
    $aspirantura_kand_exam_params['department_id'] = $c_department_id;
  }
  elseif ($c_roles['zavkaf'])
  {
    $aspirantura_kand_exam_params['chair_id'] = $_SESSION['c_chair_id'];
  }
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
      safeAdd($stats_by_type['aspirantura_kand_exam'], $hours);
    }
    else
    {
      safeAdd($stats['not_assigned']['sum'], $hours);
    }
  }
}


$aspirantura_ruk_asp_params = ['deleted' => 0];

if ($chair_id && $chair_id != 'all')
{
  $aspirantura_ruk_asp_params['lecturer_chair_id'] = $chair_id;
}

if ($lecturer_uid)
{
  $aspirantura_ruk_asp_params['lecturer_uid'] = $lecturer_uid;
}

if (!$aspirantura_ruk_asp_params['lecturer_chair_id'])
{
  if ($c_roles['dean'] && $c_department_id)
  {
    $aspirantura_ruk_asp_params['lecturer_department_id'] = $c_department_id;
  }
  elseif ($c_roles['zavkaf'])
  {
    $aspirantura_ruk_asp_params['lecturer_chair_id'] = $_SESSION['c_chair_id'];
  }
}


$AspiranturaRukAsp = GetRows('aspirantura_ruk_asp', $aspirantura_ruk_asp_params, null, null, 'lecturer_uid');

if ($AspiranturaRukAsp)
{
  foreach ($AspiranturaRukAsp as $row)
  {
    safeAdd($stats['total']['sum'], $_aspirantura_ruk_asp_hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats_by_type['aspirantura_ruk_asp'], $_aspirantura_ruk_asp_hours);
      safeAdd($stats['assigned']['sum'], $_aspirantura_ruk_asp_hours);
    }
    else
    {
      safeAdd($stats['not_assigned']['sum'], $_aspirantura_ruk_asp_hours);
    }
  }
}


$aspirantura_ruk_soisk_params = ['deleted' => 0];


if ($chair_id && $chair_id != 'all')
{
  $aspirantura_ruk_soisk_params['lecturer_chair_id'] = $chair_id;
}

if ($lecturer_uid)
{
  $aspirantura_ruk_soisk_params['lecturer_uid'] = $lecturer_uid;
}

if (!$aspirantura_ruk_soisk_params['lecturer_chair_id'])
{
  if ($c_roles['dean'] && $c_department_id)
  {
    $aspirantura_ruk_soisk_params['lecturer_department_id'] = $c_department_id;
  }
  elseif ($c_roles['zavkaf'])
  {
    $aspirantura_ruk_soisk_params['lecturer_chair_id'] = $_SESSION['c_chair_id'];
  }
}


$AspiranturaRukSoisk = GetRows('aspirantura_ruk_soisk', $aspirantura_ruk_soisk_params, null, null, 'lecturer_uid');

if ($AspiranturaRukSoisk)
{
  foreach ($AspiranturaRukSoisk as $row)
  {
    safeAdd($stats['total']['sum'], $_aspirantura_ruk_soisk_hours);

    if ($row['lecturer_uid'])
    {
      safeAdd($stats_by_type['aspirantura_ruk_soisk'], $_aspirantura_ruk_soisk_hours);
      safeAdd($stats['assigned']['sum'], $_aspirantura_ruk_soisk_hours);
    }
    else
    {
      safeAdd($stats['not_assigned']['sum'], $_aspirantura_ruk_soisk_hours);
    }
  }
}


// !! Возможно, not_assigned в этом скрипте не имеет смысла

echo json_encode(['stat' => $stats, 'stat_by_type' => $stats_by_type, 'lecturer_fio' => $lecturer_fio]);
?>