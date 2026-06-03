<?

// ЗАГРУЗКА инструкции для завкафов (от УОУП)

include '../../functions.php';

session_name('lkzk');
session_start();

$result = array();


$file = $_FILES['file'];

$dst_dir = $_SERVER['DOCUMENT_ROOT'] . "/docs/";

if ($file['error'] == 1)
{
  $_message = "Размер файла превышает максимально допустимый";
  $result['result'] =  'fail';
  EchoLog('max size error');
}
elseif ($file['error'])
{
  $_message = "Неизвестная ошибка загрузки файла";
  $result['result'] =  'fail';
  EchoLog('Неизвестная ошибка загрузки файла, код ' . $file['error']);
}
elseif ($file['size'])
{
  $src_file_name = quote_smart($file['name']);  // имя файла пользовательское
  // $dst_dir = $_SERVER['DOCUMENT_ROOT'] . '/obj_images/';
  $src_extension = pathinfo($src_file_name, PATHINFO_EXTENSION);
  $uniq = uniq(16);
  $dst_file_name = "{$uniq}.$src_extension";

  //if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $dst_dir)) 
//      mkdir($_SERVER['DOCUMENT_ROOT'] . $dst_dir);

  $res = move_uploaded_file($file['tmp_name'], $dst_dir  . $dst_file_name);

  if ($res)
  // upload success
  {

    // Удалим предыдущий файл
    $PrevFile = GetRow('params', ['param' => 'zavkaf_instructions']);

    if ($PrevFile)
    {
      unlink("$dst_dir$PrevFile[value]");
    }

    $Result = $mysqli->query("REPLACE `params` 
    SET
    `value` = '$dst_file_name',
    `comment` = '$src_file_name',
    `datetime` = NOW(),
    `param` = 'zavkaf_instructions'
     ");


    if (!$Result)
    {
      EchoLog("Error mysql uploading file zavkaf_instruction_upload: " . $mysqli->error);
    }
    else
    {
      
      // $created_file = ['id' => $id, 'zayavka_id' => $zayavka_id, 'file_name' => $dst_file_name, 'file_src_name' => $src_file_name, 'file_size' => $file_size, 'file_hash' => $hash, 'linked_to_position' => 0];
      
      $result['created_file'] = ['file_name' => $dst_file_name, 'file_src_name' => $src_file_name, 'date' => date('Y-m-d H:i:s')];

      $result['result'] = 'success';
    }
  }
  else
  {
    EchoLog("Ошибка перемещения файла");
    $result['result'] = 'fail';
  }
}
// else
// {
//   EchoLog("Неверные параметры: zayavka_id = $zayavka_id");
//   $result['result'] = 'fail';
// }

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Дата в прошлом
header('Content-Type: application/json; charset=UTF-8');
echo ")]}',\n" .  array2json($result);
?>