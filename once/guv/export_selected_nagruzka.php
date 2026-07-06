<?php

include '../../functions.php';

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

$ZavkafSplits = GetSQL($query);

$KSRO = GetTable('ksro', "`base_uid` IS NOT NULL");

if (!$ZavkafSplits && !$KSRO)
{
  EchoLog('No data ' . $mysqli->error);
  exit;
}

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

$rows_count = 0;

$SplitsByBaseUID = [];

foreach ($ZavkafSplits as $row)
{
  $SplitsByBaseUID[$row['base_uid']] = $row['base_uid'];
}

$KSROByBaseUID = [];

foreach ($KSROByBaseUID as $row)
{
  $KSROByBaseUID[$row['base_uid']] = $row['base_uid'];
}

if ($SplitsByBaseUID)
{
  foreach ($SplitsByBaseUID as $uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}

if ($KSROByBaseUID)
{
  foreach ($KSROByBaseUID as $uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}


// Выгрузка в XML

foreach ($ZavkafSplits as $row)
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


// Приклеим выгрузку КСРО, у которой есть base_uid
// КСРО идет только по часам, подразумевается LoadType=1, StudentAmount тут не указывается, поскольку их тут нет..
// LoadType не нужен, Галактика сама его определяет автоматически на основании UID_KindOfWork


if ($KSRO)
foreach ($KSRO as $row)
{
  // на всякий случай
  if ($row['uid'] == '-1' || !$row['uid']) continue;

  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  $node->setAttribute('LoadType', "");
  $node->setAttribute('Amount', $row['Amount']);
  $node->setAttribute('StudentAmount', "");
  $node->setAttribute('UID_Lecturer', $row['uid']);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}


$filename = "ContentOfLoads_selected.xml";

// отдать в браузер

// header('Content-Type: text/xml; charset=UTF-8');
// header("Content-Disposition: attachment; filename=\"$filename\"");
// echo $doc->saveXML();

// Сохраняем в файл
$doc->save($filename);

// EchoLog("Export finished. Exported $rows_count rows to browser");
