<?

// Для УОУП для раздела Настройки получить данные, есть ли файл инструкций
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

$ParamRow = [];

if ($c_roles['uoup'])
{
  $ParamRow = GetRow('params', ['param' => 'zavkaf_instructions']);
}

if (!$ParamRow) $ParamRow = new stdClass;


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode($ParamRow);


?>