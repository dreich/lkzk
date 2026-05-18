<?php

include '../functions.php';
include '../connect.php';

// EchoLog('Start export of selected nagruzka rows');

// $query = "
//           SELECT
//             xml_content_of_load.base_uid,
//             xml_content_of_load.base_uid2,
//             xml_content_of_load.LoadType,
//             xml_content_of_load.Amount,
//             xml_content_of_load.StudentAmount,
//             nagruzka.lecturer_uid,
//             nagruzka.lecturer_fio
//           FROM `nagruzka`
//           JOIN `xml_content_of_load` ON xml_content_of_load.base_uid = nagruzka.load_base_UID2
//           WHERE nagruzka.valid = 1
//             AND TRIM(IFNULL(nagruzka.lecturer_fio, '')) <> ''
//         ";

$XmlChairByCode = GetTable('xml_chair', "", "", "Code");

$ksro_kind_uid = '26003.281474976710751';
$ksro_discipline_uid = '26006.281474976727808';
$ik_kind_uid = '26003.281474976710750';
$ik_discipline_uid = '26006.281474976727807';


$query = "SELECT * FROM `ksro`";

$Result = $mysqli->query($query);

if (!$Result)
{
  EchoLog('DB error: ' . $mysqli->error);
  exit;
}

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

while ($Row = $Result->fetch_assoc())
{
  $object = $doc->createElement('Object');
  $object->setAttribute('LoadId', $Row['id']);
  $object->setAttribute('class_id', 'ContentOfLoad');
  
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

$Result->free();

// Сохраняем XML в файл
$filename = 'ksro.xml';
$filepath = __DIR__ . '../' . $filename;

// отдать в браузер

header('Content-Type: text/xml; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
echo $doc->saveXML();

// $doc->save($filepath);

EchoLog("Export finished. Exported $rows_count rows to file: $filename");

?>