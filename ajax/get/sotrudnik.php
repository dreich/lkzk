<?

// Используется при поиске сотрудника (универсальный поиск по системе Сотрудник)

include '../../functions.php';

session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  EchoLog('No access');
  exit;
}

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}


$callback = $_GET['callback'];
$s = quote_smart($_GET['s']);

$c_roles = ExplodePalki($_SESSION['c_roles'], true);


$fio_array = explode(' ', $s);
$fio_array = quote_smart($fio_array);

if ($fio_array[0]) $surname_sql = "AND `surname` LIKE ('%$fio_array[0]%')";
if ($fio_array[1]) $name_sql = "AND `name` LIKE ('%$fio_array[1]%')";
if ($fio_array[2]) $patronymic_sql = "AND `patronymic` LIKE ('%$fio_array[2]%')";


$position_table_name = 'position' . date('Y');
$ukrup_table_name = 'ukrup' . date('Y');
$podrazdelenia_table_name = 'podrazdelenia' . date('Y');

if ($podrazdelenie_id) $podrazdelenie_id_sql = "AND $position_table_name.podrazdelenia_chain LIKE('%|$podrazdelenie_id|%')";

include '../../connect/sotrudnik.php';

$query = "SELECT person.`id` as id, surname, name, patronymic, alias as login, $position_table_name.dolzhnost as dolzhnost, CONCAT($position_table_name.dolzhnost, ', ', $ukrup_table_name.ukrup_name) as hint, $ukrup_table_name.ukrup_name as ukrup_name,
  CONCAT(surname, ' ', name, ' ', patronymic) as fio,
  $podrazdelenia_table_name.pname as podrazdelenie_title, $podrazdelenia_table_name.`id` as podrazdelenie_id, $position_table_name.podrazdelenia_chain
  as podrazdelenia_chain, ldap_employees_contacts.`e_mail`, ldap_employees_contacts.`mobile` as phone
  FROM `person` 
          INNER JOIN `$position_table_name` ON person.`id` = $position_table_name.`person_id`
          INNER JOIN `$podrazdelenia_table_name` ON $position_table_name.`podrazdelenie_id` = $podrazdelenia_table_name.`id`
          INNER JOIN `$ukrup_table_name` ON $position_table_name.ukrup_code = $ukrup_table_name.ukrup_code
          LEFT JOIN `ldap_employees_contacts` USING (`alias`)
          WHERE $position_table_name.`main_wo_low_priority` > 0
          AND $position_table_name.`actual` = '1'
          AND 
          (1 $surname_sql $name_sql $patronymic_sql)
          $podrazdelenie_id_sql
          ORDER BY `main_wo_low_priority` DESC 
          ";

$Rows = GetSQL($query);

// EchoLog($query);
$People = array();
  
if ($Rows)
{
  foreach ($Rows as &$row)
  {
    if (!$People[$row['id']])
    {
      if ($limit_cfo_id)
      {
        $row['cfo_id'] = $limit_cfo_id;
      }
      $People[$row['id']] = $row;
    }
  }

  // Для каждого подразделения получим названия всех подразделений-предков
  $People = AddChainString($People);
}



header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($People));

?>