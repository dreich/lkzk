<?

// в сплитах проставить где пустые zavkaf_chair_uid
// ! Для ВШОПФ и других псевдо- скрипт не отрабатывает (не было времени), потом вручную запросом прописываю
include '../functions.php';

$Zavkafs = GetSQL("SELECT DISTINCT zavkaf_login FROM zavkaf_splits", null, 'zavkaf_login');
$XmlChairByCode = GetTable('xml_chair', "", "", "Code");
$XmlChairByUID = GetTable('xml_chair', "", "", "UID");

include '../connect/sotrudnik.php';

$Person = GetTable('person', "", "", "alias");



print_r($Zavkafs);

include '../connect/sotrudnik.php';

foreach ($Zavkafs as $zavkaf_login)
{
  $Podrazdelenie = GetTable('podrazdelenia2026', "`has_real_chief` = '1' AND `chief_id` = '{$Person[$zavkaf_login]['id']}' AND (podrazdelenia2026.`pname` LIKE('Кафедра%') OR podrazdelenia2026.`pname` LIKE('%базовая кафедра%') OR pname = 'Высшая школа общей и прикладной физики')");

  // echo $mysqli->error;

  // echo "$zavkaf_login<br>";

  // print_r($Podrazdelenie);

  if (sizeof($Podrazdelenie) > 1)
  {
    echo "Для $zavkaf_login более одной строки кафедр<br>";
  }
  elseif (sizeof($Podrazdelenie) == 1)
  {
    // echo "Для $zavkaf_login код подразделения = {$Podrazdelenie[0]['id']}<br>";

    // echo "Для $zavkaf_login chair_uid = {$XmlChairByCode[$Podrazdelenie[0]['id']]['UID']}<br>";
    
    $Person[$zavkaf_login]['chair_uid'] = $XmlChairByCode[$Podrazdelenie[0]['id']]['UID'];
  }
  else
  {
    echo "Для {$Person[$zavkaf_login]['id']} $zavkaf_login кафедру не нашли<br>";
  }

}


include '../connect.php';

$ZavkafSplits = GetTable('zavkaf_splits', "`zavkaf_chair_uid` = ''");

foreach ($ZavkafSplits as $split)
{
  if ($XmlChairByUID[$split['chair_uid']])
  {
    // не вакансия
    // if ($split['lecturer_fio'] !== 'Вакансия')
    {
      if ($split['chair_uid'] === $Person[$split['zavkaf_login']]['chair_uid'])
      {
        // совпало, возьмём uid кафедры
        $Result = $mysqli->query("UPDATE `zavkaf_splits` SET `zavkaf_chair_uid` = '{$Person[$split['zavkaf_login']]['chair_uid']}' WHERE `id` = $split[id]");

        if (!$Result)
        {
          echo $mysqli->error . '<br>';
        }
      }
      else
      {
        echo "Сплит $split[id] кафедры не совпали {$split['chair_uid']} <> {$Person[$split['zavkaf_login']]['chair_uid']} <br>";
      } 
    }
    
  }
  // такой кафедры нет, это может быть ГПХ, а может псевдокафедра. Возьмём кафедру от завкафа 
  else
  {
    // $Person[$split['zavkaf_login']]['chair_uid']

    $Result = $mysqli->query("UPDATE `zavkaf_splits` SET `zavkaf_chair_uid` = '{$Person[$split['zavkaf_login']]['chair_uid']}' WHERE `id` = $split[id]");

    if (!$Result)
    {
      echo $mysqli->error . '<br>';
    }
  }
}

?>