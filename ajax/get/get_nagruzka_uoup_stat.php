<?

// Получить статистику по распределению нагрузки по типам по всем кафедрам для УОУП
session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}

// HACK

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode([]);
exit;

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    exit('Forbidden');
}

include '../../functions.php';

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

// если выбрана кафедра (УОУП или это завкаф), нужно получить название кафедры для интерфейса
// $chair_id = quote_smart($_GET['chair_id']);

// $XMLChair = GetRow('xml_chair', ['Code' => $chair_id]);

if ($c_roles)
{
  $dop_sql = '';

  // if ($c_roles['zavkaf'])
  // {
  //   $c_chair_id = $_SESSION['c_chair_id'];
  //   // $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);
  //   $dop_sql = "AND chair_id = '$c_chair_id'";
  // }

  // $Sotrudniki = GetSQL("
  //       SELECT sotrudniki.*, SUM(xml_content_of_load.Amount) as amount_sum
  //       FROM `sotrudniki`
  //       LEFT JOIN nagruzka ON sotrudniki.person_id = nagruzka.lecturer_person_id
  //       LEFT JOIN `xml_content_of_load` ON nagruzka.`load_base_UID2` = xml_content_of_load.`base_uid`
  //       WHERE 1 AND sotrudniki.`chair_id` = '$chair_id'
  //       GROUP BY sotrudniki.person_id
  //       "); 


  $sql = "SELECT `lecturer_fio`, xml_content_of_load.Amount, chair_id, chair_name
          FROM `nagruzka` 
          LEFT JOIN `xml_content_of_load` ON nagruzka.`load_base_UID2` = xml_content_of_load.`base_uid2`
          WHERE 1";

  $Nagruzka = GetSQL($sql);

   $stats = [
        'discipline' =>
        [
          'assigned_to_vacancy' => 0,   // 'Вакансия'
          'not_assigned' => 0,     // пустые
          'assigned' => 0  // непустые (кроме 'Вакансия')
        ]
    ];
    
    foreach ($Nagruzka as $item) {
        $fio = $item['lecturer_fio'];
        
        if ($fio === 'Вакансия') {
            $stats['discipline']['assigned_to_vacancy'] += $item['Amount'];
        }

        if ($fio == '') {
            $stats['discipline']['not_assigned'] += $item['Amount'];
        } else {
            $stats['discipline']['assigned'] += $item['Amount'];
        }

        $stats['discipline']['total'] += $item['Amount'];
    }
  
}

// $stats['chair_name'] = $XMLChair['Name'];

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode($stats);


?>