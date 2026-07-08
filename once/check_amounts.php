<?

include '../functions.php';


$ContentOfLoad = GetSQL("SELECT * FROM `xml_content_of_load`"); //  LIMIT 10

foreach ($ContentOfLoad as $row)
{
  $amount = round(floatval($row['Amount']), 2);

  $Splits = GetSQL("SELECT SUM(`Amount`) as s FROM `zavkaf_splits` WHERE `base_uid2` = '$row[base_uid2]' AND `delete` <> '1' AND `lecturer_person_id` <> '' AND `lecturer_person_id` <> '000000'");

  $splits_sum = $Splits[0]['s'];

  if (!empty($splits_sum))
  {
    $splits_sum = round($splits_sum, 2);

    if ($splits_sum > $amount)
    {
      // echo sizeof($Splits) . ' - ';
      echo "$row[base_uid2]: $amount != $splits_sum<br>";
    }
  }
  
}


?>