<?php

include '../functions.php';

include '../connect/opop2.php';

EchoLog("Start cron");

$Napravlenia = GetTable('napravlenia', "", "", "napravlenie");

include '../connect.php';

// Получим режим работы системы из БД
$_system_mode = GetSystemParam('system_mode');

// Столбцы, используемые для создания кеша, чтобы выявлять изменения в строках при обновлении из XML
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

// $hash = hash_column_values_only($data, $xml_content_of_load_staff_columns_for_hash);

// нагрузка до обновления
$XMLContentOfLoadPrev = GetTable('xml_content_of_load', "", "", "UID", "UID, base_uid, hash");
$_XMLContentOfLoadStaffPrev = GetTable('xml_content_of_load_staff', "", "", null, "UID, UID_ContentOfLoad, hash");

// нагрузка стафф до обновления
$XMLContentOfLoadStaffPrev = [];

foreach ($_XMLContentOfLoadStaffPrev as $row)
{
  $XMLContentOfLoadStaffPrev[$row['UID_ContentOfLoad']][$row['UID']] = $row;
}

unset($_XMLContentOfLoadStaffPrev);

$NagruzkaPrev = GetTable('nagruzka', "", "", "load_base_UID");

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

  $query = "
              SELECT person.`id` as person_id, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`, `$position_table_name`.podrazdelenie_id, `$position_table_name`.ukrup_code as department_id, $position_table_name.`podrazdelenia_chain`, $podrazdelenia_table_name.`id` as chair_id, $position_table_name.`position_category`, $position_table_name.`type`, $position_table_name.`qualify_category`
              FROM `$position_table_name`
              JOIN `person` ON `$position_table_name`.person_id = `person`.id
              JOIN `$podrazdelenia_table_name` ON  `$position_table_name`.podrazdelenia_chain LIKE CONCAT('%|', $podrazdelenia_table_name.`id`, '|%')
              WHERE $podrazdelenia_table_name.`pname` LIKE('Кафедра%')
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

function LoadXML($filename, $table_name)
{
  global $mysqli, $Napravlenia, $xml_content_of_load_columns_for_hash, $xml_content_of_load_staff_columns_for_hash;

  EchoLog("LoadXML: $table_name", 'file screen');

  $XML = simplexml_load_string(file_get_contents($filename));

  $mysqli->query("TRUNCATE `$table_name`");

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

    if ($table_name == 'xml_content_of_load')
    {
      $base_uid = get_base_uid($arr['UID']);
      $sql_arr[] = "`base_uid` = '$base_uid'";

      $hash = hash_column_values_only($arr, $xml_content_of_load_columns_for_hash);
      $sql_arr[] = "`hash` = '$hash'";
    }
    
    if ($table_name == 'xml_content_of_load_staff')
    {
      $hash = hash_column_values_only($arr, $xml_content_of_load_staff_columns_for_hash);
      $sql_arr[] = "`hash` = '$hash'";
    }

    $sql = JoinArrayElements($sql_arr, ', ');
    // echo $sql;
    // print_r($arr);

    $Result = $mysqli->query("INSERT INTO `$table_name` SET $sql");

    if (!$Result)
    {
      EchoLog($mysqli->error, 'file screen');
    }

    // var_dump($s->Collection->attributes());
    // exit;
  }
}

include '../connect.php';


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

// exit;


/*
LoadXML('Stream.xml', 'xml_stream');
LoadXML('Faculty.xml', 'xml_faculty');
LoadXML('Language.xml', 'xml_language');
LoadXML('Specialization.xml', 'xml_specialization');
LoadXML('Speciality.xml', 'xml_speciality');
LoadXML('SubGroup.xml', 'xml_subgroup');
LoadXML('Chair.xml', 'xml_chair');
LoadXML('KindOfWork.xml', 'xml_kind_of_work');
LoadXML('Group.xml', 'xml_group');
LoadXML('Discipline.xml', 'xml_discipline');
LoadXML('Lecturer.xml', 'xml_lecturer');
LoadXML('Post.xml', 'xml_post');

LoadXML('ContentOfLoadStaff.xml', 'xml_content_of_load_staff');
LoadXML('ContentOfLoad.xml', 'xml_content_of_load');




// Получим данные кандидатов; они нам нужны, чтобы получить id кандидата = будущего сотрудника; он мог быть уже сотрудником прежде, тогда его id является прежним id сотрудника
$url = 'http://www:nahuheti9@ip.unn.ru/integration/rest/base/getChangedObjects?map=nngu.ais.employees.add';
file_put_contents('nngu.ais.employees.add.xml', file_get_contents($url));

// exit;

*/

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

$SotrudnikiActual = GetChairSotrudniki($cur_year, "", true);

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

$mysqli->query("START TRANSACTION");

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



    if ($adding)
    {
      // uid должности из Галактики
      $post_uid = $XMLPost[$chair_sotrudnik['dolzhnost']]['UID'];
      // EchoLog($post_uid);
      $chair_uid = $XMLChairByCode[$chair_sotrudnik['chair_id']]['UID'];
      // EchoLog($chair_uid);

      // $lecturer = GetRow('xml_lecturer', ['Tab_number' => $chair_sotrudnik['person_id'], 'UID_Post' => $post_uid, 'UID_Chair' => $chair_uid]);

      $lecturer_rows = GetRows('xml_lecturer', ['Tab_number' => $chair_sotrudnik['person_id'], 'UID_Post' => $post_uid, 'UID_Chair' => $chair_uid], null, "`Archive` ASC, `DateContractEnd` DESC");

      if ($lecturer_rows)
      {
        $lecturer = $lecturer_rows[0];
      }

      // EchoLog($lecturer);

      if ($chair_sotrudnik['type'] == 'sotrudnik')
      {
        $selected = '1';
      }
      else
      {
        $selected = '0';
      }

      $login = $Person[$chair_sotrudnik['person_id']]['alias'];

      $query = "
              INSERT INTO `sotrudniki` 
              SET `person_id` = '$chair_sotrudnik[person_id]', `lecturer_uid` = '$lecturer[UID]', `lecturer_login` = '$login',
              `fio` = '$chair_sotrudnik[fio]', `chair_id` = '$chair_sotrudnik[chair_id]', `department_id` = '$chair_sotrudnik[department_id]',
              `podrazdelenie_id` = '$chair_sotrudnik[podrazdelenie_id]', `dolzhnost` = '$chair_sotrudnik[dolzhnost]', `type` = '$chair_sotrudnik[type]', `selected` = '$selected', `date_add` = NOW()
            ";

      // echo $query . '<br><br>';

      $Result = $mysqli->query($query);

      if (!$Result)
      {
        EchoLog($mysqli->error);
      }
    }
    else
    {

    }

    
  }
}

unset($Sotrudniki);
unset($SotrudnikiInLKByKey);
unset($SotrudnikiItogoByKey);


// Данные после текущего импорта
$XMLLecturer = GetTable('xml_lecturer', "", "", "UID");
$XMLPost = GetTable('xml_post', "", "", "Name");
$XMLChairByCode = GetTable('xml_chair', "", "", "Code");
$XMLChairByUID = GetTable('xml_chair', "", "", "UID");
$XMLContentOfLoad = GetTable('xml_content_of_load', "", "", "base_uid", "UID, UID_Chair, base_uid, hash, UID_Lecturer");
$_XMLContentOfLoadStaff = GetTable('xml_content_of_load_staff', "", "", null, "UID, UID_ContentOfLoad, hash");

$XMLContentOfLoadStaff = [];

if ($_XMLContentOfLoadStaff)
{
  foreach ($_XMLContentOfLoadStaff as $row)
  {
    // UID_ContentOfLoad соотв. base_uid
    $XMLContentOfLoadStaff[$row['UID_ContentOfLoad']][$row['UID']] = $row;
  }
}

unset($_XMLContentOfLoadStaff);

// echo sizeof($XMLContentOfLoadStaff);
// print_r(array_pop($XMLContentOfLoadStaff));
// exit;

// print_r($XMLChairByUID);
// exit;

// $mysqli->query("TRUNCATE `nagruzka`");

if ($XMLContentOfLoadStaff)
{
  if (sizeof($XMLContentOfLoadStaff) < sizeof($XMLContentOfLoadStaffPrev) / 2)
  {
    EchoLog("ЛК ЗК: В таблице 2 стало заметно меньше строк. Скрипт стоп.", 'file mail');
    exit;
  }
}
else
{
  EchoLog("ЛК ЗК: Пустая таблица нагрузки 2. Скрипт стоп.", 'file mail');
  exit;
}


// Текущие данные
if ($XMLContentOfLoad)
{
  // Проверим, сколько строк нагрузки исчезло; если подозрительно много, то не будем ничего менять, а отправим письмо
  $rows_gone_counter = 0;
  $prev_rows_count = sizeof($XMLContentOfLoadPrev);

  // Таблица 1 нагрузки до текущего обновления
  foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
  {
    // прежняя нагрузка не обнаружена в текущем справочнике нагрузок
    if (!$XMLContentOfLoad[$xml_content_of_load_prev_row['base_uid']])
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
      // сравниваем всё на базе base_uid
      $base_uid = $xml_content_of_load_prev_row['base_uid'];

      $new_nagr_row = $XMLContentOfLoad[$base_uid];

      // хеши таблицы 1
      if ($xml_content_of_load_prev_row['hash'] != $new_nagr_row['hash'])
      {
        $hash_changed_rows_count1++;
      }

      // хеши таблицы 2
      if ($XMLContentOfLoadStaff[$base_uid])
      {
        foreach ($XMLContentOfLoadStaff[$base_uid] as $load_staff_UID => $load_staff_new_row)
        {
          if ($XMLContentOfLoadStaffPrev[$base_uid][$load_staff_UID])
          {
            // сравним хеши соотв. строк load_staff
            if ($XMLContentOfLoadStaffPrev[$base_uid][$load_staff_UID]['hash'] != $load_staff_new_row['hash'])
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

      exit;
    }

    // Таблица нагрузки до текущего обновления.
    // Если прежде бывшей нагрузки уже нет, удалим её в таблице nagruzka ЛК ЗК
    foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
    {
      // сравниваем всё на базе base_uid
      $base_uid = $xml_content_of_load_prev_row['base_uid'];

      // прежняя нагрузка не обнаружена в текущем справочнике нагрузок
      if (!$XMLContentOfLoad[$base_uid])
      {
        EchoLog("Прежняя нагрузка $base_uid не обнаружена в текущем справочнике xml_content_of_load, удаляем");
        $mysqli->query("DELETE FROM `nagruzka` WHERE `load_base_UID` = '$base_uid'");
        continue;
      }
      // прежняя нагрузка есть в текущем справочнике нагрузок
      // проверим, поменялось ли хотя бы одно поле в строке нагрузки и в ContentOfLoadStaff
      else
      {
        $new_nagr_row = $XMLContentOfLoad[$base_uid];
        $some_changed = false;

        if ($xml_content_of_load_prev_row['hash'] != $new_nagr_row['hash']) $some_changed = true;

        // if ($base_uid === '26589.281474976744756')
        // {
        //   EchoLog("base_uid: $base_uid");
        //   EchoLog("UID: $xml_content_of_load_prev_row[UID]");
        //   EchoLog("Prev hash: $xml_content_of_load_prev_row[hash]");
        //   EchoLog("New hash: $new_nagr_row[hash]");
        //   EchoLog($some_changed);
        // }

        // сделаем сравнение строк load_staff: 

        if (is_array($XMLContentOfLoadStaffPrev[$base_uid]) && is_array($XMLContentOfLoadStaff[$base_uid]) && sizeof($XMLContentOfLoadStaffPrev[$base_uid]) != sizeof($XMLContentOfLoadStaff[$base_uid]))
        {
          $some_changed = true;
          EchoLog("Изменилось кол-во staff для base_uid = $base_uid");
        }
        // если строк load_staff столько же, то сравним по каждой строке, изменились ли столбцы (соотв-но изменились хеши)
        elseif (is_array($XMLContentOfLoadStaffPrev[$base_uid]) && is_array($XMLContentOfLoadStaff[$base_uid]))
        {
          if ($XMLContentOfLoadStaff[$base_uid])
          {
            foreach ($XMLContentOfLoadStaff[$base_uid] as $load_staff_UID => $load_staff_new_row)
            {
              if ($XMLContentOfLoadStaffPrev[$base_uid][$load_staff_UID])
              {
                // сравним хеши соотв. строк load_staff
                if ($XMLContentOfLoadStaffPrev[$base_uid][$load_staff_UID]['hash'] != $load_staff_new_row['hash'])
                {
                  $some_changed = true;
                  // EchoLog("Хеши 2 изменились");
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
        if ($some_changed)
        {
          $chair_id = $XMLChairByUID[$new_nagr_row['UID_Chair']]['Code'];

          $query = "
            UPDATE `nagruzka` SET `lecturer_fio` = NULL, `lecturer_uid` = NULL, `lecturer_person_id` = NULL, `prev_status` = `status`, `status` = 'initial'
            WHERE `chair_id` = '$chair_id' AND `load_base_UID` = '$base_uid'";

          $Result = $mysqli->query($query);

          if ($Result)
          {
            // выведем только если лектор был
            if ($NagruzkaPrev[$base_uid]['lecturer_fio'])
            EchoLog("Очистили лектора кафедры {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ($chair_id) у нагрузки $base_uid");
          }
          else
          {
            EchoLog("ОШИБКА очистки лектора кафедры {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ({$new_nagr_row['UID_Chair']}, $chair_id) у нагрузки $base_uid");
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
      $zavkaf_id = $Podrazdelenia[$chair_id]['chief_id'];
      $zavkaf_fio = $Podrazdelenia[$chair_id]['chief_fio'];
      $zavkaf_login = $Person[$zavkaf_id]['alias'];

      // EchoLog($chair_id);

      // echo "$chair_id<br>";
      // Кафедра у нагрузки не пустая
      /* TG 13.11.25: если пришла нагрузка и там есть кафедра, то она точно есть в Chairs.xml.. но сама кафедра в Chairs.xml может быть неактуальной уже.. для этого надо проверить по коду подразделения что это актуальное подразделение.. и если такого подразделения реально уже нет, то такую нагрузку надо помечать как невалидную.. также как и отсутсвие кафедры (код 25031.0)
        название кафедры (если есть UID) всегда берем из Chairs.xml
      */
      if ($xml_content_of_load_row['UID_Chair'])
      {
        // такой нагрузки на кафедре ещё не было
        if (!$NagruzkaPrev[$xml_content_of_load_row['base_uid']])
        {
          // EchoLog($nagr['base_uid']);

          if ($XMLChairByCode[$chair_id]) // === true
          {
            $lecturer = $XMLLecturer[$xml_content_of_load_row['UID_Lecturer']];

            // признак актуальности подразделения в Сотруднике
            if (!$Podrazdelenia[$chair_id]['deleted'])
            {
              EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра актуальна");

              $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = '$chair_id', `chair_name` = '$chair_name', `department_id` = '$department_id', `department_name` = '$department_name', `zavkaf_login` = '$zavkaf_login', `zavkaf_id` = '$zavkaf_id', `zavkaf_fio` = '$zavkaf_fio', `lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$xml_content_of_load_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]', `load_base_UID` = '$xml_content_of_load_row[base_uid]', `valid` = '1'";
            }
            // Кафедра не актуальна в Сотруднике:
            // нагрузку пометим невалидной, а название кафедры возьмём в Сотруднике
            else
            {
              // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра НЕ актуальна");
              $chair_name = $Podrazdelenia[$chair_id]['pname'];
              $department_name = $Podrazdelenia[$chair_id]['ukrup_name'];

              $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = NULL, `chair_name` = '$chair_name', `department_id` = NULL, `department_name` = '$department_name', `zavkaf_login` = NULL, `zavkaf_id` = NULL, `zavkaf_fio` = NULL, `lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$xml_content_of_load_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]', `load_base_UID` = '$xml_content_of_load_row[base_uid]', `valid` = '0'";
            }
          }

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("Error #153 inserting into `nagruzka`: $query", "file mail");
            EchoLog($mysqli->error, "file mail");
          }
          else
          {
            ActivityLog($xml_content_of_load_row['base_uid'], ["Нагрузка добавлена на кафедру $chair_name", $chair_id, $xml_content_of_load_row['base_uid']], "", "initial", 0, 1);
          }

          
        }
        else
        {
          // Нагрузка была и есть.
          // Возьмём нагрузку в "очереди" в статусе done_change
          // Это когда зав. каф. подаёт заявку на изменение, а админ УОУП нажимает "Выполнено"
          // При этом комментарий идёт в публичную историю, а нагрузка становится initial
          if ($NagruzkaPrev[$xml_content_of_load_row['base_uid']]['status'] == 'done_change')
          {
            $Rows = GetRows('log', ['load_base_UID' => $xml_content_of_load_row['base_uid'], 'action_name' => 'done_change'], null, "`datetime` DESC");

            if ($Rows)
            {
              $LastLogRow = $Rows[0];

              // строка лога, которую инициировал УОУП, когда нажал "Выполнено", станет публичной
              // для информативности добавим строку в лог об этом автоматическом событии

              $mysqli->query("UPDATE `log` SET `internal` = '0' WHERE `id` = '$LastLogRow[id]'");

              ActivityLog($xml_content_of_load_row['base_uid'], ['Разблокировка нагрузки в кроне из done_change', $chair_id, $chair_name, $zavkaf_fio], "", 'initial', 1, 1);

              $mysqli->query("UPDATE `nagruzka` SET `status` = 'initial' WHERE `load_base_UID` = '$xml_content_of_load_row[base_uid]'");
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

          $query = "UPDATE `nagruzka` SET `valid` = '$valid' WHERE `load_base_UID` = '$xml_content_of_load_row[base_uid]'";
          $mysqli->query($query);


          if ($xml_content_of_load_row['UID_Lecturer'])
          {
            // EchoLog($xml_content_of_load_row['UID_Lecturer']);
          }

          // Если у нагрузки в Галактике указан преподаватель, то его взять, но только если система в режиме выверки
          if ($xml_content_of_load_row['UID_Lecturer'] && $xml_content_of_load_row['UID_Lecturer'] != '-1' && $NagruzkaPrev[$xml_content_of_load_row['base_uid']]['lecturer_uid'] != $xml_content_of_load_row['UID_Lecturer'] && $_system_mode == 'mode_verification')
          {
            EchoLog('here');
            $lecturer = $XMLLecturer[$xml_content_of_load_row['UID_Lecturer']];

            // ? МЕНЯТЬ ЛИ СТАТУС ?
            $query = "
              UPDATE `nagruzka` SET `lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$xml_content_of_load_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]'
              WHERE `load_base_UID` = '$xml_content_of_load_row[base_uid]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog("ОШИБКА простановки лектора кафедры (из Галактики) {$XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Name']} ({$xml_content_of_load_row['UID_Chair']}) у нагрузки $xml_content_of_load_row[base_uid]");
              EchoLog($query);
              EchoLog($mysqli->error);
            }
          }



        }
      }
      // иначе нагрузка не распределена на кафедру, пометим как valid = 0 (чтобы не выдавать завкафам, но выдавать уоуп в разделе плохих нагрузок no_chairs)
      else
      {
        $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = NULL, `chair_name` = NULL, `department_id` = NULL, `department_name` = NULL, `zavkaf_login` = NULL, `zavkaf_id` = NULL, `zavkaf_fio` = NULL,  `load_base_UID` = '$xml_content_of_load_row[base_uid]', `valid` = '0'";

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("Error #842 inserting into `nagruzka`: $query", "file mail");
            EchoLog($mysqli->error, "file mail");
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





$mysqli->query("COMMIT");

EchoLog("END cron");
echo "<br>Конец скрипта<br>";
?>