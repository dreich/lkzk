<?php

include '../../functions.php';

session_name('lkzk');
session_start();

header('Content-Type: application/json');

if (!$_SESSION['c_login']) {
    echo json_encode(['result' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$chair_id = $_SESSION['c_chair_id']; // ?? 0;

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  echo json_encode(['result' => 'error', 'message' => 'Invalid data']);
  exit;
}

$XmlChairByCode = GetTable('xml_chair', "", "", "Code");


foreach ($data as $nagruzka_lector)
{
  $escaped_base_uid = quote_smart($nagruzka_lector['base_uid']);
  $query = "DELETE FROM `zavkaf_splits` WHERE `base_uid` = '$escaped_base_uid'";
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

      
      $delete = $nagruzka_lector['delete'] ? '1' : '0';

      $escaped_content_of_load_uid = quote_smart($content_of_load_row['UID']);
      $escaped_base_uid = quote_smart($content_of_load_row['base_uid']);
      $escaped_base_uid2 = quote_smart($content_of_load_row['base_uid2']);
      $escaped_new_base_uid2 = quote_smart($new_base_uid2);
      $escaped_LoadType = quote_smart($nagruzka_lector['LoadType']);
      $escaped_StudentAmount = quote_smart($nagruzka_lector['StudentAmount']);
      $escaped_Amount = quote_smart($nagruzka_lector['Amount']);
      $escaped_lecturer_login = quote_smart($nagruzka_lector['lecturer_login']);
      $escaped_lecturer_person_id = quote_smart($nagruzka_lector['lecturer_person_id']);
      $escaped_lecturer_fio = quote_smart($nagruzka_lector['lecturer_fio']);
      $escaped_lecturer_uid = quote_smart($nagruzka_lector['lecturer_uid']);
      $escaped_chair_uid = quote_smart($XmlChairByCode[$nagruzka_lector['chair_id']]['UID']);
      $escaped_zavkaf_login = quote_smart($_SESSION['c_login']);
      $escaped_zavkaf_fio = quote_smart($_SESSION['c_fio']);
      $escaped_delete = quote_smart($delete);

      $query = "INSERT INTO `zavkaf_splits` SET 
                      `content_of_load_uid` = '$escaped_content_of_load_uid',
                      -- `content_of_load_uid_new` = '$new_content_of_load_uid',
                      `base_uid` = '$escaped_base_uid',
                      `base_uid2` = '$escaped_base_uid2',
                      `base_uid2_new` = '$escaped_new_base_uid2',
                      `LoadType` = '$escaped_LoadType',
                      `StudentAmount` = '$escaped_StudentAmount',
                      `Amount` = '$escaped_Amount',
                      `lecturer_login` = '$escaped_lecturer_login',
                      `lecturer_person_id` = '$escaped_lecturer_person_id',
                      `lecturer_fio` = '$escaped_lecturer_fio',
                      `lecturer_uid` = '$escaped_lecturer_uid',
                      `chair_uid` = '$escaped_chair_uid',
                      `zavkaf_login` = '$escaped_zavkaf_login',
                      `zavkaf_fio` = '$escaped_zavkaf_fio',
                      `delete` = '$escaped_delete'

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
