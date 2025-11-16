<?

$logfilename = 'log.txt';
$CONNECT = 'connect.php';


function AddZero($num)
{
	if (strlen($num)==1) $num = '0'.$num;
	return $num;
}


function PutLine($line)
{
	global $logfilename, $_SERVER;
	
	$REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
	$REQUEST_URI = $_SERVER['REQUEST_URI'];
	$HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
	$SCRIPT_FILENAME = $_SERVER['SCRIPT_FILENAME'];

  if (function_exists('user_browser'))
  {
    $browser_info_array = user_browser($_SERVER['HTTP_USER_AGENT']);
  }
  if ($browser_info_array)
  {
    $HTTP_USER_AGENT = "$browser_info_array[browser] $browser_info_array[version]";
  }


	$logfile = @fopen($logfilename, 'a');
	@fwrite($logfile, $line."[$REQUEST_URI] [$HTTP_USER_AGENT] [$REMOTE_ADDR]\n");
}


function EchoLog($line, $output = 'file')
{
  global $CONNECT, $_SESSION, $_site_domain, $mysqli;
  
	$curdate = getdate();
	$sec = $curdate['seconds'];
	$min = $curdate['minutes'];
	$hours = $curdate['hours'];
	$day = $curdate['mday'];
	$mon = $curdate['mon'];
	$year = $curdate['year'];
	
	if (is_array($line) || is_object($line)) $line = var_export($line, true);
  else $line = trim($line);
	
  if (stristr($output, 'file'))	PutLine("[".AddZero($day).'.'.AddZero($mon).'.'.$year.' - '.AddZero($hours).':'.AddZero($min).'.'.AddZero($sec)."] $line");
  if (stristr($output, 'db'))
  {
    include_once $CONNECT;
    $mysqli->query("INSERT INTO `log` (`datetime`, `log`, `person_id`) VALUES (NOW(),  '$line', '$_SESSION[c_person_id]')");
  }
  if (stristr($output, 'screen')) echo "<p>$line</p>";
  if (stristr($output, 'error')) error_log($line);
  if (stristr($output, 'mail'))
  {
    $to = $_admin_mail?$_admin_mail:'wwwlab@unn.ru';
    mail_utf8($to, $_site_domain, $to, "Сообщение с $_site_domain", $line);
  }

  
//  if ($echo) echo "$line<br>";
}

function DeleteLogFile()
{
	global $logfilename;
	
	@unlink($logfilename);
}



?>