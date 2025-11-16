<?

$db_name = 'unn_opop2';

/*
$database = mysql_connect("localhost","root" ,"");
if (!$database) echo "Can`t establish connection to database";


if (!mysql_select_db($db_name, $database)) echo "Can`t change database";


mysql_query ("set character_set_client='utf8'");
mysql_query ("set character_set_results='utf8'");
mysql_query ("set collation_connection='utf8'");
mysql_query ("set character_set_connection='utf8'");
*/

// EchoLog('connect opop2');

$mysqli = new Mysqli("localhost", "root",  "", 'unn_opop2');

$mysqli->query ("set character_set_client='utf8'");
$mysqli->query ("set character_set_results='utf8'");
$mysqli->query ("set collation_connection='utf8'");
$mysqli->query ("set character_set_connection='utf8'");


?>