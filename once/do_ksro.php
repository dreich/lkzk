<?

include_once '../functions.php';

include '../connect/sotrudnik.php';

$Person = GetTable('person', "", "", "id", "id, alias");

include '../connect.php';

$_system_mode = GetSystemMode();

if ($_system_mode != 'mode_verification')
{
  EchoLog("do_ksro.php: wrong system mode $_system_mode");
}

$XMLChairByUID = GetTable('xml_chair', "", "", "UID");
$XMLFacultyByUID = GetTable('xml_faculty', "", "", "UID");
$XMLLecturerByUID = GetTable('xml_lecturer', "", "", "UID");
$XMLPostByUID = GetTable('xml_post', "", "", "UID");
$GUVKSRO = GetRows('xml_content_of_load', ['nagruzka_type' => 'ksro']);

$table_name = "ksro_guv";

echo sizeof($GUVKSRO);
$mysqli->query("TRUNCATE `$table_name`");

foreach ($GUVKSRO as $row)
{
  if ($row['UID_Lecturer'] == '-1') continue;
  
  $chair_id = $XMLChairByUID[$row['UID_Chair']]['Code'];
  $faculty_uid = $XMLChairByUID[$row['UID_Chair']]['UID_Faculty'];

  $department_id = $XMLFacultyByUID[$faculty_uid]['Code'];

  $lecturer_uid = $row['UID_Lecturer'];
  $lecturer_fio = $XMLLecturerByUID[$lecturer_uid]['FIO'];
  $lecturer_dolzhnost = $XMLPostByUID[$XMLLecturerByUID[$lecturer_uid]['UID_Post']]['Name'];
  $stavka = $XMLLecturerByUID[$lecturer_uid]['Rate'];
  $lecturer_person_id = $XMLLecturerByUID[$lecturer_uid]['Tab_number'];
  $login = $Person[$lecturer_person_id]['alias'];

  $query = "INSERT INTO `$table_name` SET
      `load_id` = '$row[LoadId]',
      `base_uid` = '$row[base_uid]',
      `chair_id` = '$chair_id',
      `department_id` = '$department_id',
      `lecturer_person_id` = '$lecturer_person_id',
      `uid` = '$lecturer_uid',
      `lecturer_fio` = '$lecturer_fio',
      `login` = '$login',
      `stavka` = '$stavka',
      `dolzhnost` = '$lecturer_dolzhnost',
      `UID_Language` = '$row[UID_Language]',
      `UID_Semester` = '$row[UID_Semester]',
      `Amount` = '$row[Amount]',
      `UID_KindOfWork` = '$row[UID_KindOfWork]',
      `UID_Discipline` = '$row[UID_Discipline]',
      `UID_Chair` = '$row[UID_Chair]',
      `UID_FacultyPerformer` = '$faculty_uid'
    ";

  $Result = $mysqli->query($query);

  if (!$Result)
  {
    echo $mysqli->error . '<br>';
    echo $query . '<br>';
    // exit;
  }
}


?>