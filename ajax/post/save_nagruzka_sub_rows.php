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


$stmt = $mysqli->prepare("DELETE FROM `zavkaf_splits` WHERE `base_uid` = ?");
if ($stmt) {
    foreach ($data as $nagruzka_lector) {
        $stmt->bind_param("s", $nagruzka_lector['base_uid']);
        $stmt->execute();
    }
    $stmt->close();
} else {
    foreach ($data as $nagruzka_lector) {
        $safe_base_uid = $mysqli->real_escape_string($nagruzka_lector['base_uid']);
        $query = "DELETE FROM `zavkaf_splits` WHERE `base_uid` = '$safe_base_uid'";
        $mysqli->query($query);
    }
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

      $safe_LoadType = $mysqli->real_escape_string($nagruzka_lector['LoadType']);
      $safe_StudentAmount = $mysqli->real_escape_string($nagruzka_lector['StudentAmount']);
      $safe_Amount = $mysqli->real_escape_string($nagruzka_lector['Amount']);
      $safe_lecturer_login = $mysqli->real_escape_string($nagruzka_lector['lecturer_login']);
      $safe_lecturer_person_id = $mysqli->real_escape_string($nagruzka_lector['lecturer_person_id']);
      $safe_lecturer_fio = $mysqli->real_escape_string($nagruzka_lector['lecturer_fio']);
      $safe_lecturer_uid = $mysqli->real_escape_string($nagruzka_lector['lecturer_uid']);

      $query = "INSERT INTO `zavkaf_splits` SET 
                      `content_of_load_uid` = '$content_of_load_row[UID]',
                      -- `content_of_load_uid_new` = '$new_content_of_load_uid',
                      `base_uid` = '$content_of_load_row[base_uid]',
                      `base_uid2` = '$content_of_load_row[base_uid2]',
                      `base_uid2_new` = '$new_base_uid2',
                      `LoadType` = '$safe_LoadType',
                      `StudentAmount` = '$safe_StudentAmount',
                      `Amount` = '$safe_Amount',
                      `lecturer_login` = '$safe_lecturer_login',
                      `lecturer_person_id` = '$safe_lecturer_person_id',
                      `lecturer_fio` = '$safe_lecturer_fio',
                      `lecturer_uid` = '$safe_lecturer_uid',
                      `chair_uid` = '{$XmlChairByCode[$nagruzka_lector['chair_id']]['UID']}',
                      `zavkaf_login` = '$_SESSION[c_login]',
                      `zavkaf_fio` = '$_SESSION[c_fio]',
                      `delete` = '$delete'

                    ";
      
      $result = $mysqli->query($query);

      // EchoLog($query);
    }
  }

}



if ($result !== false) {
    echo json_encode(['result' => 'success']);
} else {
    echo json_encode(['result' => 'error', 'message' => 'Failed to save']);
}
