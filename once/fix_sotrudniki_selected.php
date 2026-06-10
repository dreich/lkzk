<?

//  Для таблицы Сотрудники проставить через палки id кафедр, которые должны были выбрать этого сотрудника, и добавили на него нагрузку
// Сначала проверить, что в сплитах заполнен столбец zavkaf_chair_uid (для этого пригодится скрипт fix_splits_zavkaf_chair.php)
include '../functions.php';

$XmlChairByUID = GetTable('xml_chair', "", "", "UID");

$Sotrudniki = GetSQL("
  SELECT * FROM sotrudniki
  WHERE `selected_chairs_ids` = ''
  ORDER BY chair_uid");


foreach ($Sotrudniki as $sotrudnik)
{
  echo "<br>$sotrudnik[fio]<br>";

  // $chair_uids = [];
  $chairs_ids = [];

  // сотрудник псевдо-кафедры
  // if (in_array($sotrudnik['chair_id'], $_pseudo_chairs))
  // {
  //   $chairs_ids[] = $sotrudnik['chair_id'];
  // }
  // else
  {
    // для не ГПХ-шников просто возьмём одну кафедру из таблицы sotrudniki
    if ($sotrudnik['selected'] && $sotrudnik['type'] != 'gph')
    {
      // $sotrudnik['chair_id'];

      // если это псевдо-кафедра, возьмём её псевдо-код, т.к. этот код записывается в сессию завкафа
      $pseudo_chair_id = array_search($sotrudnik['chair_id'], $_pseudo_chairs);
      if ($pseudo_chair_id)
      {
        $chair_id = $pseudo_chair_id;
      }
      else
      {
        $chair_id = $sotrudnik['chair_id'];
      }

      $query = "UPDATE `sotrudniki` SET `selected_chairs_ids` = '|$chair_id|' WHERE `person_id` = '$sotrudnik[person_id]' AND `chair_id` = '$sotrudnik[chair_id]'";

      $Result = $mysqli->query($query);

      echo "Проставили просто кафедру не по нагрузке: $query<br>";

      if (!$Result)
      {
        echo $mysqli->error . '<br>';
      }

      continue;
    
    }

    $ZavkafSplits = GetRows('zavkaf_splits', ['lecturer_uid' => $sotrudnik['lecturer_uid']]);

    if ($sotrudnik['lecturer_uid'] && $ZavkafSplits)
    {
      foreach ($ZavkafSplits as $split)
      {
        // $chair_uids[$split['zavkaf_chair_uid']] = $split['zavkaf_chair_uid'];
        $chair_id = $XmlChairByUID[$split['zavkaf_chair_uid']]['Code'];
        $chairs_ids[$chair_id] = $chair_id;
      }

      
      $chairs_ids_palki = ImplodePalki($chairs_ids);
      echo "Проставили по нагрузке: " . $chairs_ids_palki . '<br>';

      $query = "UPDATE `sotrudniki` SET `selected_chairs_ids` = '$chairs_ids_palki' WHERE `person_id` = '$sotrudnik[person_id]' AND `chair_id` = '$sotrudnik[chair_id]'";

      $Result = $mysqli->query($query);


      echo "$query<br>";

      if (!$Result)
      {
        echo $mysqli->error . '<br>';
      }

      continue;
    }


    $ZavkafSplits = GetRows('zavkaf_splits', ['lecturer_uid' => $sotrudnik['lecturer_uid']]);

    if ($sotrudnik['lecturer_uid'] && $ZavkafSplits)
    {
      foreach ($ZavkafSplits as $split)
      {
        // $chair_uids[$split['zavkaf_chair_uid']] = $split['zavkaf_chair_uid'];
        $chair_id = $XmlChairByUID[$split['zavkaf_chair_uid']]['Code'];
        $chairs_ids[$chair_id] = $chair_id;
      }

      
      $chairs_ids_palki = ImplodePalki($chairs_ids);
      echo "Проставили по нагрузке: " . $chairs_ids_palki . '<br>';

      $query = "UPDATE `sotrudniki` SET `selected_chairs_ids` = '$chairs_ids_palki' WHERE `person_id` = '$sotrudnik[person_id]' AND `chair_id` = '$sotrudnik[chair_id]'";

      $Result = $mysqli->query($query);


      echo "$query<br>";

      if (!$Result)
      {
        echo $mysqli->error . '<br>';
      }

      // continue;
    }
  }


}


?>