<?

$sotrudnik_db_name = 'sotrudnik';

// $database=mysql_connect("localhost","root" ,"");
// if ($database==false) echo "Can`t establish connection to database sotrudnik";

// $connect_db = mysql_select_db($sotrudnik_db_name, $database);
// if ($connect_db == false) echo "Can`t change database";


// $mysqli->query ("set character_set_client='utf8'");
// $mysqli->query ("set character_set_results='utf8'");
// $mysqli->query ("set collation_connection='utf8'");
// $mysqli->query ("set character_set_connection='utf8'");

// EchoLog('CONNECT SOTR');

$mysqli = new Mysqli("localhost", "root",  "", 'sotrudnik');

// EchoLog($mysqli);

$mysqli->query ("set character_set_client='utf8'");
$mysqli->query ("set character_set_results='utf8'");
$mysqli->query ("set collation_connection='utf8'");
$mysqli->query ("set character_set_connection='utf8'");

?>