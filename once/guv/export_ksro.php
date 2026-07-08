<?php

include '../../functions.php';

// EchoLog('Start export of selected nagruzka rows');


$XmlContentOfLoad = GetTable('xml_content_of_load', "`LoadId` <> ''", "", "LoadId", "LoadId");
$XmlChairByCode = GetTable('xml_chair', "", "", "Code");
$XmlFacultyByCode = GetTable('xml_faculty', "", "", "Code");
$XmlFacultyByName = GetTable('xml_faculty', "", "", "Name");
$XmlGroupByUID = GetTable('xml_group', "", "", "UID");

$query = "SELECT * FROM `ksro`";

// $Result = $mysqli->query($query);

// if (!$Result)
// {
//   EchoLog('DB error: ' . $mysqli->error);
//   exit;
// }

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$data_root = $doc->createElement('Data_Root');
$doc->appendChild($data_root);

$data = $doc->createElement('Data');
$data_root->appendChild($data);

$collection = $doc->createElement('Collection');
$collection->setAttribute('name', 'Data.ContentOfLoad');
$collection->setAttribute('caption', 'Содержание нагрузки расписания');
$collection->setAttribute('child_tags', 'Object');
$data->appendChild($collection);

$rows_count = 0;

// КСРО
if ($Result)
while ($Row = $Result->fetch_assoc())
{
  // если в xml уже есть такой load_id, то не выгружаем
  if ($XmlContentOfLoad[$Row['load_id']]) continue;

  if (empty($Row['Amount'])) continue;

  $object = $doc->createElement('Object');
  $object->setAttribute('LoadId', $Row['load_id']);
  $object->setAttribute('class_id', 'ContentOfLoad');
  $object->setAttribute('nagruzka_type', 'ksro');
  $object->setAttribute('fio', $Row['lecturer_fio']);
  
  $prop_values = $doc->createElement('Collection');
  $prop_values->setAttribute('name', 'Prop_Values');
  $prop_values->setAttribute('child_tags', 'prop_value');
  $prop_values->setAttribute('caption', 'Свойства');
  
  // Amount
  $prop_amount = $doc->createElement('prop_value');
  $prop_amount->setAttribute('prop_name', 'Amount');
  $prop_amount->setAttribute('value', $Row['Amount']);
  $prop_values->appendChild($prop_amount);
  
  // TypeOfContingent
  $prop_contingent = $doc->createElement('prop_value');
  $prop_contingent->setAttribute('prop_name', 'TypeOfContingent');
  $prop_contingent->setAttribute('value', '4');
  $prop_values->appendChild($prop_contingent);
  
  // UID_KindOfWork (КСРО)
  $prop_kind = $doc->createElement('prop_value');
  $prop_kind->setAttribute('prop_name', 'UID_KindOfWork');
  $prop_kind->setAttribute('value', $Row['UID_KindOfWork']);
  $prop_values->appendChild($prop_kind);
  
  // UID_Discipline
  $prop_discipline = $doc->createElement('prop_value');
  $prop_discipline->setAttribute('prop_name', 'UID_Discipline');
  $prop_discipline->setAttribute('value', $Row['UID_Discipline']);
  $prop_values->appendChild($prop_discipline);
  
  // UID_Semester (осенний = 1, весенний = 2)
  $prop_semester = $doc->createElement('prop_value');
  $prop_semester->setAttribute('prop_name', 'UID_Semester');
  $prop_semester->setAttribute('value', $Row['UID_Semester']);
  $prop_values->appendChild($prop_semester);
  
  // UID_Language
  $prop_language = $doc->createElement('prop_value');
  $prop_language->setAttribute('prop_name', 'UID_Language');
  $prop_language->setAttribute('value', $Row['UID_Language']);
  $prop_values->appendChild($prop_language);

  // UID_Chair
  $prop_chair = $doc->createElement('prop_value');
  $prop_chair->setAttribute('prop_name', 'UID_Chair');
  $prop_chair->setAttribute('value', $Row['UID_Chair']);
  $prop_values->appendChild($prop_chair);
  
  // UID_FacultyPerformer
  $prop_faculty = $doc->createElement('prop_value');
  $prop_faculty->setAttribute('prop_name', 'UID_FacultyPerformer');
  $prop_faculty->setAttribute('value', $Row['UID_FacultyPerformer']);
  $prop_values->appendChild($prop_faculty);
  
  $object->appendChild($prop_values);
  $collection->appendChild($object);
  $rows_count++;
}

if ($Result) $Result->free();


// $AspiranturaKandExam = GetTable('aspirantura_kand_exam', "`deleted` <> '1'");

if ($AspiranturaKandExam)
{
  foreach ($AspiranturaKandExam as $row)
  {
    // если в xml уже есть такой load_id, то не выгружаем
    if ($XmlContentOfLoad[$row['load_id']]) continue;

    $object = $doc->createElement('Object');
    $object->setAttribute('LoadId', $row['load_id']);
    $object->setAttribute('class_id', 'ContentOfLoad');
    $object->setAttribute('nagruzka_type', 'aspirantura_kand_exam');
    $object->setAttribute('fio', $row['lecturer_fio']);
    
    $prop_values = $doc->createElement('Collection');
    $prop_values->setAttribute('name', 'Prop_Values');
    $prop_values->setAttribute('child_tags', 'prop_value');
    $prop_values->setAttribute('caption', 'Свойства');
    
    // Amount
    $prop_amount = $doc->createElement('prop_value');
    $prop_amount->setAttribute('prop_name', 'Amount');
    $prop_amount->setAttribute('value', $_aspirantura_hours_per_student * $row['students_num']);
    $prop_values->appendChild($prop_amount);

    // StudentAmount
    $prop_amount = $doc->createElement('prop_value');
    $prop_amount->setAttribute('prop_name', 'StudentAmount');
    $prop_amount->setAttribute('value', $row['students_num']);
    $prop_values->appendChild($prop_amount);
    
    // TypeOfContingent
    $prop_contingent = $doc->createElement('prop_value');
    $prop_contingent->setAttribute('prop_name', 'TypeOfContingent');
    $prop_contingent->setAttribute('value', '2');
    $prop_values->appendChild($prop_contingent);
    
    // UID_KindOfWork («Экзамен кандидатский»)
    $prop_kind = $doc->createElement('prop_value');
    $prop_kind->setAttribute('prop_name', 'UID_KindOfWork');
    $prop_kind->setAttribute('value', $_aspirantura_kand_exam_kind_uid);
    $prop_values->appendChild($prop_kind);
    
    // UID_Discipline
    $uid_discipline = "26006.{$row['disc_nrec']}";

    $prop_discipline = $doc->createElement('prop_value');
    $prop_discipline->setAttribute('prop_name', 'UID_Discipline');
    $prop_discipline->setAttribute('value', $uid_discipline);
    $prop_values->appendChild($prop_discipline);
    
    // UID_Semester (осенний = 1, весенний = 2)
    $prop_semester = $doc->createElement('prop_value');
    $prop_semester->setAttribute('prop_name', 'UID_Semester');
    $prop_semester->setAttribute('value', $row['exam_semester'] % 2 == 0 ? 2 : 1);
    $prop_values->appendChild($prop_semester);
    
    // UID_Language

    if ($row['bup_language'] == 'русский') $uid_language = $_language_rus_uid;
    else if ($row['bup_language'] == 'английский') $uid_language = $_language_eng_uid;
    else $uid_language = '';

    $prop_language = $doc->createElement('prop_value');
    $prop_language->setAttribute('prop_name', 'UID_Language');
    $prop_language->setAttribute('value', $uid_language);
    $prop_values->appendChild($prop_language);

    // UID_Group
    $prop_chair = $doc->createElement('prop_value');
    $prop_chair->setAttribute('prop_name', 'UID_Group');
    $prop_chair->setAttribute('value', $row['groups_uid']);
    $prop_values->appendChild($prop_chair);

    // для таких случаев всем ставь
    // факультет: 25031.281474976762091
    // кафедру: 25031.281474976763050
    // это аспирантский центр
    if (empty($XmlChairByCode[$row['chair_id']]['UID']))
    {
      $chair_uid = '25031.281474976763050';
      $fac_uid = '25031.281474976762091';
    }
    else
    {
      $chair_uid = $XmlChairByCode[$row['chair_id']]['UID'];
      $fac_uid = $XmlFacultyByCode[$row['department_id']]['UID'];
    }

    // UID_Chair
    $prop_chair = $doc->createElement('prop_value');
    $prop_chair->setAttribute('prop_name', 'UID_Chair');
    $prop_chair->setAttribute('value', $chair_uid);
    $prop_values->appendChild($prop_chair);
    
    // UID_FacultyPerformer
    $prop_faculty = $doc->createElement('prop_value');
    $prop_faculty->setAttribute('prop_name', 'UID_FacultyPerformer');
    $prop_faculty->setAttribute('value', $fac_uid);
    $prop_values->appendChild($prop_faculty);

    // UID_FacultyOwner
    $prop_faculty = $doc->createElement('prop_value');
    $prop_faculty->setAttribute('prop_name', 'UID_FacultyOwner');
    $prop_faculty->setAttribute('value', $XmlFacultyByName[$row['bup_department_name']]['UID']);
    $prop_values->appendChild($prop_faculty);

    // UID_Speciality
    $prop_speciality = $doc->createElement('prop_value');
    $prop_speciality->setAttribute('prop_name', 'UID_Speciality');
    $prop_speciality->setAttribute('value', $XmlGroupByUID[$row['groups_uid']]['UID_Speciality']);
    $prop_values->appendChild($prop_speciality);
    

    $object->appendChild($prop_values);
    $collection->appendChild($object);
    $rows_count++;
  }
}



$AspiranturaRukAsp = GetTable('aspirantura_ruk_asp', "`deleted` <> '1' AND `lecturer_uid` = ''");

if ($AspiranturaRukAsp)
{
  foreach ($AspiranturaRukAsp as $row)
  {
    // если в xml уже есть такой load_id, то не выгружаем
    if ($XmlContentOfLoad[$row['load_id']]) continue;

    $object = $doc->createElement('Object');
    $object->setAttribute('LoadId', $row['load_id']);
    $object->setAttribute('class_id', 'ContentOfLoad');
    $object->setAttribute('nagruzka_type', 'aspirantura_ruk_asp');
    $object->setAttribute('fio', $row['lecturer_fio']);
    
    $prop_values = $doc->createElement('Collection');
    $prop_values->setAttribute('name', 'Prop_Values');
    $prop_values->setAttribute('child_tags', 'prop_value');
    $prop_values->setAttribute('caption', 'Свойства');
    
    // Amount
    $prop_amount = $doc->createElement('prop_value');
    $prop_amount->setAttribute('prop_name', 'Amount');
    $prop_amount->setAttribute('value', $_aspirantura_ruk_asp_hours / 2);
    $prop_values->appendChild($prop_amount);

    // StudentAmount
    $prop_amount = $doc->createElement('prop_value');
    $prop_amount->setAttribute('prop_name', 'StudentAmount');
    $prop_amount->setAttribute('value', 1);
    $prop_values->appendChild($prop_amount);
    
    // TypeOfContingent
    $prop_contingent = $doc->createElement('prop_value');
    $prop_contingent->setAttribute('prop_name', 'TypeOfContingent');
    $prop_contingent->setAttribute('value', '4');
    $prop_values->appendChild($prop_contingent);
    
    // UID_KindOfWork («Руководство аспирантом»)
    $prop_kind = $doc->createElement('prop_value');
    $prop_kind->setAttribute('prop_name', 'UID_KindOfWork');
    $prop_kind->setAttribute('value', $_aspirant_ruk_asp_kind_uid);
    $prop_values->appendChild($prop_kind);
    
    // UID_Discipline
    // Руководство аспирантом
    $uid_discipline = '26006.281474976731761';

    $prop_discipline = $doc->createElement('prop_value');
    $prop_discipline->setAttribute('prop_name', 'UID_Discipline');
    $prop_discipline->setAttribute('value', $uid_discipline);
    $prop_values->appendChild($prop_discipline);
    
    // UID_Semester (осенний = 1, весенний = 2)
    $prop_semester = $doc->createElement('prop_value');
    $prop_semester->setAttribute('prop_name', 'UID_Semester');
    $prop_semester->setAttribute('value', $row['UID_Semester']);
    $prop_values->appendChild($prop_semester);
    
    // UID_Language
    // if ($row['bup_language'] == 'русский') $uid_language = $_language_rus_uid;
    // else if ($row['bup_language'] == 'английский') $uid_language = $_language_eng_uid;
    // else $uid_language = '';

    // $prop_language = $doc->createElement('prop_value');
    // $prop_language->setAttribute('prop_name', 'UID_Language');
    // $prop_language->setAttribute('value', $uid_language);
    // $prop_values->appendChild($prop_language);

    // UID_Group
    // $prop_chair = $doc->createElement('prop_value');
    // $prop_chair->setAttribute('prop_name', 'UID_Chair');
    // $prop_chair->setAttribute('value', $row['groups_uid']);
    // $prop_values->appendChild($prop_chair);

    // для таких случаев всем ставь
    // факультет: 25031.281474976762091
    // кафедру: 25031.281474976763050
    // это аспирантский центр
    if (empty($XmlChairByCode[$row['lecturer_chair_id']]['UID']))
    {
      $chair_uid = '25031.281474976763050';
      $fac_uid = '25031.281474976762091';
    }
    else
    {
      $chair_uid = $XmlChairByCode[$row['lecturer_chair_id']]['UID'];
      $fac_uid = $XmlFacultyByCode[$row['lecturer_department_id']]['UID'];
    }

    // UID_Chair
    $prop_chair = $doc->createElement('prop_value');
    $prop_chair->setAttribute('prop_name', 'UID_Chair');
    $prop_chair->setAttribute('value', $chair_uid);
    $prop_values->appendChild($prop_chair);
    
    // UID_FacultyPerformer
    $prop_faculty = $doc->createElement('prop_value');
    $prop_faculty->setAttribute('prop_name', 'UID_FacultyPerformer');
    $prop_faculty->setAttribute('value', $fac_uid);
    $prop_values->appendChild($prop_faculty);

    // UID_Chair
    $prop_chair = $doc->createElement('prop_value');
    $prop_chair->setAttribute('prop_name', 'UID_Chair');
    $prop_chair->setAttribute('value', $chair_uid);
    $prop_values->appendChild($prop_chair);
    
    // UID_FacultyPerformer
    $prop_faculty = $doc->createElement('prop_value');
    $prop_faculty->setAttribute('prop_name', 'UID_FacultyPerformer');
    $prop_faculty->setAttribute('value', $fac_uid);
    $prop_values->appendChild($prop_faculty);

    $object->appendChild($prop_values);
    $collection->appendChild($object);
    $rows_count++;
  }
}



// $AspiranturaRukSoisk = GetTable('aspirantura_ruk_soisk', "`deleted` <> '1'");

if ($AspiranturaRukSoisk)
{
  foreach ($AspiranturaRukSoisk as $row)
  {
    // если в xml уже есть такой load_id, то не выгружаем
    if ($XmlContentOfLoad[$row['load_id']]) continue;

    $object = $doc->createElement('Object');
    $object->setAttribute('LoadId', $row['load_id']);
    $object->setAttribute('class_id', 'ContentOfLoad');
    $object->setAttribute('nagruzka_type', 'aspirantura_ruk_soisk');
    $object->setAttribute('fio', $row['lecturer_fio']);
    
    $prop_values = $doc->createElement('Collection');
    $prop_values->setAttribute('name', 'Prop_Values');
    $prop_values->setAttribute('child_tags', 'prop_value');
    $prop_values->setAttribute('caption', 'Свойства');
    
    // Amount
    $prop_amount = $doc->createElement('prop_value');
    $prop_amount->setAttribute('prop_name', 'Amount');
    $prop_amount->setAttribute('value', $_aspirantura_ruk_soisk_hours / 2);
    $prop_values->appendChild($prop_amount);

    // StudentAmount
    $prop_amount = $doc->createElement('prop_value');
    $prop_amount->setAttribute('prop_name', 'StudentAmount');
    $prop_amount->setAttribute('value', 1);
    $prop_values->appendChild($prop_amount);
    
    // TypeOfContingent
    $prop_contingent = $doc->createElement('prop_value');
    $prop_contingent->setAttribute('prop_name', 'TypeOfContingent');
    $prop_contingent->setAttribute('value', '4');
    $prop_values->appendChild($prop_contingent);
    
    // UID_KindOfWork («Руководство соискателем»)
    $prop_kind = $doc->createElement('prop_value');
    $prop_kind->setAttribute('prop_name', 'UID_KindOfWork');
    $prop_kind->setAttribute('value', $_aspirant_ruk_soisk_kind_uid);
    $prop_values->appendChild($prop_kind);
    
    // UID_Discipline
    // Руководство соискателем
    $uid_discipline = '26006.281474976731762';

    $prop_discipline = $doc->createElement('prop_value');
    $prop_discipline->setAttribute('prop_name', 'UID_Discipline');
    $prop_discipline->setAttribute('value', $uid_discipline);
    $prop_values->appendChild($prop_discipline);
    
    // UID_Semester (осенний = 1, весенний = 2)
    $prop_semester = $doc->createElement('prop_value');
    $prop_semester->setAttribute('prop_name', 'UID_Semester');
    $prop_semester->setAttribute('value', $row['UID_Semester']);
    $prop_values->appendChild($prop_semester);
    
    // UID_Language
    // if ($row['bup_language'] == 'русский') $uid_language = $_language_rus_uid;
    // else if ($row['bup_language'] == 'английский') $uid_language = $_language_eng_uid;
    // else $uid_language = '';

    // $prop_language = $doc->createElement('prop_value');
    // $prop_language->setAttribute('prop_name', 'UID_Language');
    // $prop_language->setAttribute('value', $uid_language);
    // $prop_values->appendChild($prop_language);

    // UID_Group
    // $prop_chair = $doc->createElement('prop_value');
    // $prop_chair->setAttribute('prop_name', 'UID_Chair');
    // $prop_chair->setAttribute('value', $row['groups_uid']);
    // $prop_values->appendChild($prop_chair);

    // для таких случаев всем ставь
    // факультет: 25031.281474976762091
    // кафедру: 25031.281474976763050
    // это аспирантский центр
    if (empty($XmlChairByCode[$row['lecturer_chair_id']]['UID']))
    {
      $chair_uid = '25031.281474976763050';
      $fac_uid = '25031.281474976762091';
    }
    else
    {
      $chair_uid = $XmlChairByCode[$row['lecturer_chair_id']]['UID'];
      $fac_uid = $XmlFacultyByCode[$row['lecturer_department_id']]['UID'];
    }

    // UID_Chair
    $prop_chair = $doc->createElement('prop_value');
    $prop_chair->setAttribute('prop_name', 'UID_Chair');
    $prop_chair->setAttribute('value', $chair_uid);
    $prop_values->appendChild($prop_chair);
    
    // UID_FacultyPerformer
    $prop_faculty = $doc->createElement('prop_value');
    $prop_faculty->setAttribute('prop_name', 'UID_FacultyPerformer');
    $prop_faculty->setAttribute('value', $fac_uid);
    $prop_values->appendChild($prop_faculty);

    $object->appendChild($prop_values);
    $collection->appendChild($object);
    $rows_count++;
  }
}




// Сохраняем XML в файл
$filename = 'export_ruk_asp.xml';
$filepath = __DIR__ . '/' . $filename;

$doc->save($filepath);

// отдать в браузер

header('Content-Type: text/xml; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
echo $doc->saveXML();


// EchoLog("Export finished. Exported $rows_count rows to file: $filename");

?>