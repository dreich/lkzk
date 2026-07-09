<?php

include '../functions.php';

// print_r($_SERVER);
// exit;

// Получим режим работы системы из БД
$_system_mode = GetSystemParam('system_mode');

$LOAD_NEW_DATA_FROM_NETWORK = true;
$UPDATE_TABLES = true;  // для проверки изменения хешей это должно быть включено

$XMLGroupByName = GetTable('xml_group', "", "", "Name", "Name, UID");

// print_r ($XMLGroupByName['3525А1ЭКмж_и1']);

include '../connect/opop2.php';

EchoLog("Start cron");


$Napravlenia = GetTable('napravlenia', "", "", "napravlenie");


$BUPs = GetTable('bup', "", "", "nrec", "`nrec`, `reg_number`, `students_num`, `department`, `year`, `end_date`, `language`"); // reg_number

$BUPDisciplines = GetTable('bup_disciplines', "`exam_semester` IS NOT NULL AND `exam_semester` <> ''", "", "", "`nrec`, `disc_nrec`, `abr`, `title`, `exam_semester`");

// -- Объяснение сортировки: Должна получиться одна группа, если вдруг получилось несколько, то взять одну (для однозначности отсортировать сначала где больше студентов, если одинаково то по имени). [ЛК ЗК (аспирантура).docx]

// $BUPGroups = GetTable('bup_groups', "`students_in_group` <> '' AND `students_in_group` <> '0'", "`students_in_group` ASC, `group` DESC", "reg_number");

$BUPGroups = GetTable('bup_groups', "`students_in_group` <> '' AND `students_in_group` <> '0'");
// до нескольких групп на bup reg_number
$BUPGroupsByRegNum = [];

if ($BUPGroups)
{
  foreach ($BUPGroups as $bup_group)
  {
    $group_uid = $XMLGroupByName[$bup_group['group']]['UID'];
    $BUPGroupsByRegNum[$bup_group['reg_number']][] = ['uid' => $group_uid, 'name' => $bup_group['group']];

    if ($bup_group['group'] == '3525А1ЭКмж_и1')
    {
      // print_r(['uid' => $group_uid, 'name' => $bup_group['group']]);
    }
  }
}

// $BUPGroups = [];

include '../connect.php';

if ($UPDATE_TABLES) //  && $_system_mode == 'mode_filling')
{
  fullBackupTable('nagruzka', 10);
  fullBackupTable('zavkaf_splits', 10);
  fullBackupTable('ksro', 10);
  fullBackupTable('aspirantura_kand_exam', 10);
  fullBackupTable('aspirantura_ruk_asp', 10);
  fullBackupTable('aspirantura_ruk_soisk', 10);
}

echo sizeof($BUPDisciplines);

if ($BUPDisciplines)
{
  // т.к. мы не знаем, покроем ли все существующие в aspirantura_kand_exam строки (может быть дисциплина исчезла), то проставим всем строкам deleted = 1
  $mysqli->query("UPDATE `aspirantura_kand_exam` SET `deleted` = '1'");

  foreach ($BUPDisciplines as $bup_discipline)
  {
    $bup = $BUPs[$bup_discipline['nrec']];

    // строки должны удовлетворять год БУП + int((semestr - 1)/2) == текущий год
    if ($bup['year'] + floor(($bup_discipline['exam_semester'] - 1) / 2) != date('Y'))
    {
      $bad_bup = true;
    }
    else
    {
      $bad_bup = false;
    }

    // если в БУПе 0 студентов, пометим эту строку как удалённую (либо удалить...)
    if (!$bup['students_num'] || $bad_bup)
    {
      $Result = $mysqli->query("
        UPDATE `aspirantura_kand_exam` 
        SET `deleted` = '1', `date_update` = NOW()
        WHERE `bup_nrec` = '$bup_discipline[nrec]' AND `disc_nrec` = '$bup_discipline[disc_nrec]' AND `disc_abr` = '$bup_discipline[abr]'");

      if (!$Result)
      {
        EchoLog("Error #739 in cron: " . $mysqli->error);
      }
    }
    else
    {
      // т.к. в БУПе студентов не 0, то здесь должна быть не пустая группа
      if (!$BUPGroups[$bup['reg_number']]['group'])
      {
        // EchoLog("Проблема цельности данных в Галактике: В БУПе $bup[reg_number] студентов $bup[students_num], а группу со студентами не нашли в bup_groups");
      }

      // Если строка уже есть, обновлять ли какие-то столбцы? Например, кол-во студентов, группу?

      // $Result = $mysqli->query("INSERT IGNORE INTO `aspirantura_kand_exam` 
      //                 SET `deleted` = '0', `bup_nrec` = '$bup_discipline[nrec]', `disc_nrec` = '$bup_discipline[disc_nrec]', `disc_abr` = '$bup_discipline[abr]', `disc_title` = '$bup_discipline[title]', `exam_semester` = '$bup_discipline[exam_semester]', `group` = '{$BUPGroups[$bup['reg_number']]['group']}', `students_num` = '{$BUPGroups[$bup['reg_number']]['students_in_group']}'");

      // ! берём первую, если несколько
      $group_name = $BUPGroupsByRegNum[$bup['reg_number']][0]['name']; //  ? JoinArrayElements($BUPGroupsByRegNum[$bup['reg_number']][0]['name']) : '';
      $group_uid = $BUPGroupsByRegNum[$bup['reg_number']][0]['uid']; // ? JoinArrayElements($BUPGroupsByRegNum[$bup['reg_number']][0]['uid']) : '';

      // EchoLog($BUPGroupsByRegNum[$bup['reg_number']][0]);
      // EchoLog($group_uid);

      // Т.к. в таблице может быть несколько строк по ключу с преподавателями, мы не можем использовать уникальный ключ,
      // поэтому.. проверим, есть ли строка по ключу
      $RowExists = GetRow('aspirantura_kand_exam', ['bup_nrec' => $bup_discipline['nrec'], 'disc_nrec' => $bup_discipline['disc_nrec'], 'disc_abr' => $bup_discipline['abr']]);

      // если строка(и) по ключу есть, они обновят некоторые поля
      if ($RowExists)
      {
        $Result = $mysqli->query(
          "UPDATE `aspirantura_kand_exam` SET `deleted` = '0', `exam_semester` = '$bup_discipline[exam_semester]', `students_num` = '$bup[students_num]', `groups` = '$group_name', `groups_uid` = '$group_uid', `bup_language` = '$bup[language]'
           WHERE `bup_nrec` = '$bup_discipline[nrec]' AND `disc_nrec` = '$bup_discipline[disc_nrec]' AND `disc_abr` = '$bup_discipline[abr]'");
      }
      else
      {
        $load_id = uniq(16);

        $Result = $mysqli->query("INSERT INTO `aspirantura_kand_exam` 
        SET `load_id` = '$load_id',
            `deleted` = '0', 
            `bup_department_name` = '$bup[department]',
            `bup_nrec` = '$bup_discipline[nrec]', 
            `bup_language` = '$bup[language]',
            `disc_nrec` = '$bup_discipline[disc_nrec]', 
            `disc_abr` = '$bup_discipline[abr]', 
            `disc_title` = '$bup_discipline[title]', 
            `exam_semester` = '$bup_discipline[exam_semester]', 
            `groups` = '$group_name', 
            `groups_uid` = '$group_uid',
            `students_num` = '$bup[students_num]',
            `date` = NOW()");
      }

      /*
      $Result = $mysqli->query("INSERT INTO `aspirantura_kand_exam` 
      SET `deleted` = '0', 
          `bup_nrec` = '$bup_discipline[nrec]', 
          `disc_nrec` = '$bup_discipline[disc_nrec]', 
          `disc_abr` = '$bup_discipline[abr]', 
          `disc_title` = '$bup_discipline[title]', 
          `exam_semester` = '$bup_discipline[exam_semester]', 
          `group` = '{$BUPGroups[$bup['reg_number']]['group']}', 
          `students_num` = '$bup[students_num]',
          `date` = NOW()
      ON DUPLICATE KEY UPDATE 
          `deleted` = '0',
          `exam_semester` = VALUES(`exam_semester`),
          `group` = VALUES(`group`),
          `students_num` = VALUES(`students_num`),
          `date_update` = NOW()
      ");
      */

      if (!$Result)
      {
        EchoLog("Error #928 in cron: " . $mysqli->error);
      }
    }
  }
}

// exit;

if ($_system_mode == 'mode_filling')
{
  include '../connect/vkr.php';

  $Aspirants = GetRows('students_ip', ['education_level' => 'Аспирант', 'status' => 'Учится']);

  include '../connect.php';
  // echo sizeof($Aspirants);


  if ($Aspirants)
  {
    // Т.к. аспирант мог перестать учиться, (и не попадёт в массив) то проставим всем строкам deleted = 1
    $mysqli->query("UPDATE `aspirantura_ruk_asp` SET `deleted` = '1', `date_update` = NOW()");

    foreach ($Aspirants as $aspirant)
    {
      // EchoLog($aspirant['group']);
      $bup = $BUPs[$aspirant['bup_nrec']];

      // дата окончания в БУПе студента должна быть больше 1 октября текущего года
      $october1 = date('Y') . "-10-01";

      if ($bup['end_date'] >= $october1)
      {
        $load_id = uniq(16);
        $semester = 1;
        $query = GetAspRukAspQuery($aspirant, $semester, $load_id);
        $Result = $mysqli->query($query);

        $load_id = uniq(16);
        $semester = 2;
        $query = GetAspRukAspQuery($aspirant, $semester, $load_id);
        $Result = $mysqli->query($query);
      }
      // других нужно "удалить"
      else
      {
        $Result = $mysqli->query("UPDATE `aspirantura_ruk_asp` SET `deleted` = '1' WHERE `uid` = '$aspirant[uid]'");
      }
      
      if (!$Result)
      {
        EchoLog("Error #583 in cron: " . $mysqli->error);
      }
    }
  }
}

// exit;



// Временный режим выгрузки в Галактику
// Если в этом режиме ещё не стоит параметр may_set_mode_verification, то запуск этого скрипта дополнительно проставляет base_uid для нагрузки вида КСРО и Аспирантура (хотя это делает всегда)
// Если в этом режиме уже поставлен параметр may_set_mode_verification, значит, "ручная" завкафовская нагрузка КСРО и Аспирантура выгружена в Галактику, и её можно очищать, переводить систему в следующий режим - mode_verification (Выверка)
if ($_system_mode == 'export_to_galaktika')
{
  if (GetSystemParam('may_set_mode_verification') == '1')
  {
    // Бэкапы и так делаются в начале скрипта всегда
    // $r1 = fullBackupTable('zavkaf_splits');
    // $r2 = fullBackupTable('ksro');

    // if ($r1 && $r2)
    // {
      $Result = $mysqli->query("DELETE FROM `zavkaf_splits`");
      // $Result = $mysqli->query("DELETE FROM `ksro`");
      // $Result = $mysqli->query("DELETE FROM `aspirantura_kand_exam`");
      // $Result = $mysqli->query("DELETE FROM `aspirantura_ruk_asp`");
      // $Result = $mysqli->query("DELETE FROM `aspirantura_ruk_soisk`");
    // }

    SaveSystemParam('may_set_mode_verification', '');
    SaveSystemParam('system_mode', 'mode_verification');

    $_system_mode = 'mode_verification';
  }
}

// при каждой синхронизации в этом режиме в начале очищаются все сплиты в ЛК ЗК (если появились)
if ($_system_mode == 'mode_verification')
{
  EchoLog("Режим выверка: очищаем все сплиты");
  // Бэкапы и так делаются в начале скрипта всегда
  // $r1 = fullBackupTable('zavkaf_splits');
  // $r2 = fullBackupTable('ksro');

  // if ($r1 && $r2)
  {
    $Result = $mysqli->query("DELETE FROM `zavkaf_splits`");
    // $Result = $mysqli->query("DELETE FROM `ksro`");
    // $Result = $mysqli->query("DELETE FROM `aspirantura_kand_exam`");
    // $Result = $mysqli->query("DELETE FROM `aspirantura_ruk_asp`");
    // $Result = $mysqli->query("DELETE FROM `aspirantura_ruk_soisk`");
  }
}

// exit;

// Столбцы, используемые для создания кеша, чтобы выявлять изменения в строках при обновлении из XML
// 'UID' - если юид сменился, то могли спотчить или распоточить (изменение суффикса)
$xml_content_of_load_columns_for_hash = ['YearOfEducation', 'DateFrom', 'DateTo', 'Amount', 'AmountInUnit', 'TypeOfContingent', 'UID_Group', 'UID_SubGroup', 'UID_Stream', 'UID_KindOfWork', 'PackageNumber', 'ID_Auditorium', 'UID_Discipline', 'UID_Chair', 'UID_Semester', 'Module', 'TypeWorkload', 'UID_Course', 'DisciplineTypeLoad', 'LoadType', 'StudentAmount'];

$xml_content_of_load_staff_columns_for_hash = ['TypeOfContingent', 'UID_Group', 'UID_SubGroup', 'Abbr', 'UID_FormOfEducation', 'UID_Speciality', 'UID_Specialization', 'UID_Language', 'UID_FacultyOwner', 'UID_FacultyPerformer'];



// $hash = hash_column_values_only($data, $xml_content_of_load_staff_columns_for_hash);

// Получим данные по псевдо-кафедрам
$pseudo_departments_ids = [];
$pseudo_departments_uids = [];



if ($_pseudo_chairs)
{
  $pseudo_chairs_str = JoinArrayElements(array_keys($_pseudo_chairs), ', ', false, "'", "'");
  // EchoLog($pseudo_chairs_str);

  $PseudoChairs = GetTable("xml_chair", "`code` IN($pseudo_chairs_str)");

  if ($PseudoChairs)
  {
    foreach ($PseudoChairs as $pseudo_chair)
    {
      $Faculty = GetRow('xml_faculty', ['UID' => $pseudo_chair['UID_Faculty']]);

      $pseudo_departments_ids[] = $Faculty['Code'];
      $pseudo_departments_uids[] = $Faculty['UID'];
    }
  }
}

// EchoLog($pseudo_departments_ids);

// нагрузка до обновления
$XMLContentOfLoadPrev = GetTable('xml_content_of_load', "", "", "UID", "UID, base_uid, base_uid2, hash, UID_Chair");

$XMLContentOfLoadPrevByBaseUID2 = [];

if ($XMLContentOfLoadPrev)
{
  foreach ($XMLContentOfLoadPrev as $row)
  {
    $XMLContentOfLoadPrevByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
  }
}

$_XMLContentOfLoadStaffPrev = GetTable('xml_content_of_load_staff', "", "", null, "UID, base_uid2, UID_ContentOfLoad, hash");

// нагрузка стафф до обновления
$XMLContentOfLoadStaffPrevByBaseUID2 = [];

foreach ($_XMLContentOfLoadStaffPrev as $row)
{
  // $XMLContentOfLoadStaffPrev[$row['UID_ContentOfLoad']][$row['UID']] = $row;
  $XMLContentOfLoadStaffPrevByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
}

unset($_XMLContentOfLoadStaffPrev);

$NagruzkaPrev = GetTable('nagruzka', "", "", "load_base_UID2");

// EchoLog($NagruzkaPrev['26589.281474976773927']);
// без этой строки непонятный баг: ошибочно проваливаемся в if (!$NagruzkaPrev[$nagr['base_uid']])
echo sizeof($NagruzkaPrev);

// $NagruzkaPrev = [];

// foreach ($_Nagruzka as $row)
// {
//   $NagruzkaPrev["$row[chair_id]-$row[load_base_UID]"] = $row;
// }

// unset($_Nagruzka);


// требует подключения к Сотруднику
// $pseudo_departments_ids - дополнительно будем брать сотрудников из родительских подразделений псевдо-кафедр
function GetChairSotrudniki($year, $dop_sql = "", $actual = null /*, $qualify_category_not_empty = false */)
{
  global $pseudo_departments_ids;

  $position_table_name = "position$year";
  $podrazdelenia_table_name = "podrazdelenia$year";

  // if ($qualify_category_not_empty)
  // {
  //   $qualify_category_not_empty_sql = "AND $position_table_name.`qualify_category` <> ''";
  // }

  if ($actual != null)
  {
    $actual_sql = "AND `actual` = '$actual'";
  }

  if ($year >= 2025)
  {
    $pkg_sql = ", $position_table_name.`pkg`, $position_table_name.`pku`";
  }

  // Правильно это или нет, но как будто ГПХ-шники ищутся по всем подразделениям,
  // пока это так, искать по всевдокафедрам будем в другой ветке
  // Ищем ГПХ-шников
  if (mb_stripos($dop_sql, 'ГПХ') !== false)
  {
    $kaf_sql = "";
  }
  else
  // не ГПХ-шники
  {
    if ($pseudo_departments_ids)
    {
      $pseudo_departments_ids_str = JoinArrayElements($pseudo_departments_ids, ", ", false, "'", "'");
      $pseudo_departments_ids_sql = "OR $podrazdelenia_table_name.`id` IN ($pseudo_departments_ids_str)";
    }
    else
    {
      $pseudo_departments_ids_sql = '';
    }

    // если нет всевдо-подразделений, ничего страшного
    // #dup code functions.php Aurhorize()
    $kaf_sql = "AND (($podrazdelenia_table_name.`pname` LIKE('Кафедра%') OR $podrazdelenia_table_name.`pname` LIKE('%базовая кафедра%'))
                OR `$position_table_name`.dolzhnost IN('декан факультета', 'директор института', 'директор филиала') $pseudo_departments_ids_sql ) ";
  }

  // AND $podrazdelenia_table_name.`parent_id` <> '00255'
  $query = "
              SELECT person.`id` as person_id, person.`surname`, person.`name`, person.`patronymic`, $position_table_name.`dolzhnost`, `$position_table_name`.podrazdelenie_id, `$position_table_name`.ukrup_code as department_id, $position_table_name.`podrazdelenia_chain`, $podrazdelenia_table_name.`id` as chair_id, $position_table_name.`position_category`, $position_table_name.`type`, $position_table_name.`qualify_category`, $position_table_name.`stavka`
              $pkg_sql
              FROM `$position_table_name`
              JOIN `person` ON `$position_table_name`.person_id = `person`.id
              JOIN `$podrazdelenia_table_name` ON `$position_table_name`.podrazdelenia_chain LIKE CONCAT('%|', $podrazdelenia_table_name.`id`, '|%')
              WHERE $podrazdelenia_table_name.`id` <> '00255' AND $podrazdelenia_table_name.`parent_id` <> ''
              AND $podrazdelenia_table_name.`ukrup_code` <> '03037' # УВЦ
              $kaf_sql
              AND `position_category` = 'ППС'
              $actual_sql
              $dop_sql
              # ВЗЛ - низший приоритет
              ORDER BY $position_table_name.type DESC, $position_table_name.contract_end_date DESC #более свежая позиция
            ";

  // if (mb_stripos($dop_sql, 'ГПХ') !== false)
  // EchoLog($query);

  return GetSQL($query);
}

$cur_year = date('Y');
include '../connect/sotrudnik.php';
$Podrazdelenia = GetTable("podrazdelenia$cur_year", "", "", "id");
$Person = GetTable('person', '', '', 'id', 'id, alias');

// print_r($Podrazdelenia);

// т.к. сначала загружаем 2ю таблицу и пропускаем в ней строки не ВО, а признак не ВО определяется именно по 2й,
// то нужно сохранить пропущенные base_uid, чтобы не грузить их и в 1ю таблицу
$ContentOfLoadStaffBaseUID1sNotVo = [];


function LoadXML($filename, $table_name)
{
  global $mysqli, $Napravlenia, $xml_content_of_load_columns_for_hash, $xml_content_of_load_staff_columns_for_hash, $XMLKindOfWorkGIA1, $XMLKindOfWorkGIA2, $XMLKindOfWorkVKR, $XMLKindOfWorkKurs, $_XMLContentOfLoadStaffByBaseUID1, $XMLSpeciality, $ContentOfLoadStaffBaseUID1sNotVo, $db_error, $_ksro_kind_uid, $_ksro_discipline_uid, $_ik_kind_uid, $_ik_discipline_uid, $_aspirantura_kand_exam_kind_uid, $_aspirant_nagruzka_itog_examen_kind_uids, $_aspirant_nagruzka_itog_examen_discipline1, $_aspirant_ruk_asp_kind_uid, $_aspirant_ruk_soisk_kind_uid, $XMLLecturer, $XMLDiscipline, $XMLKindOfWorkForDisciplineSection;



  EchoLog("LoadXML: $table_name", 'file screen');

  if ($table_name === 'xml_content_of_load' || $table_name === 'xml_content_of_load_staff')
  {
       gc_collect_cycles();
  }

  $XML = loadXMLSafe($filename);

  if ($XML === false) 
  {
    // Обработка ошибки
    $db_error = true;
    EchoLog("LoadXML($filename) read XML error", 'file screen');
  }

  $Result = $mysqli->query("DELETE FROM `$table_name`");

  if (!$Result)
  {
    EchoLog($mysqli->error);
    $db_error = true;
  }

  if ($table_name === 'xml_content_of_load')
  {
    // EchoLog("DELETE FROM `$table_name` - удалено " . $mysqli->affected_rows . " строк");
    
    // Проверяем, что таблица действительно пуста
    // $result = $mysqli->query("SELECT COUNT(*) as cnt FROM `xml_content_of_load`");
    // $count = $result->fetch_assoc()['cnt'];
    // EchoLog("После DELETE в таблице $table_name: $count строк");

    // $TestRow = GetRow('xml_content_of_load', ['UID' => '26589.281474976799017']);

    // EchoLog($TestRow);
    
    // Временно коммитим для проверки состояния
    // $mysqli->query("COMMIT");
    // EchoLog("COMMIT для проверки, таблица очищена");
    
    // Для продолжения отладки можно остановиться или продолжить
    // exit; // если нужно остановиться полностью
    
    // Или продолжить выполнение, но начать новую транзакцию
    // $mysqli->query("START TRANSACTION");
    // EchoLog("Начинаем новую транзакцию для загрузки данных");
  }

  // if ($table_name === 'xml_content_of_load') EchoLog("HERE 3");

  foreach ($XML->Data->Collection->Object as $s)
  {
    $obj = $s->Collection;
    $arr = [];
    $sql_arr = [];

    foreach ($obj->prop_value as $prop)
    {
      $attrs = $prop->attributes();

      // var_dump($attrs);
      $prop = (string) $attrs['prop_name'];
      $value = quote_smart((string) $attrs['value']);

      if ($table_name == 'xml_lecturer' && $prop == 'FIO')
      {
        $value = str_replace('!_Вакансия_!', 'Вакансия', $value);
      }

      $arr[$prop] = $value;

      // echo $prop;
      // echo $value;

      $sql_arr[] = "`$prop` = '$value'";
    }


    if ($table_name == 'xml_lecturer')
    {
      // не будем грузить строки с пустой должностью
      if ($arr['UID_Post'] === '25031.0')
      {
        continue;
      }
    }

    // подцепим уровень образования из ОПОП-2
    if ($table_name == 'xml_speciality')
    {
      $sql_arr[] = "`education_level` = '{$Napravlenia[$arr['Code']]['education_level']}'";
    }

    if ($table_name === 'xml_content_of_load')
    {
      $base_uid = get_base_uid1($arr['UID']);
      // EchoLog($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']);
      // EchoLog($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']);

      if ($arr['UID'] === '26589.281474976799017')
      {
        // EchoLog("HERE 555");
        // EchoLog($arr);
        // EchoLog($_XMLContentOfLoadStaffByBaseUID1[$base_uid]);
        // EchoLog($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]);
        // EchoLog(IsEducationLevelVO($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']));
      }

      // Название дисциплины начинается с Секция
      // У секций нет данных во 2-й таблице, это учитываем, поэтому нельзя проверить уровень образования
      if (preg_match('/^Секция/u', $XMLDiscipline[$arr['UID_Discipline']]['Name']) && $XMLKindOfWorkForDisciplineSection[$arr['UID_KindOfWork']])
      {
        $discipline_section = true;
      }
      else
      {
        $discipline_section = false;
      }

      // Проверим уровень образования, будем загружать только ВО
      // пропускаем, пропустим остальное
      if (($_XMLContentOfLoadStaffByBaseUID1[$base_uid] && $XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level'] && !IsEducationLevelVO($XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']) || $ContentOfLoadStaffBaseUID1sNotVo[$base_uid]) && !$discipline_section)
      {
        // if ($arr['UID'] === '26589.281474976765788')
        {
          // EchoLog("$arr[UID] $base_uid - {$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']} - {$XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['UID']} - {$XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']} НЕ ЗАГРУЖАЕМ", "file screen");
        }
        continue;
      }
      
      $sql_arr[] = "`base_uid` = '$base_uid'";

      $base_uid2 = get_base_uid2($arr['UID']);
      $sql_arr[] = "`base_uid2` = '$base_uid2'";

      $hash = hash_column_values_only($arr, $xml_content_of_load_columns_for_hash);
      $sql_arr[] = "`hash` = '$hash'";

      if ($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr'] == '2.2.2')
      {
        // EchoLog(IsNagruzkaDiscipline($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr']));
      }

      // КСРО. Проставим base_uid.
      if ($arr['UID_KindOfWork'] === $_ksro_kind_uid || $arr['UID_KindOfWork'] === $_ik_kind_uid || $arr['UID_Discipline'] === $_ksro_discipline_uid ||  $arr['UID_Discipline'] === $_ik_discipline_uid)
      {
        $sql_arr[] = "`nagruzka_type` = 'ksro'";

        // Проставим в КСРО base_uid, пришедший из Галактики
        if ($arr['LoadId'] && $base_uid)
        {
          $mysqli->query("UPDATE `ksro` SET `base_uid` = '$base_uid' WHERE `load_id` = '$arr[LoadId]'");
        }

      }
      // Аспирантура - кандидатские экзамены. Проставим base_uid.
      else if ($arr['UID_KindOfWork'] === $_aspirantura_kand_exam_kind_uid)
      {
        $sql_arr[] = "`nagruzka_type` = 'aspirantura_kand_exam'";

        // Проставим base_uid, пришедший из Галактики
        if ($arr['LoadId'] && $base_uid)
        {
          $mysqli->query("UPDATE `aspirantura_kand_exam` SET `base_uid` = '$base_uid' WHERE `load_id` = '$arr[LoadId]'");
        }
      }
      // Аспирантура - руководство аспирантом. Проставим base_uid.
      else if ($arr['UID_KindOfWork'] === $_aspirant_ruk_asp_kind_uid)
      {
        $sql_arr[] = "`nagruzka_type` = 'aspirantura_ruk_asp'";

        // Проставим  base_uid, пришедший из Галактики
        if ($arr['LoadId'] && $base_uid)
        {
          $mysqli->query("UPDATE `aspirantura_ruk_asp` SET `base_uid` = '$base_uid' WHERE `load_id` = '$arr[LoadId]'");
        }
      }
      // Аспирантура - руководство соискателем. Проставим base_uid.
      else if ($arr['UID_KindOfWork'] === $_aspirant_ruk_soisk_kind_uid)
      {
        $sql_arr[] = "`nagruzka_type` = 'aspirantura_ruk_soisk'";

        // Проставим  base_uid, пришедший из Галактики
        if ($arr['LoadId'] && $base_uid)
        {
          $mysqli->query("UPDATE `aspirantura_ruk_soisk` SET `base_uid` = '$base_uid' WHERE `load_id` = '$arr[LoadId]'");
        }
      }
      // Должно быть выше ГИА, иначе отнесётся к ГИА
      elseif (in_array($arr['UID_KindOfWork'], $_aspirant_nagruzka_itog_examen_kind_uids) && $arr['UID_Discipline'] === $_aspirant_nagruzka_itog_examen_discipline1)
      {
        $sql_arr[] = "`nagruzka_type` = 'aspirantura_itog_exam'";
      }
      elseif ($XMLKindOfWorkGIA1[$arr['UID_KindOfWork']] || ($XMLKindOfWorkGIA2[$arr['UID_KindOfWork']] && (mb_stripos($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr'], "Б3") === 0 || mb_stripos($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr'], "Б.3") === 0)))
      {
        $sql_arr[] = "`nagruzka_type` = 'gia'";
      }
      elseif ($XMLKindOfWorkVKR[$arr['UID_KindOfWork']])
      {
        $sql_arr[] = "`nagruzka_type` = 'ruk_vkr'";
      }
      elseif ($XMLKindOfWorkKurs[$arr['UID_KindOfWork']])
      {
        $sql_arr[] = "`nagruzka_type` = 'ruk_kurs'";
      }
      elseif (IsNagruzkaDiscipline($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr']) || $discipline_section)
      {
        $sql_arr[] = "`nagruzka_type` = 'discipline'";
      }
      elseif (IsNagruzkaRukPractice($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr']))
      {
        $sql_arr[] = "`nagruzka_type` = 'ruk_practice'";
      }
      else
      {
        // EchoLog($XMLKindOfWorkGIA2[$arr['UID_KindOfWork']]);
        // EchoLog($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr']);
        // EchoLog(mb_stripos($_XMLContentOfLoadStaffByBaseUID1[$base_uid]['Abbr'], "Б3"));
        // EchoLog("-");

        $sql_arr[] = "`nagruzka_type` = ''";
      }

      // if ($arr['UID_Lecturer'] != '-1')
      // {
      //   $sql_arr[] = "`Lecturer_UID_Chair` = '{$XMLLecturer[$arr['UID_Lecturer']]['UID_Chair']}'"; // 
      // }
      // else
      // {
      //   $sql_arr[] = "`Lecturer_UID_Chair` = ''";
      // }

    }
    
    if ($table_name == 'xml_content_of_load_staff')
    {
      $base_uid = get_base_uid1($arr['UID_ContentOfLoad']);

      // Проверим уровень образования, будем загружать только ВО
      // пропускаем, пропустим остальное
      if ($XMLSpeciality[$arr['UID_Speciality']] && !IsEducationLevelVO($XMLSpeciality[$arr['UID_Speciality']]['education_level']))
      {
        // if ($base_uid === '26589.281474976765788')
        {
          // EchoLog("$arr[UID] $base_uid - {$XMLSpeciality[$_XMLContentOfLoadStaffByBaseUID1[$base_uid]['UID_Speciality']]['education_level']} НЕ ЗАГРУЖАЕМ");
        }

        $ContentOfLoadStaffBaseUID1sNotVo[$base_uid] = $base_uid;

        continue;
      }

      
      $sql_arr[] = "`base_uid` = '$base_uid'";

      $base_uid2 = get_base_uid2($arr['UID_ContentOfLoad']);
      $sql_arr[] = "`base_uid2` = '$base_uid2'";

      $hash = hash_column_values_only($arr, $xml_content_of_load_staff_columns_for_hash);
      $sql_arr[] = "`hash` = '$hash'";
    }



    $sql = JoinArrayElements($sql_arr, ', ');
    // echo $sql;
    // print_r($arr);

    $query = "INSERT INTO `$table_name` SET $sql";

    if ($table_name === 'xml_content_of_load')
    {
      // EchoLog($query);
    }

    $Result = $mysqli->query($query);

    if (!$Result)
    {
      EchoLog($mysqli->error, 'file screen');
      // $db_error = true;
    }

    // var_dump($s->Collection->attributes());
    // exit;
  }
}

include '../connect.php';

if ($LOAD_NEW_DATA_FROM_NETWORK)
{
  file_put_contents('ContentOfLoad.xml', file_get_contents('http://192.168.59.100/nagruzka/ContentOfLoad.xml'));
  file_put_contents('ContentOfLoadStaff.xml', file_get_contents('http://192.168.59.100/nagruzka/ContentOfLoadStaff.xml'));
  file_put_contents('SubGroup.xml', file_get_contents('http://192.168.59.100/nagruzka/SubGroup.xml'));
  file_put_contents('Group.xml', file_get_contents('http://192.168.59.100/nagruzka/Group.xml'));
  file_put_contents('Stream.xml', file_get_contents('http://192.168.59.100/nagruzka/Stream.xml'));
  file_put_contents('KindOfWork.xml', file_get_contents('http://192.168.59.100/nagruzka/KindOfWork.xml'));
  file_put_contents('Discipline.xml', file_get_contents('http://192.168.59.100/nagruzka/Discipline.xml'));
  file_put_contents('Chair.xml', file_get_contents('http://192.168.59.100/nagruzka/Chair.xml'));
  file_put_contents('SubGroup.xml', file_get_contents('http://192.168.59.100/nagruzka/SubGroup.xml'));
  file_put_contents('FormOfEducation.xml', file_get_contents('http://192.168.59.100/nagruzka/FormOfEducation.xml'));
  file_put_contents('Speciality.xml', file_get_contents('http://192.168.59.100/nagruzka/Speciality.xml'));
  file_put_contents('Specialization.xml', file_get_contents('http://192.168.59.100/nagruzka/Specialization.xml'));
  file_put_contents('Language.xml', file_get_contents('http://192.168.59.100/nagruzka/Language.xml'));
  file_put_contents('Faculty.xml', file_get_contents('http://192.168.59.100/nagruzka/Faculty.xml'));
  file_put_contents('Lecturer.xml', file_get_contents('http://192.168.59.100/nagruzka/Lecturer.xml'));
  file_put_contents('Post.xml', file_get_contents('http://192.168.59.100/nagruzka/Post.xml'));

  // Получим данные кандидатов; они нам нужны, чтобы получить id кандидата = будущего сотрудника; он мог быть уже сотрудником прежде, тогда его id является прежним id сотрудника
  $url = 'http://www:nahuheti9@ip.unn.ru/integration/rest/base/getChangedObjects?map=nngu.ais.employees.add';
  file_put_contents('nngu.ais.employees.add.xml', file_get_contents($url));
}

// $mysqli->query("START TRANSACTION");


if ($UPDATE_TABLES)
{
  LoadXML('Stream.xml', 'xml_stream');
  LoadXML('Faculty.xml', 'xml_faculty');
  LoadXML('Language.xml', 'xml_language');
  

  if ($_SERVER['SERVER_ADDR'] != '127.0.0.1')
  LoadXML('Speciality.xml', 'xml_speciality'); // // TMP comment, чтобы локально не загружалось, т.к. нет опоп2
  LoadXML('Specialization.xml', 'xml_specialization');
  LoadXML('SubGroup.xml', 'xml_subgroup');
  LoadXML('Chair.xml', 'xml_chair');
  LoadXML('KindOfWork.xml', 'xml_kind_of_work');

  // понадобится в LoadXML('ContentOfLoad.xml', 'xml_content_of_load')
  // ГИА два критерия (один будет связан с аббревиатурой)
  $XMLKindOfWorkGIA1 = GetTable('xml_kind_of_work', "`Name` LIKE('Участие в комиссии%')", "", "UID");
  $XMLKindOfWorkGIA2 = GetTable('xml_kind_of_work', "`Name` = 'Лекция' OR `Name` = 'Практика (семинарские занятия)'", "", "UID");
  $XMLKindOfWorkForDisciplineSection = GetTable('xml_kind_of_work', "`Name` = 'Практика (семинарские занятия)'", "", "UID");
  // Руководство ВКР
  $XMLKindOfWorkVKR = GetTable('xml_kind_of_work', "`Name` LIKE('Руководство ВКР%')", "", "UID");
  // Руководство курсовыми работами
  $XMLKindOfWorkKurs = GetTable('xml_kind_of_work', "`Name` LIKE('%курсовой работ%')", "", "UID");
  // Специальности (нужны для загрузки только нагрузки Высшего образования: xml_content_of_load_staff.UID_speciality ~ xml_speciality)
  $XMLSpeciality = GetTable('xml_speciality', "", "", "UID");

  // + Руководство практикой...

  LoadXML('Group.xml', 'xml_group');

  LoadXML('Discipline.xml', 'xml_discipline');
  $XMLDiscipline = GetTable('xml_discipline', "", "", "UID");

  LoadXML('Lecturer.xml', 'xml_lecturer');  // загружать до xml_content_of_load, xml_content_of_load_staff
  $XMLLecturer = GetTable('xml_lecturer', "", "", "UID");

  LoadXML('Post.xml', 'xml_post');

  // должно идти до загрузки xml_content_of_load
  LoadXML('ContentOfLoadStaff.xml', 'xml_content_of_load_staff');

  // Чтобы определить, является ли нагрузка xml_content_of_load типом Руководство практикой (определяется по аббревиатуре в xml_content_of_load_staff), получим по одной любой строке из xml_content_of_load_staff
  // Также это используется для получения уровня образования (указаны в xml_content_of_load_staff.UID_Speciality ~ xml_speciality), чтобы не загружать лишние
  $_XMLContentOfLoadStaffByBaseUID1 = GetTable('xml_content_of_load_staff', "", "", 'base_uid', "base_uid, Abbr, UID_Speciality");

  // Должно идти после загрузки xml_content_of_load_staff
  LoadXML('ContentOfLoad.xml', 'xml_content_of_load');
}

// $mysqli->query("COMMIT");
// exit;
// 
// Если  начинать транзакцию раньше, до загрузки страниц, то происходит непонятное с обновлением строк справочников, пришлось перенести сюда
$mysqli->query("START TRANSACTION");

// Данные после текущего импорта

$XMLLecturer = GetTable('xml_lecturer', "", "", "UID");
$XMLPost = GetTable('xml_post', "", "", "Name");
$XMLChairByCode = GetTable('xml_chair', "", "", "Code");
$XMLChairByUID = GetTable('xml_chair', "", "", "UID");
$XMLFacultyByCode = GetTable('xml_faculty', "", "", "Code");
$XMLFacultyByUID = GetTable('xml_faculty', "", "", "UID");



$kandidats_xml = simplexml_load_string(file_get_contents('nngu.ais.employees.add.xml'));

$Kandidats_arr = [];

foreach ($kandidats_xml->Employee as $s)
{
  $person_id = (string) $s->UID; 
  $fio = (string)  $s->FullName;
  $birth_date = (string)  $s->BornDate;

  $Works = $s->Works;

  foreach ($Works->Work as $work)
  {
    $podrazdelenie_id_attrs = $work->Department1->attributes();
    $podrazdelenie_id = (string) str_pad($podrazdelenie_id_attrs['ID']->__toString(), 5, "0000", STR_PAD_LEFT);
    $dolzhnost = $work->Duty->__toString();

    // if (!$Podrazdelenia[])
    // var_dump($podrazdelenie_id) . '<br>';
    // echo $Podrazdelenia[$work->podrazdelenie_id]['pname'] . '<br>';

    // echo $podrazdelenie_id . ' ' . $Podrazdelenia[$podrazdelenie_id]['pname'] . '<br>';

    // Начинается с "Кафедра" или есть "базовая кафедра"
    if (mb_stripos($Podrazdelenia[$podrazdelenie_id]['pname'], 'Кафедра') === 0 || mb_stripos($Podrazdelenia[$podrazdelenie_id]['pname'], 'базовая кафедра') !== false)
    {
      $Kandidats_arr["$person_id-$podrazdelenie_id"] = ['person_id' => $person_id, 'fio' => $fio, 'department_id' => $Podrazdelenia[$podrazdelenie_id]['ukrup_code'], 'dolzhnost' => $dolzhnost, 'type' => 'kandidat', 'chair_id' => $podrazdelenie_id, 'podrazdelenie_id' => $podrazdelenie_id];

      // echo $Podrazdelenia[$podrazdelenie_id]['ukrup_code'] . '<br>';
    }
  }
}

unset($kandidats_xml);

// print_r($Kandidats_arr);

// exit;
// Для того, чтобы добавить сотрудников псевдо-кафедр, соберём id-шники таких подразделений, из которых будем брать ППС
// не используем таблицу nagruzka, т.к. она будет обновляться ниже по коду, а xml_content_of_load - уже обновилась

// кроме "пустых"
$XMLContentOfLoadChairsUnique = GetSQL("SELECT DISTINCT `UID_Chair` FROM `xml_content_of_load` WHERE `UID_Chair` <> '' AND `UID_Chair` <> '25031.0'");
// ids родительских подразделений для псевдо-кафедр, чтобы брать из них ППС
// $pseudo_departments_ids = [];

if ($XMLContentOfLoadChairsUnique)
{
  foreach ($XMLContentOfLoadChairsUnique as $nagruzka_chair_uid_row)
  {
    // if ($nagruzka_chair_uid_row['UID_Chair'] !== '25031.281474976763050') continue;

    $chair_uid = $nagruzka_chair_uid_row['UID_Chair'];
    $chair_id = $XMLChairByUID[$chair_uid]['Code'];
    $faculty_uid = $XMLChairByUID[$chair_uid]['UID_Faculty'];
    $faculty_id = $XMLFacultyByUID[$faculty_uid]['Code'];

    // EchoLog($chair_uid);
    // EchoLog($chair_id);
    // EchoLog($faculty_uid);
    // EchoLog($faculty_id);

    // Если такой кафедры в Сотруднике нет, то нужно взять всех ППС родительского подразделения
    // Эта проблема не пересекается с тем, что у ГПХ-шников в нагрузке стоит UID_Chair = uid факультета, потому что для в случае ГПХ такой кафедры нет в xml_chairs, а в нашем случае есть (но нет в Сотруднике)
    // ! Это делается выше, и задаётся в data.php ($_pseudo_chairs)
    // if ($faculty_id && !$Podrazdelenia[$chair_id])
    // {
    //   $pseudo_departments_ids[] = $faculty_id;
    // }
  }
}




include '../connect/sotrudnik.php';

$SotrudnikiItogoByKey = [];

// 1. «сотрудник» - ППС кафедры, трудоустроенные на текущий момент

$SotrudnikiActual = GetChairSotrudniki($cur_year, "", 1);

if ($SotrudnikiActual)
{
  foreach ($SotrudnikiActual as $sotrudnik)
  {
    $sotrudnik['type'] = 'sotrudnik';

    if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
    $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
  }
}

// EchoLog($SotrudnikiActual);

unset($SotrudnikiActual);

// exit;

// 2. «кандидат» - ППС из сервиса Кандидат, привязанные к этой кафедре и дошедшие до согласования с УОУП (т.е. согласованные УК) и последующие статусы.

if ($Kandidats_arr)
{
  if ($Kandidats_arr)
  {
    foreach ($Kandidats_arr as $sotrudnik)
    {
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])

      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

unset($Kandidats_arr);

// print_r($SotrudnikiItogoByKey);
// exit;

// 3. «работал» - ППС когда-либо работавшие на кафедре (последние 3 года)

$ChairsSotrudnikiCurYear = GetChairSotrudniki($cur_year);
$ChairsSotrudnikiPrevYear = GetChairSotrudniki($cur_year - 1);
$ChairsSotrudnikiPrevPrevYear = GetChairSotrudniki($cur_year - 2);

if ($ChairsSotrudnikiCurYear)
{
  if ($ChairsSotrudnikiCurYear)
  {
    foreach ($ChairsSotrudnikiCurYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'worked';

      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

if ($ChairsSotrudnikiPrevYear)
{
  if ($ChairsSotrudnikiPrevYear)
  {
    foreach ($ChairsSotrudnikiPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'worked';

      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

if ($ChairsSotrudnikiPrevPrevYear)
{
  if ($ChairsSotrudnikiPrevPrevYear)
  {
    foreach ($ChairsSotrudnikiPrevPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'worked';

      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"])
      $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
    }
  }
}

// EchoLog($ChairsSotrudnikiCurYear);
// exit;



// EchoLog($ChairsSotrudnikiPrevYear);
// exit;



// EchoLog($SotrudnikiItogoByKey);
// exit;

unset($ChairsSotrudnikiCurYear);

// 4. «ГПХ» - ППС ГПХ, работающие или когда-либо работавшие на факультете, привязки к кафедре у ГПХ нет (последние 3 года)

$SotrudnikiGPH = GetChairSotrudniki($cur_year, "AND `type` = 'ГПХ'");
$SotrudnikiGPHPrevYear = GetChairSotrudniki($cur_year - 1, "AND `type` = 'ГПХ'");
$SotrudnikiGPHPrevPrevYear = GetChairSotrudniki($cur_year - 2, "AND `type` = 'ГПХ'");

// EchoLog($SotrudnikiGPH);
// exit;

if ($SotrudnikiGPHPrevPrevYear)
{
  if ($SotrudnikiGPHPrevPrevYear)
  {
    foreach ($SotrudnikiGPHPrevPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'gph';

      // Для ГПХ кафедра должна быть факультетом
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] && $sotrudnik['chair_id'] == $sotrudnik['department_id'])
      {
        if ($sotrudnik['person_id'] == 23413)
        {
          // EchoLog("ADDING HERE");
          // EchoLog($sotrudnik);
        }

        $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
      }
    }
  }
}

// EchoLog($SotrudnikiActual);

unset($SotrudnikiGPHPrevPrevYear);


if ($SotrudnikiGPHPrevYear)
{
  if ($SotrudnikiGPHPrevYear)
  {
    foreach ($SotrudnikiGPHPrevYear as $sotrudnik)
    {
      $sotrudnik['type'] = 'gph';
      // Для ГПХ кафедра должна быть факультетом
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] && $sotrudnik['chair_id'] == $sotrudnik['department_id'])
      {
        $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
      }
    }
  }
}

unset($SotrudnikiGPHPrevYear);


if ($SotrudnikiGPH)
{
  if ($SotrudnikiGPH)
  {
    foreach ($SotrudnikiGPH as $sotrudnik)
    {
      $sotrudnik['type'] = 'gph';
      // Для ГПХ кафедра должна быть факультетом
      if (!$SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] && $sotrudnik['chair_id'] == $sotrudnik['department_id'])
      { 
        $SotrudnikiItogoByKey["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
      }
    }
  }
}

unset($SotrudnikiGPH);

// 5. Доп. деканы. Добавляем из специальной таблицы dop_deans, потому что эти деканы не относятся к кафедрам

// if ($DopDeans)
// {
//   foreach ($DopDeans as $dop_dean)
//   {

//   }
// }


// EchoLog($SotrudnikiGPH);

// EchoLog($SotrudnikiItogoByKey);
// exit;


// EchoLog(sizeof($ChairsSotrudniki));

// include '../connect/kandidat.php';

// $Kandidats = GetTable('kandidat', "`person_id` IS NOT NULL AND `status` IN ('soglasovanie_uoup', 'accepted_uoup', 'accepted', 'predstavlenie', 'predstavlenie_signed_dean', 'predstavlenie_signed_rector', 'done')");

// $KandidatsByKey = [];

// foreach ($Kandidats as $kandidat)
// {
//   $KandidatsByKey["$kandidat[person_id]-$kandidat[chair_id]"] = true;
// }

include '../connect.php';




// Соберём добавляемых по ключу,
// чтобы проставлять дату удаления
// $AddingSotrudniki = [];
// $ChairsSotrudnikiPrevYearByKey = [];
// $ChairsSotrudnikiPrevPrevYearByKey = [];

// if ($ChairsSotrudniki)
// {
//   foreach ($ChairsSotrudniki as $chair_sotrudnik)
//   {
//     $AddingSotrudniki["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] = $chair_sotrudnik;
//   }
// }

// if ($ChairsSotrudnikiPrevYear)
// {
//   foreach ($ChairsSotrudnikiPrevYear as $chair_sotrudnik)
//   {
//     $ChairsSotrudnikiPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] = $chair_sotrudnik;
//   } 
// }

// if ($ChairsSotrudnikiPrevPrevYear)
// {
//   foreach ($ChairsSotrudnikiPrevPrevYear as $chair_sotrudnik)
//   {
//     $ChairsSotrudnikiPrevPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] = $chair_sotrudnik;
//   } 
// }

// Сотрудники, которые в данный момент есть в справочнике
// чтобы проставлять дату добавления
$Sotrudniki = GetRows('sotrudniki', null, null, null, 'person_id, chair_id, type');
$SotrudnikiInLKByKey = [];

if ($Sotrudniki)
{
  foreach ($Sotrudniki as $sotr)
  {
    if ($sotr['person_id'] == 50144)
    {
      // EchoLog($sotr);
      // EchoLog($SotrudnikiItogoByKey["$sotr[person_id]-$sotr[chair_id]"]);
    }

    $SotrudnikiInLKByKey["$sotr[person_id]-$sotr[chair_id]"] = $sotr;

    // if ($sotr['person_id'] && $sotr['chair_id'])
    {
      // Если есть в справочнике, но нет среди добавляемых
      if (!$SotrudnikiItogoByKey["$sotr[person_id]-$sotr[chair_id]"])
      {
        $Result = $mysqli->query("
                  UPDATE `sotrudniki` 
                  SET `date_remove` = NOW()
                  WHERE `date_remove` IS NULL AND `person_id` = '$sotr[person_id]' AND `chair_id` = '$sotr[chair_id]'
                  ");

        if (!$Result)
        {
          EchoLog($mysqli->error);
          $db_error = true;
        }
      }
    }
    // else
    // {
    //   EchoLog();
    // }
  }
}




if ($SotrudnikiItogoByKey)
{
  foreach ($SotrudnikiItogoByKey as $chair_sotrudnik)
  {
    // Человека на кафедре не было в базе ЛК ЗК
    if (!$SotrudnikiInLKByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"])
    {
      // $add_date_sql = ", `date_add` = NOW()";
      $adding = true;
    }
    // Человек в базе ЛК ЗК на этой кафедре уже есть
    else
    {
      // $add_date_sql = '';
      $adding = false;
    }

    if (!$chair_sotrudnik['fio'])
    {
      $chair_sotrudnik['fio'] = "$chair_sotrudnik[surname] $chair_sotrudnik[name] $chair_sotrudnik[patronymic]";
    }

    // $type = '';

    // определим "тип"
    // if ($chair_sotrudnik['position_category'] == 'ППС' && $chair_sotrudnik['type'] == 'ГПХ')
    // {
    //   $type = 'gph';
    // }
    
    // if ($ChairsSotrudnikiPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"] || $ChairsSotrudnikiPrevPrevYearByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"])
    // {
    //   $type = 'worked';
    // }
    
    // if ($KandidatsByKey["$chair_sotrudnik[person_id]-$chair_sotrudnik[chair_id]"])
    // {
    //   $type = 'kandidat';
    // }

    $post_uid = $XMLPost[mb_strtolower($chair_sotrudnik['dolzhnost'])]['UID'];
    // EchoLog($post_uid);
    
    // Для ГПХ-шников здесь пусто!, потому что у них $chair_sotrudnik['chair_id'] - это код факультета
    $chair_uid = $XMLChairByCode[$chair_sotrudnik['chair_id']]['UID'];
    $department_uid = $XMLFacultyByCode[$chair_sotrudnik['department_id']]['UID'];
    $person_id = $chair_sotrudnik['person_id'];
    $person_type = $chair_sotrudnik['type'];
    // EchoLog($chair_uid);

    // #dup 647
    if ($person_type == 'gph')
    {
      // если не равно, значит, в chair_id находится не факультет, а не понятно что, например, Отделение.. 
      // таких не берём
      // Не берутся в сам массив
      // if ($chair_sotrudnik['chair_id'] != $chair_sotrudnik['department_id'])
      // {
      //   continue;
      // }

      if (!$chair_uid) $chair_uid = $department_uid;
    }
    // если у сотрудника в качестве кафедры (это поле position*.podrazdelenie_id) стоит псевдо-факультет (родитель псевдо-кафедры) [при этом псевдо-факультет является подразделением, а псевдо-кафедры не существует], то сделаем подмены
    elseif (in_array($chair_sotrudnik['chair_id'], $pseudo_departments_ids))
    {
      $PseudoFaculty = GetRow('xml_faculty', ['Code' => $chair_sotrudnik['chair_id']]);
      $department_uid = $PseudoFaculty['UID'];
      // для таких в department_id исходно стоит укрупнённое для псевдо-факультета, пусть как у ГПХ-шников здесь будет тоже факультет
      $chair_sotrudnik['department_id'] = $chair_sotrudnik['chair_id'];
      $chair_uid = $department_uid;
    }

    $lecturer = GetLecturer($person_id, $post_uid, $chair_uid, $department_uid);

    if ($person_id == 23413)
    {
      // EchoLog("Post_uid: $post_uid, chair_uid: $chair_uid, department_uid: $department_uid, person_type: $person_type");
      // EchoLog($lecturer);
    }
  

    // Если не нашли, то не добавляем сотрудника и не обновляем
    if (!$lecturer || !$lecturer['UID'])
    {
      
      $query = "
                UPDATE `sotrudniki` 
                SET `date_remove` = NOW()
                WHERE `date_remove` IS NULL AND `person_id` = '$chair_sotrudnik[person_id]' AND `chair_id` = '$chair_sotrudnik[chair_id]'
              ";

      if ($chair_sotrudnik['person_id'] == 19972)
      {
        // EchoLog($query);
      }

      $Result = $mysqli->query($query);

      if (!$Result)
      {
        EchoLog("Error #493 in cron.php:<br>" . $mysqli->error . "<br><br>$query", "file mail");
        $db_error = true;
      }

      continue;

    }

    if ($adding)
    {
      // uid должности из Галактики
      // EchoLog($chair_sotrudnik['dolzhnost']);

      // $lecturer = GetRow('xml_lecturer', ['Tab_number' => $chair_sotrudnik['person_id'], 'UID_Post' => $post_uid, 'UID_Chair' => $chair_uid]);

      // EchoLog($lecturer);

      // if ((!$lecturer || !$lecturer['UID']) && $person_type != 'worked')
      // {
      //   EchoLog("$chair_sotrudnik[chair_id] $chair_sotrudnik[department_id]");
      //   EchoLog("! НЕ НАЙДЕН ЛЕКТОР ДЛЯ $person_id, $post_uid ($chair_sotrudnik[dolzhnost]), $chair_uid, $department_uid, $person_type)");
      // }

      // if ($chair_sotrudnik['type'] == 'sotrudnik')
      // {
      //   $selected = '1';
      // }
      // else
      // {
      //   $selected = '0';
      // }

      $login = $Person[$chair_sotrudnik['person_id']]['alias'];

      // if ($sotrudnik['type'])

      $query = "
              INSERT INTO `sotrudniki` 
              SET `person_id` = '$chair_sotrudnik[person_id]', `lecturer_uid` = '$lecturer[UID]', `lecturer_login` = '$login',
              `fio` = '$chair_sotrudnik[fio]', `chair_id` = '$chair_sotrudnik[chair_id]', `chair_uid` = '$lecturer[UID_Chair]',
              `department_id` = '$chair_sotrudnik[department_id]',
              `podrazdelenie_id` = '$chair_sotrudnik[podrazdelenie_id]', `dolzhnost` = '$chair_sotrudnik[dolzhnost]', `type` = '$chair_sotrudnik[type]', `stavka` = '$chair_sotrudnik[stavka]', `pku` = '$chair_sotrudnik[pku]', `pkg` = '$chair_sotrudnik[pkg]', `date_add` = NOW()
              ON DUPLICATE KEY UPDATE
              `pku` = VALUES(`pku`),
              `pkg` = VALUES(`pkg`),
              `stavka` = VALUES(`stavka`),
              `date_remove` = NULL
            ";

      if ($chair_sotrudnik['person_id'] == 19972)
      {
        // EchoLog($query);
      }

      $Result = $mysqli->query($query);

      if (!$Result)
      {
        EchoLog("Error #573 in cron.php:<br>" . $mysqli->error . "<br><br>$query", "file mail");
        $db_error = true;
      }
    }
    // updating
    else
    {
      // if ($chair_sotrudnik['type'] == 'sotrudnik')
      // {
      //   $sql_selected = ", `selected` = '1'";
      // }
      // else
      // {
      //   $sql_selected = '';
      // }

      // `selected` = '$selected',
      $query = "
                UPDATE `sotrudniki` 
                SET `fio` = '$chair_sotrudnik[fio]', `dolzhnost` = '$chair_sotrudnik[dolzhnost]', `type` = '$chair_sotrudnik[type]', `stavka` = '$chair_sotrudnik[stavka]', `pku` = '$chair_sotrudnik[pku]', `pkg` = '$chair_sotrudnik[pkg]',
                # !! обновление lecturer_uid, chair_uid
                `lecturer_uid` = '$lecturer[UID]',
                `chair_uid` = '$lecturer[UID_Chair]',
                `date_remove` = NULL
                $sql_selected
                WHERE `person_id` = '$chair_sotrudnik[person_id]' AND `chair_id` = '$chair_sotrudnik[chair_id]'
              ";

      if ($chair_sotrudnik['person_id'] == 19972)
      {
        // EchoLog($query);
      }

      $Result = $mysqli->query($query);

      if (!$Result)
      {
        EchoLog("Error #683 in cron.php:<br>" . $mysqli->error . "<br><br>$query", "file mail");
        $db_error = true;
      }
    }

    
  }
}

unset($Sotrudniki);
unset($SotrudnikiInLKByKey);
unset($SotrudnikiItogoByKey);

// exit;

// Перед тем как использовать nagruzka, xml_content_of_load, xml_content_of_load_staff
// Произведём ЗАМЕНЫ lecturer_uid на более подходящие
// Выше в таблице sotrudniki замены уже сделали, получим сотрудников с обновлёнными uid
// TODO: ограничить конкретным этапом??
$Sotrudniki = GetTable('sotrudniki');
$SotrudnikiByPersonChair = [];

foreach ($Sotrudniki as $sotrudnik)
{
  $SotrudnikiByPersonChair["$sotrudnik[person_id]-$sotrudnik[chair_id]"] = $sotrudnik;
}


// $ZavkafSplits = GetTable('zavkaf_splits');

foreach ($SotrudnikiByPersonChair as $sotrudnik)
{
  $post_uid = $XMLPost[mb_strtolower($sotrudnik['dolzhnost'])]['UID'];
  $chair_uid = $XMLChairByCode[$sotrudnik['chair_id']]['UID'];
  $department_uid = $XMLFacultyByCode[$sotrudnik['department_id']]['UID'];
  $person_type = $sotrudnik['type'];

  // #dup 647
  if ($person_type == 'gph')
  {
    // если не равно, значит, в chair_id находится не факультет, а не понятно что, например, Отделение.. 
    // таких не берём
    // Не берутся в сам массив
    // if ($chair_sotrudnik['chair_id'] != $chair_sotrudnik['department_id'])
    // {
    //   continue;
    // }

    if (!$chair_uid) $chair_uid = $department_uid;
  }
  // если у сотрудника в качестве кафедры (это поле position*.podrazdelenie_id) стоит псевдо-факультет (родитель псевдо-кафедры) [при этом псевдо-факультет является подразделением, а псевдо-кафедры не существует], то сделаем подмены
  elseif (in_array($sotrudnik['chair_id'], $pseudo_departments_ids))
  {
    $PseudoFaculty = GetRow('xml_faculty', ['Code' => $sotrudnik['chair_id']]);
    $department_uid = $PseudoFaculty['UID'];
    // для таких в department_id исходно стоит укрупнённое для псевдо-факультета, пусть как у ГПХ-шников здесь будет тоже факультет
    $sotrudnik['department_id'] = $sotrudnik['chair_id'];
    $chair_uid = $department_uid;

    if ($sotrudnik['person_id'] == 22681)
    {
      // EchoLog("#412: chair_uid = $chair_uid, chair_id = $sotrudnik[chair_id]");
    }
  }

  $lecturer = GetLecturer($sotrudnik['person_id'], $post_uid, $chair_uid, $department_uid);

  $SplitsForSotrudnik = GetRows('zavkaf_splits', ['lecturer_person_id' => $sotrudnik['person_id'], 'chair_uid' => $chair_uid]);


  if ($sotrudnik['person_id'] == 22681)
  {
    // EchoLog("chair_id $sotrudnik[chair_id], person_id $sotrudnik[person_id], $post_uid, $chair_uid, $department_uid");
    // EchoLog($lecturer);
    // EchoLog($SplitsForSotrudnik);
  }

  // не будем заменять сплит, в котром пустая кафедра - таких случаев быть не должно, нужно за ними посматривать!
  if ($lecturer && $SplitsForSotrudnik && $chair_uid)
  {
    foreach ($SplitsForSotrudnik as $split_row)
    {
      if ($split_row['lecturer_uid'] !== $lecturer['UID'])
      {
        // if ($sotrudnik['person_id'] == 51586)
        EchoLog("Заменяем в сплите для person_id=$sotrudnik[person_id], split_id: $split_row[id] (base_uid2 $split_row[base_uid2]), lecturer uids: $split_row[lecturer_uid] !=> $lecturer[UID], chair_uid: $chair_uid");

        // content_of_load_uid не правим, т.к. считается, что завкаф разбивает "с нуля", т.е. из Галактики нет разбиений, значит uid не содержит сотрудников
        $base_uid2_obj = parseNagruzkaBaseUid2($split_row['content_of_load_uid_new']);

        // заменяем суффикс лектора на новый
        $base_uid2_obj['lector_suffix'] = $lecturer['UID'];

        // склеиваем в обновлённый base_uid2
        // $new_content_of_load_uid_new = glueNagruzkaBaseUid2Parts($base_uid2_obj);

        $base_uid2_obj = parseNagruzkaBaseUid2($split_row['base_uid2_new']);

        // заменяем суффикс лектора на новый
        $base_uid2_obj['lector_suffix'] = $lecturer['UID'];

        // склеиваем в обновлённый base_uid2
        $new_base_uid2_new = glueNagruzkaBaseUid2Parts($base_uid2_obj);


        $query = "UPDATE `zavkaf_splits` 
          SET #`content_of_load_uid_new` = '$new_content_of_load_uid_new', 
          `base_uid2_new` = '$new_base_uid2_new',
          `lecturer_uid` = '$lecturer[UID]' 
          WHERE  `id` = '$split_row[id]'";

        $Result = $mysqli->query($query);

        if (!$Result)
        {
          EchoLog($mysqli->error);
          EchoLog($query);
          $db_error = true;
        }
      }
    }
  }

  // Обновим в таблице КСРО аналогично lecturer_uid и несколько других полей сотрудника (ставка, должность..)
  if ($lecturer)
  {
    // В таблице ksro chair_id используются с псевдо-значениями (888, 999, ...)
    // поэтому для не-псевдо из sotrudniki возьмём псевдо, как используется в таблице ksro
    if (in_array($sotrudnik['chair_id'], $_pseudo_chairs))
    {
      $chair_id_for_ksro = array_search($sotrudnik['chair_id'], $_pseudo_chairs);
    }
    else
    {
      $chair_id_for_ksro = $sotrudnik['chair_id'];
    }

    $KSROForSotrudnik = GetRows('ksro', ['lecturer_person_id' => $sotrudnik['person_id'], 'chair_id' => $chair_id_for_ksro]);

    // if ($sotrudnik['person_id'] == 25944)
    // {
    //   EchoLog($chair_id_for_ksro);
    //   EchoLog($KSROForSotrudnik);
    // }

    if ($KSROForSotrudnik)
    {
      foreach($KSROForSotrudnik as $ksro)
      {
        $stavka = str_replace(',', '.', $sotrudnik['stavka']);

        $Result = $mysqli->query("UPDATE `ksro`
                                  SET `uid` = '$lecturer[UID]', `stavka` = '$stavka', `dolzhnost` = '$sotrudnik[dolzhnost]'
                                  WHERE `id` = '$ksro[id]'
                                ");

        if ($ksro['uid'] !== $lecturer['UID'])
        {
          EchoLog("Заменяется lecturer_uid в ksro для person_id=$sotrudnik[person_id], ksro_id: $ksro[id], lecturer uids: $ksro[uid] !=> $lecturer[UID], chair_uid: $chair_uid");
        }

        if (!$Result)
        {
          EchoLog($mysqli->error);
          EchoLog($query);
          $db_error = true;
        }

      }
    }


    $AspiranturaKandExamForSotrudnik = GetRows('aspirantura_kand_exam', ['lecturer_person_id' => $sotrudnik['person_id'], 'chair_id' => $sotrudnik['chair_id']]);

    if ($AspiranturaKandExamForSotrudnik)
    {
      foreach($AspiranturaKandExamForSotrudnik as $asp_row)
      {
        if ($asp_row['lecturer_uid'] !== $lecturer['UID'])
        {
          EchoLog("Заменяется lecturer_uid в aspirantura_kand_exam для person_id=$sotrudnik[person_id], row_id: $asp_row[id], lecturer uids: $asp_row[lecturer_uid] !=> $lecturer[UID], chair_id: $sotrudnik[chair_id]");

          // если нужно обновлять не только lecturer_uid, то запрос нужно вынести из if (как в ksro)
          $Result = $mysqli->query("UPDATE `aspirantura_kand_exam`
                                  SET `lecturer_uid` = '$lecturer[UID]'
                                  WHERE `id` = '$asp_row[id]'
                                ");

          if (!$Result)
          {
            EchoLog($mysqli->error);
            EchoLog($query);
            $db_error = true;
          }
        }
      }
    }



    $AspiranturaRukAspForSotrudnik = GetRows('aspirantura_ruk_asp', ['lecturer_person_id' => $sotrudnik['person_id'], 'lecturer_chair_id' => $sotrudnik['chair_id']]);

    if ($AspiranturaRukAspForSotrudnik)
    {
      foreach($AspiranturaRukAspForSotrudnik as $asp_row)
      {
        if ($asp_row['lecturer_uid'] !== $lecturer['UID'])
        {
          EchoLog("Заменяется lecturer_uid в aspirantura_ruk_asp для person_id=$sotrudnik[person_id], row_id: $asp_row[uid] (sem $asp_row[UID_Semester]), lecturer uids: $asp_row[lecturer_uid] !=> $lecturer[UID], chair_id: $sotrudnik[chair_id]");

          // если нужно обновлять не только lecturer_uid, то запрос нужно вынести из if (как в ksro)
          $Result = $mysqli->query("UPDATE `aspirantura_ruk_asp`
                                  SET `lecturer_uid` = '$lecturer[UID]'
                                  WHERE `uid` = '$asp_row[uid]' AND `UID_Semester` = '$asp_row[UID_Semester]'
                                ");

          if (!$Result)
          {
            EchoLog($mysqli->error);
            EchoLog($query);
            $db_error = true;
          }
        }
      }
    }


    $AspiranturaRukSoiskForSotrudnik = GetRows('aspirantura_ruk_soisk', ['lecturer_person_id' => $sotrudnik['person_id'], 'lecturer_chair_id' => $sotrudnik['chair_id']]);

    if ($AspiranturaRukSoiskForSotrudnik)
    {
      foreach($AspiranturaRukSoiskForSotrudnik as $asp_row)
      {
        if ($asp_row['lecturer_uid'] !== $lecturer['UID'])
        {
          EchoLog("Заменяется lecturer_uid в aspirantura_ruk_soisk для person_id=$sotrudnik[person_id], row_id: $asp_row[id], lecturer uids: $asp_row[lecturer_uid] !=> $lecturer[UID], chair_id: $sotrudnik[chair_id]");

          // если нужно обновлять не только lecturer_uid, то запрос нужно вынести из if (как в ksro)
          $Result = $mysqli->query("UPDATE `aspirantura_ruk_soisk`
                                  SET `lecturer_uid` = '$lecturer[UID]'
                                  WHERE `id` = '$asp_row[id]'
                                ");

          if (!$Result)
          {
            EchoLog($mysqli->error);
            EchoLog($query);
            $db_error = true;
          }
        }
      }
    }


  }
  
}

/*
КОД ЗАКОММЕНТИРОВАН, ПОТОМУ ЧТО В СООТВ. ЭТАПЕ ЭТИ ДВЕ ТАБЛИЦЫ ПРИХОДЯТ БЕЗ РАСПРЕДЕЛЕНИЯ, ПОЭТОМУ ЗАМЕНЯТЬ НЕЧЕГО

$DeletedOrChangedNagruzka = [];
$ChangedXmlContentOfLoad[] = [];

$Nagruzka = GetTable('nagruzka', "`valid` = '1' AND `lecturer_fio` IS NOT NULL AND `lecturer_fio` <> 'Вакансия' AND `lecturer_fio` <> ''");
$NagruzkaByLecturer = [];

foreach ($Nagruzka as $nagruzka)
{
  if ($nagruzka['lecturer_person_id'] == 51586)
  {
    // EchoLog($nagruzka);
  }

  $sotrudnik = $SotrudnikiByPersonChair["$nagruzka[lecturer_person_id]-$nagruzka[chair_id]"];

  if ($sotrudnik)
  {
    // $NagruzkaByLecturer[$nagruzka['lecturer_uid']][] = $nagruzka;
    $post_uid = $XMLPost[mb_strtolower($sotrudnik['dolzhnost'])]['UID'];
    $chair_uid = $XMLChairByCode[$sotrudnik['chair_id']]['UID'];
    $department_uid = $XMLFacultyByCode[$sotrudnik['department_id']]['UID'];
    $person_type = $sotrudnik['type'];

    $lecturer = GetLecturer($sotrudnik['person_id'], $post_uid, $chair_uid, $department_uid);

    if ($sotrudnik['person_id'] == 51586)
    {
      // EchoLog($lecturer);
    }

    if ($lecturer && $lecturer['UID'])
    {
      // $nagruzka_base_uid2_obj = parseNagruzkaBaseUid2($nagruzka['load_base_UID2']);
      $nagruzka_lecturer_uid = $nagruzka['lecturer_uid'];

      // В таблице nagruzka load_base_UID2 должен содержать lecturer_uid (если он не пуст)
      // удалим некорректные строки, если не содержит
      if ($nagruzka['lecturer_uid'] && strpos($nagruzka['load_base_UID2'], $nagruzka['lecturer_uid']) === false)
      {
        $query = "DELETE FROM `nagruzka` WHERE `load_base_UID2` = '$nagruzka[load_base_UID2]'";
        EchoLog($query);
        $mysqli->query($query);
        $DeletedOrChangedNagruzka[$nagruzka['load_base_UID2']] = true;
        continue;
      }

      // есть лектор в нагрузке, но мы по приоритетам нашли более подходящий UID лектора, заменим на него в таблицах БД
      if ($nagruzka_lecturer_uid && $nagruzka_lecturer_uid != $lecturer['UID'])
      {
        $base_uid2_obj = parseNagruzkaBaseUid2($nagruzka['load_base_UID2']);

        // if ($sotrudnik['person_id'] == 51586)
        {
          EchoLog("Заменяем для $nagruzka[load_base_UID2]: $nagruzka_lecturer_uid != $lecturer[UID]");
        }

        // заменяем суффикс лектора на новый
        $base_uid2_obj['lector_suffix'] = $lecturer['UID'];

        // склеиваем в обновлённый base_uid2
        $new_base_uid2 = glueNagruzkaBaseUid2Parts($base_uid2_obj);

        $query = "UPDATE `nagruzka` SET `load_base_UID2` = '$new_base_uid2', `lecturer_uid` = '$lecturer[UID]' 
          WHERE  `load_base_UID2` = '$nagruzka[load_base_UID2]'";

        $Result = $mysqli->query($query);

        if (!$Result)
        {
          if ($mysqli->errno == 1062) 
          { 
            // Код ошибки дубликата
            // Это ошибка DUPLICATE ENTRY
            $query = "DELETE FROM `nagruzka` WHERE `load_base_UID2` = '$nagruzka[load_base_UID2]'";
            $Result = $mysqli->query($query);

            EchoLog("Некритичная ошибка дубликата в nagruzka, удаляем строку, т.к. более правильная уже есть");

            if (!$Result)
            {
              EchoLog($mysqli->error);
              EchoLog($query);
            }

          }
          // другая критичная ошибка
          else
          {
            $db_error = true;
          }

          EchoLog($mysqli->error);
          EchoLog($query);
        }
        else
        {
          $DeletedOrChangedNagruzka[$nagruzka['load_base_UID2']] = true;
        }

        // Сделаем замены в zavkaf_splits



        // Сделаем замены в xml_content_of_load
        $xml_content_of_load_rows = GetRows('xml_content_of_load', ['base_uid2' => $nagruzka['load_base_UID2']]);

        if ($xml_content_of_load_rows)
        {
          foreach ($xml_content_of_load_rows as $xml_content_of_load_row)
          {
            $uid_obj = parseNagruzkaBaseUid2($xml_content_of_load_row['UID']);

            if ($uid_obj['lector_suffix'] == $nagruzka_lecturer_uid)
            {
              // заменяем суффикс лектора на новый
              $uid_obj['lector_suffix'] = $lecturer['UID'];
              $new_uid = glueNagruzkaBaseUid2Parts($base_uid2_obj);
            }
            else
            {
              $new_uid = $xml_content_of_load_row['UID'];
            }

            $query = "UPDATE `xml_content_of_load` SET `UID` = '$new_uid', `base_uid2` = '$new_base_uid2', `UID_Lecturer` = '$lecturer[UID]' WHERE `UID` = '$xml_content_of_load_row[UID]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog($mysqli->error);
              EchoLog($query);
              $db_error = true;
            }
            else
            {
              $ChangedXmlContentOfLoad[$xml_content_of_load_row['UID']] = true;
            }
          }
        }

        // Сделаем замены в xml_content_of_load_staff
        $xml_content_of_load_staff_rows = GetRows('xml_content_of_load_staff', ['base_uid2' => $nagruzka['load_base_UID2']]);

        if ($xml_content_of_load_staff_rows)
        {
          foreach ($xml_content_of_load_staff_rows as $xml_content_of_load_staff_row)
          {
            $uid_obj = parseNagruzkaBaseUid2($xml_content_of_load_staff_row['UID']);

            if ($uid_obj['lector_suffix'] == $nagruzka_lecturer_uid)
            {
              // заменяем суффикс лектора на новый
              $uid_obj['lector_suffix'] = $lecturer['UID'];
              $new_uid = glueNagruzkaBaseUid2Parts($uid_obj);
            }
            else
            {
              $new_uid = $xml_content_of_load_row['UID'];
            }


            $uid_content_of_load_staff_obj = parseNagruzkaBaseUid2($xml_content_of_load_staff_row['UID_ContentOfLoad']);

            if ($uid_content_of_load_staff_obj['lector_suffix'] == $nagruzka_lecturer_uid)
            {
              // заменяем суффикс лектора на новый
              $uid_content_of_load_staff_obj['lector_suffix'] = $lecturer['UID'];
              $new_uid_content_of_load = glueNagruzkaBaseUid2Parts($uid_content_of_load_staff_obj);
            }
            else
            {
              $new_uid_content_of_load = $xml_content_of_load_row['UID_ContentOfLoad'];
            }

            $query = "
                      UPDATE `xml_content_of_load_staff` 
                      SET `UID` = '$new_uid', `UID_ContentOfLoad` = '$new_uid_content_of_load', `base_uid2` = '$new_base_uid2', 
                      `ContentOfLoadUID` = '$new_uid_content_of_load' 
                      WHERE `UID` = '$xml_content_of_load_staff_row[UID]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog($mysqli->error);
              EchoLog($query);
              $db_error = true;
            }
          }
        }


        

      }

    }

  }

  
}

*/

// foreach ($NagruzkaByLecturer as $lecturer_uid => $nagruzka_rows)
{
  // 
  // GetLecturer($person_id, $post_uid, $chair_uid, $department_uid)
}





if (!$XMLContentOfLoad)
$XMLContentOfLoad = GetTable('xml_content_of_load', "", "", "UID", "UID, UID_Chair, base_uid, base_uid2, hash, UID_Lecturer");
// EchoLog("HERE 666");
// EchoLog($XMLContentOfLoad['26589.281474976799017']);

// $XMLContentOfLoadByBaseUID = GetTable('xml_content_of_load', "", "", "base_uid", "UID, UID_Chair, base_uid, hash, UID_Lecturer");
$_XMLContentOfLoadStaff = GetTable('xml_content_of_load_staff', "", "", null, "UID, base_uid2, UID_ContentOfLoad, hash");

$XMLContentOfLoadStaffByBaseUID2 = [];

if ($_XMLContentOfLoadStaff)
{
  foreach ($_XMLContentOfLoadStaff as $row)
  {
    // UID_ContentOfLoad соотв. base_uid ?
    // $XMLContentOfLoadStaff[$row['UID_ContentOfLoad']][$row['UID']] = $row;
    $XMLContentOfLoadStaffByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
  }
}

$XMLContentOfLoadByBaseUID2 = [];

if ($XMLContentOfLoad)
{
  foreach ($XMLContentOfLoad as $row)
  {
    // в этой таблице из-за споточенности для одного base_uid может быть несколько UID с разными суффиксами
    $XMLContentOfLoadByBaseUID2[$row['base_uid2']][$row['UID']] = $row;
  }
}

unset($_XMLContentOfLoadStaff);



// echo sizeof($XMLContentOfLoadStaff);
// print_r(array_pop($XMLContentOfLoadStaff));
// exit;

// print_r($XMLChairByUID);
// exit;

// $mysqli->query("TRUNCATE `nagruzka`");

if ($XMLContentOfLoadStaffByBaseUID2)
{
  if (sizeof($XMLContentOfLoadStaffByBaseUID2) < sizeof($XMLContentOfLoadStaffPrevByBaseUID2) / 2)
  {
    EchoLog("ЛК ЗК: В таблице 2 стало заметно меньше строк. Скрипт стоп.", 'file mail');
    $mysqli->query("ROLLBACK");
    exit;
  }
}
else
{
  EchoLog("ЛК ЗК: Пустая таблица нагрузки 2. Скрипт стоп.", 'file mail');
  $mysqli->query("ROLLBACK");
  exit;
}


// Текущие данные, после обновления
if ($XMLContentOfLoad)
{
  // Проверим, сколько строк нагрузки исчезло; если подозрительно много, то не будем ничего менять, а отправим письмо
  $rows_gone_counter = 0;
  $prev_rows_count = sizeof($XMLContentOfLoadPrev);

  // Таблица 1 нагрузки до текущего обновления
  foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
  {
    // прежняя нагрузка не обнаружена в текущем справочнике нагрузок
    if (!$XMLContentOfLoad[$xml_content_of_load_prev_row['UID']])
    {
      $rows_gone_counter++; 
    }
  }

  // исчезло не более трети строк нагрузки в справочнике
  // -- TMP HACK разрешение
  if ($rows_gone_counter < $prev_rows_count / 3)
  {
    // Сначала сосчитаем, у скольких строк изменились хеши: если слишком много, то отправим письмо и остановимся
    $hash_changed_rows_count1 = 0;
    $hash_changed_rows_count2 = 0;

    foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
    {
      // -- сравниваем всё на базе base_uid
      $base_uid = $xml_content_of_load_prev_row['base_uid'];
      $base_uid2 = $xml_content_of_load_prev_row['base_uid2'];

      // $new_nagr_row = $XMLContentOfLoad[$base_uid];
      $new_nagr_row = $XMLContentOfLoad[$xml_content_of_load_prev_row['UID']];

      // хеши таблицы 1
      if ($xml_content_of_load_prev_row['hash'] != $new_nagr_row['hash'])
      {
        $hash_changed_rows_count1++;
      }

      // хеши таблицы 2
      if ($XMLContentOfLoadStaffByBaseUID2[$base_uid2])
      {
        foreach ($XMLContentOfLoadStaffByBaseUID2[$base_uid2] as $load_staff_UID => $load_staff_new_row)
        {
          if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID])
          {
            // сравним хеши соотв. строк load_staff
            if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID]['hash'] != $load_staff_new_row['hash'])
            {
              $hash_changed_rows_count2++;
            }
          }
          else
          {
            $hash_changed_rows_count2++;
          }
        }
      }
    }

    if ($hash_changed_rows_count1 > sizeof($XMLContentOfLoadPrev) / 3)
    {
      $changed_num_percent = round($hash_changed_rows_count1 / sizeof($XMLContentOfLoadPrev) * 100);
      $message_subject = "ЛК ЗК cron: изменилось много строк нагрузки в таблице 1";
      $message_text = "Изменилось $changed_num_percent% строк нагрузки в таблице 1. Обработка не выполнена.";

      mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text);
      mail_utf8($_admin_mail, $_site_domain, $_from_mail, $message_subject, $message_text);

      EchoLog($message_text . " Остановка скрипта.");
      $mysqli->query("ROLLBACK");

      exit;
    }

    // Таблица нагрузки до текущего обновления.
    // Если прежде бывшей нагрузки уже нет, удалим её в таблице nagruzka ЛК ЗК
    foreach ($XMLContentOfLoadPrev as $xml_content_of_load_prev_row)
    {
      // -- сравниваем всё на базе base_uid
      $base_uid = $xml_content_of_load_prev_row['base_uid'];
      $base_uid2 = $xml_content_of_load_prev_row['base_uid2'];
      $xml_content_of_load_UID = $xml_content_of_load_prev_row['UID'];

      // if ($base_uid === '26589.281474976799017')
      // {
      //   EchoLog("HERE 777");
      //   EchoLog($XMLContentOfLoadByBaseUID2[$base_uid2]);
      // }

      // прежняя нагрузка не обнаружена в текущем справочнике нагрузок по base_UID,
      // т.е. нет такой нагрузки независимо от споточенности
      // т.к. в цикле идём по UID, и base_uid может повторяться, то код в скобках может повториться
      // -- в случае, если выше уже не удалили эту нагрузку / не заменили base_uid2
      if (!$XMLContentOfLoadByBaseUID2[$base_uid2])
      {
        EchoLog("Прежняя нагрузка (base_uid2=$base_uid2, content_uid=$xml_content_of_load_UID) не обнаружена в текущем справочнике xml_content_of_load, удаляем");

        $mysqli->query("DELETE FROM `nagruzka` WHERE `load_base_UID2` = '$base_uid2'");
        // TODO !!! удалять это (помечать) и в сплитах, и м.б. КСРО, аспирантура !!!
        // TODO взять тип нагрузки и "удалять" в соотв. таблицах
        // !! если нагрузка исчезла НЕ СЕГОДНЯ НОЧЬЮ, А РАНЬШЕ, то она здесь не удалится
        $mysqli->query("UPDATE `zavkaf_splits` SET `delete` = '1' WHERE `base_uid2` = '$base_uid2'");

        continue;
      }
      // прежняя нагрузка есть в текущем справочнике нагрузок
      // [позже] проверим, поменялось ли хотя бы одно поле в строке нагрузки и в ContentOfLoadStaff
      else
      {
        $some_changed = false;

        // Проверим, изменилось ли количество строк в xml_content_of_load для конкретного base_UID (а, значит, для строки таблица nagruzka) - это означает изменение споточенности (суффиксов)
        // Если это изменилось, то нужно очистить привязанного преподавателя
        if (sizeof($XMLContentOfLoadPrevByBaseUID2[$base_uid2]) != sizeof($XMLContentOfLoadByBaseUID2[$base_uid2]))
        {
          $some_changed = true;
          EchoLog("Для base_uid2 = $base_uid2 (uid = $xml_content_of_load_UID) изменилось количество строк в таблице xml_content_of_load, очистим преподавателя {$NagruzkaPrev[$base_uid2]['lecturer_fio']}");
        }

        // если изменился UID в таблице 1 (споточенность-суффикс или суффикс стал юидом привязанного в Галактике лектора),
        // то здесь строка не найдётся по прежнему UID, тогда тоже очистим лектора
        $new_nagr_row = $XMLContentOfLoad[$xml_content_of_load_UID];
        
        // -- если не заменяли uid выше
        if (!$new_nagr_row)
        {
          $some_changed = true;
          EchoLog("Для uid = $xml_content_of_load_UID не найдена строка в таблице xml_content_of_load, очистим преподавателя");
        }

        if ($new_nagr_row && $xml_content_of_load_prev_row['hash'] != $new_nagr_row['hash'])
        {
          $some_changed = true;

          EchoLog("Для uid = $xml_content_of_load_UID (base_uid2 = $base_uid2) в таблице xml_content_of_load изменился хеш ($xml_content_of_load_prev_row[hash] => $new_nagr_row[hash]), очистим преподавателя {$NagruzkaPrev[$base_uid2]['lecturer_fio']}");

          // if ($base_uid2 === '26589.281474976744972')
          // {
          //   EchoLog($new_nagr_row);
          //   EchoLog($xml_content_of_load_prev_row);
          // }
          // Если сменилась кафедра, добавим это событие в лог
          // Нагрузка может быть в статусах refused, done_refused (не обязательно), ниже статус будет сброшен в initial
          // Для done_refused, которые здесь не будут сброшены, ниже будем сбрасывать принудительно
          if ($new_nagr_row['UID_Chair'] && $xml_content_of_load_prev_row['UID_Chair'] && $new_nagr_row['UID_Chair'] !== $xml_content_of_load_prev_row['UID_Chair'])
          {
            ActivityLog($base_uid2, ["Изменение кафедры нагрузки", $xml_content_of_load_prev_row['UID_Chair'], $new_nagr_row['UID_Chair'], $XMLChairByUID[$xml_content_of_load_prev_row['UID_Chair']]['Name'], $XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']], "", "change_chair", 0, 0);
          }

          // if ($base_uid === '26589.281474976786399')
          // {
          //   EchoLog("base_uid: $base_uid");
          //   EchoLog("base_uid2: $base_uid2");
          //   EchoLog("UID: xml_content_of_load_UID");
          //   EchoLog("Prev hash: $xml_content_of_load_prev_row[hash]");
          //   EchoLog("New hash: $new_nagr_row[hash]");
          //   EchoLog("Хеши 1 изменились");
          // }
        }

        // сделаем сравнение строк load_staff: 

        if (is_array($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2]) && is_array($XMLContentOfLoadStaffByBaseUID2[$base_uid2]) && sizeof($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2]) != sizeof($XMLContentOfLoadStaffByBaseUID2[$base_uid2]))
        {
          $some_changed = true;
          EchoLog("Изменилось кол-во staff для base_uid2 = $base_uid2");
        }
        // если строк load_staff столько же, то сравним по каждой строке, изменились ли столбцы (соотв-но изменились хеши)
        elseif (is_array($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2]) && is_array($XMLContentOfLoadStaffByBaseUID2[$base_uid2]))
        {
          if ($XMLContentOfLoadStaffByBaseUID2[$base_uid2])
          {
            foreach ($XMLContentOfLoadStaffByBaseUID2[$base_uid2] as $load_staff_UID => $load_staff_new_row)
            {
              if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID])
              {
                // сравним хеши соотв. строк load_staff
                if ($XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID]['hash'] != $load_staff_new_row['hash'])
                {
                  $some_changed = true;

                  // if ($base_uid === '26589.281474976786399')
                  // {
                  //   EchoLog("base_uid: $base_uid");
                  //   EchoLog("base_uid2: $base_uid2");
                  //   // EchoLog("UID: $xml_content_of_load_prev_row[UID]");
                  //   EchoLog("Prev hash: {$XMLContentOfLoadStaffPrevByBaseUID2[$base_uid2][$load_staff_UID]['hash']}"); 
                  //   EchoLog("New hash: {$load_staff_new_row['hash']}");
                  //   // EchoLog($some_changed);
                  //   EchoLog("Хеши 2 изменились");
                  // }
                  
                }
              }
              else
              {
                $some_changed = true;
                break;
              }
            }
          }
          else
          {
            // EchoLog("base_uid $base_uid не определён в массиве таблицы текущей xml_content_of_load_staff");
          }
        }
        // сюда попадаем, если строке в таблице xml_content_of_load не соотв. ни одна строка в xml_content_of_load_staff
        // "это реальная нагрузка, но не дисциплины.. поэтому загружать надо, но в текущие списки "Дисциплины" не попадет, поскольку нет аббревиатур.. должна будет попадать в другие разделы"
        else
        {
          // EchoLog("\$XMLContentOfLoadStaffPrev [$base_uid] или \$XMLContentOfLoadStaff [$base_uid] - не массивы", 'file screen');
          // EchoLog($XMLContentOfLoadStaffPrev[$base_uid]);
          // EchoLog($XMLContentOfLoadStaff[$base_uid]);
        }

        // Что-то изменилось, нужно сбросить в нагрузке назначенного преподавателя
        if ($some_changed)
        {
          $chair_id = $XMLChairByUID[$new_nagr_row['UID_Chair']]['Code'];

          $query = "
            UPDATE `nagruzka` SET # `lecturer_fio` = NULL, `lecturer_uid` = NULL, `lecturer_person_id` = NULL, 
              `prev_status` = `status`, `status` = 'initial', `date_update` = NOW()
            WHERE  `load_base_UID2` = '$base_uid2' # `chair_id` = '$chair_id' AND";

          $Result = $mysqli->query($query);

          if ($Result)
          {
            // на самом деле лекторы здесь не хранятся
            $NagruzkaPrev[$base_uid2]['lecturer_fio'] = '';

            $mysqli->query("UPDATE `zavkaf_splits` SET `delete` = '1' WHERE `base_uid` = '$base_uid'");

            // -- выведем только если лектор был
            // if ($base_uid2 === '26589.281474976786399' || $NagruzkaPrev[$base_uid2]['lecturer_fio'])
            EchoLog("Очистили лекторов кафедры {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ($chair_id) у нагрузки base_uid = $base_uid, base_uid2 = $base_uid2");
          }
          else
          {
            EchoLog("ОШИБКА очистки лектора кафедры {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ({$new_nagr_row['UID_Chair']}, $chair_id) у нагрузки $base_uid2");
            EchoLog($query);
            $db_error = true;
          }

        }

        /*
        if ($new_nagr_row['UID_Lecturer'])
        {
          // EchoLog($new_nagr_row['UID_Lecturer']);
        }

        // Если у нагрузки в Галактике указан преподаватель, то его взять
        if ($new_nagr_row['UID_Lecturer'] && $NagruzkaPrev[$base_uid]['lecturer_uid'] != $new_nagr_row['UID_Lecturer'])
        {
          // EchoLog('here');
          $lecturer = $XMLLecturer[$new_nagr_row['UID_Lecturer']];

          // ? МЕНЯТЬ ЛИ СТАТУС ?
          $query = "
            UPDATE `nagruzka` SET `lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$new_nagr_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]'
            WHERE `load_base_UID` = '$base_uid'";

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("ОШИБКА простановки лектора кафедры (из Галактики) {$XMLChairByUID[$new_nagr_row['UID_Chair']]['Name']} ({$new_nagr_row['UID_Chair']}, $chair_id) у нагрузки $base_uid");
            EchoLog($query);
          }
        }

        */

      }
      
    }


    foreach ($XMLContentOfLoad as $xml_content_of_load_row)
    {
      // EchoLog("HERE 1");

      // 25031.0 - код отсутствия кафедры
      // Если в нагрузке указана кафедра, то она должна быть и в справочнике $XMLChairByUID, тогда и в Сотруднике ($Podrazdelenia) она должна быть и как deleted = 0 (за исключением случаев "псевдо-кафедр")
      if ($xml_content_of_load_row['UID_Chair'] && $xml_content_of_load_row['UID_Chair'] != '25031.0')
      {
        $chair_id = $XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Code'];
        $chair_name = $XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Name'];
        $department_id = $department_name = "";

        // if ($chair_id == '02910')
        // {
        //   EchoLog($Podrazdelenia[$chair_id]);
        //   $mysqli->query("ROLLBACK");
        //   exit;
        // }

        // Такое подразделение есть в Сотруднике (кафедра)
        if ($Podrazdelenia[$chair_id])
        {
          $department_id = $Podrazdelenia[$chair_id]['ukrup_code'];
          $department_name = $Podrazdelenia[$chair_id]['ukrup_name'];

          if ($Podrazdelenia[$chair_id]['has_real_chief'])
          {
            $zavkaf_id = $Podrazdelenia[$chair_id]['chief_id'];
            $zavkaf_fio = $Podrazdelenia[$chair_id]['chief_fio'];
            $zavkaf_login = $Person[$zavkaf_id]['alias'];
          }
          else
          {
            $zavkaf_id = $zavkaf_fio = $zavkaf_login = '';
          }
        }
        /*
          есть псевдо-кафедры типа "Кафедра ЦИНиРАО".. код подразделения у них левый, поскольку у этой кафедры факультет это центр, куда зачислены студенты, а самой кафедры нет.. тоже самое будет и у вшопф и еще где-то..
          соответственно система не может определить завкафа.. тут нужна проверка.. если у кафедры стоит несуществующий код подразделения, то завкафом ставить руководителя факультета этой кафедры
        */
        // Такого подразделения нет в Сотруднике, завкафом ставить руководителя "факультета" этой кафедры
        else
        {
          $faculty_uid = $XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['UID_Faculty'];
          $parent_fac_code = $XMLFacultyByUID[$faculty_uid]['Code'];

          if ($Podrazdelenia[$parent_fac_code]['has_real_chief'])
          {
            $zavkaf_id = $Podrazdelenia[$parent_fac_code]['chief_id'];
            $zavkaf_fio = $Podrazdelenia[$parent_fac_code]['chief_fio'];
            $zavkaf_login = $Person[$zavkaf_id]['alias'];

            $department_id = $Podrazdelenia[$parent_fac_code]['id'];
            $department_name = $Podrazdelenia[$parent_fac_code]['pname'];
          }
          else
          {
            $zavkaf_id = $zavkaf_fio = $zavkaf_login = '';
          }
        }

        $zavkaf_sql = ", `zavkaf_login` = '$zavkaf_login', `zavkaf_id` = '$zavkaf_id', `zavkaf_fio` = '$zavkaf_fio'";

        // if ($xml_content_of_load_row['UID_Chair'] == '25031.281474976763050')
        // {
        //   EchoLog($xml_content_of_load_row);
        //   EchoLog($parent_fac_code);
        //   EchoLog($Podrazdelenia[$parent_fac_code]);
        //   EchoLog($chair_name);
        //   EchoLog($department_id);
        //   EchoLog($department_name);
        //   EchoLog($zavkaf_id);
        //   EchoLog($zavkaf_fio);
        //   EchoLog($zavkaf_login);
        // }

        // EchoLog($chair_id);

        // echo "$chair_id<br>";
        // Кафедра у нагрузки не пустая
        /* TG 13.11.25: если пришла нагрузка и там есть кафедра, то она точно есть в Chairs.xml.. но сама кафедра в Chairs.xml может быть неактуальной уже.. для этого надо проверить по коду подразделения что это актуальное подразделение.. и если такого подразделения реально уже нет, то такую нагрузку надо помечать как невалидную.. также как и отсутствие кафедры (код 25031.0)
          название кафедры (если есть UID) всегда берем из Chairs.xml
        */


        // EchoLog("HERE 2");

        // такой нагрузки на кафедре ещё не было
        if (!$NagruzkaPrev[$xml_content_of_load_row['base_uid2']])
        {
          if ($xml_content_of_load_row['base_uid2'] === '26589.281474976916746')
          {
            // EchoLog("HERE 3");
            // EchoLog($xml_content_of_load_row);
          }

          if ($XMLChairByCode[$chair_id]) // === true
          {
            // EchoLog("HERE 4");

            $lecturer = $XMLLecturer[$xml_content_of_load_row['UID_Lecturer']];

            // Определим, является ли нагрузка "Руководством практики"
            // Возьмём первую попавшуюся соотв. строку из 2й таблицы
            // $some_xml_content_of_load_staff = array_values($XMLContentOfLoadStaffByBaseUID2[$xml_content_of_load_row['base_uid2']])[0];

            // IsNagruzkaRukPractice($some_xml_content_of_load_staff['Abbr']);

            // признак актуальности подразделения в Сотруднике
            // Как мы поняли, в нагрузке не должно быть не актуальных кафедр
            // if (!$Podrazdelenia[$chair_id]['deleted'])
            {
              // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра актуальна");

              $lecturer['FIO'] = str_replace('!_Вакансия_!', 'Вакансия', $lecturer['FIO']);

              $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = '$chair_id', `chair_name` = '$chair_name', `department_id` = '$department_id', `department_name` = '$department_name', `load_base_UID2` = '$xml_content_of_load_row[base_uid2]', `valid` = '1' $zavkaf_sql";

              if ($lecturer['FIO'] == 'Фомина Ирина Юрьевна')
              {
                // EchoLog($query);
              }
            }
            // Кафедра не актуальна в Сотруднике:
            // [Предположительно, тогда она пропадёт и в xml_chair, тогда и $chair_id не будет]
            // нагрузку пометим невалидной, а название кафедры возьмём в Сотруднике
            /*
            else
            {
              // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра НЕ актуальна");
              $chair_name = $Podrazdelenia[$chair_id]['pname'];
              $department_name = $Podrazdelenia[$chair_id]['ukrup_name'];

              $lecturer['FIO'] = str_replace('!_Вакансия_!', 'Вакансия', $lecturer['FIO']);

              $query = "INSERT IGNORE INTO `nagruzka` SET `chair_id` = NULL, `chair_name` = '$chair_name', `department_id` = NULL, `department_name` = '$department_name', `load_base_UID2` = '$xml_content_of_load_row[base_uid2]', `valid` = '0', `zavkaf_login` = NULL, `zavkaf_id` = NULL, `zavkaf_fio` = NULL";
            }
            */
          }

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("Error #153 inserting into `nagruzka`: $query", "file mail");
            EchoLog($mysqli->error, "file mail");
            $db_error = true;
          }
          elseif ($mysqli->affected_rows)
          {
            ActivityLog($xml_content_of_load_row['base_uid2'], ["Нагрузка добавлена на кафедру $chair_name", $chair_id, $xml_content_of_load_row['base_uid2']], "", "initial", 0, 1);
          }
        }
        else
        {
          // Нагрузка была и есть.
          // Возьмём нагрузку в "очереди" в статусе done_change
          // Это когда зав. каф. подаёт заявку на изменение, а админ УОУП нажимает "Выполнено"
          // При этом комментарий идёт в публичную историю, а нагрузка становится initial
          if ($NagruzkaPrev[$xml_content_of_load_row['base_uid2']]['status'] == 'done_change')
          {
            $Rows = GetRows('log', ['load_base_UID2' => $xml_content_of_load_row['base_uid2'], 'action_name' => 'done_change'], null, "`datetime` DESC");

            if ($Rows)
            {
              $LastLogRow = $Rows[0];

              // строка лога, которую инициировал УОУП, когда нажал "Выполнено", станет публичной
              // для информативности добавим строку в лог об этом автоматическом событии

              $Result = $mysqli->query("UPDATE `log` SET `internal` = '0' WHERE `id` = '$LastLogRow[id]'");

              if (!$Result)
              {
                EchoLog($mysqli->error);
                EchoLog($query);
                $db_error = true;
              }

              ActivityLog($xml_content_of_load_row['base_uid2'], ['Разблокировка нагрузки в кроне из done_change', $chair_id, $chair_name, $zavkaf_fio], "", 'initial', 1, 1);

              $Result = $mysqli->query("UPDATE `nagruzka` SET `status` = 'initial', `date_update` = NOW() WHERE `load_base_UID2` = '$xml_content_of_load_row[base_uid2]'");

              if (!$Result)
              {
                EchoLog($mysqli->error);
                $db_error = true;
              }
            }
          }


          // Обновим признак валидности нагрузки по актуальности подразделения (кафедры) в Сотруднике
          // признак актуальности подразделения в Сотруднике
          // Закомментировано: мы поняли, что в нагрузке кафедра всегда актуальна, если она проставлена
          /*
          if (!$Podrazdelenia[$chair_id]['deleted'])
          {
            $valid = '1';
          }
          // Кафедра не актуальна в Сотруднике:
          // нагрузку пометим невалидной
          else
          {
            // EchoLog("base_uid = $xml_content_of_load_row[base_uid], chair_id = $chair_id кафедра НЕ актуальна");
            $valid = '0';
          }
          */

          $valid = '1';

          // кафедра может измениться
          $query = "
          UPDATE `nagruzka` SET `chair_id` = '$chair_id', `chair_name` = '$chair_name', `department_id` = '$department_id', `department_name` = '$department_name', `valid` = '$valid', `date_update` = NOW() $zavkaf_sql
          WHERE `load_base_UID2` = '$xml_content_of_load_row[base_uid2]'";

          $Result = $mysqli->query($query);

          // if ($chair_id == '02910')
          // {
          //   EchoLog($Podrazdelenia[$chair_id]);
          //   EchoLog($query);
          //   $mysqli->query("ROLLBACK");
          //   exit;
          // }

          if (!$Result)
          {
            EchoLog($mysqli->error);
            EchoLog($query);
            $db_error = true;
          }


          if ($xml_content_of_load_row['UID_Lecturer'])
          {
            // EchoLog($xml_content_of_load_row['UID_Lecturer']);
          }

          // -- Если у нагрузки в Галактике указан преподаватель, то его взять, но только если система в режиме выверки
          // По документу ЛК ЗК (режимы) этот код стал не актуальным
          /*
          if ($xml_content_of_load_row['UID_Lecturer'] && $xml_content_of_load_row['UID_Lecturer'] != '-1' && $NagruzkaPrev[$xml_content_of_load_row['base_uid2']]['lecturer_uid'] != $xml_content_of_load_row['UID_Lecturer'] && $_system_mode == 'mode_verification')
          {
            // EchoLog('here');
            $lecturer = $XMLLecturer[$xml_content_of_load_row['UID_Lecturer']];

            $lecturer['FIO'] = str_replace('!_Вакансия_!', 'Вакансия', $lecturer['FIO']);

            // ? МЕНЯТЬ ЛИ СТАТУС ?
            $query = "
              UPDATE `nagruzka` SET #`lecturer_fio` = '$lecturer[FIO]', `lecturer_uid` = '$xml_content_of_load_row[UID_Lecturer]', `lecturer_person_id` = '$lecturer[Tab_number]', 
                `date_update` = NOW()
              WHERE `load_base_UID2` = '$xml_content_of_load_row[base_uid2]'";

            $Result = $mysqli->query($query);

            if (!$Result)
            {
              EchoLog("ОШИБКА простановки лектора кафедры (из Галактики) {$XMLChairByUID[$xml_content_of_load_row['UID_Chair']]['Name']} ({$xml_content_of_load_row['UID_Chair']}) у нагрузки $xml_content_of_load_row[base_uid2]");
              EchoLog($query);
              EchoLog($mysqli->error);
              $db_error = true;
            }
          }
          */


        }
      }
      // иначе нагрузка не распределена на кафедру, пометим как valid = 0 (чтобы не выдавать завкафам, но выдавать УОУП в разделе плохих нагрузок no_chairs)
      else
      {
        // INSERT IGNORE не подходит, потому что, если у нагрузки кафедра исчезла, то это не обновит строку, которая уже есть
        $query = "REPLACE INTO `nagruzka` SET `chair_id` = NULL, `chair_name` = NULL, `department_id` = NULL, `department_name` = NULL, `zavkaf_login` = NULL, `zavkaf_id` = NULL, `zavkaf_fio` = NULL, `load_base_UID2` = '$xml_content_of_load_row[base_uid2]', `valid` = '0'";

          $Result = $mysqli->query($query);

          if (!$Result)
          {
            EchoLog("Error #842 inserting into `nagruzka`: $query", "file");
            EchoLog($mysqli->error, "file");
            EchoLog($query);
            $db_error = true;
          }
      }
    }

    // Сбросим done_refused в initial для тех строк нагрузки, у которых не поменялась кафедра (решили не менять)
    // Будет правильно, если это будет происходить только ночью (без лайтовых запусков)
    if ($LOAD_NEW_DATA_FROM_NETWORK)
    {
      $mysqli->query("UPDATE `nagruzka` SET `prev_status` = 'done_refused', `status` = 'initial', `date_update` = NOW() WHERE `status` = 'done_refused'");

      if (!$Result)
      {
        EchoLog("Error #693: $query", "file");
        EchoLog($mysqli->error, "file");
        EchoLog($query);
        $db_error = true;
      }
    }




  }
  // исчезло более трети строк нагрузки в справочнике, отправим письмо
  elseif ($prev_rows_count)
  {
    $disappears_num_percent = round($rows_gone_counter / $prev_rows_count * 100);
    $message_subject = "ЛК ЗК cron: слишком много строк нагрузки пропадает";
    $message_text = "Пропадает $disappears_num_percent% строк нагрузки. Обработка не выполнена.";

    mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text);
    mail_utf8($_admin_mail, $_site_domain, $_from_mail, $message_subject, $message_text);
    EchoLog($message_text, 'file');
  }
}
else
{
  EchoLog("ЛК ЗК: Пустая таблица нагрузки 1", 'file mail');
}






if ($db_error)
{
  EchoLog('ROLLBACK все запросы', 'file screen');
  $mysqli->query("ROLLBACK");
}
else
{
  EchoLog('COMMIT все запросы', 'file screen');
  $mysqli->query("COMMIT");
}



EchoLog("END cron");
echo "<br>Конец скрипта<br>";


$peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
EchoLog("Peak memory usage: {$peakMemory} MB");


?>