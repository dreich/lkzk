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
$_nagruzka_type = quote_smart($_GET['type']);
// Флаг только у УОУП, чтобы не грузить очень много данных
$_only_stat = $_GET['only_stat'];
// Флаг только у УОУП, чтобы не грузить очень много данных
$_lite = $_GET['lite'];
// получим режим работы системы
$ModeRow = GetRow('params', ['param' => 'system_mode']);
$_system_mode = $ModeRow['value'];

// УОУП просматривает нагрузку кафедры
if ($_GET['chair_id'])
{
  $_chair_id = quote_smart($_GET['chair_id']);
}

$_lecturer_uid = isset($_GET['lecturer_uid']) ? quote_smart($_GET['lecturer_uid']) : '';

if ($_lecturer_uid)
{
  $Lecturer = GetRow('xml_lecturer', ['UID' => $_lecturer_uid]);
  $_lecturer_fio = $Lecturer['FIO'];
}

if ($c_roles['zavkaf'])
{
  $c_chair_id = $_SESSION['c_chair_id'];
  $XMLChair = GetRow('xml_chair', ['Code' => $c_chair_id]);
  $chair_id_sql = "AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'";
}

if ($c_roles['uoup'] && $_GET['chair_id'])
{
  // $chair_id = quote_smart($_GET['chair_id']);
  $XMLChair = GetRow('xml_chair', ['Code' => $_chair_id]);
  $chair_id_sql = "AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'";
}

if ($c_roles['sotrudnik'])
{
  // $chairs_ids_arr = ExplodePalki($_SESSION['c_sotrudnik_chairs_ids']);
  // $chair_id = $chairs_ids_arr[0];
  // $chair_id = quote_smart($_GET['chair_id']);
  $XMLChair = GetRow('xml_chair', ['Code' => $_chair_id]);
  $chair_id_sql = "AND xml_content_of_load.UID_Chair = '$XMLChair[UID]'";

  if (!$_lecturer_uid)
  {
    echo json_encode(['nagruzka' => [], 'stat' => []]);
    exit;
  }
}

// if ($XMLChair)
// {
//   $_chair_name = $XMLChair['Name'];
// }

$global_nagruzka_filter = $_COOKIE['global_nagruzka_filter'];


// EchoLog($global_nagruzka_filter);

// if ($global_nagruzka_filter)
// {
//   if ($global_nagruzka_filter == 'assigned')
//   {
//     $global_nagruzka_filter_sql = "AND `lecturer_fio` <> '' AND `lecturer_fio` <> 'Вакансия' AND `lecturer_fio` IS NOT NULL";
//   }
//   elseif ($global_nagruzka_filter == 'not_assigned')
//   {
//     $global_nagruzka_filter_sql = "AND (`lecturer_fio` = '' OR `lecturer_fio` IS NULL)";
//   }
//   elseif ($global_nagruzka_filter == 'assigned_to_vancancy')
//   {
//     $global_nagruzka_filter_sql = "AND `lecturer_fio` = 'Вакансия'";
//   }
// }
  

// $XMLContentOfLoad = GetRows('xml_content_of_load', ['UID_Chair' => $XMLChair['UID']]);

// в режиме заполнения ИК-КСРО не будем брать из галактийных таблиц
if ($_system_mode == 'mode_filling')
{
  $ksro_sql = "AND `nagruzka_type` <> 'ksro'";
}

$dop_sql = "$chair_id_sql
            $global_nagruzka_filter_sql
            AND `chair_id` IS NOT NULL AND `valid` = '1'
            $ksro_sql
            #AND `status` NOT IN ('')
            #AND `base_uid` = '26589.281474976773927'
            # TMP
            #AND `UID_Discipline` = '26006.281474976725278'
            #ORDER BY `status`, ``
            #LIMIT 150
";

// if ($_lecturer_uid) {
//     $dop_sql .= " AND nagruzka.lecturer_uid = '$_lecturer_uid'";
// }


$nagruzka_query = GetNagruzkaBaseQuery($dop_sql, $_nagruzka_type ? $_nagruzka_type : 'all', false, $_lite ? true : false);

// EchoLog($nagruzka_query);

// $_Nagruzka = GetSQL($nagruzka_query);

// массив индексирован по base_uid2
$Nagruzka = PrepareNagruzka(GetSQL($nagruzka_query));

// EchoLog($Nagruzka);

$ZavkafSplits = GetTable('zavkaf_splits', "`delete` = '0'");

$ZavkafSplitsByBaseUID1ByBaseUID2New = [];
$ZavkafSplitsByBaseUID1ByBaseUID2 = [];
$ZavkafSplitsByBaseUID1ByBaseUID2NewSplitted = [];

if ($ZavkafSplits)
{
  foreach ($ZavkafSplits as $zs)
  {
    // $ZavkafSplitsByBaseUID1ByBaseUID2New[$zs['base_uid']][$zs['base_uid2_new']] = $zs;
    // в случае споточенности будет более одной строки, ниже будем брать первую (в content_of_load_uid_new)
    $ZavkafSplitsByBaseUID1ByBaseUID2New[$zs['base_uid']][$zs['base_uid2_new']][$zs['content_of_load_uid']][] = $zs;
    // $ZavkafSplitsByBaseUID1ByBaseUID2[$zs['base_uid']][$zs['base_uid2']][] = $zs; // -- with overrides
    $ZavkafSplitsByBaseUID1ByBaseUID2[$zs['base_uid']][$zs['base_uid2']][$zs['content_of_load_uid']][] = $zs; // -- with overrides
  }
}

// EchoLog($ZavkafSplitsByBaseUID1ByBaseUID2New['26589.281474976773565']);
// EchoLog($ZavkafSplitsByBaseUID1ByBaseUID2['26589.281474976773565']);


$NagruzkaByBaseUID1 = [];

if ($Nagruzka)
{
  // EchoLog($ZavkafSplitsByBaseUID1ByBaseUID2['26589.281474976764373']);

  // Формируем строки зелёной таблицы, с подстроками lectors для столбца Преподаватели (распределение нагрузки)
  foreach ($Nagruzka as $nagruzka)
  {
    // EchoLog($nagruzka);

    if (!$NagruzkaByBaseUID1[$nagruzka['base_uid']])
    {
      $NagruzkaByBaseUID1[$nagruzka['base_uid']] = $nagruzka;
    }
    else
    {
      $NagruzkaByBaseUID1[$nagruzka['base_uid']]['Amount'] += $nagruzka['Amount'];
    }

    // Данные распределения (разбивки нагрузки) от завкафа, ещё не интегрированные в Галактику
    if ($ZavkafSplitsByBaseUID1ByBaseUID2[$nagruzka['base_uid']])
    {
      // Если такое распределение (по base_uid2, возможно, из Галактики..), уже есть, то в этой строке перезапишем данные
      if ($ZavkafSplitsByBaseUID1ByBaseUID2[$nagruzka['base_uid']][$nagruzka['base_uid2']])
      {
        
        // $zavkaf_raspred_row = $ZavkafSplitsByBaseUID1ByBaseUID2New[$nagruzka['base_uid']][$nagruzka['base_uid2']];
        // если есть споточивание, то возьмётся одна строка (первая) - все лекторы по content_of_load_uid_new, например по 26589.281474976773929.1

        $zavkaf_raspred_rows = array_values($ZavkafSplitsByBaseUID1ByBaseUID2[$nagruzka['base_uid']][$nagruzka['base_uid2']])[0];

        // EchoLog($zavkaf_raspred_rows);
        
        if ($zavkaf_raspred_rows)
        {
          foreach ($zavkaf_raspred_rows as $zavkaf_raspred_row)
          {
            // $nagruzka['amount'] = $zavkaf_raspred_row['amount'];
            $nagruzka['lecturer_login'] = $zavkaf_raspred_row['lecturer_login'];
            $nagruzka['lecturer_person_id'] = $zavkaf_raspred_row['lecturer_person_id'];
            $nagruzka['lecturer_uid'] = $zavkaf_raspred_row['lecturer_uid'];
            $nagruzka['lecturer_fio'] = $zavkaf_raspred_row['lecturer_fio'];

            if (isset($zavkaf_raspred_row['LoadType']))
            $nagruzka['LoadType'] = $zavkaf_raspred_row['LoadType'];

            if (isset($zavkaf_raspred_row['StudentAmount']))
            $nagruzka['StudentAmount'] = $zavkaf_raspred_row['StudentAmount'];

            if (isset($zavkaf_raspred_row['Amount']))
            $nagruzka['Amount'] = $zavkaf_raspred_row['Amount'];
          
            $nagruzka['delete'] = $zavkaf_raspred_row['delete'];
            $nagruzka['zs'] = true;

            // $ZavkafSplitsByBaseUID1ByBaseUID2New[$nagruzka['base_uid']][$nagruzka['base_uid2']]['used'] = true;
            $ZavkafSplitsByBaseUID1ByBaseUID2NewSplitted[$nagruzka['base_uid']][$nagruzka['base_uid2']] = true;

            // if ($_lecturer_uid && $nagruzka['lecturer_uid'] === $_lecturer_uid || !$_lecturer_uid)
            {
              $NagruzkaByBaseUID1[$nagruzka['base_uid']]['lectors'][] = $nagruzka;

              // EchoLog($nagruzka);
            }

            if ($nagruzka['base_uid'] === '26589.281474976764368')
            {
              // EchoLog($nagruzka);
            }

            // EchoLog($nagruzka);

            // $ZavkafSplitsByBaseUID1ByBaseUID2New[$nagruzka['base_uid']][$nagruzka['base_uid2']]['zs'] = true;
          }

          continue;
        }
        
      }
    }

    // проверка на то, что нагрузка из Галактики без преподавателя вида 26589.281474976773929[._]
    // есть (распределена) в таблице zavkaf_splits по преподавателям, её добавлять не нужно, она "исходная"
    $base_uid2_obj = parseNagruzkaBaseUid2($nagruzka['base_uid2']);

    // EchoLog($base_uid2_obj);
    // EchoLog($nagruzka);

    // if (!$ZavkafSplitsByBaseUID1ByBaseUID2New[$nagruzka['base_uid']] && !$base_uid2_obj['lector_suffix'] 
    //     || $ZavkafSplitsByBaseUID1ByBaseUID2New[$nagruzka['base_uid']] && $base_uid2_obj['lector_suffix'] )

    if ( // !$ZavkafSplitsByBaseUID1ByBaseUID2[$nagruzka['base_uid']][$nagruzka['base_uid2']] || 
        // true ||
        // $ZavkafSplitsByBaseUID1ByBaseUID2New[$nagruzka['base_uid']][$nagruzka['base_uid2']]['used']
      !$ZavkafSplitsByBaseUID1ByBaseUID2NewSplitted[$nagruzka['base_uid']][$nagruzka['base_uid2']]
      )
    {
      // EchoLog('here');
      // $NagruzkaByBaseUID1[$nagruzka['base_uid']]['lectors'][$nagruzka['base_uid2']] = $nagruzka;

      // if ($_lecturer_uid && $nagruzka['lecturer_uid'] === $_lecturer_uid || !$_lecturer_uid)
      {
        $NagruzkaByBaseUID1[$nagruzka['base_uid']]['lectors'][] = $nagruzka;
      }

      if ($nagruzka['base_uid'] === '26589.281474976764368')
      {
        // EchoLog($nagruzka);
      }
      
    }
  }

  // EchoLog($ZavkafSplitsByBaseUID1ByBaseUID2New);
  /*
  foreach ($ZavkafSplitsByBaseUID1ByBaseUID2New as $base_uid => $zs_array)
  {
    // EchoLog($base_uid);
    // EchoLog($zs_array);

    foreach ($zs_array as $zs)
    {
      // if (!$zs['used'] && 
      if ($NagruzkaByBaseUID1[$base_uid])
      {
        $zs['zs'] = true;
        // $NagruzkaByBaseUID1[$base_uid]['lectors'][$zs['base_uid2_new']] = $zs;
        $NagruzkaByBaseUID1[$base_uid]['lectors'][] = $zs;

        EchoLog($zs);
      }
    }
  }
  */
}

$Stat = [];
$StatByChair = [];


if ($NagruzkaByBaseUID1)
{
  foreach ($NagruzkaByBaseUID1 as $base_uid => $lectors_arr)
  {
    // EchoLog($base_uid);

    if ($NagruzkaByBaseUID1[$base_uid]['lectors'])
    {
      // Нельзя фильтровать Галактийную нагрузку по лекторам на этом этапе, потому что ещё будут применяться сплиты
      // Filter lectors to only include those with matching lecturer_uid
      // if ($_lecturer_uid)
      // {
      //   $NagruzkaByBaseUID1[$base_uid]['lectors'] = array_filter($NagruzkaByBaseUID1[$base_uid]['lectors'], function($lector) use ($_lecturer_uid) {
      //       return $lector['lecturer_uid'] === $_lecturer_uid;
      //   });
      // }

      $NagruzkaByBaseUID1[$base_uid]['lectors'] = array_values($NagruzkaByBaseUID1[$base_uid]['lectors']);

      $NagruzkaByBaseUID1[$base_uid]['assigned'] = false;
      $NagruzkaByBaseUID1[$base_uid]['assigned_to_vacancy'] = false;
      $NagruzkaByBaseUID1[$base_uid]['not_assigned'] = false;

      foreach ($NagruzkaByBaseUID1[$base_uid]['lectors'] as &$lector)
      {
        // EchoLog($lector);
        $lector['delete'] = !!$lector['delete'];

        if ($lector['delete']) continue;

        if (!$lector['chair_id'])
        {
          // EchoLog($lector['chair_id']);
        }

        // Вакансия
        if ($lector['lecturer_fio'] && mb_strcasecmp($lector['lecturer_fio'], 'Вакансия') === 0)
        {
          $NagruzkaByBaseUID1[$base_uid]['assigned_to_vacancy'] = true;
          $Stat['assigned_to_vacancy']['sum'] += (float) $lector['Amount'];
          $StatByChair[$lector['chair_id']]['assigned_to_vacancy']['sum'] += (float) $lector['Amount'];

          // $NagruzkaByBaseUID1[$base_uid]['assigned'] = true;
          // $Stat['assigned']['sum'] += $lector['Amount'];

          // $NagruzkaByBaseUID1[$base_uid]['not_assigned'] = false;
        }
        // Не вакансия, а лектор
        elseif (($_lecturer_uid && $lector['lecturer_uid'] === $_lecturer_uid || !$_lecturer_uid && $lector['lecturer_fio']) && mb_strcasecmp($lector['lecturer_fio'], 'Вакансия') != 0)
        {
          $NagruzkaByBaseUID1[$base_uid]['assigned'] = true;
          $Stat['assigned']['sum'] += (float) $lector['Amount'];
          $StatByChair[$lector['chair_id']]['assigned']['sum'] += (float) $lector['Amount'];

          // $NagruzkaByBaseUID1[$base_uid]['not_assigned'] = false;
        }
        // пустой лектор - не распределено
        elseif (!$lector['lecturer_fio'])
        {
          $Stat['not_assigned']['sum'] += (float) $lector['Amount'];
          $StatByChair[$lector['chair_id']]['not_assigned']['sum'] += (float) $lector['Amount'];

          $NagruzkaByBaseUID1[$base_uid]['not_assigned'] = true;
        }
      }

      // if ($NagruzkaByBaseUID1[$base_uid]['not_assigned'])
      // {
      //   $Stat['not_assigned']['sum'] += $NagruzkaByBaseUID1[$base_uid]['Amount'];
      // }

      $Stat['total']['sum'] += $NagruzkaByBaseUID1[$base_uid]['Amount'];
      $StatByChair[$NagruzkaByBaseUID1[$base_uid]['chair_id']]['total']['sum'] += $NagruzkaByBaseUID1[$base_uid]['Amount'];

      // if ($_chair_id)
      // {
      //   $_chair_name = $Stat['chair_name'] = $NagruzkaByBaseUID1[$base_uid]['chair_name'];
      // }
      // $StatByChair[$NagruzkaByBaseUID1[$base_uid]['chair_id']]['chair_name'] = $NagruzkaByBaseUID1[$base_uid]['chair_name'];

      if (!$NagruzkaByBaseUID1[$base_uid]['chair_id'])
      {
        // EchoLog($NagruzkaByBaseUID1[$base_uid]);
      }
    }
    else
    {
      $NagruzkaByBaseUID1[$base_uid]['lectors'] = [];
    }
  }
}

// В режиме заполнения в группировку по кафедрам для #/uoup_nagruzka нужно взять данные из таблицы ksro 
// ! Т.к. в таблице ksro нет таких полей как название кафедры, факультета, то отображение этих данных в зелёной таблице у УОУП полагается на другие виды нагрузки в $NagruzkaByBaseUID1. Т.е. если не будет других видов нагрузки, то КСРО не будет отображаться в таблице; 
if ($_system_mode == 'mode_filling' && $_lite)
{
  $Rows = GetTable('ksro', "$__chair_sql $__lecturer_uid_sql");

  // EchoLog($Rows);

  if ($Rows)
  {
    unset($row);
    foreach ($Rows as $row)
    {
      $StatByChair[$row['chair_id']]['assigned']['sum'] += (float)$row['Amount'];
      $StatByChair[$row['chair_id']]['total']['sum'] += (float)$row['Amount'];
    }
  }
}

// Filter NagruzkaByBaseUID1 based on lecturer_uid if provided
if ($_lecturer_uid) 
{
  foreach ($NagruzkaByBaseUID1 as $base_uid => &$nagruzka) 
  {
    // Filter lectors to only include those with matching lecturer_uid
    $filtered_lectors = array_filter($nagruzka['lectors'], function($lector) use ($_lecturer_uid) 
    {
      return $lector['lecturer_uid'] === $_lecturer_uid;
    });

    // $filtered_lectors = $nagruzka['lectors'];
    
    // If no matching lectors, remove this entry
    if (empty($filtered_lectors)) 
    {
      unset($NagruzkaByBaseUID1[$base_uid]);
    }
  }

  unset($nagruzka); // Break the reference
}


if ($global_nagruzka_filter)
{
  $NagruzkaByBaseUID1 = array_filter($NagruzkaByBaseUID1,
    function($nagruzka_row)
    {
      global $global_nagruzka_filter;
      return $nagruzka_row[$global_nagruzka_filter];
    }
  );


  // if ($global_nagruzka_filter == 'assigned')
  // {
  //   $global_nagruzka_filter_sql = "AND `lecturer_fio` <> '' AND `lecturer_fio` <> 'Вакансия' AND `lecturer_fio` IS NOT NULL";
  // }
  // elseif ($global_nagruzka_filter == 'not_assigned')
  // {
  //   $global_nagruzka_filter_sql = "AND (`lecturer_fio` = '' OR `lecturer_fio` IS NULL)";
  // }
  // elseif ($global_nagruzka_filter == 'assigned_to_vancancy')
  // {
  //   $global_nagruzka_filter_sql = "AND `lecturer_fio` = 'Вакансия'";
  // }
}

// Для админа УОУП нужно данные сгруппировать по кафедрам

$NagruzkaByChair = [];

// просуммируем данные по кафедрам
if ($NagruzkaByBaseUID1)
{
  foreach ($NagruzkaByBaseUID1 as $row)
  {
    if (!$NagruzkaByChair[$row['chair_id']])
    {
      $row['assigned_to_vacancy'] = $StatByChair[$row['chair_id']]['assigned_to_vacancy']['sum'];
      $row['assigned'] = $StatByChair[$row['chair_id']]['assigned']['sum'];
      $row['not_assigned'] = $StatByChair[$row['chair_id']]['not_assigned']['sum'];
      $row['total'] = $StatByChair[$row['chair_id']]['total']['sum'];

      // $row[''] = $StatByChair[$row['chair_id']]['assigned_to_vacancy']['sum'];

      $NagruzkaByChair[$row['chair_id']] = $row;

      
      // $StatByChair[$row['chair_id']]['assigned']['sum']
      // $StatByChair[$row['chair_id']]['not_assigned']['sum']
      // $StatByChair[$row['chair_id']]['total']['sum']

      // $NagruzkaByChair[$row['chair_id']]['amount_sum'] = $row['Amount'];
      // $NagruzkaByChair[$row['chair_id']]['on_vacancy_num'] = $NagruzkaByChair[$row['chair_id']]['assigned_num'] = $NagruzkaByChair[$row['chair_id']]['not_assigned_num'] = 0;


    }
    else
    {
      // не исп. ?
      $NagruzkaByChair[$row['chair_id']]['amount_sum'] += $row['Amount'];
    }

    // На вакансии
    // if (mb_strcasecmp('Вакансия', $row['lecturer_fio']) == 0)
    // {
    //   $NagruzkaByChair[$row['chair_id']]['on_vacancy_num'] += $row['Amount'];
    // }
    // else
    // {
    //   // Выбран преподаватель
    //   if ($row['lecturer_uid'])
    //   {
    //     $NagruzkaByChair[$row['chair_id']]['assigned_num'] += $row['Amount'];
    //   }
    //   // Ничего не выбрано
    //   else
    //   {
    //     $NagruzkaByChair[$row['chair_id']]['not_assigned_num'] += $row['Amount'];
    //   }
    // }
  }
}

// EchoLog($Stat);

// #/uoup_nagruzka
if ($c_roles['uoup'] && ($_only_stat || $_lite))
{
  $ReturnNagruzka = $NagruzkaByChair;
}
// #/nagruzka
else
{
  $ReturnNagruzka = $NagruzkaByBaseUID1;
}

// для скорости, где достаточно только статистики
if ($_only_stat) $ReturnNagruzka = [];

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/javascript; charset=UTF-8');

$ret_arr = ['nagruzka' => array_values($ReturnNagruzka), 'stat' => $Stat ? $Stat : new stdClass, 'lecturer_fio' => $_lecturer_fio];

echo json_encode($ret_arr);


?>