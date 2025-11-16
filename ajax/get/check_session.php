<?

session_name('lkzk');
session_start();

if ($_SESSION['c_login']) $result = 'true';
else $result = 'false';

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');

echo $result;

?>