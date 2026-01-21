<?

// Получить данные в таблицу нагрузки вида Дисциплина
// 1) Завкаф просматривает/правит свою кафедру
// 2) УОУП просматривает нагрузку кафедры

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

// УОУП просматривает нагрузку кафедры
if ($_GET['chair_id'])
{

}

$lecturer_uid = isset($_GET['lecturer_uid']) ? quote_smart($_GET['lecturer_uid']) : '';


if ($c_roles['zavkaf'])
{
  $c_chair_id = $_SESSION['c_chair_id'];
  $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);
  $chair_id_sql = "AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'";
}

if ($c_roles['uoup'] && $_GET['chair_id'])
{
  $chair_id = quote_smart($_GET['chair_id']);
  $XMLChair = GetRow('xml_chair', ['Code' => $chair_id]);
  $chair_id_sql = "AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'";
}


$global_nagruzka_filter = $_COOKIE['global_nagruzka_filter'];

// EchoLog($global_nagruzka_filter);

if ($global_nagruzka_filter)
{
  if ($global_nagruzka_filter == 'assigned')
  {
    $global_nagruzka_filter_sql = "AND `lecturer_fio` <> '' AND `lecturer_fio` <> 'Вакансия' AND `lecturer_fio` IS NOT NULL";
  }
  elseif ($global_nagruzka_filter == 'not_assigned')
  {
    $global_nagruzka_filter_sql = "AND (`lecturer_fio` = '' OR `lecturer_fio` IS NULL)";
  }
  elseif ($global_nagruzka_filter == 'assigned_to_vancancy')
  {
    $global_nagruzka_filter_sql = "AND `lecturer_fio` = 'Вакансия'";
  }
}

  

// $XMLContentOfLoad = GetRows('xml_content_of_load', ['UID_Chair' => $XMLChair['UID']]);

$dop_sql = "$chair_id_sql
            $global_nagruzka_filter_sql
            AND `chair_id` IS NOT NULL AND `valid` = '1'
            #AND `status` NOT IN ('')
            #AND `base_uid` = '26589.281474976773927'
            # TMP
            #AND `UID_Discipline` = '26006.281474976725278'
            #ORDER BY `status`, ``
            #LIMIT 150
";

if ($lecturer_uid) {
    $dop_sql .= " AND nagruzka.lecturer_uid = '$lecturer_uid'";
}

$nagruzka_query = GetNagruzkaBaseQuery($dop_sql, false);

// EchoLog($nagruzka_query);

// $_Nagruzka = GetSQL($nagruzka_query);

// $Nagruzka = PrepareNagruzka(GetSQL($nagruzka_query));






header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values(PrepareNagruzka(GetSQL($nagruzka_query))));


?>