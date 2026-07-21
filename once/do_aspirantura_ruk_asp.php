<?

include_once '../functions.php';

include '../connect/sotrudnik.php';

$Person = GetTable('person', "", "", "id", "id, alias");

include '../connect.php';

$_system_mode = GetSystemMode();

if ($_system_mode != 'mode_verification')
{
  EchoLog("do_aspirantura_ruk_asp.php: wrong system mode $_system_mode");
}

$XMLChairByUID = GetTable('xml_chair', "", "", "UID");
$XMLFacultyByUID = GetTable('xml_faculty', "", "", "UID");
$XMLLecturerByUID = GetTable('xml_lecturer', "", "", "UID");
$XMLPostByUID = GetTable('xml_post', "", "", "UID");
$XMLDisciplineByUID = GetTable('xml_discipline', "", "", "UID");
$XMLGroupByUID = GetTable('xml_group', "", "", "UID", 'UID, Name');
$AspiranturaRukAsp = GetRows('xml_content_of_load', ['nagruzka_type' => 'aspirantura_ruk_asp']);


echo sizeof($AspiranturaRukAsp);
// Не можем очищать таблицу, т.к. из ГУВ идут не все поля: даже нет полей аспирантских (uid) в данный момент
// $mysqli->query("TRUNCATE `aspirantura_ruk_asp`");
// Скорее всего нужно будет удалить строки с пустым load_id

$AspiranturaRukAspCurrentByLoadId = GetTable('aspirantura_ruk_asp', "`load_id` IS NOT NULL AND `load_id` <> ''", "", "load_id");

foreach ($AspiranturaRukAsp as $row)
{
  $chair_id = $XMLChairByUID[$row['UID_Chair']]['Code'];
  $chair_name = $XMLChairByUID[$row['UID_Chair']]['Name'];
  // факультет (3-й столбец)
  $faculty_uid = $XMLChairByUID[$row['UID_Chair']]['UID_Faculty'];
  $faculty_id = $XMLFacultyByUID[$faculty_uid]['Code'];
  $faculty_name = $XMLFacultyByUID[$faculty_uid]['Name'];
  $department_id = $XMLFacultyByUID[$faculty_uid]['Code'];

  $lecturer_uid = $row['UID_Lecturer'];

  // лектор-человек
  if ($lecturer_uid && $lecturer_uid != '-1')
  {
    $lecturer_fio = $XMLLecturerByUID[$lecturer_uid]['FIO'];
    // $lecturer_dolzhnost = $XMLPostByUID[$XMLLecturerByUID[$lecturer_uid]['UID_Post']]['Name'];
    // $stavka = $XMLLecturerByUID[$lecturer_uid]['Rate'];
    $lecturer_person_id = $XMLLecturerByUID[$lecturer_uid]['Tab_number'];
    // Кафедра преподавателя
    $lecturer_chair_uid = $XMLLecturerByUID[$lecturer_uid]['UID_Chair'];

    if ($XMLChairByUID[$lecturer_chair_uid])
    {
      $lecturer_chair_id = $XMLChairByUID[$lecturer_chair_uid]['Code'];
      $lecturer_chair_name = $XMLChairByUID[$lecturer_chair_uid]['Name'];
      // Факультет преподавателя
      $lecturer_faculty_uid = $XMLChairByUID[$lecturer_chair_uid]['UID_Faculty'];
    }
    // ГПХ и псевдо-фак.
    else
    {
      $lecturer_chair_id = $XMLFacultyByUID[$lecturer_chair_uid]['Code'];
      $lecturer_chair_name = $XMLFacultyByUID[$lecturer_chair_uid]['Name'];
      // Факультет преподавателя
      $lecturer_faculty_uid = $XMLFacultyByUID[$lecturer_chair_uid]['UID'];
    }

    $lecturer_faculty_id = $XMLFacultyByUID[$lecturer_faculty_uid]['Code'];
    $lecturer_faculty_name = $XMLFacultyByUID[$lecturer_faculty_uid]['Name'];
    $login = $Person[$lecturer_person_id]['alias'];
  }
  // лектор -1, т.е. его нет
  else
  {
    $lecturer_fio = $lecturer_person_id = $lecturer_chair_id = $lecturer_chair_name = $lecturer_faculty_id = $lecturer_faculty_name = $lecturer_uid = $login = '';
  }
  

  $groups = $XMLGroupByUID[$row['UID_Group']]['Name'];
  // !! $course = ... ПОКА КУРС НЕ ПРИХОДИТ, не обновляем

  // только поля, которые идут из ГУВ, чтобы не перезатереть те, которые не приходят
  $common_sql = "
    `base_uid` = '$row[base_uid]',
    `department_id` = '$faculty_id',
    `department` = '$faculty_name',
    #`group` = '$groups',
    `lecturer_chair_id` = '$lecturer_chair_id',
    `lecturer_chair_name` = '$lecturer_chair_name',
    `lecturer_department_id` = '$lecturer_faculty_id',
    `lecturer_department_name` = '$lecturer_faculty_name',
    `lecturer_person_id` = '$lecturer_person_id',
    `lecturer_uid` = '$lecturer_uid',
    `lecturer_fio` = '$lecturer_fio',
    `lecturer_login` = '$login'
  ";

  // Строка из ГУВ с LoadId (значит, нагрузка прежде создана в ЛК ЗК)
  // В этом случае обновляем строку
  if ($row['LoadId'] && $AspiranturaRukAspCurrentByLoadId[$row['LoadId']])
  {
    $row['LoadId'] = quote_smart($row['LoadId']);

    $query = "UPDATE `aspirantura_ruk_asp`
                    SET
                    $common_sql,
                    `deleted` = '0',
                    `date_update` = NOW()
                    WHERE `load_id` = '$row[LoadId]'
                    ";
  }
  // в строке из ГУВ нет load_id - эта строка создана в ГУВ, добавить её
  elseif (!$row['LoadId'])
  {
    $query = "INSERT INTO `aspirantura_ruk_asp`
                    SET
                    `uid` = NULL,
                    `UID_Semester` = NULL,
                    `load_id` = NULL,
                    `fio` = '',
                    `napravlenie_code` = '',
                    `napravlenie_title` = '',
                    `course` = '',
                    `deleted` = '0',
                    $common_sql,
                    `date` = NOW()
                    ";
  }

  $Result = $mysqli->query($query);

  if (!$Result)
  {
    echo $mysqli->error . '<br>';
    echo $query . '<br>';
    // exit;
  }
}


?>