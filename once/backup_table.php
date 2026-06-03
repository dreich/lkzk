<?

include '../functions.php';;

$table = quote_smart($_GET['table']);

fullBackupTable($table, 4);


?>