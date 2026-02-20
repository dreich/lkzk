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

          ";

$Result = $mysqli->query($query);

if (!$Result)
{
  EchoLog('DB error: ' . $mysqli->error);
  exit;
}

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

$rows_count = 0;

while ($Row = $Result->fetch_assoc())
{
  if ($Row['LoadType'] == 1)
  {
    $Row['StudentAmount'] = '';
  }
  elseif ($Row['LoadType'] == 0)
  {
    $Row['Amount'] = '';
  }
  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $Row['base_uid2']);
  $node->setAttribute('LoadType', $Row['LoadType']);
  $node->setAttribute('Amount', $Row['Amount']);
  $node->setAttribute('StudentAmount', $Row['StudentAmount']);
  $node->setAttribute('UID_Lecturer', $Row['lecturer_uid']);
  $node->setAttribute('delete', $Row['delete']);
  $root->appendChild($node);
  $rows_count++;
}

$Result->free();

header('Content-Type: text/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="ContentOfLoads_selected.xml"');

echo $doc->saveXML();

// EchoLog("Export finished. Exported $rows_count rows to browser");
