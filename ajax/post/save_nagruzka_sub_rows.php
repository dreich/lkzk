<?php

include '../../functions.php';

session_name('lkzk');
session_start();

header('Content-Type: application/json');

if (!$_SESSION['c_login']) {
    echo json_encode(['result' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$c_roles = ExplodePalki($_SESSION['c_roles'], true);



$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  echo json_encode(['result' => 'error', 'message' => 'Invalid data']);
  exit;
}

// $XmlChairByCode = GetTable('xml_chair', "", "", "Code");
$XMLLecturerByUID = GetTable('xml_lecturer', "", "", "UID");


foreach ($data as $nagruzka_lector)
{
  $query = "DELETE FROM `zavkaf_splits` WHERE `base_uid` = '$nagruzka_lector[base_uid]'";
  // EchoLog($query);

  $mysqli->query($query);
}

foreach ($data as $nagruzka_lector)
{
  // Если лектор во фронте был взят из таблицы распределения zavkaf_splits, то другое поле
  // if (!$nagruzka_lector['xml_content_of_load_UID'])
  // {
  //   $nagruzka_lector['xml_content_of_load_UID'] = $nagruzka_lector['content_of_load_uid'];
  // }

  // проанализируем xml_content_of_load_UID на предмет споточенности
  // если есть потоки, то нужно обрабатывать все соотв. xml_content_of_load_UID`ы (убирать или добавлять лектора)
  // $uid_obj = parseNagruzkaBaseUid2($nagruzka_lector['xml_content_of_load_UID']);

  // // берём все строки для потока вида 26589.281474976773927[.26115.281474976816519].*
  // if ($uid_obj['potok_suffix'])
  // {
  //   // EchoLog($uid_obj);
    
  //   $wildcard_uid = "$uid_obj[base]";
  //   if ($uid_obj['lector_suffix']) $wildcard_uid .= ".$uid_obj[lector_suffix]";

  //   $sql = "`UID` LIKE ('$wildcard_uid._')";
  // }
  // потока нет, берём единственную строку 26589.281474976773927[.26115.281474976816519]
  // else
  {
    $sql = "`base_uid2` = '$nagruzka_lector[base_uid2]'";
  }

  // $XMLContentOfLoadRows = GetTable('xml_content_of_load', $sql);
  // С таким base_uid2 может быть более одной строки, если споточенность, возьмём одну
  $content_of_load_row = GetRow('xml_content_of_load', ['base_uid2' => $nagruzka_lector['base_uid2']]);
  // EchoLog($XMLContentOfLoadRows);


  if ($content_of_load_row)
  {
    // EchoLog(sizeof($XMLContentOfLoadRows));
    // foreach ($XMLContentOfLoadRows as $content_of_load_row)
    {
      // EchoLog($content_of_load_row);

      // uid как он приходит из Галактики
      // $uid_obj = parseNagruzkaBaseUid2($content_of_load_row['UID']);
      // base_uid2 аналогичен, но не содержит суффиксов споточенности, будем "заменять" суффикс лектора
      // $base_uid2_obj = parseNagruzkaBaseUid2($content_of_load_row['base_uid2']);

      if ($nagruzka_lector['lecturer_uid'] && $nagruzka_lector['lecturer_uid'] != '-1')
      {
        // ПОДРАЗУМЕВАЕТСЯ, что лектор не убирается, а только ставится [завкафом]
        // $new_content_of_load_uid = "$uid_obj[base].$nagruzka_lector[lecturer_uid]";
        $new_base_uid2 = "$nagruzka_lector[base_uid].$nagruzka_lector[lecturer_uid]";
      }
      else
      {
        // если вдруг начнём удалять лектора
        // $new_content_of_load_uid = "$uid_obj[base]";
        $new_base_uid2 = "$nagruzka_lector[base_uid]";
      }

      // if ($uid_obj['potok_suffix'])
      // {
      //   $new_content_of_load_uid .= ".$uid_obj[potok_suffix]";
      // }

      // если завкаф добавляет преподавателя, то ... сомнение
      // if ($c_roles['zavkaf'])
      // {
      //   $nagruzka_lector['chair_id'] = $_SESSION['c_chair_id']; // ?? 0;
      // }

      
      $delete = $nagruzka_lector['delete'] ? '1' : '0';

      $query = "INSERT INTO `zavkaf_splits` SET 
                      `content_of_load_uid` = '$content_of_load_row[UID]',
                      -- `content_of_load_uid_new` = '$new_content_of_load_uid',
                      `base_uid` = '$content_of_load_row[base_uid]',
                      `base_uid2` = '$content_of_load_row[base_uid2]',
                      `base_uid2_new` = '$new_base_uid2',
                      `LoadType` = '$nagruzka_lector[LoadType]',
                      `StudentAmount` = '$nagruzka_lector[StudentAmount]',
                      `Amount` = '$nagruzka_lector[Amount]',
                      `lecturer_login` = '$nagruzka_lector[lecturer_login]',
                      `lecturer_person_id` = '$nagruzka_lector[lecturer_person_id]',
                      `lecturer_fio` = '$nagruzka_lector[lecturer_fio]',
                      `lecturer_uid` = '$nagruzka_lector[lecturer_uid]',
                      `chair_uid` = '{$XMLLecturerByUID[$nagruzka_lector['lecturer_uid']]['UID_Chair']}',
                      `zavkaf_login` = '$_SESSION[c_login]',
                      `zavkaf_fio` = '$_SESSION[c_fio]',
                      `delete` = '$delete'

                    ";
      
      $mysqli->query($query);

      // EchoLog($query);
    }
  }

}



if ($result !== false) {
    echo json_encode(['result' => 'success']);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
