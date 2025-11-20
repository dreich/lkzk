<?php

// Из-за введения на Сотруднике в таблице подразделений столбца has_real_chief, нужно актуализировать завкафа в таблице нагрузок

include '../functions.php';

$podrazdelenia_table_name = "podrazdelenia" . date('Y');

include '../connect/sotrudnik.php';

$Podrazdelenia = GetTable($podrazdelenia_table_name, "", "", 'id');

include '../connect.php';

$Nagruzka = GetTable('nagruzka');

foreach ($Nagruzka as $nagruzka)
{

  if (!$nagruzka['chair_id'] || !$nagruzka['zavkaf_id']) continue;

  if ($Podrazdelenia[$nagruzka['chair_id']]['has_real_chief'])
  {
    // echo "$nagruzka[chair_name] zavkaf is real chief<br>";
  }
  else
  {
    echo "$nagruzka[chair_name] zavkaf IS NOT real chief<br>";

    $Result = $mysqli->query("UPDATE `nagruzka` SET `zavkaf_login` = '', `zavkaf_id` = '', `zavkaf_fio` = '' WHERE `load_base_UID` = '$nagruzka[load_base_UID]'");

    if (!$Result)
    {
      echo $mysqli->error . '<br>';
    }
  }
}

?>