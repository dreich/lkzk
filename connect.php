<?

$db_name = 'lkzk';

// $database=mysql_connect("localhost","root" ,"");
// if ($database==false) echo "Can`t establish connection to database";

// $db_result=mysql_select_db($db_name, $database);
// if ($db_result==false) echo "Can`t change database";


// $mysqli->query ("set character_set_client='utf8'");
// $mysqli->query ("set character_set_results='utf8'");
// $mysqli->query ("set collation_connection='utf8'");
// $mysqli->query ("set character_set_connection='utf8'");



$mysqli = new Mysqli("localhost", "root",  "", $db_name);

$mysqli->query ("set character_set_client='utf8mb4'");
$mysqli->query ("set character_set_results='utf8mb4'");
$mysqli->query ("set collation_connection='utf8mb4_general_ci'");
$mysqli->query ("set character_set_connection='utf8mb4'");


?>