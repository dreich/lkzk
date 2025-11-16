<?

// Получить историю по нагрузке из лога для попапа
session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}

include '../../functions.php';

$c_roles = ExplodePalki($_SESSION['c_roles'], true);
$load_base_UID = quote_smart($_GET['load_base_UID']);

if ($c_roles)
{

  $sql = "SELECT * FROM `log` WHERE `load_base_UID` = '$load_base_UID' AND `internal` = '0' ORDER BY `datetime` DESC";

  $History = GetSQL($sql);

  
}


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode($History);


?>