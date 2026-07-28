<?php

// exit;
if (session_status() === PHP_SESSION_NONE)
{
  session_name('lkzk');
  session_start();
}

if (empty($_SESSION['c_roles']))
{
  http_response_code(403);
  echo 'Forbidden. Please log in.';
  return;
}

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;

$c_roles = function_exists('ExplodePalki') ? ExplodePalki($_SESSION['c_roles'], true) : [];

// Access check
$hasAccess = false;
$filter_faculty_uid = null;
$filter_chair_uid = null;

if (!empty($c_roles['uoup']))
{
  $hasAccess = true;
  $filter_faculty_uid = isset($_GET['faculty']) ? $_GET['faculty'] : null;
  $filter_chair_uid = isset($_GET['chair']) ? $_GET['chair'] : null;
  $report_type = isset($_GET['type']) ? (int)$_GET['type'] : 1;
} 
// TODO !!!
elseif (!empty($c_roles['dean']))
{
  $hasAccess = true;
  $dean_dep_id = $_SESSION['c_department_id']; // Code in xml_faculty
  $faculty = GetRow('xml_faculty', ['Code' => $dean_dep_id]);
  if ($faculty)
  {
    $filter_faculty_uid = $faculty['UID'];
  }
  $filter_chair_uid = isset($_GET['chair']) ? $_GET['chair'] : null;
  $report_type = isset($_GET['type']) ? (int)$_GET['type'] : 1;
} 
else
if (!empty($c_roles['zavkaf']))
{
  $hasAccess = true;
  $zavkaf_chair_id = $_SESSION['c_chair_id']; // Code in xml_chair
  $chair = GetRow('xml_chair', ['Code' => $zavkaf_chair_id]);
  if ($chair)
  {
    $filter_chair_uid = $chair['UID'];
  }
}

if (!$hasAccess)
{
  http_response_code(403);
  echo 'Access denied.';
  return;
}

$XmlChairByCode = GetTable('xml_chair', "", "", "Code");
$XmlFacultyByUID = GetTable('xml_faculty', "", "", "UID");
$AspiranturaRukAspByLoadId = GetTable('aspirantura_ruk_asp', "", "", "load_id", "load_id, fio");
$AspiranturaRukSoiskByLoadId = GetTable('aspirantura_ruk_soisk', "", "", "load_id", "load_id, fio");

// Build query
$where = [];
if ($filter_faculty_uid)
{
  if ($report_type === 1)
  {
    $where[] = "ls.UID_FacultyOwner = '" . $mysqli->real_escape_string($filter_faculty_uid) . "'";
  } 
  elseif ($report_type === 2 || $report_type === 3)
  {
    $chairs = GetRows('xml_chair', ['UID_Faculty' => $filter_faculty_uid]);
    $chair_uids = [];
    if ($chairs)
    {
      foreach ($chairs as $ch)
      {
          $chair_uids[] = "'" . $mysqli->real_escape_string($ch['UID']) . "'";
      }
      if ($chair_uids)
      {
          $where[] = "l.UID_Chair IN (" . implode(',', $chair_uids) . ")";
      } else
      {
          $where[] = "0=1";
      }
    } else
    {
      $where[] = "0=1";
    }
  }
}

if (!empty($c_roles['zavkaf']) && empty($c_roles['uoup']) && empty($c_roles['dean'])) {
    $filter_chair_uid = $XmlChairByCode[$_SESSION['c_chair_id']]['UID']; // For ZavKaf, force their own chair using UID instead of Code
}

if ($filter_chair_uid && $report_type !== 1 && $report_type !== 2)
{
  $where[] = "l.UID_Chair = '" . $mysqli->real_escape_string($filter_chair_uid) . "'";
}

if (!empty($c_roles['zavkaf']) && empty($c_roles['uoup']) && empty($c_roles['dean'])) {
    $where[] = "l.UID_Chair = '" . $mysqli->real_escape_string($filter_chair_uid) . "'"; // For ZavKaf force chair unconditionally
}


if (isset($_GET['year'])) {
    $year = (int)$_GET['year'];
    $where[] = "l.YearOfEducation = '" . $year . "'";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";
// comment_to_admin
$query = "
  SELECT 
    #l.UID as UID_ContentOfLoad,
    ls.UID_ContentOfLoad,
    n.comment_to_admin,
    l.base_uid,
    l.UID_Chair,
    l.LoadId,
    f_perf.UID,
    l.UID_Language as content_of_load_UID_Language,
    ls.UID_Group, ls.UID_SubGroup, l.UID_Lecturer, l.Amount, l.StudentAmount, ls.UID_Language, l.UID_Course, l.UID_Semester, l.nagruzka_type, l.UID_KindOfWork, l.YearOfEducation,
    ls.UID_FacultyOwner,
    ls.UID_FacultyPerformer,
    ls.UID_Speciality,
    ls.UID_Specialization,
    ls.UID_FormOfEducation,
    ls.Abbr,
    f_owner.Name as FacultyOwnerName,
    f_owner.Abbr as FacultyOwnerAbbr,
    c.Name as ChairName,
    c.Code as ChairCode,
    f_perf.Name as FacultyPerformerName,
    f_perf.Abbr as FacultyPerformerAbbr,
    d.Name as DisciplineName,
    lec.FIO as LecturerFIO,
    lec.Tab_number as LecturerPersonId,
    sp.Name as SpecialityName,
    sp.education_level,
    kw.Name as KindOfWorkName,
    spz.Name as napravlennost
  FROM xml_content_of_load l
  LEFT JOIN `nagruzka` n ON l.base_uid = n.load_base_UID2
  LEFT JOIN xml_content_of_load_staff ls ON ls.UID_ContentOfLoad = l.base_uid2
  LEFT JOIN xml_faculty f_owner ON f_owner.UID = ls.UID_FacultyOwner
  LEFT JOIN xml_chair c ON c.UID = l.UID_Chair
  LEFT JOIN xml_faculty f_perf ON f_perf.UID = c.UID_Faculty
  LEFT JOIN xml_discipline d ON d.UID = l.UID_Discipline
  LEFT JOIN xml_lecturer lec ON lec.UID = l.UID_Lecturer
  LEFT JOIN xml_speciality sp ON sp.UID = ls.UID_Speciality
  LEFT JOIN xml_kind_of_work kw ON kw.UID = l.UID_KindOfWork
  LEFT JOIN xml_specialization spz ON spz.UID = ls.UID_Specialization
  $where_sql
  #AND `nagruzka_type` IN ('ruk_vkr', 'ruk_kurs', 'ruk_practice', 'gia')
  -- AND `nagruzka_type` IN ('aspirantura_kand_exam', 'aspirantura_ruk_asp', 'aspirantura_ruk_soisk', 'ksro')
  -- AND `nagruzka_type` IN ('aspirantura_ruk_asp')
  -- AND `nagruzka_type` IN ('aspirantura_ruk_soisk')
  -- AND `nagruzka_type` IN ('discipline')
  -- AND `nagruzka_type` IN ('ksro')
";

$result = $mysqli->query($query);
if (!$result)
{
  echo "Error executing query: " . $mysqli->error;
  return;
}

if (!$result->num_rows)
{
  echo "Данных нет";
  exit;
}

$groups = [];
$res = $mysqli->query("SELECT UID, Name, Number FROM xml_group");
if ($res)
{
  while ($row = $res->fetch_assoc())
  {
    $groups[$row['UID']] = $row;
  }
}

$sub_groups = [];
$res = $mysqli->query("SELECT UID, Name, Number FROM xml_subgroup");
if ($res)
{
  while ($row = $res->fetch_assoc())
  {
    $sub_groups[$row['UID']] = $row;
  }
}


$sotrudniki = [];
$res = $mysqli->query("SELECT person_id, chair_id, dolzhnost, pku, stavka FROM sotrudniki");
if ($res)
{
  while ($row = $res->fetch_assoc())
  {
    $sotrudniki[$row['person_id'] . '_' . $row['chair_id']] = $row;
  }
}


$langNames = [];
$res = $mysqli->query("SELECT UID, Name FROM xml_language");
if ($res)
{
  while ($row = $res->fetch_assoc())
  {
    $langNames[$row['UID']] = $row['Name'];
  }
}



$col_mapping = [
  '26003.281474976710659' => 18, // Лекция
  '26003.281474976710660' => 20, // Лабораторная
  '26003.281474976710661' => 19, // Практика семинарские занятия
  '26003.281474976710662' => 24, // Экзамен
  '26003.281474976710663' => 22, // Зачёт
  '26003.281474976710665' => 21, // Консультации перед экзаменом
  '26003.281474976710672' => 28, // Контрольная работа
  '26003.281474976710676' => 23, // Дифференцированный зачет
  '26003.281474976710684' => 26, // Участие в комиссии
  '26003.281474976710713' => 23, // Дифференцированный зачет по практике
  '26003.281474976710728' => 22, // Зачёт по практике
  '26003.281474976710748' => 29, // Руководство курсовой работой проектом
  '26003.281474976710749' => 30, // Организация курсовой работы проекта
  '26003.281474976710757' => 33, // Практика групповая в университете
  '26003.281474976710758' => 35, // Практика индивидуальная в университете
  '26003.281474976710761' => 32, // Практика групповая в организации
  '26003.281474976710762' => 34, // Практика индивидуальная в организации
  '26003.281474976710763' => 31, // Практика выездная
  '26003.281474976710767' => 36, // Руководство ВКР
  '26003.281474976710768' => 27, // Участие в комиссии (председатель)
  '26003.281474976710769' => 25, // Промежуточная аттестация по курсовой работе (проекту)
];

// Prepare OpenSpout 3.x Writer
$style = (new StyleBuilder())
           ->setFontName('Calibri')
           ->setFontSize(9)
           ->build();

$writer = WriterEntityFactory::createXLSXWriter();
$writer->setDefaultRowStyle($style);

// Do not send headers or open writer until we know we have data!
$isWriterOpened = false;

// Write headers (extracted from template.xlsx or inferred)
$headerValues = [
    'Фак-т каф.', 'Фак-т студ.', 'Кафедра', 'ФИО', 'Должность', 'ПКУ', 'Ставка', 'Дисциплина', 'Группа', 'Ур. об.', 'Специальность', 'Направленность', 'Язык', 'Форма', 'Курс', 'Сем.', 'Студ.',
    'Лек', 'Прак', 'Лаб', 'Конс.Э', 'Зач', 'Диф.Зач/Зач.Пр', 'Экз', 'ПромАтт.КР', 'Уч.Ком', 'Пред.Ком', 'Контр.Раб', 'Рук.КР', 'Орг.КР', 'Пр.Выезд', 'Пр.Гр.Орг', 'Пр.Гр.Унив', 'Пр.Инд.Орг', 'Пр.Инд.Унив', 'Рук.ВКР', 'Асп', 'Соиск', 'ИК', 'КСРО',
    'Комментарий', 'Всего часов', 'Ауд. часов', 'Ино. часов', 'Год пост.', 'Уч. год', 'Аббрев.', ''
];

$groupedData = [];
while ($row = $result->fetch_assoc())
{
  $uid = $row['UID_ContentOfLoad'];
  if (!$uid) $uid = uniqid(); // fallback just in case
  
  if (!isset($groupedData[$uid]))
  {
    $groupedData[$uid] = $row;
    $groupedData[$uid]['UID_Group_arr'] = $row['UID_Group'] ? explode(',', $row['UID_Group']) : [];
    $groupedData[$uid]['UID_SubGroup_arr'] = $row['UID_SubGroup'] ? explode(',', $row['UID_SubGroup']) : [];
    $groupedData[$uid]['Abbr_arr'] = $row['Abbr'] ? [$row['Abbr']] : [];
    $groupedData[$uid]['SpecialityName_arr'] = $row['SpecialityName'] ? [$row['SpecialityName']] : [];
    $groupedData[$uid]['napravlennost_arr'] = $row['napravlennost'] ? [$row['napravlennost']] : [];
    $groupedData[$uid]['FacultyOwnerAbbr_arr'] = $row['FacultyOwnerAbbr'] ? [$row['FacultyOwnerAbbr']] : [];
    $groupedData[$uid]['UID_Language_arr'] = $row['UID_Language'] ? [$row['UID_Language']] : [];
    $groupedData[$uid]['DisciplineName_arr'] = $row['DisciplineName'] ? [$row['DisciplineName']] : [];
    $groupedData[$uid]['UID_FormOfEducation_arr'] = [$row['UID_FormOfEducation']];
  }
  else
  {
    if ($row['UID_Group']) $groupedData[$uid]['UID_Group_arr'] = array_merge($groupedData[$uid]['UID_Group_arr'], explode(',', $row['UID_Group']));
    if ($row['UID_SubGroup']) $groupedData[$uid]['UID_SubGroup_arr'] = array_merge($groupedData[$uid]['UID_SubGroup_arr'], explode(',', $row['UID_SubGroup']));
    if ($row['Abbr']) $groupedData[$uid]['Abbr_arr'][] = $row['Abbr'];
    if ($row['SpecialityName']) $groupedData[$uid]['SpecialityName_arr'][] = $row['SpecialityName'];
    if ($row['napravlennost']) $groupedData[$uid]['napravlennost_arr'][] = $row['napravlennost'];
    if ($row['FacultyOwnerAbbr']) $groupedData[$uid]['FacultyOwnerAbbr_arr'][] = $row['FacultyOwnerAbbr'];
    if ($row['UID_FormOfEducation']) $groupedData[$uid]['UID_FormOfEducation_arr'][] = $row['UID_FormOfEducation'];
    if ($row['DisciplineName']) $groupedData[$uid]['DisciplineName_arr'][] = $row['DisciplineName'];
    if ($row['UID_Language']) $groupedData[$uid]['UID_Language_arr'][] = $row['UID_Language'];
  }
}


// Основной цикл вывода
foreach ($groupedData as $uid => $row)
{
  $kwName = trim($row['KindOfWorkName']);
  $nType = trim($row['nagruzka_type']);

  $row['UID_Group'] = implode(',', array_unique($row['UID_Group_arr']));
  $row['UID_SubGroup'] = implode(',', array_unique($row['UID_SubGroup_arr']));
  $row['Abbr'] = implode(', ', array_unique($row['Abbr_arr']));
  $row['SpecialityName'] = implode(', ', array_unique($row['SpecialityName_arr']));
  $row['napravlennost'] = implode(', ', array_unique($row['napravlennost_arr']));
  $row['FacultyOwnerAbbr'] = implode(', ', array_unique($row['FacultyOwnerAbbr_arr']));
  $row['UID_Language'] = implode(', ', array_unique($row['UID_Language_arr']));
  $row['DisciplineName'] = implode(', ', array_unique($row['DisciplineName_arr']));
  $row['UID_FormOfEducation'] = empty($row['UID_FormOfEducation_arr']) ? '' : $row['UID_FormOfEducation_arr'][0];
  
  $formNames = [];
  foreach (array_unique($row['UID_FormOfEducation_arr']) as $fUid) {
    if (isset($_forms_obuchenia[$fUid])) {
      $formNames[] = $_forms_obuchenia[$fUid];
    }
  }
  $formStr = implode(', ', $formNames);

  // EchoLog($row['UID_FormOfEducation_arr']);
  $groupNames = [];
  $yearsOfEntry = [];

  if ($row['UID_Group'])
  {
    $groupUids = explode(',', $row['UID_Group']);

    foreach ($groupUids as $gUid)
    {
      $gUid = trim($gUid);
      if (isset($groups[$gUid]))
      {
        $groupNames[] = $groups[$gUid]['Name'];
        $num = $groups[$gUid]['Number'];
        if (strlen($num) >= 4)
        {
            $year = '20' . substr($num, 2, 2);
            $yearsOfEntry[$year] = $year;
        }
      }
    }
  }
  elseif ($row['UID_SubGroup'])
  {
    $groupUids = explode(',', $row['UID_SubGroup']);
    
    foreach ($groupUids as $gUid)
    {
      $gUid = trim($gUid);
      if (isset($sub_groups[$gUid]))
      {
        $groupNames[] = $sub_groups[$gUid]['Name'];
        $num = $sub_groups[$gUid]['Number'];
        if (strlen($num) >= 4)
        {
            $year = '20' . substr($num, 2, 2);
            $yearsOfEntry[$year] = $year;
        }
      }
    }
  }

  $groupStr = implode(', ', array_unique($groupNames));
  $yearOfEntryStr = implode(', ', array_keys($yearsOfEntry));
  
  $fio = $row['LecturerFIO'];

  if (empty($row['UID_Lecturer']) || $row['UID_Lecturer'] === '26115.281474976893938' || $row['UID_Lecturer'] === '-1')
  {
    $fio = 'Вакансия';

    // EchoLog($row);
  }
  
  $dolzhnost = '';
  $pku = '';
  $stavka = '';
  
  if ($fio !== 'Вакансия' && !empty($row['LecturerPersonId']) && !empty($row['ChairCode']))
  {
    $key = $row['LecturerPersonId'] . '_' . $row['ChairCode'];

    // для псевдо и ГПХ искать по факультету
    if (!$sotrudniki[$key])
    {
      $faculty_uid = $XmlChairByCode[$row['ChairCode']]['UID_Faculty'];
      $faculty_code = $XmlFacultyByUID[$faculty_uid]['Code'];

      $key = $row['LecturerPersonId'] . '_' . $faculty_code;
    }

    if (isset($sotrudniki[$key]))
    {
      $dolzhnost = $sotrudniki[$key]['dolzhnost'];
      $pku = $sotrudniki[$key]['pku'];
      $stavka = $sotrudniki[$key]['stavka'];
    }
  }

  $amount = (float)str_replace(',', '.', $row['Amount']);

  // if ($nType == 'aspirantura_ruk_soisk')
  // {
  //   $amount = 50;
  // }

  // if ($nType == 'aspirantura_ruk_asp')
  // {
  //   $amount = 75;
  // }


  if ($nType == 'aspirantura_ruk_asp' || $nType == 'aspirantura_ruk_soisk' || $nType == 'ksro')
  {
    $row['FacultyOwnerAbbr'] = $row['FacultyPerformerAbbr'];
  }

  
  $lang = isset($langNames[$row['UID_Language']]) ? $langNames[$row['UID_Language']] : '';
  if (empty($lang)) {
    $langUID = $row['content_of_load_UID_Language'];
    if ($langUID === $_language_eng_uid) $lang = 'Английский';
    elseif ($langUID) $lang = 'Русский';
  }

  $semester = $row['UID_Semester'] % 2 == 0 ? 'В' : 'О';
  $studentAmount = (float)str_replace(',', '.', $row['StudentAmount']);
  $stavka = (float) str_replace(',', '.', isset($sotrudniki[$key]) ? $sotrudniki[$key]['stavka'] : 0);

  $rowData = array_fill(0, 48, '');
  
  $rowData[0] = $row['FacultyOwnerAbbr']; // A
  $rowData[1] = $row['FacultyPerformerAbbr']; // B
  $rowData[2] = $row['ChairName']; // C
  $rowData[3] = $fio; // D
  $rowData[4] = $dolzhnost; // E
  $rowData[5] = $pku; // F
  $rowData[6] = $stavka; // G
  $rowData[7] = $row['DisciplineName']; // H
  $rowData[8] = $groupStr; // I
  $rowData[9] = $row['education_level']; // J
  $rowData[10] = $row['SpecialityName']; // K
  $rowData[11] = $row['napravlennost']; // L
  $rowData[12] = $lang; // M
  $rowData[13] = $formStr; // N
  $rowData[14] = $row['UID_Course'] ? $row['UID_Course'] : ''; // O
  $rowData[15] = $semester; // P
  $rowData[16] = $studentAmount; // Q

  $total_hours = 0;
  $auditor_hours = 0;
  
  if ($nType == 'aspirantura_ruk_asp') {
    $rowData[36] = $amount; // AK
    $total_hours += $amount;
  } elseif ($nType == 'aspirantura_ruk_soisk') {
    $rowData[37] = $amount; // AL
    $total_hours += $amount;
  } elseif ($nType == 'ik' || mb_stripos($kwName, 'Индивидуальные консультации', 0, 'UTF-8') !== false) {
    $rowData[38] = $amount; // AM
    $total_hours += $amount;
  } elseif ($nType == 'ksro' || mb_stripos($kwName, 'Контроль самостоятельной работы', 0, 'UTF-8') !== false) {
    $rowData[39] = $amount; // AN
    $total_hours += $amount;
  } else {
    if (isset($col_mapping[$row['UID_KindOfWork']])) {
      $colNum = $col_mapping[$row['UID_KindOfWork']];
      $rowData[$colNum - 1] = $amount;
      $total_hours += $amount;
      
      if ($colNum >= 18 && $colNum <= 27) {
        $auditor_hours += $amount;
      }
    } elseif ($nType == 'aspirantura_kand_exam') {
      $rowData[25] = $amount; // Z
      $total_hours += $amount;
      $auditor_hours += $amount;
    }
  }

  if ($nType == 'aspirantura_ruk_asp') {
    $comment = $AspiranturaRukAspByLoadId[$row['LoadId']]['fio'];
  } elseif ($nType == 'aspirantura_ruk_soisk') {
    $comment = $AspiranturaRukSoiskByLoadId[$row['LoadId']]['fio'];
  } else {
    $comment = $row['comment_to_admin'];
  }
  
  $rowData[40] = $comment; // AO
  $rowData[41] = $total_hours; // AP
  $rowData[42] = $auditor_hours; // AQ
  
  if (mb_strtolower($lang, 'UTF-8') == 'английский') {
    $rowData[43] = $total_hours; // AR
  } else {
    $rowData[43] = 0; // AR
  }
  
  $rowData[44] = $yearOfEntryStr; // AS
  $rowData[45] = $row['YearOfEducation']; // AT
  $rowData[46] = $row['Abbr'] ?: ''; // AU
  $rowData[47] = ''; // AV
  
  if (!$isWriterOpened) {
      // openToBrowser inside spout sends headers automatically. We just call it.
      @$writer->openToBrowser('report.xlsx');
      $isWriterOpened = true;

      $cells = [];
      foreach ($headerValues as $val) {
          $cells[] = WriterEntityFactory::createCell($val);
      }
      $headerRow = WriterEntityFactory::createRow($cells);
      $writer->addRow($headerRow);
  }

  // Convert array to Spout Row
  $cells = [];
  foreach ($rowData as $val) {
      // Avoid passing null to fromValue
      $cells[] = WriterEntityFactory::createCell($val !== null ? $val : '');
  }
  $spoutRow = WriterEntityFactory::createRow($cells);
  $writer->addRow($spoutRow);
}

// Finish writing
if ($isWriterOpened) {
    $writer->close();
}


$peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

EchoLog("Memory used: $peakMemory MB");
?>