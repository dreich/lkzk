<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('lkzk');
    session_start();
}

if (empty($_SESSION['c_roles'])) {
    http_response_code(403);
    echo 'Forbidden. Please log in.';
    return;
}

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$c_roles = function_exists('ExplodePalki') ? ExplodePalki($_SESSION['c_roles'], true) : [];

// Access check
$hasAccess = false;
$filter_faculty_uid = null;
$filter_chair_uid = null;

if (!empty($c_roles['uoup'])) {
    $hasAccess = true;
    $filter_faculty_uid = isset($_GET['faculty_uid']) ? $_GET['faculty_uid'] : null;
    $filter_chair_uid = isset($_GET['chair_uid']) ? $_GET['chair_uid'] : null;
} elseif (!empty($c_roles['dean'])) {
    $hasAccess = true;
    $dean_dep_id = $_SESSION['c_department_id']; // Code in xml_faculty
    $faculty = GetRow('xml_faculty', ['Code' => $dean_dep_id]);
    if ($faculty) {
        $filter_faculty_uid = $faculty['UID'];
    }
    $filter_chair_uid = isset($_GET['chair_uid']) ? $_GET['chair_uid'] : null;
} elseif (!empty($c_roles['zavkaf'])) {
    $hasAccess = true;
    $zavkaf_chair_id = $_SESSION['c_chair_id']; // Code in xml_chair
    $chair = GetRow('xml_chair', ['Code' => $zavkaf_chair_id]);
    if ($chair) {
        $filter_chair_uid = $chair['UID'];
    }
}

if (!$hasAccess) {
    http_response_code(403);
    echo 'Access denied.';
    return;
}

// Build query
$where = [];
if ($filter_faculty_uid) {
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'owner';
    if ($filter_type === 'owner') {
        $where[] = "ls.UID_FacultyOwner = '" . $mysqli->real_escape_string($filter_faculty_uid) . "'";
    } elseif ($filter_type === 'performer') {
        $chairs = GetRows('xml_chair', ['UID_Faculty' => $filter_faculty_uid]);
        $chair_uids = [];
        if ($chairs) {
            foreach ($chairs as $ch) {
                $chair_uids[] = "'" . $mysqli->real_escape_string($ch['UID']) . "'";
            }
            if ($chair_uids) {
                $where[] = "l.UID_Chair IN (" . implode(',', $chair_uids) . ")";
            } else {
                $where[] = "0=1";
            }
        } else {
            $where[] = "0=1";
        }
    }
}
if ($filter_chair_uid) {
    $where[] = "l.UID_Chair = '" . $mysqli->real_escape_string($filter_chair_uid) . "'";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";

$query = "
    SELECT
        l.*,
        ls.UID_FacultyOwner,
        ls.UID_FacultyPerformer,
        ls.UID_Speciality,
        ls.UID_Specialization,
        ls.UID_FormOfEducation,
        ls.Abbr,
        f_owner.Name as FacultyOwnerName,
        c.Name as ChairName,
        c.Code as ChairCode,
        f_perf.Name as FacultyPerformerName,
        d.Name as DisciplineName,
        lec.FIO as LecturerFIO,
        lec.Tab_number as LecturerPersonId,
        sp.Name as SpecialityName,
        sp.education_level,
        kw.Name as KindOfWorkName
    FROM xml_content_of_load l
    LEFT JOIN xml_content_of_load_staff ls ON ls.UID_ContentOfLoad = l.UID
    LEFT JOIN xml_faculty f_owner ON f_owner.UID = ls.UID_FacultyOwner
    LEFT JOIN xml_chair c ON c.UID = l.UID_Chair
    LEFT JOIN xml_faculty f_perf ON f_perf.UID = c.UID_Faculty
    LEFT JOIN xml_discipline d ON d.UID = l.UID_Discipline
    LEFT JOIN xml_lecturer lec ON lec.UID = l.UID_Lecturer
    LEFT JOIN xml_speciality sp ON sp.UID = ls.UID_Speciality
    LEFT JOIN xml_kind_of_work kw ON kw.UID = l.UID_KindOfWork
    $where_sql
";

$result = $mysqli->query($query);
if (!$result) {
    echo "Error executing query: " . $mysqli->error;
    return;
}

$groups = [];
$res = $mysqli->query("SELECT UID, Name, Number, YearOfEntry, YearOfEducation FROM xml_group");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $groups[$row['UID']] = $row;
    }
}

$sotrudniki = [];
$res = $mysqli->query("SELECT person_id, chair_id, dolzhnost, pku, stavka FROM sotrudniki");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $sotrudniki[$row['person_id'] . '_' . $row['chair_id']] = $row;
    }
}

$courseNames = [];
$res = $mysqli->query("SELECT UID, Name FROM xml_course");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $courseNames[$row['UID']] = $row['Name'];
    }
}

$langNames = [];
$res = $mysqli->query("SELECT UID, Name FROM xml_language");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $langNames[$row['UID']] = $row['Name'];
    }
}

$formNames = [];
$res = $mysqli->query("SELECT UID, Name FROM xml_form_of_education");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $formNames[$row['UID']] = $row['Name'];
    }
}

$semesters = [];
$res = $mysqli->query("SELECT UID, Name FROM xml_semester");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $semesters[$row['UID']] = $row['Name'];
    }
}

$col_mapping = [
    'Лекция' => 18,
    'Практика (семинарские занятия)' => 19,
    'Лабораторная' => 20,
    'Консультации перед экзаменом' => 21,
    'Зачет' => 22,
    'Зачет по практике' => 22,
    'Дифференцированный зачет' => 23,
    'Дифференцированный зачет по практике' => 23,
    'Экзамен' => 24,
    'Промежуточная аттестация по курсовой работе (проекту)' => 25,
    'Участие в комиссии' => 26,
    'Участие в комиссии (председатель)' => 27,
    'Контрольная работа' => 28,
    'Руководство курсовой работой (проектом)' => 29,
    'Организация курсовой работы (проекта)' => 30,
    'Практика выездная' => 31,
    'Практика групповая в организации' => 32,
    'Практика групповая в университете' => 33,
    'Практика индивидуальная в организации' => 34,
    'Практика индивидуальная в университете' => 35,
    'Руководство ВКР' => 36
];

$templatePath = __DIR__ . '/Шаблон отчёта по учебной нагрузке.xlsx';
$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getActiveSheet();

$rowIdx = 3;

while ($row = $result->fetch_assoc()) {

    $groupUids = explode(',', $row['UID_Group']);
    $groupNames = [];
    $yearsOfEntry = [];

    foreach ($groupUids as $gUid) {
        $gUid = trim($gUid);
        if (isset($groups[$gUid])) {
            $groupNames[] = $groups[$gUid]['Name'];
            $num = $groups[$gUid]['Number'];
            if (strlen($num) >= 4) {
                $year = '20' . substr($num, 2, 2);
                $yearsOfEntry[$year] = $year;
            }
        }
    }

    $groupStr = implode(', ', array_unique($groupNames));
    $yearOfEntryStr = implode(', ', array_keys($yearsOfEntry));

    $fio = $row['LecturerFIO'];
    if (empty($row['UID_Lecturer']) || $row['UID_Lecturer'] == '26115.281474976893938' || $row['UID_Lecturer'] == '-1') {
        $fio = 'Вакансия';
    }

    $dolzhnost = '';
    $pku = '';
    $stavka = '';

    if ($fio !== 'Вакансия' && !empty($row['LecturerPersonId']) && !empty($row['ChairCode'])) {
        $key = $row['LecturerPersonId'] . '_' . $row['ChairCode'];
        if (isset($sotrudniki[$key])) {
            $dolzhnost = $sotrudniki[$key]['dolzhnost'];
            $pku = $sotrudniki[$key]['pku'];
            $stavka = $sotrudniki[$key]['stavka'];
        }
    }

    $amount = (float)str_replace(',', '.', $row['Amount']);
    $studentAmount = (float)str_replace(',', '.', $row['StudentAmount']);

    $sheet->setCellValueByColumnAndRow(1, $rowIdx, $row['FacultyOwnerName']);
    $sheet->setCellValueByColumnAndRow(2, $rowIdx, $row['FacultyPerformerName']);
    $sheet->setCellValueByColumnAndRow(3, $rowIdx, $row['ChairName']);
    $sheet->setCellValueByColumnAndRow(4, $rowIdx, $fio);
    $sheet->setCellValueByColumnAndRow(5, $rowIdx, $dolzhnost);
    $sheet->setCellValueByColumnAndRow(6, $rowIdx, $pku);
    $sheet->setCellValueByColumnAndRow(7, $rowIdx, $stavka);
    $sheet->setCellValueByColumnAndRow(8, $rowIdx, $row['DisciplineName']);
    $sheet->setCellValueByColumnAndRow(9, $rowIdx, $groupStr);

    $sheet->setCellValueByColumnAndRow(10, $rowIdx, $row['education_level']);
    $sheet->setCellValueByColumnAndRow(11, $rowIdx, $row['SpecialityName']);
    $sheet->setCellValueByColumnAndRow(12, $rowIdx, '');

    $langUID = $row['UID_Language'];
    $lang = isset($langNames[$langUID]) ? $langNames[$langUID] : '';
    if (empty($lang)) {
        if ($langUID == '26002.281474976711674') $lang = 'Английский';
        elseif ($langUID) $lang = 'Русский';
    }
    $sheet->setCellValueByColumnAndRow(13, $rowIdx, $lang);

    $formUID = $row['UID_FormOfEducation'];
    $form = isset($formNames[$formUID]) ? $formNames[$formUID] : '';
    $sheet->setCellValueByColumnAndRow(14, $rowIdx, $form);

    $courseUID = $row['UID_Course'];
    $course = isset($courseNames[$courseUID]) ? $courseNames[$courseUID] : '';
    $sheet->setCellValueByColumnAndRow(15, $rowIdx, $course);

    $semesterUID = $row['UID_Semester'];
    $semesterName = isset($semesters[$semesterUID]) ? $semesters[$semesterUID] : '';
    $semester = '';
    if (strpos(mb_strtolower($semesterName, 'UTF-8'), 'осен') !== false) {
        $semester = 'О';
    } elseif (strpos(mb_strtolower($semesterName, 'UTF-8'), 'весен') !== false) {
        $semester = 'В';
    }
    $sheet->setCellValueByColumnAndRow(16, $rowIdx, $semester);

    $sheet->setCellValueByColumnAndRow(17, $rowIdx, $studentAmount);

    $kwName = trim($row['KindOfWorkName']);
    $nType = trim($row['nagruzka_type']);

    $total_hours = 0;
    $auditor_hours = 0;

    if ($nType == 'aspirantura_ruk_asp') {
        $sheet->setCellValueByColumnAndRow(37, $rowIdx, $amount);
        $total_hours += $amount;
    } elseif ($nType == 'aspirantura_ruk_soisk') {
        $sheet->setCellValueByColumnAndRow(38, $rowIdx, $amount);
        $total_hours += $amount;
    } elseif ($nType == 'ik' || mb_stripos($kwName, 'Индивидуальные консультации', 0, 'UTF-8') !== false) {
        $sheet->setCellValueByColumnAndRow(39, $rowIdx, $amount);
        $total_hours += $amount;
    } elseif ($nType == 'ksro' || mb_stripos($kwName, 'Контроль самостоятельной работы', 0, 'UTF-8') !== false) {
        $sheet->setCellValueByColumnAndRow(40, $rowIdx, $amount);
        $total_hours += $amount;
    } else {
        if (isset($col_mapping[$kwName])) {
            $colNum = $col_mapping[$kwName];
            $sheet->setCellValueByColumnAndRow($colNum, $rowIdx, $amount);
            $total_hours += $amount;

            if ($colNum >= 18 && $colNum <= 27) {
                $auditor_hours += $amount;
            }
        } elseif ($nType == 'aspirantura_kand_exam') {
            $sheet->setCellValueByColumnAndRow(26, $rowIdx, $amount);
            $total_hours += $amount;
            $auditor_hours += $amount;
        }
    }

    $sheet->setCellValueByColumnAndRow(41, $rowIdx, '');
    $sheet->setCellValueByColumnAndRow(42, $rowIdx, $total_hours);
    $sheet->setCellValueByColumnAndRow(43, $rowIdx, $auditor_hours);

    if (mb_strtolower($lang, 'UTF-8') == 'английский') {
        $sheet->setCellValueByColumnAndRow(44, $rowIdx, $total_hours);
    } else {
        $sheet->setCellValueByColumnAndRow(44, $rowIdx, 0);
    }

    $sheet->setCellValueByColumnAndRow(45, $rowIdx, $yearOfEntryStr);
    $sheet->setCellValueByColumnAndRow(46, $rowIdx, $row['YearOfEducation']);
    $sheet->setCellValueByColumnAndRow(47, $rowIdx, $row['Abbr'] ?: '');
    $sheet->setCellValueByColumnAndRow(48, $rowIdx, '');

    $rowIdx++;
}

// Add output headers correctly and ensure no whitespace causes header issues
if (!headers_sent()) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="report.xlsx"');
    header('Cache-Control: max-age=0');
}

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
