<?

// Получить данные в таблицу "Без типа" для УОУП
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

if ($c_roles['uoup'])
{

  $dop_sql = "#AND (`nagruzka_type` IS NULL OR `nagruzka_type` = '')
              #AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'
              #AND `base_uid` = '26589.281474976773927'
              ORDER BY `original_uid`
              # TMP
              #AND `UID_Discipline` = '26006.281474976725278'
              #LIMIT 15
  ";

  $Nagruzka = PrepareNagruzka(GetSQL(GetNagruzkaBaseQuery($dop_sql, 'empty', false)));

  
}


header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Nagruzka));


?>