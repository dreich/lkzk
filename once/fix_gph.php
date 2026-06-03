<?

// одноразовый скрипт восстановления selected = 1 для ГПХ, по существующим сплитам
include '../functions.php';


$Splits = GetTable('zavkaf_splits');


foreach ($Splits as $split)
{
  if ($split['lecturer_person_id'])
  {
    $sotrudnik = GetRow('sotrudniki', ['person_id' => $split['lecturer_person_id'], 'type' => 'gph', 'lecturer_uid' => $split['lecturer_uid']]);

    if ($sotrudnik && !$sotrudnik['selected'])
    {
      echo "$sotrudnik[fio] $sotrudnik[chair_id]<br>";

      // $mysqli->query("UPDATE `sotrudniki` SET `selected` = '1' WHERE `person_id` = '$split[lecturer_person_id]' AND `chair_id` = '$sotrudnik[chair_id]'");
    }
  }
}

?>