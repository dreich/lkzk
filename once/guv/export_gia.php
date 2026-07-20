<?php

// Разовый скрипт экспорта нагрузки gia после ошибок в ГУВ


include '../../functions.php';

// получим base_uid из backup, потому что в сплитах даже нет возможности выбрать по типу нагрузки, плохо!
$BaseUIDs = GetSQL("SELECT zavkaf_splits.base_uid
                    FROM zavkaf_splits
                    JOIN xml_content_of_load_08_07_2026
                    USING (`base_uid`)
                    WHERE nagruzka_type = 'gia'
                    ", 'base_uid');

// EchoLog($BaseUIDs);

$splits_table_name = 'zavkaf_splits';


// Нужно перечислить все сплиты для base_uid, полученных выше, т.е. не только вакансии, а всё распределение для base_uid
// т.к. в ГУВ нельзя передать для base_uid несколько раз одного и того же лектора (включая вакансию), то будем схлопывать и суммировать часы и студентов
$ZavkafSplitsByUID_Lecturer_UID = [];
// 26589.281474976811101
if ($BaseUIDs)
foreach ($BaseUIDs as $zs)
{
  // EchoLog($zs['base_uid']);
  $splits = GetRows($splits_table_name, ['base_uid' => $zs['base_uid'], 'delete' => 0]);

  if (!$splits)
  {
    echo "Нет строк для $zs[base_uid]<br>";
    // exit;
  }
  else
  {
    foreach ($splits as $split)
    {
      if (!$ZavkafSplitsByUID_Lecturer_UID["$split[base_uid]-$split[lecturer_uid]"])
      {
        $ZavkafSplitsByUID_Lecturer_UID["$split[base_uid]-$split[lecturer_uid]"] = $split;
      }
      else
      {
        safeAdd($ZavkafSplitsByUID_Lecturer_UID["$split[base_uid]-$split[lecturer_uid]"]['Amount'], $split['Amount']);
        safeAdd($ZavkafSplitsByUID_Lecturer_UID["$split[base_uid]-$split[lecturer_uid]"]['StudentAmount'], $split['StudentAmount']);
      }
    }
  }

  // $ZavkafSplitsByUID_Lecturer_UID = array_merge($ZavkafSplitsByUID_Lecturer_UID, $splits);
}


if (!$ZavkafSplitsByUID_Lecturer_UID)
{
  EchoLog('No data ' . $mysqli->error);
  exit;
}


$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

$rows_count = 0;

if ($BaseUIDs)
{
  foreach ($BaseUIDs as $row)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $row['base_uid']);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}



// Выгрузка в XML


// $doc = new DOMDocument('1.0', 'UTF-8');
// $doc->formatOutput = true;

// $root = $doc->createElement('ContentOfLoads');
// $doc->appendChild($root);

foreach ($ZavkafSplitsByUID_Lecturer_UID as $row)
{
  // на данный момент не понятно, как -1 может быть в таблице
  // || !$row['lecturer_uid'] - нельзя, отсеются вакансии

  if ($row['lecturer_uid'] == '-1' || !$row['lecturer_fio']) continue;

  // if ($row['LoadType'] == 1)
  // {
  //   $row['StudentAmount'] = '';
  // }
  // elseif ($Row['LoadType'] == 0)
  // {
  //   $row['Amount'] = '';
  // }
  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  // $node->setAttribute('LoadType', $row['LoadType']);
  $node->setAttribute('Amount', $row['Amount']);
  $node->setAttribute('StudentAmount', $row['StudentAmount']);

  // !! TMP HACK for vacancy fix

  if ($row['lecturer_fio'] == 'Вакансия')
  {
    $lecturer_uid = '26115.281474976893938';
  }
  else
  {
    $lecturer_uid = $row['lecturer_uid'];
  }

  $node->setAttribute('UID_Lecturer', $lecturer_uid /* $row['lecturer_uid'] */);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}

$doc->save("zavkaf_splits_fix_gia.xml");
exit;


?>
