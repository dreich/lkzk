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

$query = "SELECT * FROM `zavkaf_splits`
          ORDER BY `base_uid`
          ";

$Result = GetSQL($query);

if (!$Result)
{
  EchoLog('No data splits ' . $mysqli->error);
  exit;
}

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

$rows_count = 0;

$RowsByBaseUID = [];

foreach ($Result as $row)
{
  $RowsByBaseUID[$row['base_uid']] = $row['base_uid'];
}

if ($RowsByBaseUID)
{
  foreach ($RowsByBaseUID as $row)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $row);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}


foreach ($Result as $row)
{
  // на данный момент не понятно, как -1 может быть в таблице
  if ($row['lecturer_uid'] == '-1' || !$row['lecturer_uid']) continue;

  if ($row['LoadType'] == 1)
  {
    $row['StudentAmount'] = '';
  }
  elseif ($Row['LoadType'] == 0)
  {
    $row['Amount'] = '';
  }
  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  $node->setAttribute('LoadType', $row['LoadType']);
  $node->setAttribute('Amount', $row['Amount']);
  $node->setAttribute('StudentAmount', $row['StudentAmount']);
  $node->setAttribute('UID_Lecturer', $row['lecturer_uid']);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}


$filename = "ContentOfLoads_selected.xml";

// отдать в браузер

header('Content-Type: text/xml; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
echo $doc->saveXML();

// Сохраняем в файл
// $doc->save($filename);

// EchoLog("Export finished. Exported $rows_count rows to browser");
