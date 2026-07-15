<?

// !! TODO У некоторых кафедра и факультет пустые (псевдо-кафедры, декан)

include '../functions.php';

include '../connect/sotrudnik.php';

$Person = GetTable('person', "", "", "id", "id, alias");

include '../connect.php';

$_system_mode = GetSystemMode();

if ($_system_mode != 'mode_verification')
{
  EchoLog("do_aspirantura_kand_exam.php: wrong system mode $_system_mode");
}

$XMLChairByUID = GetTable('xml_chair', "", "", "UID");
$XMLFacultyByUID = GetTable('xml_faculty', "", "", "UID");
$XMLLecturerByUID = GetTable('xml_lecturer', "", "", "UID");
$XMLPostByUID = GetTable('xml_post', "", "", "UID");
$XMLDisciplineByUID = GetTable('xml_discipline', "", "", "UID");
$XMLGroupByUID = GetTable('xml_group', "", "", "UID", 'UID, Name');
$AspiranturaKandExam = GetRows('xml_content_of_load', ['nagruzka_type' => 'aspirantura_kand_exam']);

// ! Таблица очищается, некоторые поля очищаются
echo sizeof($AspiranturaKandExam);
$mysqli->query("TRUNCATE `aspirantura_kand_exam`");

foreach ($AspiranturaKandExam as $row)
{
  $chair_id = $XMLChairByUID[$row['UID_Chair']]['Code'];
  $chair_name = $XMLChairByUID[$row['UID_Chair']]['Name'];
  // факультет (1-й столбец)
  $faculty_uid = $XMLChairByUID[$row['UID_Chair']]['UID_Faculty'];
  $faculty_name = $XMLFacultyByUID[$faculty_uid]['Name'];
  // $department_id = $XMLFacultyByUID[$faculty_uid]['Code'];

  $lecturer_uid = $row['UID_Lecturer'];
  $lecturer_fio = $XMLLecturerByUID[$lecturer_uid]['FIO'];
  $lecturer_dolzhnost = $XMLPostByUID[$XMLLecturerByUID[$lecturer_uid]['UID_Post']]['Name'];
  $stavka = $XMLLecturerByUID[$lecturer_uid]['Rate'];
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

  if ($row['UID_Language'] === $_language_eng_uid) $lang = 'английский';
  elseif ($row['UID_Language'] === $_language_rus_uid) $lang = 'русский';
  else $lang = '';

  $discipline_title = $XMLDisciplineByUID[$row['UID_Discipline']]['Name'];

  $groups = $XMLGroupByUID[$row['UID_Group']]['Name'];

  $query = "INSERT INTO `aspirantura_kand_exam` SET
      `load_id` = '$row[LoadId]',
      `base_uid` = '$row[base_uid]',
      `bup_nrec` = '',
      `bup_department_name` = '$faculty_name',
      `bup_language` = '$lang',
      `disc_nrec` = '',
      `disc_abr` = '',
      `disc_title` = '$discipline_title',
      `exam_semester` = '$row[UID_Semester]',
      `groups` = '$groups',
      `groups_uid` = '$row[UID_Group]',
      `students_num` = '$row[StudentAmount]',
      `chair_id` = '$lecturer_chair_id',
      `chair_name` = '$lecturer_chair_name',
      `department_id` = '$lecturer_faculty_id',
      `department_name` = '$lecturer_faculty_name',
      `lecturer_person_id` = '$lecturer_person_id',
      `lecturer_uid` = '$lecturer_uid',
      `lecturer_fio` = '$lecturer_fio',
      `lecturer_login` = '$login',
      `date` = NOW(),
      `date_update` = NULL
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