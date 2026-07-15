<?php

// !! TMP HACK ED

// Должен/нужен ещё до режима mode_verification (т.е. в нём сплиты уже должны быть очищены по ТЗ)
// Перед выгрузкой проверить, что в сплитах нет строк по base_uid, которых нет в xml_content_of_load:
// SELECT zavkaf_splits.base_uid, xml_content_of_load.base_uid
// FROM zavkaf_splits
// LEFT JOIN xml_content_of_load USING(`base_uid`)
// WHERE xml_content_of_load.base_uid IS NULL AND zavkaf_splits.`delete` <> '1'
// Если есть, то пометить удалёнными их можно с помощью:
// UPDATE zavkaf_splits zs
// LEFT JOIN xml_content_of_load xcol ON zs.base_uid = xcol.base_uid
// SET zs.`delete` = '1'
// WHERE xcol.base_uid IS NULL 
//   AND zs.`delete` <> '1';
// а сперва можно проверить, сколько пометится с помощью:
// SELECT COUNT(*) 
// FROM zavkaf_splits zs
// LEFT JOIN xml_content_of_load xcol ON zs.base_uid = xcol.base_uid
// WHERE xcol.base_uid IS NULL 
//   AND zs.`delete` <> '1';


include '../../functions.php';


// $ZavkafSplits = GetTable('zavkaf_splits', "`base_uid` IS NOT NULL AND `base_uid` <> '' AND `delete` <> '1'", "base_uid");
// из-за бага, выгрузить доп. пропущенные строки Вакансий
$ZavkafSplits = GetTable('zavkaf_splits', "`base_uid` IS NOT NULL AND `base_uid` <> '' AND `delete` <> '1' AND `lecturer_fio` = 'Вакансия' AND `lecturer_uid` = ''", "base_uid");

// $KSRO = GetTable('ksro', "`base_uid` IS NOT NULL AND `base_uid` <> '' AND `Amount` <> ''", "base_uid");
// $AspiranturaKandExam = GetTable('aspirantura_kand_exam', "`base_uid` IS NOT NULL AND `base_uid` <> '' AND `deleted` <> '1'", "base_uid");
// $AspiranturaRukAsp = GetTable('aspirantura_ruk_asp', "`base_uid` IS NOT NULL AND `base_uid` <> '' AND `deleted` <> '1'", "base_uid");
// $AspiranturaRukSoisk = GetTable('aspirantura_ruk_soisk', "`base_uid` IS NOT NULL AND `base_uid` <> '' AND `deleted` <> '1'", "base_uid");

if (!$ZavkafSplits && !$KSRO)
{
  EchoLog('No data ' . $mysqli->error);
  exit;
}


// Схлопы для Clean
$SplitsByBaseUID = [];

foreach ($ZavkafSplits as $row)
{
  $SplitsByBaseUID[$row['base_uid']] = $row['base_uid'];
}



$KSROByBaseUID = [];

if ($KSRO)
foreach ($KSRO as $row)
{
  $KSROByBaseUID[$row['base_uid']] = $row['base_uid'];
}


$AspiranturaKandExamByBaseUID = [];

if ($AspiranturaKandExam)
foreach ($AspiranturaKandExam as $row)
{
  $AspiranturaKandExamByBaseUID[$row['base_uid']] = $row['base_uid'];
}


$AspiranturaRukAspByBaseUID = [];

if ($AspiranturaRukAsp)
foreach ($AspiranturaRukAsp as $row)
{
  $AspiranturaRukAspByBaseUID[$row['base_uid']] = $row['base_uid'];
}


$AspiranturaRukSoiskByBaseUID = [];

if ($AspiranturaRukSoisk)
foreach ($AspiranturaRukSoisk as $row)
{
  $AspiranturaRukSoiskByBaseUID[$row['base_uid']] = $row['base_uid'];
}



$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

$rows_count = 0;

if ($SplitsByBaseUID)
{
  foreach ($SplitsByBaseUID as $base_uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $base_uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}

if ($KSROByBaseUID)
{
  foreach ($KSROByBaseUID as $base_uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $base_uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}

if ($AspiranturaKandExamByBaseUID)
{
  foreach ($AspiranturaKandExamByBaseUID as $base_uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $base_uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}

if ($AspiranturaRukAspByBaseUID)
{
  foreach ($AspiranturaRukAspByBaseUID as $base_uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $base_uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}

if ($AspiranturaRukSoiskByBaseUID)
{
  foreach ($AspiranturaRukSoiskByBaseUID as $base_uid)
  {
    $node = $doc->createElement('ContentOfLoad');
    $node->setAttribute('UID', $base_uid);
    $node->setAttribute('Clean', 1);
    $root->appendChild($node);
  }
}

// $doc->save("clean2.xml");




// Выгрузка в XML


$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

foreach ($ZavkafSplits as $row)
{
  // на данный момент не понятно, как -1 может быть в таблице
  // || !$row['lecturer_uid'] - нельзя, отсеются вакансии

  if ($row['lecturer_uid'] == '-1') continue;

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
  $node->setAttribute('UID_Lecturer', '26115.281474976893938' /* $row['lecturer_uid'] */);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}

$doc->save("zavkaf_splits_fix_vacancy.xml");
exit;


// Приклеим выгрузку КСРО, у которой есть base_uid
// КСРО идет только по часам, подразумевается LoadType=1, StudentAmount тут не указывается, поскольку их тут нет..
// LoadType не нужен, Галактика сама его определяет автоматически на основании UID_KindOfWork

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

if ($KSRO)
foreach ($KSRO as $row)
{
  // на всякий случай
  if ($row['uid'] == '-1' || !$row['uid']) continue;

  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  // $node->setAttribute('LoadType', "");
  $node->setAttribute('Amount', $row['Amount']);
  $node->setAttribute('StudentAmount', "");
  $node->setAttribute('UID_Lecturer', $row['uid']);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}

$doc->save("ksro.xml");



$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);


if ($AspiranturaKandExam)
foreach ($AspiranturaKandExam as $row)
{
  // на всякий случай
  if ($row['lecturer_uid'] == '-1' || !$row['lecturer_uid']) continue;
  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  // $node->setAttribute('LoadType', "");
  $node->setAttribute('Amount', $row['students_num'] * $_aspirantura_hours_per_student);
  $node->setAttribute('StudentAmount', $row['students_num']);
  $node->setAttribute('UID_Lecturer', $row['lecturer_uid']);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}


$doc->save("aspirantura_kand_exam.xml");


$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

if ($AspiranturaRukAsp)
foreach ($AspiranturaRukAsp as $row)
{
  // на всякий случай
  if ($row['lecturer_uid'] == '-1' || !$row['lecturer_uid']) continue;
  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  // $node->setAttribute('LoadType', "");
  $node->setAttribute('Amount', $_aspirantura_ruk_asp_hours / 2);
  $node->setAttribute('StudentAmount', 1);
  $node->setAttribute('UID_Lecturer', $row['lecturer_uid']);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}

$doc->save("aspirantura_ruk_asp.xml");



$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$root = $doc->createElement('ContentOfLoads');
$doc->appendChild($root);

if ($AspiranturaRukSoisk)
foreach ($AspiranturaRukSoisk as $row)
{
  // на всякий случай
  if ($row['lecturer_uid'] == '-1' || !$row['lecturer_uid']) continue;
  
  $node = $doc->createElement('ContentOfLoad');
  $node->setAttribute('UID', $row['base_uid']);
  // $node->setAttribute('LoadType', "");
  $node->setAttribute('Amount', $_aspirantura_ruk_soisk_hours / 2);
  $node->setAttribute('StudentAmount', 1);
  $node->setAttribute('UID_Lecturer', $row['lecturer_uid']);
  // $node->setAttribute('delete', $row['delete']);
  $root->appendChild($node);
  $rows_count++;
}


$doc->save("aspirantura_ruk_soisk.xml");

$filename = "ContentOfLoads_selected.xml";

echo "Файлы созданы:<br>";

echo "<a href='./clean.xml'>clean.xml</a><br>";
echo "<a href='./zavkaf_splits.xml'>zavkaf_splits.xml</a><br>";
echo "<a href='./ksro.xml'>ksro.xml</a><br>";
echo "<a href='./aspirantura_kand_exam.xml'>aspirantura_kand_exam.xml</a><br>";
echo "<a href='./aspirantura_ruk_asp.xml'>aspirantura_ruk_asp.xml</a><br>";
echo "<a href='./aspirantura_ruk_soisk.xml'>aspirantura_ruk_soisk.xml</a><br>";

// отдать в браузер

// header('Content-Type: text/xml; charset=UTF-8');
// header("Content-Disposition: attachment; filename=\"$filename\"");
// echo $doc->saveXML();

// Сохраняем в файл
// $doc->save($filename);

// EchoLog("Export finished. Exported $rows_count rows to browser");
