<?

/**
 * Выгрузка данных в Галактику
 * Вызывается при переходе из режима "Заполнение" в "Выверка"
 * 
 * Порядок действий:
 * 1) Выгрузка новой нагрузки (ИК/КСРО) в Галактику
 * 2) Получение base_uid для новой нагрузки из Галактики
 * 3) Выгрузка распределений (сплитов) по Lecturer
 */

include '../functions.php';
include '../connect.php';

// Проверка режима
$modeRow = GetRow('params', ['param' => 'system_mode']);
if ($modeRow['value'] !== 'mode_exporting') {
    EchoLog('System not in exporting mode');
    exit;
}

// Обновляем статус выгрузки
SetField('params', ['param' => 'export_status'], 'value', 'in_progress');

try {
    // Этап 1: Выгрузка новой нагрузки (ИК/КСРО, аспирантура)
    $newNagruzkaXml = exportNewNagruzka();
    saveExportFile('new_nagruzka.xml', $newNagruzkaXml);

    // Этап 2: Выгрузка распределений (сплитов)
    $splitsXml = exportSplits();
    saveExportFile('splits.xml', $splitsXml);

    // Этап 3: Формирование итогового файла для выгрузки
    $finalXml = combineExports($newNagruzkaXml, $splitsXml);
    saveExportFile('export_to_galaxy.xml', $finalXml);

    // Обновляем статус
    SetField('params', ['param' => 'export_status'], 'value', 'completed');
    SetField('params', ['param' => 'export_completed_at'], 'value', date('Y-m-d H:i:s'));

    EchoLog('Export completed successfully');

} catch (Exception $e) {
    SetField('params', ['param' => 'export_status'], 'value', 'error');
    EchoLog('Export error: ' . $e->getMessage());
    exit;
}

/**
 * Выгрузка новой нагрузки (ИК/КСРО, созданной в ЛК ЗК)
 */
function exportNewNagruzka()
{
    global $mysqli;

    // Получаем нагрузку из таблицы ksro (ИК/КСРО)
    $query = "SELECT * FROM `ksro` WHERE `exported` = 0 OR `exported` IS NULL";
    $rows = GetSQL($query);

    if (empty($rows)) {
        return null;
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('Data_Root');
    $doc->appendChild($root);

    $data = $doc->createElement('Data');
    $root->appendChild($data);

    $collection = $doc->createElement('Collection');
    $collection->setAttribute('name', 'Data.ContentOfLoad');
    $collection->setAttribute('caption', 'Содержание нагрузки расписания');
    $collection->setAttribute('child_tags', 'Object');
    $data->appendChild($collection);

    foreach ($rows as $row) {
        $object = $doc->createElement('Object');
        $object->setAttribute('LoadId', $row['id']); // Временный ID для сопоставления
        $object->setAttribute('class_id', 'ContentOfLoad');
        $collection->appendChild($object);

        $propCollection = $doc->createElement('Collection');
        $propCollection->setAttribute('name', 'Prop_Values');
        $propCollection->setAttribute('child_tags', 'prop_value');
        $propCollection->setAttribute('caption', 'Свойства');
        $object->appendChild($propCollection);

        // Amount - часы
        addPropValue($doc, $propCollection, 'Amount', $row['amount']);
        // TypeOfContingent - всегда 4
        addPropValue($doc, $propCollection, 'TypeOfContingent', '4');
        // UID_KindOfWork
        addPropValue($doc, $propCollection, 'UID_KindOfWork', $row['kind_of_work_uid']);
        // UID_Discipline
        addPropValue($doc, $propCollection, 'UID_Discipline', $row['discipline_uid']);
        // UID_Chair
        addPropValue($doc, $propCollection, 'UID_Chair', $row['chair_uid']);
        // UID_Semester
        addPropValue($doc, $propCollection, 'UID_Semester', $row['semester_uid']);
        // UID_Language
        addPropValue($doc, $propCollection, 'UID_Language', $row['language_uid']);
        // UID_FacultyPerformer
        addPropValue($doc, $propCollection, 'UID_FacultyPerformer', $row['faculty_uid']);
    }

    return $doc->saveXML();
}

/**
 * Выгрузка распределений (сплитов) по преподавателям
 */
function exportSplits()
{
    $query = "SELECT * FROM `zavkaf_splits` WHERE `delete` = 0 ORDER BY `base_uid`";
    $rows = GetSQL($query);

    if (empty($rows)) {
        return null;
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('ContentOfLoads');
    $doc->appendChild($root);

    // Группировка по base_uid для Clean-атрибутов
    $baseUids = [];
    foreach ($rows as $row) {
        $baseUids[$row['base_uid']] = true;
    }

    // Добавляем Clean-ноды
    foreach ($baseUids as $baseUid => $true) {
        $node = $doc->createElement('ContentOfLoad');
        $node->setAttribute('UID', $baseUid);
        $node->setAttribute('Clean', '1');
        $root->appendChild($node);
    }

    // Добавляем распределения
    foreach ($rows as $row) {
        // Пропускаем пустые lecturer_uid
        if (empty($row['lecturer_uid']) || $row['lecturer_uid'] == '-1') {
            continue;
        }

        $node = $doc->createElement('ContentOfLoad');
        $node->setAttribute('UID', $row['base_uid']);

        // LoadType
        if (isset($row['LoadType'])) {
            $node->setAttribute('LoadType', $row['LoadType']);
        }

        // Amount или StudentAmount в зависимости от LoadType
        if ($row['LoadType'] == 1) {
            $node->setAttribute('StudentAmount', isset($row['amount']) ? $row['amount'] : '');
            $node->setAttribute('Amount', '');
        } else {
            $node->setAttribute('Amount', isset($row['amount']) ? $row['amount'] : '');
            $node->setAttribute('StudentAmount', '');
        }

        $node->setAttribute('UID_Lecturer', $row['lecturer_uid']);

        $root->appendChild($node);
    }

    return $doc->saveXML();
}

/**
 * Добавить свойство в XML
 */
function addPropValue($doc, $parent, $propName, $value)
{
    $prop = $doc->createElement('prop_value');
    $prop->setAttribute('prop_name', $propName);
    $prop->setAttribute('value', $value);
    $parent->appendChild($prop);
}

/**
 * Сохранить файл выгрузки
 */
function saveExportFile($filename, $content)
{
    $exportDir = __DIR__ . '/../exports/';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    $filepath = $exportDir . date('Y-m-d_H-i-s') . '_' . $filename;
    file_put_contents($filepath, $content);

    return $filepath;
}

/**
 * Объединить выгрузки в один файл
 */
function combineExports($newNagruzkaXml, $splitsXml)
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('Export');
    $root->setAttribute('timestamp', date('Y-m-d H:i:s'));
    $doc->appendChild($root);

    // Новая нагрузка
    if ($newNagruzkaXml) {
        $newNode = $doc->createElement('NewNagruzka');
        $newNode->nodeValue = 'CDATA_PLACEHOLDER';
        $root->appendChild($newNode);
    }

    // Распределения
    if ($splitsXml) {
        $splitsNode = $doc->createElement('Splits');
        $splitsNode->nodeValue = 'CDATA_PLACEHOLDER';
        $root->appendChild($splitsNode);
    }

    $xml = $doc->saveXML();

    // Заменяем placeholder на реальный CDATA
    if ($newNagruzkaXml) {
        $xml = str_replace(
            '<NewNagruzka>CDATA_PLACEHOLDER</NewNagruzka>',
            "<NewNagruzka><![CDATA[$newNagruzkaXml]]></NewNagruzka>",
            $xml
        );
    }

    if ($splitsXml) {
        $xml = str_replace(
            '<Splits>CDATA_PLACEHOLDER</Splits>',
            "<Splits><![CDATA[$splitsXml]]></Splits>",
            $xml
        );
    }

    return $xml;
}
