<?

// Разовый скрипт простановки load_id. Потом при добавлении строк должен будет проставляться.

include '../functions.php';

exit;

$Rows = GetTable('aspirantura_kand_exam');

foreach ($Rows as $row)
{
  $load_id = uniq(16);
  $mysqli->query("UPDATE `aspirantura_kand_exam` SET `load_id` = '$load_id' WHERE `id` = '$row[id]'");
}


$Rows = GetTable('aspirantura_ruk_asp');

foreach ($Rows as $row)
{
  $load_id = uniq(16);
  $mysqli->query("UPDATE `aspirantura_ruk_asp` SET `load_id` = '$load_id' WHERE `uid` = '$row[uid]' AND `UID_Semester` = '$row[UID_Semester]'");
}


$Rows = GetTable('aspirantura_ruk_soisk');

foreach ($Rows as $row)
{
  $load_id = uniq(16);
  $mysqli->query("UPDATE `aspirantura_ruk_soisk` SET `load_id` = '$load_id' WHERE `id` = '$row[id]'");
}

?>