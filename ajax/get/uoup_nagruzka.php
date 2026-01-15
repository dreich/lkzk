<?

// Получить данные в таблицу нагрузки администратора УОУП
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
$Nagruzka = [];

if ($c_roles['uoup'])
{
  $_Nagruzka = GetSQL("
              SELECT nagruzka.*, xml_content_of_load.Amount, xml_content_of_load.base_uid2
              FROM `nagruzka`
              JOIN `xml_content_of_load` ON nagruzka.`load_base_UID2` = xml_content_of_load.`base_uid2`
              WHERE `chair_id` IS NOT NULL AND `valid` = '1'
              #LIMIT 100
      ");

  EchoLog(sizeof($_Nagruzka));

  // приведём к уникальному base_uid, потому что в таблице xml_content_of_load base_uid повторяются для xml_content_of_load.UID с цифрами после точки в конце ("споточенное")
  if ($_Nagruzka)
  {
    foreach ($_Nagruzka as $row)
    {
      $Nagruzka[$row['base_uid2']] = $row;
    }
  }

  $NagruzkaByChair = [];

  // просуммируем данные по кафедрам
  if ($Nagruzka)
  {
    foreach ($Nagruzka as $row)
    {
      if (!$NagruzkaByChair[$row['chair_id']])
      {
        $NagruzkaByChair[$row['chair_id']] = $row;
        $NagruzkaByChair[$row['chair_id']]['amount_sum'] = $row['Amount'];
        $NagruzkaByChair[$row['chair_id']]['on_vacancy_num'] = $NagruzkaByChair[$row['chair_id']]['assigned_num'] = $NagruzkaByChair[$row['chair_id']]['not_assigned_num'] = 0;
      }
      else
      {
        $NagruzkaByChair[$row['chair_id']]['amount_sum'] += $row['Amount'];
      }

      // На вакансии
      if (mb_strcasecmp('Вакансия', $row['lecturer_fio']) == 0)
      {
        $NagruzkaByChair[$row['chair_id']]['on_vacancy_num'] += $row['Amount'];
      }
      else
      {
        // Выбран преподаватель
        if ($row['lecturer_uid'])
        {
          $NagruzkaByChair[$row['chair_id']]['assigned_num'] += $row['Amount'];
        }
        // Ничего не выбрано
        else
        {
          $NagruzkaByChair[$row['chair_id']]['not_assigned_num'] += $row['Amount'];
        }
      }
    }
  }
}



header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');
echo json_encode(array_values($NagruzkaByChair));


?>