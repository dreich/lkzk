<?

// Просто скачать файл инструкций для завкафа
session_name('lkzk');
session_start();

if (!$_SESSION['c_roles'])
{
  echo 'expired';
  exit;
}


include '../functions.php';

$c_roles = ExplodePalki($_SESSION['c_roles'], true);

$ParamRow = GetRow('params', ['param' => 'zavkaf_instructions']);

$filePath = $_SERVER['DOCUMENT_ROOT'] . '/docs/' . $ParamRow['value'];
$originalName = $ParamRow['comment'];

if (file_exists($filePath)) {
    // Определяем MIME-тип файла
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    // Отдаём файл
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    readfile($filePath);
    exit;
} else {
    http_response_code(404);
    echo "Файл не найден";
}


?>