<?

// Получить данные в таблицу отказов факультетов
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
  $c_chair_id = $_SESSION['c_chair_id'];

  $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);

  $XMLContentOfLoad = GetRows('xml_content_of_load', ['UID_Chair' => $XMLChair['UID']]);

  $dop_sql = "AND `status` = 'require_admin_change'
              #AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'
              #AND `base_uid` = '26589.281474976773927'
              ORDER BY `original_uid`
              # TMP
              #AND `UID_Discipline` = '26006.281474976725278'
              #LIMIT 15
  ";

  $Nagruzka = PrepareNagruzka(GetSQL(GetNagruzkaBaseQuery($dop_sql)));

  
}

// Получим из лога последнее собщения action_name = 'require_admin_change'
if ($Nagruzka)
{
  foreach ($Nagruzka as &$nagruzka)
  {
    $History = GetSQL("SELECT * FROM `log` WHERE `action_name` = 'require_admin_change' AND `load_base_UID2` = '$nagruzka[base_uid2]' ORDER BY `id` DESC LIMIT 1");

    $nagruzka['require_admin_change_message'] = $History[0]['message'];
    // оставить дату без времени
    $nagruzka['require_admin_change_date'] = date('Y-m-d', strtotime($History[0]['datetime']));

  }
}





header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($Nagruzka));


?>