<?php

include '../functions.php';

include '../connect/opop2.php';

EchoLog("Start cron");

$LOAD_NEW_DATA_FROM_NETWORK = true;
$UPDATE_TABLES = true;

$Napravlenia = GetTable('napravlenia', "", "", "napravlenie");

include '../connect.php';

// Получим режим работы системы из БД
$_system_mode = GetSystemParam('system_mode');

// Столбцы, используемые для создания кеша, чтобы выявлять изменения в строках при обновлении из XML
// 'UID' - если юид сменился, то могли спотчить или распоточить (изменение суффикса)
$xml_content_of_load_columns_for_hash = ['YearOfEducation', 'DateFrom', 'DateTo', 'Amount', 'AmountInUnit', 'TypeOfContingent', 'UID_Group', 'UID_SubGroup', 'UID_Stream', 'UID_KindOfWork', 'PackageNumber', 'ID_Auditorium', 'UID_Discipline', 'UID_Chair', 'UID_Semester', 'Module', 'TypeWorkload', 'UID_Course', 'DisciplineTypeLoad', 'LoadType', 'StudentAmount'];

$xml_content_of_load_staff_columns_for_hash = ['TypeOfContingent', 'UID_Group', 'UID_SubGroup', 'Abbr', 'UID_FormOfEducation', 'UID_Speciality', 'UID_Specialization', 'UID_Language', 'UID_FacultyOwner', 'UID_FacultyPerformer'];

function hash_column_values_only($data, $columns)
{
  $values = [];
  
  foreach ($columns as $column) {
      if (isset($data[$column])) {
          $values[] = $data[$column];
      }
  }
  
  // Сортируем значения для consistency (если порядок столбцов может меняться)
  // sort($values);
  
  return md5(implode('|', $values));
}

function IsEducationLevelVO($education_level)
{
  return in_array($education_level, ['бакалавриат', 'специалитет', 'магистратура', 'аспирантура']);
}

// $hash = hash_column_values_only($data, $xml_content_of_load_staff_columns_for_hash);

// нагрузка до обновления
$XMLContentOfLoadPrev = GetTable('xml_content_of_load', "", "", "UID", "UID, base_uid, base_uid2, hash");

$XMLContentOfLoadPrevByBaseUID2 = [];

if ($XMLContentOfLoadPrev)
{
  foreach ($XMLContentOfLoadPrev as $row)
  {
    $XMLContentOfLoadPrevByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
  }
}

$_XMLContentOfLoadStaffPrev = GetTable('xml_content_of_load_staff', "", "", null, "UID, base_uid2, UID_ContentOfLoad, hash");

// нагрузка стафф до обновления
$XMLContentOfLoadStaffPrevByBaseUID2 = [];

foreach ($_XMLContentOfLoadStaffPrev as $row)
{
  // $XMLContentOfLoadStaffPrev[$row['UID_ContentOfLoad']][$row['UID']] = $row;
  $XMLContentOfLoadStaffPrevByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
}

unset($_XMLContentOfLoadStaffPrev);

$NagruzkaPrev = GetTable('nagruzka', "", "", "load_base_UID2");

// EchoLog($NagruzkaPrev['26589.281474976773927']);
// без этой строки непонятный баг: ошибочно проваливаемся в if (!$NagruzkaPrev[$nagr['base_uid']])
echo sizeof($NagruzkaPrev);

// $NagruzkaPrev = [];

// foreach ($_Nagruzka as $row)
// {
//   $NagruzkaPrev["$row[chair_id]-$row[load_base_UID]"] = $row;
// }

// unset($_Nagruzka);


// требует подключения к Сотруднику
function GetChairSotrudniki($year, $dop_sql = "", $actual = null /*, $qualify_category_not_empty = false */)
{
  $position_table_name = "position$year";
  $podrazdelenia_table_name = "podrazdelenia$year";

  // if ($qualify_category_not_empty)
  // {
  //   $qualify_category_not_empty_sql = "AND $position_table_name.`qualify_category` <> ''";
  // }

  if ($actual != null)
  {
    $actual_sql = "AND `actual` = '$actual'";
  }

  if ($year >= 2025)
  {
    $pkg_sql = ", $position_table_name.`pkg`, $position_table_name.`pku`";
  }

  // Ищем ГПХ-шников
  if (mb_stripos($dop_sql, 'ГПХ') !== false)
  {
    $kaf_sql = "";
  }
  else
  // не ГПХ-шники
  {
    $kaf_sql = "AND $podrazdelenia_table_name.`pname` LIKE('Кафедра%') ";
  }

  // AND $podrazdelenia_table_name.`parent_id` <> '00255'
  $query = "
              SELECT person.`id` as person_id, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`, `$position_table_name`.podrazdelenie_id, `$position_table_name`.ukrup_code as department_id, $position_table_name.`podrazdelenia_chain`, $podrazdelenia_table_name.`id` as chair_id, $position_table_name.`position_category`, $position_table_name.`type`, $position_table_name.`qualify_category`, $position_table_name.`stavka`
              $pkg_sql
              FROM `$position_table_name`
              JOIN `person` ON `$position_table_name`.person_id = `person`.id
              JOIN `$podrazdelenia_table_name` ON `$position_table_name`.podrazdelenia_chain LIKE CONCAT('%|', $podrazdelenia_table_name.`id`, '|%')
              WHERE $podrazdelenia_table_name.`id` <> '00255'  AND $podrazdelenia_table_name.`parent_id` <> ''
              AND $podrazdelenia_table_name.`ukrup_code` <> '03037' # УВЦ
              $kaf_sql
              AND `position_category` = 'ППС'
              $actual_sql
              $dop_sql
            ";

  return GetSQL($query);
}

$cur_year = date('Y');
include '../connect/sotrudnik.php';
$Podrazdelenia = GetTable("podrazdelenia$cur_year", "", "", "id");
$Person = GetTable('person', '', '', 'id', 'id, alias');

// print_r($Podrazdelenia);

// т.к. сначала загружаем 2ю таблицу и пропускаем в ней строки не ВО, а признак не ВО определяется именно по 2й,
// то нужно сохранить пропущенные base_uid, чтобы не грузить их и в 1ю таблицу
$ContentOfLoadStaffBaseUID1sNotVo = [];


function LoadXML($filename, $table_name)
{
  global $mysqli, $Napravlenia, $xml_content_of_load_columns_for_hash, $xml_content_of_load_staff_columns_for_hash, $XMLKindOfWorkGIA, $XMLKindOfWorkVKR, $XMLKindOfWorkKurs, $_XMLContentOfLoadStaffByBaseUID1, $XMLSpeciality, $ContentOfLoadStaffBaseUID1sNotVo, $db_error;

  EchoLog("LoadXML: $table_name", 'file screen');

  // if ($table_name === 'xml_content_of_load') EchoLog("HERE 1");

  $XML = simplexml_load_string(file_get_contents($filename));

  // if ($table_name === 'xml_content_of_load') EchoLog("HERE 2");

  $mysqli->query("DELETE FROM `$table_name`");

  // if ($table_name === 'xml_content_of_load') EchoLog("HERE 3");

  foreach ($XML->Data->Collection->Object as $s)
  {
    $obj = $s->Collection;
    $arr = [];
    $sql_arr = [];

    foreach ($obj->prop_value as $prop)
    {
      $attrs = $prop->attributes();

      // var_dump($attrs);
      $prop = (string) $attrs['prop_name'];
      $value = quote_smart((string) $attrs['value']);

      if ($table_name == 'xml_lecturer' && $prop == 'FIO')
      {
        $value = str_replace('!_Вакансия_!', 'Вакансия', $value);
      }

      $arr[$prop] = $value;

      // echo $prop;
      // echo $value;

      $sql_arr[] = "`$prop` = '$value'";
    }

    // подцепим уровень образования из ОПОП-2
    if ($table_name == 'xml_speciality')
    {
      $sql_arr[] = "`education_level` = '{$Napravlenia[$arr['Code']]['education_level']}'";
    }

    if ($table_name === 'xml_content_of_load')
    {
      $base_uid = get_base_uid1($arr['UID']);
      // EchoLog($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']);
      // EchoLog($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']);

      if ($arr['UID'] === '26589.281474976765788')
      {
        // EchoLog($arr);
        // EchoLog($_XMLContentOfLoadStaffByBaseUID1[$base_uid]);
        // EchoLog($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]);
        // EchoLog(IsEducationLevelVO($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']));
      }

      // Проверим уровень образования, будем загружать только ВО
      // пропускаем, пропустим остальное
      if ($_XMLContentOfLoadStaffByBaseUID1[$base_uid] && $XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level'] && 
          !IsEducationLevelVO($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']) || $ContentOfLoadStaffBaseUID1sNotVo[$base_uid])
      {
        // if ($arr['UID'] === '26589.281474976765788')
        {
          // EchoLog("$arr[UID] $base_uid - {$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']} - {$XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['UID']} - {$XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']} НЕ ЗАГРУЖАЕМ", "file screen");
        }
        continue;
      }


      
      $sql_arr[] = "`base_uid` = '$base_uid'";

      $base_uid2 = get_base_uid2($arr['UID']);
      $sql_arr[] = "`base_uid2` = '$base_uid2'";

      $hash = hash_column_values_only($arr, $xml_content_of_load_columns_for_hash);
      $sql_arr[] = "`hash` = '$hash'";

      if ($XMLKindOfWorkGIA[$arr['UID_KindOfWork']])
      {
        $sql_arr[] = "`nagruzka_type` = 'gia'";
      }
      elseif ($XMLKindOfWorkVKR[$arr['UID_KindOfWork']])
      {
        $sql_arr[] = "`nagruzka_type` = 'ruk_vkr'";
      }
      elseif ($XMLKindOfWorkKurs[$arr['UID_KindOfWork']])
      {
        $sql_arr[] = "`nagruzka_type` = 'ruk_kurs'";
      }
      elseif (IsNagruzkaDiscipline($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr']))
      {
        $sql_arr[] = "`nagruzka_type` = 'discipline'";
      }
      elseif (IsNagruzkaRukPractice($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr']))
      {
        $sql_arr[] = "`nagruzka_type` = 'ruk_practice'";
      }
      else
      {
        $sql_arr[] = "`nagruzka_type` = ''";
      }
    }
    
    if ($table_name == 'xml_content_of_load_staff')
    {
      $base_uid = get_base_uid1($arr['UID_ContentOfLoad']);

      // Проверим уровень образования, будем загружать только ВО
      // пропускаем, пропустим остальное
      if ($XMLSpeciality[$arr['UID_Speciality']] && !IsEducationLevelVO($XMLSpeciality[$arr['UID_Speciality']]['education_level']))
      {
        if ($base_uid === '26589.281474976765788')
        {
          // EchoLog("$arr[UID] $base_uid - {$XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']} НЕ ЗАГРУЖАЕМ");
        }

        $ContentOfLoadStaffBaseUID1sNotVo[$base_uid] = $base_uid;

        continue;
      }

      
      $sql_arr[] = "`base_uid` = '$base_uid'";

      $base_uid2 = get_base_uid2($arr['UID_ContentOfLoad']);
      $sql_arr[] = "`base_uid2` = '$base_uid2'";

      $hash = hash_column_values_only($arr, $xml_content_of_load_staff_columns_for_hash);
      $sql_arr[] = "`hash` = '$hash'";
    }



    $sql = JoinArrayElements($sql_arr, ', ');
    // echo $sql;
    // print_r($arr);

    $query = "INSERT INTO `$table_name` SET $sql";

    if ($table_name === 'xml_content_of_load')
    {
      // EchoLog($query);
    }

    $Result = $mysqli->query($query);

    if (!$Result)
    {
      EchoLog($mysqli->error, 'file screen');
      // $db_error = true;
    }

    // var_dump($s->Collection->attributes());
    // exit;
  }
}

include '../connect.php';

if ($LOAD_NEW_DATA_FROM_NETWORK)
{
  file_put_contents('ContentOfLoad.xml', file_get_contents('http://192.168.59.100/nagruzka/ContentOfLoad.xml'));
  file_put_contents('ContentOfLoadStaff.xml', file_get_contents('http://192.168.59.100/nagruzka/ContentOfLoadStaff.xml'));
  file_put_contents('SubGroup.xml', file_get_contents('http://192.168.59.100/nagruzka/SubGroup.xml'));
  file_put_contents('Group.xml', file_get_contents('http://192.168.59.100/nagruzka/Group.xml'));
  file_put_contents('Stream.xml', file_get_contents('http://192.168.59.100/nagruzka/Stream.xml'));
  file_put_contents('KindOfWork.xml', file_get_contents('http://192.168.59.100/nagruzka/KindOfWork.xml'));
  file_put_contents('Discipline.xml', file_get_contents('http://192.168.59.100/nagruzka/Discipline.xml'));
  file_put_contents('Chair.xml', file_get_contents('http://192.168.59.100/nagruzka/Chair.xml'));
  file_put_contents('SubGroup.xml', file_get_contents('http://192.168.59.100/nagruzka/SubGroup.xml'));
  file_put_contents('FormOfEducation.xml', file_get_contents('http://192.168.59.100/nagruzka/FormOfEducation.xml'));
  file_put_contents('Speciality.xml', file_get_contents('http://192.168.59.100/nagruzka/Speciality.xml'));
  file_put_contents('Specialization.xml', file_get_contents('http://192.168.59.100/nagruzka/Specialization.xml'));
  file_put_contents('Language.xml', file_get_contents('http://192.168.59.100/nagruzka/Language.xml'));
  file_put_contents('Faculty.xml', file_get_contents('http://192.168.59.100/nagruzka/Faculty.xml'));
  file_put_contents('Lecturer.xml', file_get_contents('http://192.168.59.100/nagruzka/Lecturer.xml'));
  file_put_contents('Post.xml', file_get_contents('http://192.168.59.100/nagruzka/Post.xml'));

  // Получим данные кандидатов; они нам нужны, чтобы получить id кандидата = будущего сотрудника; он мог быть уже сотрудником прежде, тогда его id является прежним id сотрудника
  $url = 'http://www:nahuheti9@ip.unn.ru/integration/rest/base/getChangedObjects?map=nngu.ais.employees.add';
  file_put_contents('nngu.ais.employees.add.xml', file_get_contents($url));
}


$mysqli->query("START TRANSACTION");

if ($UPDATE_TABLES)
{
  LoadXML('Stream.xml', 'xml_stream');
  LoadXML('Faculty.xml', 'xml_faculty');
  LoadXML('Language.xml', 'xml_language');
  // TMP comment, чтобы локально не загружалось, т.к. нет опоп2
  LoadXML('Speciality.xml', 'xml_speciality');
  LoadXML('Specialization.xml', 'xml_specialization');
  LoadXML('SubGroup.xml', 'xml_subgroup');
  LoadXML('Chair.xml', 'xml_chair');
  LoadXML('KindOfWork.xml', 'xml_kind_of_work');
  // понадобится в LoadXML('ContentOfLoad.xml', 'xml_content_of_load')
  // ГИА
  $XMLKindOfWorkGIA = GetTable('xml_kind_of_work', "`Name` LIKE('Участие в комиссии%')", "", "UID");
  // Руководство ВКР
  $XMLKindOfWorkVKR = GetTable('xml_kind_of_work', "`Name` LIKE('Руководство ВКР%')", "", "UID");
  // Руководство курсовыми работами
  $XMLKindOfWorkKurs = GetTable('xml_kind_of_work', "`Name` LIKE('%курсовой работ%')", "", "UID");
  // Специальности (нужны для загрузки только нагрузки Высшего образования: xml_content_of_load_staff.UID_speciality ~ xml_speciality)
  $XMLSpeciality = GetTable('xml_speciality', "", "", "UID");

  // + Руководство практикой...

  LoadXML('Group.xml', 'xml_group');
  LoadXML('Discipline.xml', 'xml_discipline');
  LoadXML('Lecturer.xml', 'xml_lecturer');
  LoadXML('Post.xml', 'xml_post');

  LoadXML('ContentOfLoadStaff.xml', 'xml_content_of_load_staff');

  // Чтобы определеить, является ли нагрузка xml_content_of_load типом Руководство практикой (определяется по аббревиатуре в xml_content_of_load_staff), получим по одной любой строке из xml_content_of_load_staff
  // Также это используется для получения уровня образования (указаны в xml_content_of_load_staff.UID_Speciality ~ xml_speciality), чтобы не загружать лишние
  $_XMLContentOfLoadStaffByBaseUID1 = GetTable('xml_content_of_load_staff', "", "", 'base_uid', "base_uid, Abbr, UID_Speciality");

  LoadXML('ContentOfLoad.xml', 'xml_content_of_load');
}

// $mysqli->query("COMMIT");

// exit;


// Данные после текущего импорта

$XMLLecturer = GetTable('xml_lecturer', "", "", "UID");
$XMLPost = GetTable('xml_post', "", "", "Name");
$XMLChairByCode = GetTable('xml_chair', "", "", "Code");
$XMLFacultyByCode = GetTable('xml_faculty', "", "", "Code");
$XMLChairByUID = GetTable('xml_chair', "", "", "UID");


$kandidats_xml = simplexml_load_string(file_get_contents('nngu.ais.employees.add.xml'));

$Kandidats_arr = [];

foreach ($kandidats_xml->Employee as $s)
{
  $person_id = (string) $s->UID; 
  $fio = (string)  $s->FullName;
  $birth_date = (string)  $s->BornDate;

  $Works = $s->Works;

  foreach ($Works->Work as $work)
  {
    $podrazdelenie_id_attrs = $work->Department1->attributes();
    $podrazdelenie_id = (string) str_pad($podrazdelenie_id_attrs['ID']->__toString(), 5, "0000", STR_PAD_LEFT);
    $dolzhnost = $work->Duty->__toString();

    // if (!$Podrazdelenia[])
    // var_dump($podrazdelenie_id) . '<br>';
    // echo $Podrazdelenia[$work->podrazdelenie_id]['pname'] . '<br>';

    // echo $podrazdelenie_id . ' ' . $Podrazdelenia[$podrazdelenie_id]['pname'] . '<br>';

    // Начинается с "Кафедра"
    if (mb_stripos($Podrazdelenia[$podrazdelenie_id]['pname'], 'Кафедра') === 0)
    {
      $Kandidats_arr["$person_id-$podrazdelenie_id"] = ['person_id' => $person_id, 'fio' => $fio, 'department_id' => $Podrazdelenia[$podrazdelenie_id]['ukrup_code'], 'dolzhnost' => $dolzhnost, 'type' => 'kandidat', 'chair_id' => $podrazdelenie_id, 'podrazdelenie_id' => $podrazdelenie_id];

      // echo $Podrazdelenia[$podrazdelenie_id]['ukrup_code'] . '<br>';
    }
  }
}

unset($kandidats_xml);

// print_r($Kandidats_arr);

// exit;


include '../connect/sotrudnik.php';

$SotrudnikiItogoByKey = [];

// 1. «сотрудник» - ППС кафедры, трудоустроенные на текущий момент

$SotrudnikiActual = GetChairSotrudniki($cur_year, "", 1);

if ($SotrudnikiActual)
{
  foreach ($SotrudnikiActual as $sotrudnik)
  {
    $sotrudnik['type'] = 'sotrudnik';

    if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
    $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
  }
}

unset($SotrudnikiActual);

// 2. «кандидат» - ППС из сервиса Кандидат, привязанные к этой кафедре и дошедшие до согласования с УОУП (т.е. согласованные УК) и последующие статусы.

if ($Kandidats_arr)
{
  if ($Kandidats_arr)
  {
    foreach ($Kandidats_arr as $sotrudnik)
    {
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

unset($Kandidats_arr);

// print_r($SotrudnikiItogoByKey);
// exit;

// 3. «работал» - ППС когда-либо работавшие на кафедре (последние 3 года)

$ChairsSotrudnikiCurYear = GetChairSotrudniki($cur_year);
$ChairsSotrudnikiPrevYear = GetChairSotrudniki($cur_year - 1);
$ChairsSotrudnikiPrevPrevYear = GetChairSotrudniki($cur_year - 2);

if ($ChairsSotrudnikiPrevPrevYear)
{
  if ($ChairsSotrudnikiPrevPrevYear)
  {
    foreach ($ChairsSotrudnikiPrevPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'worked';
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

if ($ChairsSotrudnikiPrevYear)
{
  if ($ChairsSotrudnikiPrevYear)
  {
    foreach ($ChairsSotrudnikiPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'worked';
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

if ($ChairsSotrudnikiCurYear)
{
  if ($ChairsSotrudnikiCurYear)
  {
    foreach ($ChairsSotrudnikiCurYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'worked';
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

unset($ChairsSotrudnikiCurYear);

// 4. «ГПХ» - ППС ГПХ, работающие или когда-либо работавшие на факультете, привязки к кафедре у ГПХ нет (последние 3 года)

$SotrudnikiGPH = GetChairSotrudniki($cur_year, "AND `type` = 'ГПХ'");
$SotrudnikiGPHPrevYear = GetChairSotrudniki($cur_year - 1, "AND `type` = 'ГПХ'");
$SotrudnikiGPHPrevPrevYear = GetChairSotrudniki($cur_year - 2, "AND `type` = 'ГПХ'");

// EchoLog($SotrudnikiGPH);

if ($SotrudnikiGPHPrevPrevYear)
{
  if ($SotrudnikiGPHPrevPrevYear)
  {
    foreach ($SotrudnikiGPHPrevPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'gph';
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

unset($SotrudnikiGPHPrevPrevYear);


if ($SotrudnikiGPHPrevYear)
{
  if ($SotrudnikiGPHPrevYear)
  {
    foreach ($SotrudnikiGPHPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'gph';
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

unset($SotrudnikiGPHPrevYear);


if ($SotrudnikiGPH)
{
  if ($SotrudnikiGPH)
  {
    foreach ($SotrudnikiGPH as $sotrudnik)
    {
      $sotrudnik['type'] = 'gph';
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

unset($SotrudnikiGPH);


// print_r($SotrudnikiItogoByKey);
// exit;


// EchoLog(sizeof($ChairsSotrudniki));

// include '../connect/kandidat.php';

// $Kandidats = GetTable('kandidat', "`person_id` IS NOT NULL AND `status` IN ('soglasovanie_uoup', 'accepted_uoup', 'accepted', 'predstavlenie', 'predstavlenie_signed_dean', 'predstavlenie_signed_rector', 'done')");

// $KandidatsByKey = [];

// foreach ($Kandidats as $kandidat)
// {
//   $KandidatsByKey["$kandidat[person_id]-$kandidat[chair_id]"] = true;
// }

include '../connect.php';



// $mysqli->query("TRUNCATE `sotrudniki`");

// Соберём добавляемых по ключу,
// чтобы проставлять дату удаления
// $AddingSotrudniki = [];
// $ChairsSotrudnikiPrevYearByKey = [];
// $ChairsSotrudnikiPrevPrevYearByKey = [];

// if ($ChairsSotrudniki)
// {
//   foreach ($ChairsSotrudniki as $chair_sotrudnik)
//   {
//     $AddingSotrudniki["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] = $chair_sotrudnik;
//   }
// }

// if ($ChairsSotrudnikiPrevYear)
// {
//   foreach ($ChairsSotrudnikiPrevYear as $chair_sotrudnik)
//   {
//     $ChairsSotrudnikiPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] = $chair_sotrudnik;
//   } 
// }

// if ($ChairsSotrudnikiPrevPrevYear)
// {
//   foreach ($ChairsSotrudnikiPrevPrevYear as $chair_sotrudnik)
//   {
//     $ChairsSotrudnikiPrevPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] = $chair_sotrudnik;
//   } 
// }

// Сотрудники, которые в данный момент есть в справочнике
// чтобы проставлять дату добавления
$Sotrudniki = GetRows('sotrudniki', null, null, null, 'person_id, chair_id, type');
$SotrudnikiInLKByKey = [];

if ($Sotrudniki)
{
  foreach ($Sotrudniki as $sotr)
  {
    $SotrudnikiInLKByKey["$sotr[person_id]-$sotr[chair_id]"] = $sotr;

    // if ($sotr['person_id'] && $sotr['chair_id'])
    {
      // Если есть в справочнике, но нет среди добавляемых
      if (!$SotrudnikiItogoByKey["$sotr[person_id]-$sotr[chair_id]"])
      {
        $mysqli->query("
                  UPDATE `sotrudniki` 
                  SET `date_remove` = NOW() WHERE  `person_id` = '$sotr[person_id]' AND `chair_id` = '$sotr[chair_id]'
                  ");
      }
    }
    // else
    // {
    //   EchoLog();
    // }
  }
}










// $mysqli->query("UPDATE `sotrudniki` SET `actual` = '0'");

if ($SotrudnikiItogoByKey)
{
  foreach ($SotrudnikiItogoByKey as $chair_sotrudnik)
  {
    // Человека на кафедре не было в базе ЛК ЗК
    if (!$SotrudnikiInLKByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"])
    {
      // $add_date_sql = ", `date_add` = NOW()";
      $adding = true;
    }
    // Человек в базе ЛК ЗК на этой кафедре уже есть
    else
    {
      // $add_date_sql = '';
      $adding = false;
    }

    if (!$chair_sotrudnik['fio'])
    {
      $chair_sotrudnik['fio'] = "$chair_sotrudnik[surname] $chair_sotrudnik[name] $chair_sotrudnik[patronymic]";
    }

    // $type = '';

    // определим "тип"
    // if ($chair_sotrudnik['position_category'] == 'ППС' && $chair_sotrudnik['type'] == 'ГПХ')
    // {
    //   $type = 'gph';
    // }
    
    // if ($ChairsSotrudnikiPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] || $ChairsSotrudnikiPrevPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"])
    // {
    //   $type = 'worked';
    // }
    
    // if ($KandidatsByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"])
    // {
    //   $type = 'kandidat';
    // }

    $post_uid = $XMLPost[mb_strtolower($chair_sotrudnik['dolzhnost'])]['UID'];
    // EchoLog($post_uid);
    
    $chair_uid = $XMLChairByCode[$chair_sotrudnik['chair_id']]['UID'];
    $department_uid = $XMLFacultyByCode[$chair_sotrudnik['department_id']]['UID'];
    $person_id = $chair_sotrudnik['person_id'];
    $person_type = $chair_sotrudnik['type'];
    // EchoLog($chair_uid);

    $lecturer = GetLecturer($person_id, $post_uid, $chair_uid, $department_uid, $person_type);

    // Если не нашли, то не добавляем сотрудника и не обновляем
    if (!$lecturer || !$lecturer['UID'])
    {
      continue;
    }

    if ($adding)
    {
      // uid должности из Галактики
      // EchoLog($chair_sotrudnik['dolzhnost']);

      // $lecturer = GetRow('xml_lecturer', ['Tab_number' => $chair_sotrudnik['person_id'], 'UID_Post' => $post_uid, 'UID_Chair' => $chair_uid]);

      // EchoLog($lecturer);

      // if ((!$lecturer || !$lecturer['UID']) && $person_type != 'worked')
      // {
      //   EchoLog("$chair_sotrudnik[chair_id] $chair_sotrudnik[department_id]");
      //   EchoLog("! НЕ НАЙДЕН ЛЕКТОР ДЛЯ $person_id, $post_uid ($chair_sotrudnik[dolzhnost]), $chair_uid, $department_uid, $person_type)");
      // }

      if ($chair_sotrudnik['type'] == 'sotrudnik')
      {
        $selected = '1';
      }
      else
      {
        $selected = '0';
      }

      $login = $Person[$chair_sotrudnik['person_id']]['alias'];

      // if ($sotrudnik['type'])

      $query = "
              INSERT INTO `sotrudniki` 
              SET `person_id` = '$chair_sotrudnik[person_id]', `lecturer_uid` = '$lecturer[UID]', `lecturer_login` = '$login',
              `fio` = '$chair_sotrudnik[fio]', `chair_id` = '$chair_sotrudnik[chair_id]', `department_id` = '$chair_sotrudnik[department_id]',
              `podrazdelenie_id` = '$chair_sotrudnik[podrazdelenie_id]', `dolzhnost` = '$chair_sotrudnik[dolzhnost]', `type` = '$chair_sotrudnik[type]', `selected` = '$selected', `stavka` = '$chair_sotrudnik[stavka]', `pku` = '$chair_sotrudnik[pku]', `pkg` = '$chair_sotrudnik[pkg]', `date_add` = NOW()
              ON DUPLICATE KEY UPDATE
              `pku` = VALUES(`pku`),
              `pkg` = VALUES(`pkg`),
              `stavka` = VALUES(`stavka`)
            ";

      // echo $query . '<br><br>';

      $Result = $mysqli->query($query);

      if (!$Result)
      {
        EchoLog("Error #573 in cron.php:<br>" . $mysqli->error . "<br><br>$query", "file mail");
        $db_error = true;
      }
    }
    // updating
    else
    {
      $query = "
              UPDATE `sotrudniki` 
              SET `fio` = '$chair_sotrudnik[fio]', `dolzhnost` = '$chair_sotrudnik[dolzhnost]', `type` = '$chair_sotrudnik[type]', `stavka` = '$chair_sotrudnik[stavka]', `pku` = '$chair_sotrudnik[pku]', `pkg` = '$chair_sotrudnik[pkg]', 
              # !! обновление lecturer_uid
              `lecturer_uid` = '$lecturer[UID]'
              WHERE `person_id` = '$chair_sotrudnik[person_id]' AND `chair_id` = '$chair_sotrudnik[chair_id]'
            ";

      // echo $query . '<br><br>';

      $Result = $mysqli->query($query);

      if (!$Result)
      {
        EchoLog("Error #683 in cron.php:<br>" . $mysqli->error . "<br><br>$query", "file mail");
        $db_error = true;
      }
    }

    
  }
}

unset($Sotrudniki);
unset($SotrudnikiInLKByKey);
unset($SotrudnikiItogoByKey);







// Перед тем как использовать nagruzka, xml_content_of_load, xml_content_of_load_staff
// Произведём ЗАМЕНЫ lecturer_uid на более подходящие
// Выше в таблице sotrudniki замены уже сделали, получим сотрудников с обновлёнными uid
// TODO: ограничить конкретным этапом??
$Sotrudniki = GetTable('sotrudniki');
$SotrudnikiByPersonChair = [];

foreach ($Sotrudniki as $sotrudnik)
{
  $SotrudnikiByPersonChair["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
}


// $ZavkafSplits = GetTable('zavkaf_splits');

foreach ($SotrudnikiByPersonChair as $sotrudnik)
{
  $post_uid = $XMLPost[mb_strtolower($sotrudnik['dolzhnost'])]['UID'];
  $chair_uid = $XMLChairByCode[$sotrudnik['chair_id']]['UID'];
  $department_uid = $XMLFacultyByCode[$sotrudnik['department_id']]['UID'];
  $person_type = $sotrudnik['type'];

  $lecturer = GetLecturer($sotrudnik['person_id'], $post_uid, $chair_uid, $department_uid, $person_type);

  $SplitsForSotrudnik = GetRows('zavkaf_splits', ['lecturer_person_id' => $sotrudnik['person_id']]);

  if ($SplitsForSotrudnik)
  {
    foreach ($SplitsForSotrudnik as $split_row)
    {
      if ($split_row['lecturer_uid'] != $lecturer['UID'])
      {
        // if ($sotrudnik['person_id'] == 51586)
        EchoLog("Заменяем в сплите для $sotrudnik[person_id] для $split_row[content_of_load_uid_new]: $split_row[lecturer_uid] != $lecturer[UID]");

        // content_of_load_uid не правим, т.к. считается, что завкаф разбивает "с нуля", т.е. из Галактики нет разбиений, значит uid не содержит сотрудников
        $base_uid2_obj = parseNagruzkaBaseUid2($split_row['content_of_load_uid_new']);

        // заменяем суффикс лектора на новый
        $base_uid2_obj['lector_suffix'] = $lecturer['UID'];

        // склеиваем в обновлённый base_uid2
        $new_content_of_load_uid_new = glueNagruzkaBaseUid2Parts($base_uid2_obj);

        $base_uid2_obj = parseNagruzkaBaseUid2($split_row['base_uid2_new']);

        // заменяем суффикс лектора на новый
        $base_uid2_obj['lector_suffix'] = $lecturer['UID'];

        // склеиваем в обновлённый base_uid2
        $new_base_uid2_new = glueNagruzkaBaseUid2Parts($base_uid2_obj);


        $query = "UPDATE `zavkaf_splits` 
          SET `content_of_load_uid_new` = '$new_content_of_load_uid_new', 
          `base_uid2_new` = '$new_base_uid2_new',
          `lecturer_uid` = '$lecturer[UID]' 
          WHERE  `id` = '$split_row[id]'";

        $Result = $mysqli->query($query);

        if (!$Result)
        {
          EchoLog($mysqli->error);
          EchoLog($query);
          $db_error = true;
        }
      }
    }
  }

  
}

/*
КОД ЗАКОММЕНТИРОВАН, ПОТОМУ ЧТО В СООТВ. ЭТАПЕ ЭТИ ДВЕ ТАБЛИЦЫ ПРИХОДЯТ БЕЗ РАСПРЕДЕЛЕНИЯ, ПОЭТОМУ ЗАМЕНЯТЬ НЕЧЕГО

$DeletedOrChangedNagruzka = [];
$ChangedXmlContentOfLoad[] = [];

$Nagruzka = GetTable('nagruzka', "`valid` = '1' AND `lecturer_fio` IS NOT NULL AND `lecturer_fio` <> 'Вакансия' AND `lecturer_fio` <> ''");
$NagruzkaByLecturer = [];

foreach ($Nagruzka as $nagruzka)
{
  if ($nagruzka['lecturer_person_id'] == 51586)
  {
    // EchoLog($nagruzka);
  }

  $sotrudnik = $SotrudnikiByPersonChair["$nagruzka[lecturer_person_id]-$nagruzka[chair_id]"];

  if ($sotrudnik)
  {
    // $NagruzkaByLecturer[$nagruzka['lecturer_uid']][] = $nagruzka;
    $post_uid = $XMLPost[mb_strtolower($sotrudnik['dolzhnost'])]['UID'];
    $chair_uid = $XMLChairByCode[$sotrudnik['chair_id']]['UID'];
    $department_uid = $XMLFacultyByCode[$sotrudnik['department_id']]['UID'];
    $person_type = $sotrudnik['type'];

    $lecturer = GetLecturer($sotrudnik['person_id'], $post_uid, $chair_uid, $department_uid, $person_type);

    if ($sotrudnik['person_id'] == 51586)
    {
      // EchoLog($lecturer);
    }

    if ($lecturer && $lecturer['UID'])
    {
      // $nagruzka_base_uid2_obj = parseNagruzkaBaseUid2($nagruzka['load_base_UID2']);
      $nagruzka_lecturer_uid = $nagruzka['lecturer_uid'];

      // В таблице nagruzka load_base_UID2 должен содержать lecturer_uid (если он не пуст)
      // удалим некорректные строки, если не содержит
      if ($nagruzka['lecturer_uid'] && strpos($nagruzka['load_base_UID2'], $nagruzka['lecturer_uid']) === false)
      {
        $query = "DELETE FROM `nagruzka` WHERE `load_base_UID2` = '$nagruzka[load_base_UID2]'";
        EchoLog($query);
        $mysqli->query($query);
        $DeletedOrChangedNagruzka[$nagruzka['load_base_UID2']] = true;
        continue;
      }

      // есть лектор в нагрузке, но мы по приоритетам нашли более подходящий UID лектора, заменим на него в таблицах БД
      if ($nagruzka_lecturer_uid && $nagruzka_lecturer_uid != $lecturer['UID'])
      {
        $base_uid2_obj = parseNagruzkaBaseUid2($nagruzka['load_base_UID2']);

        // if ($sotrudnik['person_id'] == 51586)
        {
          EchoLog("Заменяем для $nagruzka[load_base_UID2]: $nagruzka_lecturer_uid != $lecturer[UID]");
        }

        // заменяем суффикс лектора на новый
        $base_uid2_obj['lector_suffix'] = $lecturer['UID'];

        // склеиваем в обновлённый base_uid2
        $new_base_uid2 = glueNagruzkaBaseUid2Parts($base_uid2_obj);

        $query = "UPDATE `nagruzka` SET `load_base_UID2` = '$new_base_uid2', `lecturer_uid` = '$lecturer[UID]' 
          WHERE  `load_base_UID2` = '$nagruzka[load_base_UID2]'";

        $Result = $mysqli->query($query);

        if (!$Result)
        {
          if ($mysqli->errno == 1062) 
          { 
            // Код ошибки дубликата
            // Это ошибка DUPLICATE ENTRY
            $query = "DELETE FROM `nagruzka` WHERE `load_base_UID2` = '$nagruzka[load_base_UID2]'";
            $Result = $mysqli->query($query);

            EchoLog("Некритичная ошибка дубликата в nagruzka, удаляем строку, т.к. более правильная уже есть");

            if (!$Result)
            {
              EchoLog($mysqli->error);
              EchoLog($query);
            }

          }
          // другая критичная ошибка
          else
          {
            $db_error = true;
          }

          EchoLog($mysqli->error);
          EchoLog($query);
        }
        else
        {
          $DeletedOrChangedNagruzka[$nagruzka['load_base_UID2']] = true;
        }

        // Сделаем замены в zavkaf_splits



        // Сделаем замены в xml_content_of_load
        $xml_content_of_load_rows = GetRows('xml_content_of_load', ['base_uid2' => $nagruzka['load_base_UID2']]);

        if ($xml_content_of_load_rows)
        {
          foreach ($xml_content_of_load_rows as $xml_content_of_load_row)
          {
            $uid_obj = parseNagruzkaBaseUid2($xml_content_of_load_row['UID']);

            if ($uid_obj['lector_suffix'] == $nagruzka_lecturer_uid)
            {
              // заменяем суффикс лектора на новый
              $uid_obj['lector_suffix'] = $lecturer['UID'];
              $new_uid = glueNagruzkaBaseUid2Parts($base_uid2_obj);
            }
            else
            {
              $new_uid = $xml_content_of_load_row['UID'];
            }

            $query = "UPDATE `xml_content_of_load` SET `UID` = '$new_uid', `base_uid2` = '$new_base_uid2', `UID_Lecturer` = '$lecturer[UID]' WHERE `UID` = '$xml_content_of_load_row[UID]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog($mysqli->error);
              EchoLog($query);
              $db_error = true;
            }
            else
            {
              $ChangedXmlContentOfLoad[$xml_content_of_load_row['UID']] = true;
            }
          }
        }

        // Сделаем замены в xml_content_of_load_staff
        $xml_content_of_load_staff_rows = GetRows('xml_content_of_load_staff', ['base_uid2' => $nagruzka['load_base_UID2']]);

        if ($xml_content_of_load_staff_rows)
        {
          foreach ($xml_content_of_load_staff_rows as $xml_content_of_load_staff_row)
          {
            $uid_obj = parseNagruzkaBaseUid2($xml_content_of_load_staff_row['UID']);

            if ($uid_obj['lector_suffix'] == $nagruzka_lecturer_uid)
            {
              // заменяем суффикс лектора на новый
              $uid_obj['lector_suffix'] = $lecturer['UID'];
              $new_uid = glueNagruzkaBaseUid2Parts($uid_obj);
            }
            else
            {
              $new_uid = $xml_content_of_load_row['UID'];
            }


            $uid_content_of_load_staff_obj = parseNagruzkaBaseUid2($xml_content_of_load_staff_row['UID_ContentOfLoad']);

            if ($uid_content_of_load_staff_obj['lector_suffix'] == $nagruzka_lecturer_uid)
            {
              // заменяем суффикс лектора на новый
              $uid_content_of_load_staff_obj['lector_suffix'] = $lecturer['UID'];
              $new_uid_content_of_load = glueNagruzkaBaseUid2Parts($uid_content_of_load_staff_obj);
            }
            else
            {
              $new_uid_content_of_load = $xml_content_of_load_row['UID_ContentOfLoad'];
            }

            $query = "
                      UPDATE `xml_content_of_load_staff` 
                      SET `UID` = '$new_uid', `UID_ContentOfLoad` = '$new_uid_content_of_load', `base_uid2` = '$new_base_uid2', 
                      `ContentOfLoadUID` = '$new_uid_content_of_load' 
                      WHERE `UID` = '$xml_content_of_load_staff_row[UID]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog($mysqli->error);
              EchoLog($query);
              $db_error = true;
            }
          }
        }


        

      }

    }

  }

  
}

*/

// foreach ($NagruzkaByLecturer as $lecturer_uid => $nagruzka_rows)
{
  // 
  // GetLecturer($person_id, $post_uid, $chair_uid, $department_uid, $person_type)
}






$XMLContentOfLoad = GetTable('xml_content_of_load', "", "", "UID", "UID, UID_Chair, base_uid, base_uid2, hash, UID_Lecturer");
// $XMLContentOfLoadByBaseUID = GetTable('xml_content_of_load', "", "", "base_uid", "UID, UID_Chair, base_uid, hash, UID_Lecturer");
$_XMLContentOfLoadStaff = GetTable('xml_content_of_load_staff', "", "", null, "UID, base_uid2, UID_ContentOfLoad, hash");

$XMLContentOfLoadStaffByBaseUID2 = [];

if ($_XMLContentOfLoadStaff)
{
  foreach ($_XMLContentOfLoadStaff as $row)
  {
    // UID_ContentOfLoad соотв. base_uid ?
    // $XMLContentOfLoadStaff[$row['UID_ContentOfLoad']][$row['UID']] = $row;
    $XMLContentOfLoadStaffByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
  }
}

$XMLContentOfLoadByBaseUID2 = [];

if ($XMLContentOfLoad)
{
  foreach ($XMLContentOfLoad as $row)
  {
    // в этой таблице из-за споточенности для одного base_uid может быть несколько UID с разными суффиксами
    $XMLContentOfLoadByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
  }
}

unset($_XMLContentOfLoadStaff);



// echo sizeof($XMLContentOfLoadStaff);
// print_r(array_pop($XMLContentOfLoadStaff));
// exit;

// print_r($XMLChairByUID);
// exit;

// $mysqli->query("TRUNCATE `nagruzka`");

if ($XMLContentOfLoadStaffByBaseUID2)
{
  if (sizeof($XMLContentOfLoadStaffByBaseUID2) < sizeof($XMLContentOfLoadStaffPrevByBaseUID2) / 2)
  {
    EchoLog("ЛК ЗК: В таблице 2 стало заметно меньше строк. Скрипт стоп.", 'file mail');
    $mysqli->query("ROLLBACK");
    exit;
  }
}
else
{
  EchoLog("ЛК ЗК: Пустая таблица нагрузки 2. Скрипт стоп.", 'file mail');
  $mysqli->query("ROLLBACK");
  exit;
}


// Текущие данные, после обновления
if ($XMLContentOfLoad)
{
  // Проверим, сколько строк нагрузки исчезло; если подозрительно много, то не будем ничего менять, а отправим письмо
  $rows_gone_counter = 0;
  $prev_rows_count = sizeof($XMLContentOfLoadPrev);

  // Таблица 1 нагрузки до текущего обновления
  foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
  {
    // прежняя нагрузка не обнаружена в текущем справочнике нагрузок
    if (!$XMLContentOfLoad[$xml_content_of_load_prev_row['UID']])
    {
      $rows_gone_counter++; 
    }
  }

  // исчезло не более трети строк нагрузки в справочнике
  if ($rows_gone_counter < $prev_rows_count / 3)
  {
    // Сначала сосчитаем, у скольких строк изменились хеши: если слишком много, то отправим письмо и остановимся
    $hash_changed_rows_count1 = 0;
    $hash_changed_rows_count2 = 0;

    foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
    {
      // -- сравниваем всё на базе base_uid
      $base_uid = $xml_content_of_load_prev_row['base_uid'];
      $base_uid2 = $xml_content_of_load_prev_row['base_uid2'];

      // $new_nagr_row = $XMLContentOfLoad[$base_uid];
      $new_nagr_row = $XMLContentOfLoad[$xml_content_of_load_prev_row['UID']];

      // хеши таблицы 1
      if ($xml_content_of_load_prev_row['hash'] != $new_nagr_row['hash'])
      {
        $hash_changed_rows_count1++;
      }

      // хеши таблицы 2
      if ($XMLContentOfLoadStaffByBaseUID2[$base_uid2])
      {
        foreach ($XMLContentOfLoadStaffByBaseUID2[$base_uid2] as $load_staff_UID => $load_staff_new_row)
        {
          if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID])
          {
            // сравним хеши соотв. строк load_staff
            if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID]['hash'] != $load_staff_new_row['hash'])
            {
              $hash_changed_rows_count2++;
            }
          }
          else
          {
            $hash_changed_rows_count2++;
          }
        }
      }
    }

    if ($hash_changed_rows_count1 > sizeof($XMLContentOfLoadPrev) / 3)
    {
      $changed_num_percent = round($hash_changed_rows_count1 / sizeof($XMLContentOfLoadPrev) * 100);
      $message_subject = "ЛК ЗК cron: изменилось много строк нагрузки в таблице 1";
      $message_text = "Изменилось $changed_num_percent% строк нагрузки в таблице 1. Обработка не выполнена.";

      mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text);
      mail_utf8($_admin_mail, $_site_domain, $_from_mail, $message_subject, $message_text);

      EchoLog($message_text . " Остановка скрипта.");
      $mysqli->query("ROLLBACK");

      exit;
    }

    // Таблица нагрузки до текущего обновления.
    // Если прежде бывшей нагрузки уже нет, удалим её в таблице nagruzka ЛК ЗК
    foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
    {
      // -- сравниваем всё на базе base_uid
      $base_uid = $xml_content_of_load_prev_row['base_uid'];
      $base_uid2 = $xml_content_of_load_prev_row['base_uid2'];
      $xml_content_of_load_UID = $xml_content_of_load_prev_row['UID'];

      // прежняя нагрузка не обнаружена в текущем справочнике нагрузок по base_UID,
      // т.е. нет такой нагрузки независимо от споточенности
      // т.к. в цикле идём по UID, и base_uid может повторяться, то код в скобках может повториться
      // -- в случае, если выше уже не удалили эту нагрузку / не заменили base_uid2
      if (!$XMLContentOfLoadByBaseUID2[$base_uid2])
      {
        EchoLog("Прежняя нагрузка (base_uid2=$base_uid2, content_uid=$xml_content_of_load_UID) не обнаружена в текущем справочнике xml_content_of_load, удаляем");

        $mysqli->query("DELETE FROM `nagruzka` WHERE `load_base_UID2` = '$base_uid2'");

        continue;
      }
      // прежняя нагрузка есть в текущем справочнике нагрузок
      // [позже] проверим, поменялось ли хотя бы одно поле в строке нагрузки и в ContentOfLoadStaff
      else
      {
        $some_changed = false;

        // Проверим, изменилось ли количество строк в xml_content_of_load для конкретного base_UID (а, значит, для строки таблица nagruzka) - это означает изменение споточенности (суффиксов)
        // Если это изменилось, то нужно очистить привязанного преподавателя
        if (sizeof($XMLContentOfLoadPrevByBaseUID2[$base_uid2]) != sizeof($XMLContentOfLoadByBaseUID2[$base_uid2]))
        {
          $some_changed = true;
          EchoLog("Для base_uid2 = $base_uid2 (uid = $xml_content_of_load_UID) изменилось количество строк в таблице xml_content_of_load, очистим преподавателя {$NagruzkaPrev[$base_uid2]['lecturer_fio']}");
        }

        // если изменился UID в таблице 1 (споточенность-суффикс или суффикс стал юидом привязанного в Галактике лектора),
        // то здесь строка не найдётся по прежнему UID, тогда тоже очистим лектора
        $new_nagr_row = $XMLContentOfLoad[$xml_content_of_load_UID];
        
        // -- если не заменяли uid выше
        if (!$new_nagr_row)
        {
          $some_changed = true;
          EchoLog("Для uid = $xml_content_of_load_UID не найдена строка в таблице xml_content_of_load, очистим преподавателя");
        }

        if ($new_nagr_row && $xml_content_of_load_prev_row['hash'] != $new_nagr_row['hash'])
        {
          $some_changed = true;

          EchoLog("Для uid = $xml_content_of_load_UID (base_uid2 = $base_uid2) в таблице xml_content_of_load изменился хеш ($xml_content_of_load_prev_row[hash] => $new_nagr_row[hash]), очистим преподавателя {$NagruzkaPrev[$base_uid2]['lecturer_fio']}");

          // if ($base_uid === '26589.281474976786399')
          // {
          //   EchoLog("base_uid: $base_uid");
          //   EchoLog("base_uid2: $base_uid2");
          //   EchoLog("UID: xml_content_of_load_UID");
          //   EchoLog("Prev hash: $xml_content_of_load_prev_row[hash]");
          //   EchoLog("New hash: $new_nagr_row[hash]");
          //   EchoLog("Хеши 1 изменились");
          // }
        }

        // сделаем сравнение строк load_staff: 

        if (is_array($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2]) && is_array($XMLContentOfLoadStaffByBaseUID2[$base_uid2]) && sizeof($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2]) != sizeof($XMLContentOfLoadStaffByBaseUID2[$base_uid2]))
        {
          $some_changed = true;
          EchoLog("Изменилось кол-во staff для base_uid2 = $base_uid2");
        }
        // если строк load_staff столько же, то сравним по каждой строке, изменились ли столбцы (соотв-но изменились хеши)
        elseif (is_array($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2]) && is_array($XMLContentOfLoadStaffByBaseUID2[$base_uid2]))
        {
          if ($XMLContentOfLoadStaffByBaseUID2[$base_uid2])
          {
            foreach ($XMLContentOfLoadStaffByBaseUID2[$base_uid2] as $load_staff_UID => $load_staff_new_row)
            {
              if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID])
              {
                // сравним хеши соотв. строк load_staff
                if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID]['hash'] != $load_staff_new_row['hash'])
                {
                  $some_changed = true;

                  // if ($base_uid === '26589.281474976786399')
                  // {
                  //   EchoLog("base_uid: $base_uid");
                  //   EchoLog("base_uid2: $base_uid2");
                  //   // EchoLog("UID: $xml_content_of_load_prev_row[UID]");
                  //   EchoLog("Prev hash: {$XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID]['hash']}"); 
                  //   EchoLog("New hash: {$load_staff_new_row['hash']}");
                  //   // EchoLog($some_changed);
                  //   EchoLog("Хеши 2 изменились");
                  // }
                  
                }
              }
              else
              {
                $some_changed = true;
                break;
              }
            }
          }
          else
          {
            // EchoLog("base_uid $base_uid не определён в массиве таблицы текущей xml_content_of_load_staff");
          }
        }
        // сюда попадаем, если строке в таблице xml_content_of_load не соотв. ни одна строка в xml_content_of_load_staff
        // "это реальная нагрузка, но не дисциплины.. поэтому загружать надо, но в текущие списки "Дисциплины" не попадет, поскольку нет аббревиатур.. должна будет попадать в другие разделы"
        else
        {
          // EchoLog("\$XMLContentOfLoadStaffPrev [$base_uid] или \$XMLContentOfLoadStaff [$base_uid] - не массивы", 'file screen');
          // EchoLog($XMLContentOfLoadStaffPrev[$base_uid]);
          // EchoLog($XMLContentOfLoadStaff[$base_uid]);
        }

        // Что-то изменилось, нужно сбросить в нагрузке назначенного преподавателя
        // TODO ! в других таблицах надо
        if ($some_changed)
        {
          $chair_id = $XMLChairByUID[$new_nagr_row['UID_Chair']]['Code'];

          $query = "
            UPDATE `nagruzka` SET # `lecturer_fio` = NULL, `lecturer_uid` = NULL, `lecturer_person_id` = NULL, 
              `prev_status` = `status`, `status` = 'initial', `date_update` = NOW()
            WHERE `chair_id` = '$chair_id' AND `load_base_UID2` = '$base_uid2'";

          $Result = $mysqli->query($query);

          if ($Result)
          {
            $NagruzkaPrev[$base_uid2]['lecturer_fio'] = '';

            // выведем только если лектор был
            if ($base_uid2 === '26589.281474976786399' || $NagruzkaPrev[$base_uid2]['lecturer_fio'])
            EchoLog("Очистили лектора кафедры {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ($chair_id) у нагрузки $base_uid2");
          }
          else
          {
            EchoLog("ОШИБКА очистки лектора кафедры {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ({$new_nagr_row['UID_Chair']}, $chair_id) у нагрузки $base_uid2");
            EchoLog($query);
          }

        }

        /*
        if ($new_nagr_row['UID_Lecturer'])
        {
          // EchoLog($new_nagr_row['UID_Lecturer']);
        }

        // Если у нагрузки в Галактике указан преподаватель, то его взять
        if ($new_nagr_row['UID_Lecturer'] && $NagruzkaPrev[$base_uid]['lecturer_uid'] != $new_nagr_row['UID_Lecturer'])
        {
          // EchoLog('here');
          $lecturer = $XMLLecturer[$new_nagr_row['UID_Lecturer']];

          // ? МЕНЯТЬ ЛИ СТАТУС ?
          $query = "
            UPDATE `nagruzka` SET `lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$new_nagr_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]'
            WHERE `load_base_UID` = '$base_uid'";

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("ОШИБКА простановки лектора кафедры (из Галактики) {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ({$new_nagr_row['UID_Chair']}, $chair_id) у нагрузки $base_uid");
            EchoLog($query);
          }
        }

        */

      }
      
    }


    foreach ($XMLContentOfLoad as $xml_content_of_load_row)
    {
      $chair_id = $XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Code'];
      $chair_name = $XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Name'];
      $department_id = $Podrazdelenia[$chair_id]['ukrup_code'];
      $department_name = $Podrazdelenia[$chair_id]['ukrup_name'];

      if ($Podrazdelenia[$chair_id]['has_real_chief'])
      {
        $zavkaf_id = $Podrazdelenia[$chair_id]['chief_id'];
        $zavkaf_fio = $Podrazdelenia[$chair_id]['chief_fio'];
        $zavkaf_login = $Person[$zavkaf_id]['alias'];
      }
      else
      {
        $zavkaf_id = $zavkaf_fio = $zavkaf_login = '';
      }

      $zavkaf_sql = ", `zavkaf_login` = '$zavkaf_login', `zavkaf_id` = '$zavkaf_id', `zavkaf_fio` = '$zavkaf_fio'";

      if ($xml_content_of_load_row['UID_Chair'] == '25031.281474976756910')
      {
        // EchoLog($xml_content_of_load_row);
        // EchoLog($chair_name);
        // EchoLog($department_id);
        // EchoLog($department_name);
        // EchoLog($zavkaf_id);
        // EchoLog($zavkaf_fio);
        // EchoLog($zavkaf_login);
      }

      // EchoLog($chair_id);

      // echo "$chair_id<br>";
      // Кафедра у нагрузки не пустая
      /* TG 13.11.25: если пришла нагрузка и там есть кафедра, то она точно есть в Chairs.xml.. но сама кафедра в Chairs.xml может быть неактуальной уже.. для этого надо проверить по коду подразделения что это актуальное подразделение.. и если такого подразделения реально уже нет, то такую нагрузку надо помечать как невалидную.. также как и отсутствие кафедры (код 25031.0)
        название кафедры (если есть UID) всегда берем из Chairs.xml
      */
      if ($xml_content_of_load_row['UID_Chair'])
      {
        // такой нагрузки на кафедре ещё не было
        if (!$NagruzkaPrev[$xml_content_of_load_row['base_uid2']])
        {
          // EchoLog($nagr['base_uid']);

          if ($XMLChairByCode[$chair_id]) // === true
          {
            $lecturer = $XMLLecturer[$xml_content_of_load_row['UID_Lecturer']];

            // Определим, является ли нагрузка "Руководством практики"
            // Возьмём первую попавшуюся соотв. строку из 2й таблицы
            // $some_xml_content_of_load_staff = array_values($XMLContentOfLoadStaffByBaseUID2[$xml_content_of_load_row['base_uid2']])[0];

            // IsNagruzkaRukPractice($some_xml_content_of_load_staff['Abbr']);

            // признак актуальности подразделения в Сотруднике
            if (!$Podrazdelenia[$chair_id]['deleted'])
            {
              // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра актуальна");

              $lecturer['FIO'] = str_replace('!_Вакансия_!', 'Вакансия', $lecturer['FIO']);

              $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = '$chair_id', `chair_name` = '$chair_name', `department_id` = '$department_id', `department_name` = '$department_name', `load_base_UID2` = '$xml_content_of_load_row[base_uid2]', `valid` = '1' $zavkaf_sql";

              if ($lecturer['FIO'] == 'Фомина Ирина Юрьевна')
              {
                // EchoLog($query);
              }
            }
            // Кафедра не актуальна в Сотруднике:
            // нагрузку пометим невалидной, а название кафедры возьмём в Сотруднике
            else
            {
              // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра НЕ актуальна");
              $chair_name = $Podrazdelenia[$chair_id]['pname'];
              $department_name = $Podrazdelenia[$chair_id]['ukrup_name'];

              $lecturer['FIO'] = str_replace('!_Вакансия_!', 'Вакансия', $lecturer['FIO']);

              $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = NULL, `chair_name` = '$chair_name', `department_id` = NULL, `department_name` = '$department_name', `load_base_UID2` = '$xml_content_of_load_row[base_uid2]', `valid` = '0', `zavkaf_login` = NULL, `zavkaf_id` = NULL, `zavkaf_fio` = NULL";
            }
          }

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("Error #153 inserting into `nagruzka`: $query", "file mail");
            EchoLog($mysqli->error, "file mail");
            $db_error = true;
          }
          elseif ($mysqli->affected_rows)
          {
            ActivityLog($xml_content_of_load_row['base_uid2'], ["Нагрузка добавлена на кафедру $chair_name", $chair_id, $xml_content_of_load_row['base_uid2']], "", "initial", 0, 1);
          }

          
        }
        else
        {
          // Нагрузка была и есть.
          // Возьмём нагрузку в "очереди" в статусе done_change
          // Это когда зав. каф. подаёт заявку на изменение, а админ УОУП нажимает "Выполнено"
          // При этом комментарий идёт в публичную историю, а нагрузка становится initial
          if ($NagruzkaPrev[$xml_content_of_load_row['base_uid2']]['status'] == 'done_change')
          {
            $Rows = GetRows('log', ['load_base_UID2' => $xml_content_of_load_row['base_uid2'], 'action_name' => 'done_change'], null, "`datetime` DESC");

            if ($Rows)
            {
              $LastLogRow = $Rows[0];

              // строка лога, которую инициировал УОУП, когда нажал "Выполнено", станет публичной
              // для информативности добавим строку в лог об этом автоматическом событии

              $mysqli->query("UPDATE `log` SET `internal` = '0' WHERE `id` = '$LastLogRow[id]'");

              ActivityLog($xml_content_of_load_row['base_uid2'], ['Разблокировка нагрузки в кроне из done_change', $chair_id, $chair_name, $zavkaf_fio], "", 'initial', 1, 1);

              $mysqli->query("UPDATE `nagruzka` SET `status` = 'initial', `date_update` = NOW() WHERE `load_base_UID2` = '$xml_content_of_load_row[base_uid2]'");
            }
          }


          // Обновим признак валидности нагрузки по актуальности подразделения (кафедры) в Сотруднике
          // признак актуальности подразделения в Сотруднике
          if (!$Podrazdelenia[$chair_id]['deleted'])
          {
            $valid = '1';
          }
          // Кафедра не актуальна в Сотруднике:
          // нагрузку пометим невалидной
          else
          {
            // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра НЕ актуальна");
            $valid = '0';
          }

          $query = "
          UPDATE `nagruzka` SET `valid` = '$valid', `date_update` = NOW() $zavkaf_sql
          WHERE `load_base_UID2` = '$xml_content_of_load_row[base_uid2]'";
          $mysqli->query($query);


          if ($xml_content_of_load_row['UID_Lecturer'])
          {
            // EchoLog($xml_content_of_load_row['UID_Lecturer']);
          }

          // Если у нагрузки в Галактике указан преподаватель, то его взять, но только если система в режиме выверки
          // TODO ! в nagruzka нет преподов
          if ($xml_content_of_load_row['UID_Lecturer'] && $xml_content_of_load_row['UID_Lecturer'] != '-1' && $NagruzkaPrev[$xml_content_of_load_row['base_uid2']]['lecturer_uid'] != $xml_content_of_load_row['UID_Lecturer'] && $_system_mode == 'mode_verification')
          {
            // EchoLog('here');
            $lecturer = $XMLLecturer[$xml_content_of_load_row['UID_Lecturer']];

            $lecturer['FIO'] = str_replace('!_Вакансия_!', 'Вакансия', $lecturer['FIO']);

            // ? МЕНЯТЬ ЛИ СТАТУС ?
            $query = "
              UPDATE `nagruzka` SET #`lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$xml_content_of_load_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]', 
                `date_update` = NOW()
              WHERE `load_base_UID2` = '$xml_content_of_load_row[base_uid2]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog("ОШИБКА простановки лектора кафедры (из Галактики) {$XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Name']} ({$xml_content_of_load_row['UID_Chair']}) у нагрузки $xml_content_of_load_row[base_uid2]");
              EchoLog($query);
              EchoLog($mysqli->error);
              $db_error = true;
            }
          }



        }
      }
      // иначе нагрузка не распределена на кафедру, пометим как valid = 0 (чтобы не выдавать завкафам, но выдавать УОУП в разделе плохих нагрузок no_chairs)
      else
      {
        $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = NULL, `chair_name` = NULL, `department_id` = NULL, `department_name` = NULL, `zavkaf_login` = NULL, `zavkaf_id` = NULL, `zavkaf_fio` = NULL, `load_base_UID2` = '$xml_content_of_load_row[base_uid2]', `valid` = '0'";

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("Error #842 inserting into `nagruzka`: $query", "file mail");
            EchoLog($mysqli->error, "file mail");
            $db_error = true;
          }
      }
    }
  }
  // исчезло более трети строк нагрузки в справочнике, отправим письмо
  elseif ($prev_rows_count)
  {
    $disappears_num_percent = round($rows_gone_counter / $prev_rows_count * 100);
    $message_subject = "ЛК ЗК cron: слишком много строк нагрузки пропадает";
    $message_text = "Пропадает $disappears_num_percent% строк нагрузки. Обработка не выполнена.";

    mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text);
    mail_utf8($_admin_mail, $_site_domain, $_from_mail, $message_subject, $message_text);
    
  }
}
else
{
  EchoLog("ЛК ЗК: Пустая таблица нагрузки 1", 'file mail');
}










if ($db_error)
{
  EchoLog('ROLLBACK все запросы', 'file screen');
  $mysqli->query("ROLLBACK");
}
else
{
  EchoLog('COMMIT все запросы', 'file screen');
  $mysqli->query("COMMIT");
}



EchoLog("END cron");
echo "<br>Конец скрипта<br>";
?>