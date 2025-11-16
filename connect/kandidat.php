<?

$mysqli = new Mysqli("localhost", "root",  "", 'kandidat');

$mysqli->query ("set character_set_client='utf8'");
$mysqli->query ("set character_set_results='utf8'");
$mysqli->query ("set collation_connection='utf8'");
$mysqli->query ("set character_set_connection='utf8'");


?>