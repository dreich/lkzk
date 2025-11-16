<?

include_once 'connect.php';
include_once 'data.php';
include_once 'log_fs.php';


/**
* Уничтожить сессию пользователя
*/

function LogOut($message = null)
{
  global $_SESSION, $_COOKIE;
  // Initialize the session.
  // If you are using session_name("something"), don't forget it now!
//  session_start();

  // Unset all of the session variables.
  $_SESSION = array();

  // If it's desired to kill the session, also delete the session cookie.
  // Note: This will destroy the session, and not just the session data!
  if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
  }
 
  // удаление всех куков [только это удаляет фильтры зел. таблицы]
  /*
  foreach($_COOKIE as $ind => $value) 
  {
    $result = setcookie($ind,'',time()-999);
    
    // if ($result) EchoLog("ok deleting $ind");
    // else EchoLog("not ok deleting $ind");
    
    unset($_COOKIE[$ind]);
  }
  */

  // Удаление сохраненных состояний зеленых таблиц
  setcookie('SpryMedia_DataTables_t_','',time()-999, '/');

  //setcookie('SpryMedia_DataTables_ukrup__index.php','',time()-999, '/admin/');
  //for($i=0;$i<=20;$i++) setcookie("SpryMedia_DataTables_ukrup_{$i}_index.php", '', time()-999, '/admin/');

  $_COOKIE = array();
  
  // Finally, destroy the session.
  @session_destroy();

  if ($message) $message_out = "?login_message=$message";
  header("Location: /$message_out");
}



// Авторизоваться в LDAP
// Возвращает true в случае успеха, иначе код ошибки
// 49 - код неверной авторизации
function AuthorizeLDAP($alias, $password)
{
  global $_ldap_url, $_SESSION;

  //$_SESSION['c_ldap_password'] = $password;

  if (!$password) return;

  $ldapconn = ldap_connect($_ldap_url);
  ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
  ldap_set_option($ldapconn, LDAP_OPT_NETWORK_TIMEOUT, 3);
  $dn = "cn=$alias,ou=unn_staff,dc=unn,dc=global";
  $ldapbind = @ldap_bind($ldapconn, $dn, $password);  

  $err = ldap_errno($ldapconn);
  // EchoLog($ldapbind);

  if ($ldapbind)
  {
    // EchoLog('success');
    $attrs = ['unnmail', 'unnmobile', 'displayname'];
    $sr = ldap_read($ldapconn, $dn, "objectclass=*", $attrs);
    $entry = ldap_get_entries($ldapconn, $sr);

    $result = array();

    foreach ($attrs as $attr)
    {
      $result[$attr] = $entry[0][$attr][0]?$entry[0][$attr][0]:'';
    }

    ldap_unbind($ldapconn);
    return $result;
  }
  else
  {
    // EchoLog('fail');
    ldap_unbind($ldapconn);
    return $err;
  }
  
}

function UpdateSessionRolesStr()
{
  global $_SESSION, $_roles;

  session_name('zakupki');
  session_start();

  if ($_SESSION['c_roles'])
  {
    $roles_arr = ExplodePalki($_SESSION['c_roles']);
    $roles_titles = [];

    if ($roles_arr)
    {
      foreach ($roles_arr as $role)
      {
        $roles_titles[] = $_roles[$role];
      }
    }

    $_SESSION['c_roles_str'] = JoinArrayElements($roles_titles, ', ');
  }
}

// Авторизация на сайте супер-админом или любым сотрудником, в т.ч. админом подразделения
// возвращает "success" или "fail"
function Authorize($login, $password)
{

  global $_SESSION, $_COOKIE, $_full_admin_pass, $_full_admin_pass_mc, $_lite_admin_pass, $mysqli, $_roles;
  $result = false;

  // TODO CHANGE GREEN TABLE ID
  setcookie('SpryMedia_DataTables_t_', '', time()-999, '/');

  session_name('zakupki');
  session_start();

  $login = strtolower($login);
  // $ids_palki = [];
  // $titles_palki = [];

  if ($login)
  {
    // полный админ
    if ($login == "admin" && mymd5($password) == $_full_admin_pass)
    {
      $_SESSION['c_login'] = $login;
      $_SESSION['c_fio'] = $login;
      $_SESSION['c_roles'] = '|full|';
      $result = true;
      ActivityLog(null, "Вход админа", '', "authorize full $login", 1);

      
      // $result = 'success';
    }
    // админ лайт
    // elseif ($login == "radmin" && mymd5($password) == $_lite_admin_pass)
    // {
    //   $_SESSION['c_login'] = $login;
    //   $_SESSION['c_fio'] = 'admin lite';
    //   $_SESSION['c_access'] = 'lite';
    //   $result = true;
    //   ActivityLog(null, "Вход админа ОЗ (admin lite)", '', 'authorize admin oz', 1);
    //   // $result = 'success';
    // }
    // На данный момент здесь только админы УОУП
    elseif ($User = GetRow('users', ['login' => str_replace('#', '', $login)]))
    {
      if (substr_count($login, '#') == 2)
      {
        $clean_login = substr($login, 1, strlen($login) - 2);
      }
      else
      {
        $clean_login = $login;
      }

      if (substr_count($login, '#') == 0 && $password)
      {
        $attrs = AuthorizeLDAP($login, $password);
      }
      elseif (substr_count($login, '#') > 1)
      {
        $attrs = GetLdapAttrsByAdmin($clean_login, ['displayname']);
      }

      if ($attrs)
      {
        $_SESSION['c_login'] = $clean_login;
        $_SESSION['c_fio'] = $attrs['displayname'];;
        $_SESSION['c_roles'] = $User['roles'];
        $result = true;
        ActivityLog(null, "Вход админа", '', "authorize auditor $login", 1);
      }
    }
    // elseif ()
    // админы ОЗ
    // else
    {

      // $Admin = GetRow('admins', ['login' => $login, 'password' => mymd5($password)]);
      // if ($Admin)
      // {
      //   $_SESSION['c_login'] = $login;
      //   $_SESSION['c_fio'] = $Admin['title'];
      //   $_SESSION['c_email'] = $Admin['e_mail'];
      //   $_SESSION['c_access'] = 'full';
      //   $result = $_SESSION['c_access'];
      //   ActivityLog(null, null, "Вход админа ОЗ", 'authorize admin oz', 1);
      // }
      // else
      {

        // if ($login)
        {
          // TMP временный коммент, чтобы можно было входить без пароля
          if (substr_count($login, '#') == 2)
          {
            $clean_login = substr($login, 1, strlen($login) - 2);
          }
          else
          {
            $clean_login = $login;
          }

          // диезов нет
          // TMP временный коммент, чтобы можно было входить без пароля
          // substr_count($login, '#') < 2 && 
          if (substr_count($login, '#') < 2 && $password)
          {
            $attrs = AuthorizeLDAP($login, $password);
          }
          // вошли по #
          // TMP временный коммент, чтобы можно было входить без пароля
          //else // if (substr_count($login, '#') > 1)
          // if (!is_array($attrs))
          if (substr_count($login, '#') > 1)
          {
            $attrs = GetLdapAttrsByAdmin($clean_login, ['displayname']);
          }
          // EchoLog($attrs);

          // успешно авторизовался в LDAP
          // TMP временный коммент, чтобы можно было входить без пароля
          if (substr_count($login, '#') == 2 ||  is_array($attrs))
          {
            /*
            {
              
              // Сотрудники подразделений по типам
              // В этой таблице хранятся выбранные чекбоксами сотрудники руководителями подразделений
              $SotrudnikPodrazdelenia = GetRow('sotrudniki', ['login' => $clean_login]);
              // TODO несколько строк, несколько ролей одновременно
              // EchoLog($SotrudnikPodrazdelenia);

              if ($SotrudnikPodrazdelenia)
              {
                // (?) Теперь (26.11.2024, файл доработки 6) роль сотрудника будем давать только если он выбран куратором для заявки

                // $ZayavkiKurators = GetRows('zayavka_kurators', ['login' => $SotrudnikPodrazdelenia['login']]);
                // (?) если человек в таблице есть, значит, он выбран куратором
                // Будем считать, что только один руководитель может выбрать конкретного человека, поэтому берётся роль первая подряд

                if (true || $ZayavkiKurators)
                {
                  $_SESSION['c_login'] = $clean_login;
                  $_SESSION['c_fio'] = $attrs['displayname'];
                  // $_SESSION['c_email'] = $Admin['e_mail'];
                  // не исп. (использовать c_sotrudnik_podrazdelenia)
                  $_SESSION['c_access'] = "sotrudnik_podr";
                  // $_SESSION['c_podr_role'] = 'ccompetence';

                  if ($_SESSION['c_roles'])
                  {
                    $_SESSION['c_roles'] .= "$SotrudnikPodrazdelenia[role]|";
                  }
                  else
                  {
                    $_SESSION['c_roles'] = "|$SotrudnikPodrazdelenia[role]|";
                  }

                  
                  $_SESSION['c_role_area'] = str_replace('sotrudnik_' , '', $SotrudnikPodrazdelenia['role']);  // finance, science, zakupki, grants, ccompetence
                  // Это значение может перезатираться, если и сотрудник, и руководитель разных подр-й
                  $_SESSION['c_podr_id'] = $SotrudnikPodrazdelenia['podrazdelenie_id'];
                  $_SESSION['c_sotr_podr_id'] = $_SESSION['c_podr_id'];
                  // $_SESSION['c_podr_chain_str'] = $CcompRuk['podrazdelenia_chain_str'];
                  $_SESSION['c_podr_title'] = $SotrudnikPodrazdelenia['podrazdelenie_title'];
                  $_SESSION['c_sotrudnik_podrazdelenia'] = true;

                  $result = true;
                  ActivityLog(null, "Вход сотрудника подразделения", '', 'authorize podr sotr', 1);
                }
                
              }
            }

            */

            // админы ЦФО
            // $result == 'fail'
            // Проверим, является ли сотрудник руководителем к-либо подразделения
            // Если да, то проверим, что такое подразделение есть в таблице ЦФО
            // else

            {
              // EchoLog('here1');
              include './connect/sotrudnik.php';
              
              // EchoLog('here2');
              $Person = GetRow('person', ['alias' => $clean_login]);
              $Contacts = GetRow('ldap_employees_contacts', ['alias' => $clean_login]);

              // EchoLog($clean_login);
              // EchoLog($Person);
              $podrazdelenia_table_name = "podrazdelenia" . date('Y');
              $ChairsWithThisChief = GetTable($podrazdelenia_table_name, "`chief_id` = $Person[id] AND `pname` LIKE ('Кафедра%')");

              include 'connect.php';

              if ($ChairsWithThisChief)
              {
                $_SESSION['c_login'] = $clean_login;
                //  $_SESSION['c_fio'] = $Admin['fio'];
                // $_SESSION['c_access'] = 'cfo';
                // У научного (псевдо) будет пусто. Пока не до понятно
                // $_SESSION['c_podrazdelenie_id'] = $CFO['cfo_podrazdelenie_id'];
                // $_SESSION['c_cfo_science'] = $CFO['science'];
                $_SESSION['c_fio'] = $attrs['displayname'];
                $_SESSION['c_chair_id'] = $ChairsWithThisChief[0]['id'];
                $_SESSION['c_chair_name'] = $ChairsWithThisChief[0]['pname'];
                // необходимо для  работы с источниками финансирования, т.к. источники привязываются к ЦФО, а не у всех ЦФО есть cfo_podrazdelenie_id
                // $_SESSION['c_cfo_id'] = $CFO['id'];
                $_SESSION['c_phone'] = $Contacts['mobile'];
                $_SESSION['c_email'] = $Contacts['e_mail'];
                $result = true;

                if ($_SESSION['c_roles'])
                {
                  $_SESSION['c_roles'] .= 'zavkaf|';
                }
                else
                {
                  $_SESSION['c_roles'] = '|zavkaf|';
                }

                // if (!substr_count($login, '#'))
                {
                  ActivityLog(null, ['Вход заведующего кафедрой'], '', 'authorize zavkaf', 1);
                }
              }

              include './connect.php';
   

            }

          }
            
        }
      }

    }

    // $result = true (можно заменить на ($_SESSION[c_login])) означает, что у человека и роль есть, и авторизовался успешно
    // is_array($attrs) означает, что авторизовался успешно
    
    if (!$result && is_array($attrs))
    {
      $result = "У вас нет доступа в систему закупок";
    }
    elseif (!$result && !is_array($attrs))
    {
      $result = "Вы ввели неправильный логин или пароль";
    }
  }

  UpdateSessionRolesStr();

  // EchoLog($_SESSION);



  return $result;
}


/**
* Шифрование паролей в базе диссертантов
*
*/


function mymd5($str, $length = null)
{
  $md5 = md5(md5($str) . 'buykupcrumbs');
  
  if (intval($length) > 0) $md5 = substr($md5, 0, $length);

  return $md5;
}


/**
* Шифрование паролей в базе сотрудников
*
*/

function sotrudnik_md5($str)
{
  return md5(md5($str) . 'sotrenie');
}


// Определение браузера и его версии
function user_browser($agent) 
{
  $result = array();

  preg_match("/(MSIE|Opera|Firefox|Chrome|Version|Opera Mini|Netscape|Konqueror|SeaMonkey|Camino|Minefield|Iceweasel|K-Meleon|Maxthon)(?:\/| )([0-9.]+)/", $agent, $browser_info); // регулярное выражение, которое позволяет отпределить 90% браузеров
  list(,$browser,$version) = $browser_info; // получаем данные из массива в переменную
  if (preg_match("/Opera ([0-9.]+)/i", $agent, $opera)) 
  {
    $result['browser'] = 'Opera';
    $result['version'] = $opera[1];
    return $result; // определение _очень_старых_ версий Оперы (до 8.50), при желании можно убрать
  }

  if ($browser == 'MSIE') 
  { // если браузер определён как IE
    preg_match("/(Maxthon|Avant Browser|MyIE2)/i", $agent, $ie); // проверяем, не разработка ли это на основе IE
    if ($ie)
    {
      $result['browser'] = $ie[1].' based on IE';
      $result['version'] = $version;
    }
    //if ($ie) return $ie[1].' based on IE '.$version; // если да, то возвращаем сообщение об этом
    return array('browser' => 'IE', 'version' => $version); // иначе просто возвращаем IE и номер версии
  }

  if ($browser == 'Firefox') 
  { // если браузер определён как Firefox
    preg_match("/(Flock|Navigator|Epiphany)\/([0-9.]+)/", $agent, $ff); // проверяем, не разработка ли это на основе Firefox
    if ($ff) 
    {
      $result['browser'] = $ff[1];
      $result['version'] = $ff[2];
      return $result; // если да, то выводим номер и версию
    }
  }

  if ($browser == 'Opera' && $version == '9.80')
  {
    $result['browser'] = 'Opera';
    $result['version'] = substr($agent,-5);
    return $result; // если браузер определён как Opera 9.80, берём версию Оперы из конца строки
  } 

  if ($browser == 'Version') 
  {
    $result['browser'] = 'Safari';
    $result['version'] = $version;
    return $result; // определяем Сафари
  }

  if (!$browser && strpos($agent, 'Gecko')) 
  {
    $result['browser'] = 'Browser based on Gecko';
    return $result; // для неопознанных браузеров проверяем, если они на движке Gecko, и возращаем сообщение об этом
  }

  
  return array('browser' => $browser, 'version' => $version); // для всех остальных возвращаем браузер и версию
}


function Error($text)
{
  if ($text) echo "<div class='alert alert-danger'>$text</div>\n";
}


function Success($text)
{
  if ($text) echo "<div class='alert alert-success'>$text</div>\n";
}



function JoinConditions($values)
{
  $sql = '1';
  
  while (list($key, $value) = each($values))
  {
    $value = quote_smart($value);
    // обратных слешей специально нет, они нужны в get_cfo_zayavki.php
    $sql .= " AND $key = '$value'";
  }
//  EchoLog($sql);
  return $sql;
}

/**
* Составляет строку из всех элементов массива, перечисленных через разделитель
* $start_end - нужно ли делимитер добавить в начале и в конце строки
* $left_quote, $right_quote - обрамления одного элемента
*/
function JoinArrayElements($arr, $delim = ', ', $start_end = false, $left_quote = '', $right_quote = '')
{
  $num = sizeof($arr);
  if ($num)
  {
    $str = '';
    
    if ($start_end) $str = $delim;
    
    foreach ($arr as $element)
    {
      $value = $element;
      if ($value) $str .= $left_quote.$value.$right_quote.$delim;
    }
    
    
    if (!$start_end) $str = substr($str, 0, strlen($str) - strlen($delim));
    
    return $str;
  }
  else return '';
}


function MyExplode($delimiter, $str)
{
  $str_arr = explode($delimiter, $str);
  
    // для тримминга
  $str_arr = quote_smart($str_arr);
  
//  EchoLog(var_export($str_arr, true));
  

  return $str_arr;
}


// Получить одну строку из таблицы $table, в которой значение поля `$key` = '$value'
// (подразумевается, что $key - первичный ключ, и строка единственная)
function GetRow($table, $key, $value = null, $sql = null, $fields = '*')
{
  global $mysqli;

  if (!$table) return false;

  if (is_array($key))
  {
    $conditions = JoinConditions($key);
    $query = "SELECT $fields FROM `$table` WHERE $conditions";
  }
  elseif ($key)
  {
    $value = quote_smart($value);
    $query = "SELECT $fields FROM `$table` WHERE `$key` = '$value'";
  }
  else $query = "SELECT $fields FROM `$table` WHERE 1";

  if ($sql) $query .= " AND $sql";
  
  $Result = $mysqli->query($query);

  // EchoLog($query);


  if ($Result)
  {
    if ($Result->num_rows) return $Result->fetch_assoc();
    else return array();
  }
  else 
  {
    EchoLog("Error in GetRow(): ($table: $key = '$value')".$mysqli->error);
    EchoLog($query);
    return false;
  }
}


// Получить все строки из таблицы $table, в которых значение поля `$key` = '$value'
function GetRows($table, $key = null, $value = null, $sort_field = null, $fields = '*', $index_field = null, $limit = '')
{
  global $mysqli;

  if ($sort_field) $sort_sql = "ORDER BY $sort_field ";

  // все пары в массиве $key
  if (is_array($key))
  {
    $conditions = JoinConditions($key);
    $where_sql = "WHERE $conditions";
  }
  // значения одного ключа перечислены через запятую
  elseif (strpos($value, ',') !== false)
  {
    $where_sql = "WHERE `$key` IN($value)";
  }
  // просто две переменные ключ - значение
  elseif ($key && isset($value))
  {
    $where_sql = "WHERE `$key` = '$value'";
  }

  if ($limit)
  {
    $limit_sql = "LIMIT $limit";
  }
  
  $query = "SELECT $fields FROM `$table` $where_sql $sort_sql $limit_sql";
  $Result = $mysqli->query($query);

  // EchoLog($query);

  if ($Result)
  {
    if ($Result->num_rows)
    {
      while ($Row = $Result->fetch_assoc())
      {
        // $Rows[] = $Row;
        if (!$index_field) $Rows[] = $Row;
        elseif (isset($Row[$index_field])) $Rows[$Row[$index_field]] = $Row;
      }
      return $Rows;
    }
    else return [];
  }
  else
  {
    EchoLog("Error in GetRows($query): " . $mysqli->error);
    EchoLog($query);
    return false;
  }
}


// Получить все строки из таблицы $table, удовлетворяющие условию
// $where с сортировкой $sort_field
function GetTable($table, $where = '', $sort_field = '', $index_field = null, $fields = '*')
{
  global $mysqli;

  if ($where) $where_sql = "WHERE $where";
  if ($sort_field) $sort_sql = "ORDER BY $sort_field";
  
  $query = "SELECT $fields FROM `$table` $where_sql $sort_sql";

  // EchoLog($query);
  
  $Result = $mysqli->query($query);
  if ($Result)
  {
//    EchoLog(mysql_num_rows($Result));
    if ($Result->num_rows)
    {
      while ($Row = $Result->fetch_assoc()) 
      {
        if (!$index_field) $Rows[] = $Row;
        elseif (isset($Row[$index_field])) $Rows[$Row[$index_field]] = $Row;
      }
      
      return $Rows;
    }
    else return [];
  }
  else
  {
    EchoLog("Error in GetTable('$table'): " . $mysqli->error);
    return false;
  }
}


// $single_column - имя столбца, которое будет единственным
function GetSQL($query, $index_field = null, $single_column = null)
{
  global $mysqli;
  // EchoLog($query);

  $Result = $mysqli->query($query);
  if ($Result)
  {
//    EchoLog(mysql_num_rows($Result));
    if ($Result->num_rows)
    {
      while ($Row = $Result->fetch_assoc()) 
      {
        if (!$index_field)
        {
          if ($single_column && $Row[$single_column]) $Rows[] = $Row[$single_column];
          else $Rows[] = $Row;
        }
        elseif (isset($Row[$index_field]))
        {
          if ($single_column && $Row[$single_column]) $Rows[$Row[$index_field]] = $Row[$single_column];
          else $Rows[$Row[$index_field]] = $Row;
        }
      }
      
      return $Rows;
    }
    else return [];
  }
  else
  {
    EchoLog("Error in GetSQL(): " . $mysqli->error);
    EchoLog($query);
    return false;
  }
}


/**
* Обезопасить строку или массив строк для использования в SQL-запросе
*
* @param mixed $value
* @return mixed
*/

function quote_smart($values)
{

//  if (sizeof($values) == 0) return '';
  
  if (is_array($values))
  {
    while (list($id, $value) = each($values))
    {
      $values[$id] = quote_smart($value);
    }
    reset($values);
    return $values;
  
  }
  elseif (is_object($values))
  {
    $_values = clone $values;

    foreach($_values as $k=>$value) 
    { 
      $_values->{$k} = quote_smart($value);
    } 

    return $_values;
  }
  else
  {
    return _quote_value($values);
  }
}



/**
* Обезопасить строку для использования в SQL-запросе
*
* @param string $value
* @return string
*/


function _quote_value($value)
{
  global $mysqli;
  
  $value = trim($value);
  // $value = str_replace('<', '"', $value);
  // $value = str_replace('>', '"', $value);
  // если magic_quotes_gpc включена - используем stripslashes
  if (get_magic_quotes_gpc()) {
    $value = stripslashes($value);
  }
  // Если переменная - число, то экранировать её не нужно
  // если нет - экранируем
  if (!is_numeric($value)) {
    $value = $mysqli->real_escape_string($value);
  }
  return $value;
}


/*
* Возвращает HTML-код для элемента формы <select>, используя массив $data
*
* @param array $data
*   Простой массив с данными
*
* @param string $select_name
*   Имя селекта
*
* @param string $default 
*   Выбранное значение для <select>
*
* @param string $class
*   Атрибут class для селекта
*
* @param string $zerovalue
*   Значение пустого <option>
*
* @return string
*   HTML-код селекта
*/

function GetSelectFromArray($data, $select_name, $default = null, $class = null, $zerovalue = null, $attrs = '')
{

  if ($class) $class = "class=\"$class\"";
  
  if (is_array($data))
  {
    $out = "<select name=\"$select_name\" $class $attrs>\n";
    
    if ($zerovalue) $out .= "<option value=\"\" >$zerovalue</option>\n";
    
    foreach($data as $value)
    {
      if (!strcmp($default, $value)) $sel = 'selected=selected'; else $sel = '';
      $out.= "<option $sel>$value</option>\n";
    }
    $out.= "</select>\n";
    
    return $out;
  }
  else Error("Неверные данные в GetSelectFromArray()");
}




/*
* Возвращает HTML-код для элемента формы <select>, используя ассоциативный массив $data
*
* @param array $data
*   Ассоциативный массив с данными
*
* @param string $select_name
*   Имя селекта
*
* @param string $default 
*   Выбранное значение для <select>
*
* @param string $class
*   Атрибут class для селекта
*
* @param string $zerovalue
*   Значение пустого <option>
*
* @param string $attrs
*   Атрибуты для <select>
*
* @param boolean $noselect
*   Выдать только теги <option>
*
* @return string
*   HTML-код селекта
*/



function GetSelectFromAssocArray($data, $select_name, $default = null, $class = null, $zerovalue = null, $attrs = '', $noselect = null)
{
  if ($class) $class = "class='$class'";

  
  if (is_array($data))
  {

    if (!$noselect) $out = "<select name=\"$select_name\" $class $attrs>";
    
    if ($zerovalue) $out .= "<option value=\"\" >$zerovalue</option>";

    if ($data['optgroup'] == '1')
    {
      foreach ($data['data'] as $group)
      {
        $out .= "<optgroup label=\"$group[label]\">\n";
        
        while (list($index, $value) = each ($group['data']))
        {
          if (!strcmp($default, $index)) $sel = 'selected=1'; else $sel = '';
          $out.= "<option value=\"$index\" $sel>[$index] $value</option>";
        }
        
        $out .= "</optgroup>\n";
      }
    }

        
    else
    {
      while (list($index, $value) = each($data))
      {
        if (!strcmp($default, $index)) $sel = 'selected=1'; else $sel = '';
        $out.= "<option value=\"$index\" $sel>$value</option>";
      }
    }
    
    if (!$noselect) $out.= "</select>";
    
    return $out;
  }
  else EchoLog("Неверные данные в GetSelectFromAssocArray()");
}



/*
* Возвращает HTML-код для элемента формы <select>, используя ассоциативный массив $data
*
* @param array $data
*   Ассоциативный массив с данными
*
* @param string $select_name
*   Имя селекта
*
* @param string $default 
*   Выбранное значение для <select>
*
* @param string $class
*   Атрибут class для селекта
*
* @param string $zerovalue
*   Значение пустого <option>
*
* @param string $attrs
*   Атрибуты для <select>
*
* @param boolean $noselect
*   Выдать только теги <option>
*
* @return string
*   HTML-код селекта
*/



function GetRadioFromAssocArray($data, $input_name, $default = null, $class = null, $attrs = '')
{
  if ($class) $class = "class='$class'";

  
  if (is_array($data))
  {

    //if (!$noselect) $out = "<select name=\"$select_name\" $class $attrs>";
    
    //if ($zerovalue) $out .= "<input type=\"radio\" name=\"$input_name\" value=\"\" > &nbsp; $zerovalue";


    while (list($index, $value) = each($data))
    {
      if (!strcmp($default, $index)) $sel = 'checked=1'; else $sel = '';
      $out.= "<div class=\"radio\"><label><input type=\"radio\" name=\"$input_name\" value=\"$index\" $sel $class>$value</label></div>";
    }

    
    //if (!$noselect) $out.= "</select>";
    
    return $out;
  }
  else EchoLog("Неверные данные в GetRadioFromAssocArray()");
}


// внутренняя функция для сравнения строк
function ListElementCmp($a, $b)
{
  $field = 'title';
  if ($a[$field]==$b[$field]) return 0;
  return ($a[$field] > $b[$field]) ? 1 : -1;
}

/*
* Возвращает HTML-код для элемента формы <select>, используя строки
* таблицы string $table. $primary_field - первичный ключ таблицы, 
*
* @param string $table
*   Имя таблицы
*
* @param $primary_field
*   Первичный ключ таблицы
*
* @param string $select_name
*   Имя селекта
*
* @param string $value_field 
*   Поле таблицы, содержащее значения для <option>
*
* @param string $default 
*   Выбранное значение для <select>
*
* @param string $sort
*   Поле сортировки (для SQL-запроса)
*
* @param string $class
*   Атрибут class для селекта
*
* @param string $zerovalue
*   Значение пустого <option>
*
* @return string
*   HTML-код селекта
*/

function GetSelectFromDB($table, $primary_field, $select_name, $value_field = 'title', $default = null, $sort = null, $class = null, $zerovalue = '', $where = '', $attrs = '', $show_primary_field = false)
{
//  if (!$value_field) $value_field = 'title';
  if ($class) $class = "class='$class'";

  if ($table)
  {
    $List = GetTable($table, $where, $sort);
  }
  else EchoLog("Пустой input в GetSelectFromDB()");

  if ($List)
  { 
    if (!$sort) uasort($List, ListElementCmp);
    
    $out = "<select name='$select_name' $class $attrs>\n";

    if ($zerovalue) $out.="<option value=\"\" >$zerovalue</option>\n";
    foreach ($List as $Row)
    {
      if ($show_primary_field) $pf = "[{$Row[$primary_field]}] - "; else $pf = '';
      $sel = ($Row[$primary_field]==$default)?'selected="1"':'';
      $out.="<option value=\"{$Row[$primary_field]}\" $sel>$pf{$Row[$value_field]}</option>\n";  
    }

    $out.='</select>'."\n";

    return $out;
  }
  else
  {
    EchoLog('Нет данных в GetSelectFromDB()');
    return false;
  }
}



// Сгенерировать invite-code
function GetRandInvite()
{
  $c1 = mt_rand(1111, 9999);
  $c2 = mt_rand(1111, 9999);
  $c3 = mt_rand(1111, 9999);
  return "$c1-$c2-$c3";
}



function uniq($length = 8)
{
  return substr(md5(uniqid()), 0, $length);
}


function file_force_download($file, $filename_out = null, $delete_file = false) 
{
  global $_SERVER;

  if (file_exists($file)) {
    // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
    // если этого не сделать файл будет читаться в память полностью!
    if (ob_get_level()) {
      ob_end_clean();
    }

    $filename = $filename_out ? $filename_out : basename($file);

    if ( preg_match( "/MSIE/", $_SERVER["HTTP_USER_AGENT"] ))
    {
      $filename = urlencode($filename);
    }

    $pathinfo = pathinfo($filename_out);

    // EchoLog($pathinfo);
    
    if ($pathinfo['extension'] == 'xlsx') $ctype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    elseif ($pathinfo['extension'] == 'xls') $ctype = 'application/vnd.ms-excel';
    elseif ($pathinfo['extension'] == 'doc') $ctype = 'application/msword';
    elseif ($pathinfo['extension'] == 'docx') $ctype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    elseif ($pathinfo['extension'] == 'pdf') $ctype = 'application/pdf';
    else $ctype = 'application/octet-stream';

    // заставляем браузер показать окно сохранения файла
    header('Content-Description: File Transfer');
    header("Content-Type: $ctype");
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    // читаем файл и отправляем его пользователю
    readfile($file);

    if ($delete_file)
    {
      @unlink($file);
    }

    exit;
  }
  else echo "Файл не существует";
}



/**
* Разобрать дату в формате DE (01.01.2001)
*
* @param string $Date
*   Дата вида 01.01.2001
*
* @return array
*   Массив с годом, месяцем, числом
*/

function ParseDateDE($Date)
{
  $Array = explode('.', $Date);
  $Assoc['day'] = $Array[0];
  $Assoc['month'] = $Array[1];
  $Assoc['year'] = $Array[2];
  
  return $Assoc;
}

/**
* Разобрать дату в формате MySQL (2001-01-01)
*
* @param string $datetime
*   SQL DATE или DATETIME
*
* @return array
*   Массив с годом, месяцем, числом
*/

function ParseDateSQL($datetime, $remove_seconds = false)
{
  if (!$datetime) return array();
  
  $DTArray = explode(' ', $datetime);
  $date = $DTArray[0];
  
  $DateArray = explode('-', $date);
  $Array['year'] = $DateArray[0];
  $Array['month'] = $DateArray[1];
  $Array['day'] = $DateArray[2];
  $Array['time'] = isset($DTArray[1]) ? $DTArray[1] : '';

  if ($Array['time'] && $remove_seconds)
  {
    $Array['time'] = substr($Array['time'], 0, -3);
  }
  
  return $Array;
}

function MysqlToDE($MysqlDate, $no_time = false)
//2006-01-01 -> 01.01.2006
{
  if (strlen($MysqlDate) < 10 || $MysqlDate == '0000-00-00') return '';

  $DateArray = ParseDateSQL($MysqlDate);
  if ($DateArray)
  {
    $result = "$DateArray[day].$DateArray[month].$DateArray[year]";

    if ($DateArray['time'] && !$no_time) $result .= ' ' . $DateArray['time'];
    return $result;
  }
}


function DEToMysql($DateDE, $need_quotes = true)
// 01.01.2006 -> 2006-01-01
{
  $quote = $need_quotes?"'":"";

  if (strlen($DateDE)<10) return 'NULL';
  $DateArray = ParseDateDE($DateDE);
  return "$quote$DateArray[year]-$DateArray[month]-$DateArray[day]$quote";
}

function GetCurDate()
{
  $date = date('d.m.Y');
  return $date;
}




$MonthsArray = array(

  '1'=>'января', 
  '2'=>'февраля', 
  '3'=>'марта', 
  '4'=>'апреля', 
  '5'=>'мая', 
  '6'=>'июня', 
  '7'=>'июля', 
  '8'=>'августа', 
  '9'=>'сентября', 
  '10'=>'октября', 
  '11'=>'ноября', 
  '12'=>'декабря'
  );

/**
* Получить название месяца по его номеру (родительный падеж).
* 
* @param string $ind
*   Номер месяца вида '02' или '2'
* @return string
*   Название месяца
*/
function GetMonthName($ind)
{
  global $MonthsArray;
  
  // найдем позицию нуля
  $zeroPos = strpos($ind, '0');
  // если нашли
  if (!($zeroPos===false))
  {
    // если номер начинается с нуля ("02"), вырезаем вторую цифру
    if ($zeroPos==0) $ind = substr($ind, 1);
  }
  
  
  return $MonthsArray[$ind];
}


/**
* Получить название месяца по его номеру (именительный падеж).
* 
* @param string $ind
*   Номер месяца вида '02' или '2'
* @param int $offset
*   уменьшить или увеличить
* @return string
*   Название месяца
*/

function GetStraightMonthName($ind, $offset = 0)
{
  // найдем позицию нуля
  $zeroPos = strpos($ind, '0');
  // если нашли
  if (!($zeroPos===false))
  {
    // если номер начинается с нуля ("02"), вырезаем вторую цифру
    if ($zeroPos==0) $ind = substr($ind, 1);
  }
  
  $ind = $ind + $offset;
  // !!! не всё доделано, offset не использовать больший единицы
  if ($ind == 0) $ind = 12;
  if ($ind == 13) $ind = 1;
  
  $MonthsArray = array(

  '1'=>'январь', 
  '2'=>'февраль', 
  '3'=>'март', 
  '4'=>'апрель', 
  '5'=>'май', 
  '6'=>'июнь', 
  '7'=>'июль', 
  '8'=>'август', 
  '9'=>'сентябрь', 
  '10'=>'октябрь', 
  '11'=>'ноябрь', 
  '12'=>'декабрь'
  );  
  
  return $MonthsArray[$ind];

}



/**
* Получить дату вида 1 января 2001 г.
*
* @param string $sql_date
*   SQL DATE или DATETIME
*
* @return string
*   Дата вида 1 января 2001 г.
*/

function GetHumanDate($sql_date)
{
  $date_array = ParseDateSQL($sql_date);
  $month = GetMonthName($date_array['month']);
  return "$date_array[day] $month $date_array[year] г.";
}


// Если строка $path заканчивается символом / , то возвращаем строку с удалением этого символа

function TrimTrailingSlash($path)
{
  $path = trim($path);
  $pos = strrpos($path, '/');
  if ($pos === false) return $path;
  else
  {
    $len = strlen($path);
    if ($pos == $len - 1) return substr($path, 0, $len - 1);
    else return $path;
  }
}


// проверка на наличие символов . : (против PHP-инъекций)
function IsGoodInclude($inc)
{
  if (strpbrk($inc, '.:')) return false;
  else return true;
}




function fixGlobalFilesArray($files) 
{
   $ret = array();
   
   if(isset($files['tmp_name']))
   {
       if (is_array($files['tmp_name']))
       {
           foreach($files['name'] as $idx => $name)
           {
               $ret[$idx] = array(
                   'name' => $name,
                   'tmp_name' => $files['tmp_name'][$idx],
                   'size' => $files['size'][$idx],
                   'type' => $files['type'][$idx],
                   'error' => $files['error'][$idx]
               );
           }
       }
       else
       {
           $ret = $files;
       }
   }
   else
   {
       foreach ($files as $key => $value)
       {
           $ret[$key] = fixGlobalFilesArray($value);
       }
   }
   
   return $ret;
}

/* 
* Удалить файл заявки
*/
function DeleteZayavkaFile($file_id, $zayavka_type)
{
  if (!$file_id || !$zayavka_type)
  {
    EchoLog("Неверные параметры в DeleteZayavkaFile: $file_id, $zayavka_type");
    return null;
  }

  global $_SERVER, $mysqli;

  if ($zayavka_type == 'annual')
  {
    $zayavka_table = 'zayavka';
    $zayavka_files_table = 'zayavka_files';
  }

  if ($zayavka_type == 'quartal_zayavka')
  {
    $zayavka_table = 'zayavka_quartal';
    $zayavka_files_table = 'zayavka_quartal_files';
  }

  $File = GetRow($zayavka_files_table, 'id', $file_id);
  $Zayavka = GetRow($zayavka_table, 'id', $File['F']);

  if ($File && $Zayavka)
  {

    $filename = $File['file_name'];

    // удалим первый слеш, если он есть
    if ($filename[0] == '/') $filename = substr($filename, 1);

    $zayavka_dir = GetDir($Zayavka);

    @unlink($zayavka_dir . $filename);

    $Result = $mysqli->query("DELETE FROM `$zayavka_files_table` WHERE `id` = $file_id");

    if ($zayavka_type == 'quartal_zayavka') 
    {
      // у всех позиций, где встречался этот файл, его нужно убрать
      $Positions = GetSQL("SELECT id, files_id FROM `position_quartal` WHERE `files_id` LIKE('%|$file_id|%')");
      if ($Positions)
      {
        foreach ($Positions as $position)
        {
          $files_id_array = ExplodePalki($position['files_id']);
          foreach ($files_id_array as $i => $_file_id)
//          for ($i=0; $i<sizeof($files_id_array); $i++)
          {
            if ($_file_id == $file_id) unset($files_id_array[$i]);
          }

          $new_files_id = ImplodePalki($files_id_array);

          if (!$mysqli->query("UPDATE `position_quartal` SET `files_id` = '$new_files_id' WHERE `id` = $position[id]"))
          {
            EchoLog("Error #582 in DeleteZayavkaFile($file_id, $position[id])");
          }
        }

        
      }
    }

    // if ($zayavka_type == 'quartal') $mysqli->query("UPDATE `position_quartal` SET `file_id` = '' WHERE `file_id` = $file_id");

  }
  else EchoLog("DeleteZayavkaFile: в БД не найден файл или заявка ($zayavka_type, $file_id, $File[zayavka_id])");

  if ($Result) return true;
  else
  {
    EchoLog("Error in DeleteZayavkaFile($type, $file_id): " . $mysqli->error);
    return false;
  }
}


/* 
* Удалить файл из папки /files
* @param string $path
* путь после /files/, без первого слеша
* @param boolean $delete_only_file
* удалять только файл или всю позицию
*/
function DeleteAnnualPositionOrFile($position, $delete_only_file = true)
{
  if (!$position)
  {
    EchoLog("Неверные параметры в DeleteAnnualPositionOrFile()");
    return null;
  }

  global $_SERVER, $mysqli;

  $filename = $position['file_name'];

  // удалим первый слеш, если он есть
  if ($filename[0] == '/') $filename = substr($filename, 1);

  $position_dir = GetDir($position);

  @unlink($position_dir . $filename);

  if ($delete_only_file)
  {
    $Result = $mysqli->query("UPDATE `position` SET `file_name` = NULL, `file_src_name` = NULL, `file_date` = NULL, `file_size` = NULL, `file_hash` = NULL WHERE `id` = $position[id]");
  }
  else
  {
    $Result = $mysqli->query("DELETE FROM `position` WHERE `id` = $position[id]");
  }

  if ($Result) return true;
  else
  {
    EchoLog("Error in DeleteAnnualPositionOrFile($position): " . $mysqli->error);
    return false;
  }
}

/* 
* Удалить файл из папки /files
* @param string $path
* путь после /files/, без первого слеша
* @param boolean $delete_only_file
* удалять только файл или всю позицию
*/
function DeletePosition($position)
{
  if (!$position)
  {
    EchoLog("Неверные параметры в DeletePosition()");
    return null;
  }

  global $_SERVER, $mysqli;

  $Result = $mysqli->query("DELETE FROM `position_quartal` WHERE `id` = $position[id]");

  if ($Result) return true;
  else
  {
    EchoLog("Error in DeletePosition($position): " . $mysqli->error);
    return false;
  }
}




function mail_utf8($to, $from_user, $from_email, $subject = '(No subject)', $message = '' /*, $zayavka_id = null */)
{ 
  global $mysqli, $_SERVER;

  EchoLog("mail_utf8: $to,  $from_user, $from_email");

  $from_user = "=?UTF-8?B?".base64_encode($from_user)."?=";

  $headers = "From: $from_user <$from_email>\r\n". 
            "MIME-Version: 1.0" . "\r\n" . 
            "Content-type: text/html; charset=UTF-8" . "\r\n"; 

  $zayavka_id = $zayavka_id ? quote_smart($zayavka_id) : 'NULL';

  if ($zayavka_id && $zayavka_id != 'NULL')
  {
    $Zayavka = GetRow('zayavka', ['id' => $zayavka_id]);
    if ($Zayavka)
    {
      $status_sql = ", `status` = '$Zayavka[status]'";
    }
  }

  $mysqli->query("INSERT INTO `log_mail` SET `datetime` = NOW(), `subject` = '" . quote_smart($subject) . "', `to` = '$to', `message` = '" . quote_smart($message) . "' $status_sql");

  if ($_SERVER['HTTP_HOST'] == 'lkzk.unn.ru')
  {
    EchoLog("Sending message via mail function");
    $result = mail($to, "=?UTF-8?B?".base64_encode($subject)."?=", $message, $headers);
    EchoLog("Result: $result");
    return $result;
  }
  else
  {
    return true;
  }
}


function correctURL($address)
{
  if (!empty($address) AND $address[0] != '#' AND 
  strpos(strtolower($address), 'mailto:') === FALSE AND 
  strpos(strtolower($address), 'javascript:') === FALSE)
  {
     $address = explode('/', $address);
     $keys = array_keys($address, '..');

     foreach($keys AS $keypos => $key)
         array_splice($address, $key - ($keypos * 2 + 1), 2);

     $address = implode('/', $address);
     $address = str_replace('./', '', $address);
     
     $scheme = parse_url($address);
     
     if (empty($scheme['scheme']))
         $address = 'http://' . $address;

     $parts = parse_url($address);
     $address = strtolower($parts['scheme']) . '://';

     if (!empty($parts['user']))
     {
         $address .= $parts['user'];

         if (!empty($parts['pass']))
             $address .= ':' . $parts['pass'];

         $address .= '@';
     }

     if (!empty($parts['host']))
     {
         $host = str_replace(',', '.', strtolower($parts['host']));

         if (strpos(ltrim($host, 'www.'), '.') === FALSE)
             $host .= '.ru';

         $address .= $host;
     }

     if (!empty($parts['port']))
         $address .= ':' . $parts['port'];

     $address .= '/';

     if (!empty($parts['path']))
     {
         $path = trim($parts['path'], ' /\\');

         if (!empty($path) AND strpos($path, '.') === FALSE)
             $path .= '/';
             
         $address .= $path;
     }

     if (!empty($parts['query']))
         $address .= '?' . $parts['query'];

     return $address;
  }

  else return FALSE;
}


function PrintBadge($content)
{
  if ($content)
  echo "<span class='badge'>$content</span>";
}



function translit($str) 
{
  // Таблица русского алфавита:
  $trans_table_ru = array(
      'А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ё', 'ё', 
      'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 
      'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 
      'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'ый', 'Ы', 'ы', 'Э', 'э', 
      'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ю', 'ю', 'Я', 'я'
  );
  // Таблица латинского алфавита для адекватной замены букв (транслит):
  $trans_table_lat = array(
      'A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'E', 'e', 
      'Zh', 'zh', 'Z', 'z', 'I', 'i', 'Y', 'y', 'K', 'k', 'L', 'l', 'M', 'm', 
      'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 
      'F', 'f', 'Kh', 'kh', 'Ts', 'ts', 'y', 'Y', 'y', 'E', 'e',
      'Ch', 'ch', 'Sh', 'sh', 'Sch', 'sch', 'Yu', 'yu', 'Ya', 'ya'
  );

  // заменяем пробелы на знак подчерка:
  $str = str_replace(" ", "_", $str);
  // Убираем все не алфавитные символы, а также некоторые непроизносимые:
  $str = preg_replace('/Ь|ь|Ъ|ъ/', '', $str);
  // убираем все дублирующиеся подчерки (нам они не нужны):
  $str = preg_replace('/_+/', '_', $str);
  // обрезаем строку:
  //$str = trim($str, "_");
  // переводим русские символы в аналогичные латинские по определеным выше
  // правилам:
  $str = str_replace($trans_table_ru, $trans_table_lat, $str);
  // переводим в нижний регистр:
  //$str = strtolower($str);

  return $str;
}




// рекурсивно удалить непустую директорию
function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object); 
       } 
     } 
     reset($objects); 
     rmdir($dir); 
   } 
 } 



function DeleteAnnualZayavka($zayavka_id)
{
  global $mysqli;

  $zayavka_id = quote_smart($zayavka_id);
  $Zayavka = GetRow('zayavka', 'id', $zayavka_id);

  if ($Zayavka)
  {
    $create_date_array = ParseDateSQL($Zayavka['create_date']);
    $create_year = $create_date_array['year'];

    // удалим позиции и файлы
    $Positions = GetRows('position', array('zayavka_id' => $zayavka_id));
    if ($Positions) foreach ($Positions as $position)
    {
      $position['create_year'] = $create_year;
      DeleteAnnualPositionOrFile($position, false);
    }

    // удалим адреса
    $AdRes = $mysqli->query("DELETE FROM `zayavka_addresses` WHERE `zayavka_id` = '$zayavka_id");
    if (!$AdRes) EchoLog("Ошибка удаления адресов в DeleteAnnualZayavka");

    // удалим директорию заявки

    rrmdir($_SERVER['DOCUMENT_ROOT'] . "/files/$create_year/annual/$zayavka_id");


    $Result = $mysqli->query("DELETE FROM `zayavka` WHERE `id` = '$zayavka_id'");
    if ($Result)
    {
      return true;
    }
    else
    {
      EchoLog("Ошибка удаления заявки: $zayavka_id: " . $mysqli->error);
      return false;
    }
  }
  else
  {
    EchoLog("Ошибка удаления заявки: $zayavka_id: заявка не найдена");
    return false;
  }

}

// удалить заявку
function DeleteZayavka($zayavka_id)
{
  global $mysqli;

  $zayavka_id = quote_smart($zayavka_id);
  $Zayavka = GetRow('zayavka', 'id', $zayavka_id);

  if ($Zayavka)
  {
    // $create_date_array = ParseDateSQL($Zayavka['create_date']);
    // $create_year = $create_date_array['year'];

    // удалим позиции и файлы
    $Positions = GetRows('positions', array('zayavka_id' => $zayavka_id));

    // Удалим источники
    $mysqli->query("DELETE FROM `zayavka_sources` WHERE `zayavka_id` = '$zayavka_id'");
    $mysqli->query("DELETE FROM `zayavka_soglasovania` WHERE `zayavka_id` = '$zayavka_id'");

    if ($Positions) foreach ($Positions as $position)
    {
      // $position['create_year'] = $create_year;
      DeletePosition($position);
    }

    // удалим адреса
    // $AdRes = $mysqli->query("DELETE FROM `zayavka_addresses` WHERE `zayavka_id` = $zayavka_id");
    // if (!$AdRes) EchoLog("Ошибка удаления адресов в DeleteQuartalZayavka");

    // удалим файлы директорию заявки
    $Dir = GetDir($Zayavka);

    $Files = GetRows('zayavka_price_justification_files', ['zayavka_id' => $zayavka_id]);

    if ($Files) foreach ($Files as $file)
    {
      @unlink($Dir . $file['file_name']);
      $mysqli->query("DELETE FROM `zayavka_price_justification_files` WHERE `id` = $file[id]");
    }

    // rrmdir($Dir);
    // TMP comment
    $Result = $mysqli->query("DELETE FROM `zayavka` WHERE `id` = '$zayavka_id'");
    if ($Result)
    {
      return true;
    }
    else
    {
      EchoLog("Ошибка удаления заявки: $zayavka_id: " . $mysqli->error);
      return false;
    }
  }
  else
  {
    EchoLog("Ошибка удаления заявки: $zayavka_id: заявка не найдена");
    return false;
  }

}



function GetDir($Zayavka, $check_existence = false)
{
  if (!is_array($Zayavka) || !$Zayavka)  return false;

  global $_SERVER;

  return $_SERVER['DOCUMENT_ROOT'] . GetDirUrl($Zayavka, $check_existence);
}


// директория для заявки
function GetDirUrl($Zayavka, $check_existence = false)
{
  if (!is_array($Zayavka) || !$Zayavka)  return false;

  global $_SERVER;

  if ($check_existence)
  { 
    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . "/files/$Zayavka[year]")) mkdir($_SERVER['DOCUMENT_ROOT'] . "/files/$Zayavka[year]");

    if (!is_dir($_SERVER['DOCUMENT_ROOT'] . "/files/$Zayavka[year]/$Zayavka[id]")) mkdir($_SERVER['DOCUMENT_ROOT'] . "/files/$Zayavka[year]/$Zayavka[id]");
  } 

// print_r($Zayavka);
  if (!$Zayavka['year'])
  {
    EchoLog("GetDirUrl: нет года заявки (`year`) $Zayavka[id]", 'file mail');
  }

  return "/files/$Zayavka[year]/$Zayavka[id]/";
}

/**
* Преобразовать массив в тип данных JSON
* { to: 'Ivan', from: 'Andrey' }
*
* @param array $arr
*   Ассоциативный массив
* @return string
*   Строка JSON
*/

function array2json($arr)
{
  //!!!
  //return json_

  $parts = array();
  if (!is_array($arr)) return;
  if (count($arr) === 0) return '{}';

  $keys = array_keys($arr);
  foreach($keys as $key)
  {
    if (is_array($arr[$key])) 
    { //Custom handling for arrays
      $parts[] = '"' . $key . '":' . json_encode($arr[$key]); /* :RECURSION: */
    }
    else
    {
      $str = '"'.$key.'":';
      //Custom handling for multiple data types
      if (is_numeric($arr[$key]) && mb_substr($arr[$key], 0, 1) != 0) $str .= $arr[$key]; //Numbers
      elseif ($arr[$key] === false) $str .= 'false'; //The booleans
      elseif ($arr[$key] === true) $str .= 'true';
      else $str .= '"'.strtr($arr[$key],
      array('\\'=>'\\\\', '/'=>'\/', '"'=>'\"', "\b"=>'\b', "\t"=>'\t', "\n"=>'\n', "\f"=>'\f', "\r"=>'\r')
      ).'"'; //All other things

      $parts[] = $str;
    }
  }
  return '{'.implode(',', $parts).'}'; //Return associative JSON
} 


function ArrayToJS($array)
{
  $result_array = array();

  foreach ($array as $ind => $element)
  {
    $result_array[] = "'$ind': '$element'";
  }

  // EchoLog($result_array);

  return JoinArrayElements($result_array);
}


// $set_indexes - установить индексы такими же, как элементы массива ('abc' => 'abc', ...)
// !!! ФУНКЦИЯ ОБНОВЛЕНА, ПРОВЕРИТЬ ВСЕ ЛЕГАСИ USAGES
function ExplodePalki($str, $set_indexes = false)
{

  $arr = explode('|', $str);

  $arr = array_slice($arr, 1, sizeof($arr) - 2);

  if ($set_indexes && $arr)
  {
    $src_arr = $arr;
    $arr = [];

    foreach ($src_arr as $el)
    {
      $arr[$el] = $el;
    }
  }

  return $arr;

}


function ImplodePalki($arr)
{
  return JoinArrayElements($arr, '|', true);
}


function DeleteArrayElement($arr, $elem)
{
  $new = array();

  if ($arr) foreach ($arr as $ind => $e)
  {
    if (strcmp($e, $elem) !== 0) $new[$ind] = $e;
  }

  return $new;
}


/**
* Обезопасить пользовательские данные перед выводом в HTML код
*
* @param mixed $values
*   Строка или массив строк
* @return mixed
*/

function SecureOutput($values)
{
  if (is_array($values))
  {
    foreach ($values as $id => $value)
    {
      if (is_array($value)) $values[$id] = SecureOutput($value);
      else $values[$id] = htmlspecialchars ($value, ENT_QUOTES);
    }
    return $values;
  }
  else
  {
    return htmlspecialchars ($values, ENT_QUOTES);
  }
  return $values;
}

/*
function PrepareForJSON($values)
{
  if (is_array($values))
  {
    foreach ($values as $id => $value)
    {
      $values[$id] = str_replace(
        ['\\', "\r\n", '"'],
        ['', '\\n', '\"'],
        $value
      );
    }
    return $values;
  }
  else
  {
    return htmlspecialchars ($values, ENT_QUOTES);
  }
}
*/

// в каждом элементе массива нужно свойство podrazdelenia_chain
function AddChainString($Rows, $remove_zero_level = false)
{
  $podrazdelenia_table_name = 'podrazdelenia' . date('Y');

  if ($Rows)
  foreach ($Rows as $_ind => $row)
  {
    $row = AddChainStringOneRow($row, $remove_zero_level);
    $Rows[$_ind] = $row;
  }

  return $Rows;
}

function AddChainStringOneRow($row, $remove_zero_level)
{
  $podrazdelenia_table_name = 'podrazdelenia' . date('Y');

  $podrazdelenia_chain = $row['podrazdelenia_chain'];
  $chain_array = ExplodePalki($podrazdelenia_chain);
  if ($chain_array)
  {
    $podrazdelenia_chain_str_array = [];

    foreach ($chain_array as $parent_id)
    {
      $Parent = GetRow($podrazdelenia_table_name, array('id' => $parent_id));
      $podrazdelenia_chain_str_array[] = "$Parent[pname]";
    }

    $row['podrazdelenia_chain_str'] = JoinArrayElements($podrazdelenia_chain_str_array, ' / ');
    if ($remove_zero_level) ShortenChain($row);
    

    // EchoLog($podrazdelenia_chain_str);
  }

  return $row;
}


// удалить в цепочке подразделений нулевой уровень
function ShortenChain(&$phone)
{
  // $tmp = $phone['podrazdelenia_chain_str'];

  if (isset($phone['podrazdelenia_chain_str']) && isset($phone['podrazdelenia_chain']))
  {
    $phone['podrazdelenia_chain_str'] = str_replace('Факультеты, институты и филиалы / ', '', $phone['podrazdelenia_chain_str']);
    $phone['podrazdelenia_chain'] = str_replace('|00255', '', $phone['podrazdelenia_chain']);

    // if (strcmp($phone['podrazdelenia_chain_str'], $tmp) != 0) // вырезка произошла
    // {
    //   $cutted = true;
    // }

    $phone['podrazdelenia_chain_str'] = str_replace('Научно-исследовательские подразделения и организации / ', '', $phone['podrazdelenia_chain_str']);
    $phone['podrazdelenia_chain'] = str_replace('|01276', '', $phone['podrazdelenia_chain']);

    $phone['podrazdelenia_chain_str'] = str_replace('Общеуниверситетские подразделения / ', '', $phone['podrazdelenia_chain_str']);
    $phone['podrazdelenia_chain'] = str_replace('|01322', '', $phone['podrazdelenia_chain']);

    $phone['podrazdelenia_chain_str'] = str_replace('Управленческие и административно-хозяйственные подразделения / ', '', $phone['podrazdelenia_chain_str']);
    $phone['podrazdelenia_chain'] = str_replace('|00253', '', $phone['podrazdelenia_chain']);

  }

  // return $chain;
}


// для javascript
function ccode($city_id)
{
  global $_cities_codes;

  return str_replace('9', '\\9', $_cities_codes[$city_id]);
}

function add_hyphen($phone)
{

  if (strpos($phone, '-') !== false) return $phone; // дефисы уже есть
  else
  {
    $phone_length = strlen($phone);
    $new_phone = '';

    // $num_part = 3;
    for ($i = $phone_length - 1; $i>=0; $i--)
    {
      $new_phone = $phone[$i] . $new_phone;
      if ($i!=0 && (strlen($new_phone) == 2 || strlen($new_phone) == 5)) $new_phone = '-' . $new_phone;
    }

    return $new_phone;
  }
}

// подготовить запись-телефон для вывода в карточку на экране
function PrepareForCard($row)
{
  global $_cities_codes, $_cities;

  // добавим код города
  if (!$row['inner_phone']) $row['phone'] = '(' . $_cities_codes[$row['city']] . ") " . $row['phone'];
  // добавим дефисы
  $row['phone'] = add_hyphen($row['phone']);
  $row['title'] = $row['phone'];
  if ($row['dobav']) $row['title'] .= " доб. $row[dobav]";

  if ($row['fio']) $row['title'] .= ' - ' . $row['fio'];
  if ($row['description']) $row['dolzhnost'] = $row['description']; // доп. описание вместо должности

  ShortenChain($row);
  $podrazdelenia_chain_array = explode(' / ', $row['podrazdelenia_chain_str']);
  $ukrup_name = $podrazdelenia_chain_array[0];
  $row['ukrup_name'] = $ukrup_name;

  $podrazdelenie_out = [];
  if ($row['ukrup_name']) $podrazdelenie_out[] = $row['ukrup_name'];
  if ($row['podrazdelenie_title'] && strcmp($row['ukrup_name'], $row['podrazdelenie_title']) != 0) $podrazdelenie_out[] = $row['podrazdelenie_title'];

  $row['podrazdelenie_out'] = implode(' / ', $podrazdelenie_out);

  $row['address'] = $_cities[$row['city']] ;
  if ($row['street']) $row['address'] .= ", $row[street] $row[building]";
  if ($row['korpus']) $row['address'] .= ", корп. $row[korpus]";
  if ($row['room'])
  {
    if (mb_stripos($row['room'], 'вахт', 0, 'UTF-8') === false)
    {
      $row['address'] .= ", комн. $row[room]";
    }
    else
    {
      $row['address'] .= ", $row[room]";
      $vahta = true;
    }
  }

  $hint = [];
  if ($row['dolzhnost']) $hint[] = $row['dolzhnost']; 
  if ($ukrup_name) $hint[] = $ukrup_name; elseif($row['podrazdelenie_title']) $hint[] = $row['podrazdelenie_title'];
  if ($vahta) $hint[] = $row['address'];
  $row['hint'] = implode(', ', $hint);

  if ($row['dobav']) $row['phone'] .= " доб. $row[dobav]";

  return $row;
}

// $phone - телефон вида 9082312132
// TMP
function FormatPhone($phone)
{
  return $phone;
}

// Comparison function для ранжирования записей
function weight_cmp($a, $b) {
    if ($a['weight'] == $b['weight']) {
        return 0;
    }
    return ($a['weight'] > $b['weight']) ? -1 : 1;
}



// Comparison function для ранжирования записей
function title_cmp($a, $b) {
    
  return mb_strcasecmp($a['title'], $b['title']);
}

// Функция для сортировки
function sort_title_cmp($a, $b) {
    if ($a['sort'] == $b['sort']) {
        return strcmp($a['title'], $b['title']);
    }
    return ($a['sort'] < $b['sort']) ? -1 : 1;
}

function ApplyDopWeights($phone)
{
  global $_weights;

  $phone['weight'] -= strlen($phone['dolzhnost']);
  ShortenChain($phone);
  $phone['weight'] -= strlen($phone['podrazdelenia_chain_str']);

  if (!$phone['fio']) $phone['weight'] -= $_weights['podrazdelenia_chain_str'];

  return $phone;
}


function utfCharToNumber($char) 
{
   $i = 0;
   $number = '';
   while (isset($char{$i})) {
       $number.= ord($char{$i});
       ++$i;
       }
   return $number;
}

function codeOfLetter($str, $pos = 0)
{
  return utfCharToNumber(mb_substr($str, $pos, 1, 'UTF-8'));
}




/**
 * Возвращает сумму прописью
 * @author runcore
 * @uses morph(...)
 */
function num2str($num) {
  $nul='ноль';
  $ten=array(
    array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),
    array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять'),
  );
  $a20=array('десять','одиннадцать','двенадцать','тринадцать','четырнадцать' ,'пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать');
  $tens=array(2=>'двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят' ,'восемьдесят','девяносто');
  $hundred=array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот','восемьсот','девятьсот');
  $unit=array( // Units
    array('копейка' ,'копейки' ,'копеек',  1),
    array('рубль'   ,'рубля'   ,'рублей'    ,0),
    array('тысяча'  ,'тысячи'  ,'тысяч'     ,1),
    array('миллион' ,'миллиона','миллионов' ,0),
    array('миллиард','милиарда','миллиардов',0),
  );
  //
  list($rub,$kop) = explode('.',sprintf("%015.2f", floatval($num)));
  $out = array();
  if (intval($rub)>0) {
    foreach(str_split($rub,3) as $uk=>$v) { // by 3 symbols
      if (!intval($v)) continue;
      $uk = sizeof($unit)-$uk-1; // unit key
      $gender = $unit[$uk][3];
      list($i1,$i2,$i3) = array_map('intval',str_split($v,1));
      // mega-logic
      $out[] = $hundred[$i1]; # 1xx-9xx
      if ($i2>1) $out[]= $tens[$i2].' '.$ten[$gender][$i3]; # 20-99
      else $out[]= $i2>0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
      // units without rub & kop
      if ($uk>1) $out[]= morph($v,$unit[$uk][0],$unit[$uk][1],$unit[$uk][2]);
    } //foreach
  }
  else $out[] = $nul;
  $out[] = morph(intval($rub), $unit[1][0],$unit[1][1],$unit[1][2]); // rub
  $out[] = $kop.' '.morph($kop,$unit[0][0],$unit[0][1],$unit[0][2]); // kop
  return trim(preg_replace('/ {2,}/', ' ', join(' ',$out)));
}

/**
 * Склоняем словоформу
 * @ author runcore
 */
function morph($n, $f1, $f2, $f5) {
  $n = abs(intval($n)) % 100;
  if ($n>10 && $n<20) return $f5;
  $n = $n % 10;
  if ($n>1 && $n<5) return $f2;
  if ($n==1) return $f1;
  return $f5;
}


function GetUnits()
{
  return GetTable('okei', null, 'rowid, name', null, 'code, name, national');
}

function HasAccessToCFO($cfo_id)
{
  global $_SESSION;

  $cfo_ids_arr = ExplodePalki($_SESSION['c_cfo_ids'], true);

  return isset($cfo_ids_arr[$cfo_id]);
}


// $just_quantity сейчас не используется
function GetZayavkiForGreenTable($just_quantity = false, $year = null, $filter = [], $orderBy = '', $filter_where = '', $sLimit = '')
{
  global $_SESSION;

  if (!$filter['zayavki_main']) $filter['zayavki_main'] = 'need_process';
  if (!$filter['smeta_main']) $filter['smeta_main'] = 'all';
  
  $c_access = $_SESSION['c_access'];
  $c_login = $_SESSION['c_login'];

  $c_roles = ExplodePalki($_SESSION['c_roles'], true);

  // EchoLog($filter);

  // if (!$c_access) return;

  $sql_or_arr = [];

  // OLD
  // if ($c_access == 'author')
  // {
  //   $admin_sql = "`login` = '$c_login'";
  // }
  // см. таблица Согласования в ТЗ 4
  //  else

  if ($c_access == 'full' || ($c_roles['auditor'] && sizeof($c_roles) == 1))
  {
    $sql_or_arr[] = '1';
  }

  // if ($c_roles['rukovoditel_zakupki'] || $c_roles['sotrudnik_zakupki'] || $c_roles['rukovoditel_finance'] || $c_roles['sotrudnik_finance'] || $c_roles['rukovoditel_science'] || $c_roles['sotrudnik_science'] || $c_roles['rukovoditel_grants'] || $c_roles['sotrudnik_grants'] || $c_roles['prorector_finance'] || $c_roles['prorector_science'])
  // {
  //   // $admin_sql = 1; //" `status` <> 'initial' AND `status` <> 'dorabotka'";
  //   $sql_or_arr[] = "`status` <> 'new'";
  // }

  // if ($c_roles['rukovoditel_ccompetence'] || $c_roles['sotrudnik_ccompetence'])
  // {
  //   $sql_or_arr[] = "`status` <> 'new' AND `ccompetence_podr_id` = '$_SESSION[c_podr_id]'";
  // }

  // Курирующий проректор
  // if ($c_roles['prorector_kur'])
  // {
  //   $sql_or_arr[] = "`status` <> 'new' AND `prorector_login` = '$_SESSION[c_login]'";
  // }

  // EchoLog($_SESSION);

  if ($_SESSION['c_cfo_ids']) // $c_access == 'cfo' || $c_roles['cfo_otdel_zakupki'])
  {
    $cfo_ids_arr = ExplodePalki($_SESSION['c_cfo_ids']);

    $cfo_ids_sql = JoinArrayElements($cfo_ids_arr);

    

    // $admin_sql = "`cfo_id` = '$_SESSION[c_cfo_id]'";
    // $sql_or_arr[] = "`cfo_id` IN($cfo_ids_sql) OR `main_cfo_id` IN($cfo_ids_sql)";
  }

  if ($_SESSION['c_main_cfo_ids'])
  {
    $main_cfo_ids_sql = JoinArrayElements(ExplodePalki($_SESSION['c_main_cfo_ids']));
  }

  if ($_SESSION['c_cfo_helper_ids'])
  {
    $helper_cfo_ids_sql = JoinArrayElements(ExplodePalki($_SESSION['c_cfo_helper_ids']));
  }

  // if ($c_roles['zayavka_dop_admin'])
  // {
  //   // ТЗ закупки доработки 4:
  //   // У дополнительных администраторов заявки в статусе «Новая» или «На доработке» показываются в фильтре «Требуют обработки» только если там нет признака заявки

  //   $sql_or_arr[] = "
  //     `dop_admin_login` = '$c_login' AND 
  //     (
  //       `status` NOT IN ('new', 'dorabotka') OR 
  //       (`status` IN ('new', 'dorabotka') AND `status_sign` = '')
  //     )";
  // }
  // else
  // // ! Другие роли ничего не увидят (не положено)
  // {
  //   $admin_sql = "1 = 0";
  // }



  // ТРЕБУЮТ ОБРАБОТКИ
  if ($filter['zayavki_main'] == 'need_process')
  {
    // $sql_or_arr = [];

    // Руководитель подразделения закупок
    if ($c_roles['rukovoditel_zakupki'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_zakupki', 'approved')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_zakupki', 'approved', 'prepare_eis')";
    }
    // Сотрудник подразделения закупок
    // Либо Сотрудник подразделения закупок *выделенный*
    if ($c_roles['sotrudnik_zakupki'])
    {
      // $sql_or_arr[] = "(`status` IN ('soglasovanie_zakupki') OR (`status` IN ('soglasovanie_tz', 'accepted_tz_initiator', 'placed') AND `dedicated_sotrudnik_login` = '$c_login'))";

      $sql_or_arr[] = "`status` IN ('soglasovanie_zakupki', 'soglasovanie_tz', 'accepted_tz_initiator', 'placed', 'placement_eis') AND `kurators_logins` LIKE('|%$_SESSION[c_login]%|')";
    }
    // Руководитель подразделения финансов
    if ($c_roles['rukovoditel_finance'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_finance')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_finance', 'limits_correction')";
    }
    // Сотрудник подразделения финансов
    if ($c_roles['sotrudnik_finance'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_finance')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_finance', 'limits_correction') AND `kurators_logins` LIKE('|%$_SESSION[c_login]%|')";
    }
    // Руководитель центра компетенций (с учетом какому именно центру направлена заявка)
    // Сотрудник центра компетенций (с учетом какому именно центру направлена заявка)
    if ($c_roles['rukovoditel_ccompetence'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_ccompetence')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_ccompetence') AND `ccompetence_podr_id` = '$_SESSION[c_ruk_podr_id]'";
    }
    if ($c_roles['sotrudnik_ccompetence'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_ccompetence')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_ccompetence') AND `ccompetence_podr_id` = '$_SESSION[c_sotr_podr_id]' AND `kurators_logins` LIKE('|%$_SESSION[c_login]%|')";
    }
    // elseif ($c_roles['sotrudnik_ccompetence'])
    // {
    //   $status_sql = "AND `status` IN ('sotrudnik_ccompetence')";
    // }
    // КУРИРУЮЩИЙ Проректор, которому расписана заявка
    if ($c_roles['prorector_kur'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_prorector') AND `prorector_login` = '$_SESSION[c_login]'";
      $sql_or_arr[] = "`status` IN ('soglasovanie_prorector') AND (`prorector_login` = '$_SESSION[c_login]' || `prorector_login` = '$_SESSION[c_prorector_zam_for_login]')";
    }
    // Руководитель по научным проектам
    if ($c_roles['rukovoditel_science'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_science')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_science', 'soglasovanie_science_funding')";
    }
    // Руководитель по грантам
    if ($c_roles['rukovoditel_grants'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_science')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_grants')";
    }
    // Сотрудник по научным проектам
    if ($c_roles['sotrudnik_science'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_science')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_science') AND `kurators_logins` LIKE('|%$_SESSION[c_login]%|')";
    }
    // Сотрудник по грантам
    if ($c_roles['sotrudnik_grants'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_science')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_grants') AND `kurators_logins` LIKE('|%$_SESSION[c_login]%|')";
    }
    // Проректор по экономике и финансам
    if ($c_roles['prorector_finance'])
    {
      // $status_sql = "AND `status` IN ('approving')";
      $sql_or_arr[] = "`status` IN ('approving')";
    }
    // Проректор по науке
    if ($c_roles['prorector_science'])
    {
      // $status_sql = "AND `status` IN ('soglasovanie_tz_prorector_science')";
      $sql_or_arr[] = "`status` IN ('soglasovanie_tz_prorector_science')";
    }

    // Администратор ЦФО
    if ($c_roles['cfo'])
    {
      // условие NOT - из ТЗ доработки 4, пункт 3 (У администратора ЦФО там не должны показываться заявки в статусе «Новая», где назначен дополнительный администратор и нет дополнительного признака (то-есть заявки, созданные «Авторами заявок», но еще не заполненные, ему не нужно видеть в фильтре «Требуют обработки»))
      $sql_or_arr[] = "
        `status` IN ('single_supplier', 'accepted_tz_zakupki', 'riu_print_production') 
        AND `cfo_id` IN($cfo_ids_sql) 
        AND NOT (`status` = 'new' AND `dop_admin_login` <> '' AND `status_sign` = '')
      ";

      // По "Доработки 10": вариант, когда есть отв. по экономике, тогда должен быть проектом научным или грантовым
      $sql_or_arr[] = "
        `status` IN ('new') 
        AND `cfo_id` IN($cfo_ids_sql)
        AND zayavka.`econom_responsible_login` <> ''
        AND (`cfo_science` = '1' OR `cfo_grants` = '1')
        AND (`status` = 'new' AND `status_sign` <> '' OR (`status` = 'new' AND `status_sign` = '' AND (`dop_admin_login` = '' OR `dop_admin_login` = '$c_login')))
        # AND NOT (`status` = 'new' AND `dop_admin_login` <> '' AND `status_sign` = '')
      ";

      // -- По "Доработки 10": вариант, когда НЕТ отв. по экономике, тогда НЕ должен быть проектом научным или грантовым
      // $sql_or_arr[] = "
      //   `status` IN ('new') 
      //   AND `cfo_id` IN($cfo_ids_sql)
      //   AND zayavka.`econom_responsible_login` = ''
      //   AND `cfo_science` <> '1' AND `cfo_grants` <> '1'
      //   AND NOT (`status` = 'new' AND `dop_admin_login` <> '' AND `status_sign` = '')
      // ";

      // это ситуация до доработок 10, т.е. когда  нет отв. по экономике в статусе new
      $sql_or_arr[] = "
        `status` IN ('new') 
        AND `cfo_id` IN($cfo_ids_sql)
        AND zayavka.`econom_responsible_login` = ''
        # AND `cfo_science` <> '1' AND `cfo_grants` <> '1'
        AND NOT (`status` = 'new' AND `dop_admin_login` <> '' AND `status_sign` = '')
      ";

      // $sql_or_arr[] = "`status` = 'dorabotka' AND `main_cfo_id` IN($cfo_ids_sql)";

      // $sql_or_arr[] = "`status` = 'soglasovanie_cfo' AND `main_cfo_id` IN($cfo_ids_sql)";

      $sql_or_arr[] = "`status` IN ('dorabotka', 'riu_calc_soglasovanie') AND `cfo_id` IN($cfo_ids_sql)";

      // До "Доработки 10"
      //  #
      $sql_or_arr[] = "`status` = 'soglasovanie_cfo' AND `main_cfo_id` IN($cfo_ids_sql) AND zayavka.`econom_responsible_login` = ''";
      // После "Доработки 10"
      //
      if ($main_cfo_ids_sql)  
      {
        $sql_or_arr[] = "`status` = 'soglasovanie_cfo' AND (`main_cfo_id` IN($main_cfo_ids_sql) OR `cfo_id` IN($main_cfo_ids_sql)) AND zayavka.`econom_responsible_login` <> ''";
      }

      // EchoLog($main_cfo_ids_sql);
    }

    // Доработки 13
    if ($c_roles['coordinator_project'])
    {
      $sql_or_arr[] = "`status` = 'soglasovanie_coordinator_project' AND `coordinator_login` = '$c_login'";
    }

    // Доработки 10
    if ($c_roles['cfo_otdel_zakupki'])
    {
      // EchoLog($cfo_ids_sql);

      $sql_or_arr[] = "
        `status` IN ('soglasovanie_otdel_zakupki_cfo') 
        AND zayavka.`econom_responsible_login` <> ''
        AND (`cfo_id` IN($cfo_ids_sql) OR `main_cfo_id` IN($cfo_ids_sql))
        #AND NOT (`status` = 'new' AND `dop_admin_login` <> '' AND `status_sign` = '')
      ";

      // $sql_or_arr[] = "`status` = 'soglasovanie_otdel_zakupki_cfo' AND `main_cfo_id` IN($cfo_ids_sql)";
    }


    if ($c_roles['zayavka_dop_admin'])
    {
      // $cfo_ids_arr = ExplodePalki($_SESSION['c_podrazdelenia_id']);
      // $cfo_ids_sql = JoinArrayElements($cfo_ids_arr);

      // ТЗ закупки доработки 4:
      // У дополнительных администраторов заявки в статусе «Новая» или «На доработке» показываются в фильтре «Требуют обработки» только если там нет признака заявки
  
      // статусы, как в ЦФО, с корректировкой выше, разбиваются на такие логические выражения:
      // в статусе single_supplier не может быть признака, там нет согласования сотрудника
      
      $sql_or_arr[] = "
        `dop_admin_login` = '$c_login' AND 
        (
          `status` IN ('single_supplier', 'accepted_tz_zakupki') AND `status_sign` <> '' OR 
          (`status` IN ('new', 'dorabotka', 'single_supplier', 'accepted_tz_zakupki', 'riu_calc_soglasovanie', 'riu_print_production') AND `status_sign` = '')
        )";
    }


    if ($c_roles['econom_responsible'])
    {
      $sql_or_arr[] = "zayavka.`econom_responsible_login` = '$c_login' AND `status` = 'soglasovanie_econom_responsible'";
    }


    if ($c_roles['rukovoditel_riu'])
    {
      $sql_or_arr[] = "`status` IN ('riu_calc_1', 'riu_calc_2', 'riu_calc_3', 'riu_calc_4', 'riu_print_production')";
    }

    if ($c_roles['sotrudnik_riu_editor'])
    {
      $sql_or_arr[] = "`status` IN ('riu_calc_1')";
    }

    if ($c_roles['sotrudnik_riu_materials'])
    {
      $sql_or_arr[] = "`status` IN ('riu_calc_2')";
    }

    if ($c_roles['sotrudnik_riu_calculation'])
    {
      $sql_or_arr[] = "`status` IN ('riu_calc_3')";
    }


    // // Сотрудник подразделения закупок (выделенный)
    // elseif ($c_roles['sotrudnik_zakupki'])
    // {
    //   $status_sql = "AND `status` IN ('soglasovanie_tz_prorector_science')";
    // }

  }
  elseif ($filter['zayavki_main'] == 'assigned_to_me')
  {
    // $sql_or_arr[] = "AND `dedicated_sotrudnik_login` = '$c_login'";
    // $sql_or_arr[] = "`dedicated_sotrudnik_login` = '$c_login'";
    $sql_or_arr[] = "1 AND `kurators_logins` LIKE('|%$_SESSION[c_login]%|')";
  }
  elseif ($filter['zayavki_main'] == 'riu')
  {
    $sql_or_arr[] = "1 AND `zakupka_type` = 'riu' AND `status` <> 'new'";
  }
  //  ВСЕ
  else
  {
    if ($c_roles['helper'])
    {
      $sql_or_arr[] = "`cfo_id` IN($helper_cfo_ids_sql)";
    }

    if ($c_roles['cfo'])
    {
      // $status_sql = '';
      $sql_or_arr[] = "`cfo_id` IN($cfo_ids_sql) OR `main_cfo_id` IN($cfo_ids_sql)";

      // координатор
      // $sql_or_arr[] = "`cfo_id` IN($cfo_ids_sql) AND `coordinator_login` = '$_SESSION[c_login]'";
    }

    // Доработки 13
    if ($c_roles['coordinator_project'])
    {
      $sql_or_arr[] = "`status` <> 'new' AND `coordinator_login` = '$c_login'";
    }

    if ($c_roles['cfo_otdel_zakupki'])
    {
      // $status_sql = '';
      // EchoLog($_SESSION['c_cfo_ids']);
      $sql_or_arr[] = "`cfo_id` IN($cfo_ids_sql) OR `main_cfo_id` IN($cfo_ids_sql)";

      // координатор
      // $sql_or_arr[] = "`cfo_id` IN($cfo_ids_sql) AND `coordinator_login` = '$_SESSION[c_login]'";

    }
    
    if ($c_roles['rukovoditel_zakupki'] || $c_roles['rukovoditel_finance'] || $c_roles['rukovoditel_science'] || $c_roles['rukovoditel_grants'] || $c_roles['prorector_finance'] || $c_roles['prorector_science'])
    {
      // $sql_or_arr[] = "`status` <> 'new'";
      $sql_or_arr[] = "`number` IS NOT NULL";
    }

    if ($c_roles['sotrudnik_zakupki'] || $c_roles['sotrudnik_finance'])
    {
      // $sql_or_arr[] = "`status` <> 'new'";
      $sql_or_arr[] = "`number` IS NOT NULL";
    }

    if ($c_roles['sotrudnik_science'])
    {
      // $sql_or_arr[] = "`status` <> 'new' AND `cfo_science` = '1'";
      $sql_or_arr[] = "`number` IS NOT NULL AND `cfo_science` = '1'";
    }

    if ($c_roles['sotrudnik_grants'])
    {
      // $sql_or_arr[] = "`status` <> 'new' AND `cfo_grants` = '1'";
      $sql_or_arr[] = "`number` IS NOT NULL AND `cfo_grants` = '1'";
    }

    if ($c_roles['rukovoditel_ccompetence'])
    {
      // EchoLog($_SESSION['c_podr_id']);

      // $status_sql = "`status` <> 'new'";
      // $dop_sql = "AND `ccompetence_podr_id` = '$_SESSION[c_podr_id]'";

      // $sql_or_arr[] = "`status` <> 'new' AND `ccompetence_podr_id` = '$_SESSION[c_podr_id]'";
      $sql_or_arr[] = "`number` IS NOT NULL AND `ccompetence_podr_id` = '$_SESSION[c_ruk_podr_id]'";
    }

    if (($c_roles['rukovoditel_ccompetence'] || $c_roles['sotrudnik_ccompetence']) && ($_SESSION['c_ruk_podr_id'] == '05050' || $_SESSION['c_sotr_podr_id'] == '05050'))
    {
      $ZayavkiGPH = GetSQL("
          SELECT `zayavka`.id, `zayavka`.number
          FROM `zayavka`
          JOIN `zayavka_sources` ON zayavka_sources.`zayavka_id` = zayavka.`id`
          WHERE zayavka_sources.`napravlenie` = 9 AND zayavka.`number` IS NOT NULL
          ");

      $zayavki_gph_ids_arr = [];

      if ($ZayavkiGPH)
      {
        foreach ($ZayavkiGPH as $zayavka)
        {
          $zayavki_gph_ids_arr[] = $zayavka['id'];
        }

        $zayavki_gph_ids_sql = JoinArrayElements($zayavki_gph_ids_arr, ", ", false, "'", "'");

        if ($zayavki_gph_ids_sql)
        {
          $sql_or_arr[] = "zayavka.`id` IN ($zayavki_gph_ids_sql)";
        }
      }
    }

    if ($c_roles['sotrudnik_ccompetence'])
    {
      // $status_sql = "`status` <> 'new'";
      // $dop_sql = "AND `ccompetence_podr_id` = '$_SESSION[c_podr_id]'";

      // $sql_or_arr[] = "`status` <> 'new' AND `ccompetence_podr_id` = '$_SESSION[c_podr_id]'";
      $sql_or_arr[] = "`number` IS NOT NULL AND `ccompetence_podr_id` = '$_SESSION[c_sotr_podr_id]'";
    }

    // курирующий
    if ($c_roles['prorector_kur'])
    {
      // $status_sql = "`status` <> 'new'";
      // $dop_sql =  "AND `prorector_login` = '$c_login'";
      // $sql_or_arr[] = "`status` <> 'new' AND (`prorector_login` = '$_SESSION[c_login]' || `prorector_login` = '$_SESSION[c_prorector_zam_for_login]')";
      $sql_or_arr[] = "`number` IS NOT NULL AND (`prorector_login` = '$_SESSION[c_login]' || `prorector_login` = '$_SESSION[c_prorector_zam_for_login]')";
    }

    if ($c_roles['zayavka_dop_admin'])
    {
      $sql_or_arr[] = "`dop_admin_login` = '$c_login'";
    }

    if ($c_roles['econom_responsible'])
    {
      $sql_or_arr[] = "zayavka.`econom_responsible_login` = '$c_login'";
    }

    if ($c_roles['rukovoditel_riu'] || $c_roles['sotrudnik_riu_editor'] || $c_roles['sotrudnik_riu_materials'] || $c_roles['sotrudnik_riu_calculation'])
    {
      $sql_or_arr[] = "zayavka.`zakupka_type` = 'riu'";
    }

  }

  // if ($sql_or_arr)
  // {
  //   // $status_sql = "AND $status_sql";
  //   $status_sql = JoinArrayElements($sql_or_arr, ' OR ', false, '(', ')');
  // }

  if ($sql_or_arr)
  {
    $admin_sql = JoinArrayElements($sql_or_arr, ' OR ', false, '(', ')');
  }
  else
  {
    $admin_sql = "1 = 0";
  }


  // EchoLog ($admin_sql); 

  if ($year)
  {
    // if ($admin_sql)
    // {
    //   $admin_sql .= " AND";
    // }

    $year_sql = "AND  `year` = '$year'";
  }

  // if (!$status_sql)
  // {
  //   $status_sql = '1';
  // }

  // if ($just_quantity)
  // {
  //   $query = "SELECT COUNT(id) as c FROM `zayavka` WHERE 1 AND ($admin_sql) AND ($status_sql) $dop_sql $year_sql";
  //   $Result = GetSQL($query);
  //   return $Result[0]['c'];
  // }
  // else


  // Подстрах
  if (!$admin_sql) $admin_sql = "1";


  $q = "($admin_sql)  $year_sql";

  if ($c_roles['auditor'])
  {
    $auditor_sql = "AND `status` <> 'new'";
  }

  // EchoLog($q);

  // $orderBy с ORDER BY
  // $Zayavki = GetTable('zayavka', $query, $orderBy);

  // if ($orderBy)
  // {
  //   $order_sql = "ORDER BY"
  // }

  // EchoLog($filter_where);

  if ($filter['smeta_main'] && $filter['smeta_main'] != 'all')
  {
    $quoted_smeta = quote_smart($filter['smeta_main']);

    $smeta_main_sql_sql = "AND EXISTS (
          SELECT 1
          FROM zayavka_sources
          WHERE zayavka_sources.`zayavka_id` = zayavka.`id` AND zayavka_sources.`smeta` = '$quoted_smeta'
      )";
  }

  $query = "SELECT zayavka.*, cfo.`title` as cfo_title, cfo.`main_cfo_id`
      FROM zayavka 
      LEFT JOIN cfo ON cfo.`id` = zayavka.`cfo_id`
      #LEFT JOIN `zayavka_kurators` ON zayavka.`id` = zayavka_kurators.`zayavka_id`
      WHERE 
      $q
      $filter_where
      $orderBy
      $sLimit
      $smeta_main_sql_sql
      $auditor_sql
      ";

  $Zayavki = GetSQL($query);

  // EchoLog($query);

  if ($Zayavki)
  {
    foreach ($Zayavki as $ind => $zayavka)
    {
      $not_empty_okdp = GetSQL("SELECT COUNT(id) as c FROM `positions` WHERE `zayavka_id` = '$zayavka[id]' AND `okpd_code` <> '' AND `okpd_code` IS NOT NULL", null);


      $not_empty_okdp = $not_empty_okdp[0]['c'];

      $zayavka_positions_num = GetSQL("SELECT COUNT(id) as c FROM `positions` WHERE `zayavka_id` = '$zayavka[id]'", null);

      $zayavka_positions_num = $zayavka_positions_num[0]['c'];

      $zayavka['okdp_percent'] = $zayavka_positions_num ? round(($not_empty_okdp / $zayavka_positions_num) * 100) : 0;



      // Получим дату последней (публичной) записи в истории
      // $Log = GetRows('log', ['zayavka_id' => $zayavka['id'], 'internal' => 0], null, "`datetime` DESC", 'datetime');

      // if ($Log[0])
      // {
      //   $date_arr = ParseDateSQL($Log[0]['datetime'], true);
      //   $date_out = "$date_arr[year]-$date_arr[month]-$date_arr[day] $date_arr[time]";
      // }

      // $zayavka['last_history_date'] = $date_out ? $date_out : '';


      $Zayavki[$ind] = $zayavka;
    }

    // EchoLog($Zayavki);
    return $Zayavki;
  }

}

/*
function GetQuartalZayavkiForGreenTable($just_quantity = false)
{
  global $_SESSION;
  $c_access = $_SESSION['c_access'];

  if (!$c_access) return;

  if ($c_access == 'cfo')
  {
    // ukrup_code исп. временно, потом будут podrazdelenia_chain скорее всего
    $admin_sql = "`cfo_podrazdelenie_id` = '$_SESSION[c_podrazdelenie_id]'";
  }

  if ($just_quantity)
  {
    $Result = GetSQL("SELECT COUNT(id) as c FROM `zayavka_quartal` WHERE $admin_sql");
    return $Result[0]['c'];
  }
  else
  {
    $Zayavki = GetTable('zayavka_quartal', $admin_sql);

    if ($Zayavki)
    {
      foreach ($Zayavki as $ind => $zayavka)
      {
        $Zayavki[$ind] = $zayavka;
      }

      return $Zayavki;
    }
  }

}
*/

function GetQuartalByMonth($month)
{
  global $_quartals;

  if ($month)
  {
    foreach ($_quartals as $q => $quartal_months)
    {
      if (in_array($month, $quartal_months))
      {
        return $q;
      }
    }
  }

  return false;

}



// прочитать содержимое CSV файла в массив со строками (строка - тоже массив с числовыми индексами)
function ReadFileIntoArray($path_to_file, $col_divider = ';', $pass_first_line = false)
{

  $file_contents = @file_get_contents($path_to_file);
  $lines_array = explode("\n", $file_contents);

  if ($lines_array)
  {
    if ($pass_first_line) array_shift($lines_array);
  
    $rows_array = [];
    foreach ($lines_array as $line)
    {
      $line = trim($line);
      if ($line)
      {
        $line_array = explode($col_divider, $line);
        $rows_array[] = $line_array;
      }      
    }

    return $rows_array;
  }
  else
  {
    EchoLog("Не найдено строк файла в ReadFileIntoArray($path_to_file)");
    return false;
  }
}


function PrepareForFilename($filename)
{
  $filename = str_replace('"', '', $filename);
  $filename = str_replace(' ', '_', $filename);
  $filename = str_replace(':', '_', $filename);

  return $filename;
}

// получить все данные по годовой заявке
// OLD
function GetAnnualZayavkaFull($id)
{
  global $_annual_statuses;

  $sql_array = array('id' => $id);

  $Row = GetRow('zayavka', $sql_array);

  if ($Row)
  {
    $create_date_array = ParseDateSQL($Row['create_date']);
    $Row['create_year'] = $create_date_array['year'];
    $Row['status_rus'] = $_annual_statuses[$Row['status']];
    $Row['dorabotka_date'] = MysqlToDE($Row['dorabotka_date']);

    $Positions = GetRows('position', array('zayavka_id' => $id));
    if ($Positions) $Row['positions'] = $Positions;

    $Files = GetRows('zayavka_files', array('zayavka_id' => $id));
    if ($Files) $Row['files'] = $Files;

    //$Addresses = GetTable('zayavka_quartal_addresses', "`zayavka_id` = $id", null, 'id');
    //if ($Addresses) $Row['addresses'] = $Addresses;

    return $Row;
  }
  else return false;
}

// получить все позиции года
function GetAnnualZayavkiAll($year)
{
  // global $_annual_statuses;
  $Row = [];
 
  $Positions = GetRows('position', array('term_year' => $year));
  if ($Positions) $Row['positions'] = $Positions;

  // !!! TODO нет фильтра адресов по году, not critical
  //$Addresses = GetTable('zayavka_quartal_addresses', null, null, 'id');
  //if ($Addresses) $Row['addresses'] = $Addresses;

  return $Row;

}


// получить все данные по квартальной заявке
// OLD
function GetQuartalZayavkaFull($id)
{
  global $_annual_statuses;

  $sql_array = array('id' => $id);

  $Row = GetRow('zayavka_quartal', $sql_array);

  if ($Row)
  {
    $create_date_array = ParseDateSQL($Row['create_date']);
    $Row['create_year'] = $create_date_array['year'];
    // $Row['status_rus'] = $_annual_statuses[$Row['status']];
    // $Row['dorabotka_date'] = MysqlToDE($Row['dorabotka_date']);

    $Positions = GetRows('position_quartal', array('quartal_zayavka_id' => $id));
    if ($Positions) $Row['positions'] = $Positions;

    $Files = GetRows('zayavka_quartal_files', array('zayavka_id' => $id));
    if ($Files) $Row['files'] = $Files;

    $Addresses = GetTable('cfo_addresses', "`cfo_podrazdelenie_id` = '$Row[cfo_podrazdelenie_id]'", null, 'id');
    if ($Addresses) $Row['addresses'] = $Addresses;

    $Mode = GetRow('modes', ['year' => $Row['year']]);

    // EchoLog($Mode);

    if ($Mode['quartal' . $Row['quartal'] . '_allowed']) $Row['editable'] = true;

    return $Row;
  }
  else return false;
}



// получить все планируемые заявки для подразделения в году
// для генерации XLS
// $sent - только планы подразделений в статусе "на проверке" или "проверен" soglasovanie / accepted
function GetPlanForYear($podrazdelenia_arr, $year, $sent = false)
{
  global $_SESSION;

  $Result = [];

  $Cfos_sprav = GetTable('cfo', "`login` <> ''", null, 'cfo_podrazdelenie_id', 'cfo_podrazdelenie_id, title' );

  if ($sent)
  {
    // Нам нужно ограничиться планами ЦФО в двух статусах
    $SentCFOPlans = GetTable("cfo_plans", "`year` = '$year' AND `status` IN ('soglasovanie', 'accepted')", "", 'cfo_podrazdelenie_id');

    // GetTable($table, $where = '', $sort_field = '', $index_field = null, $fields = '*')
  }


  // echo $Cfos_sprav[$podrazdelenia_arr['cfo_podrazdelenie_id']];

  $sql_array = array('year' => $year, 'plan' => 1);

  if ($podrazdelenia_arr['podrazdelenie_id']) $sql_array['podrazdelenie_id'] = $podrazdelenia_arr['podrazdelenie_id'];
  if ($podrazdelenia_arr['cfo_podrazdelenie_id'] != 'all')
  {
    $sql_array['cfo_podrazdelenie_id'] = $podrazdelenia_arr['cfo_podrazdelenie_id'];
    $cfo_title = $Cfos_sprav[$podrazdelenia_arr['cfo_podrazdelenie_id']]['title'];
    $Result['cfo_title'] = $cfo_title?$cfo_title:'';
  }

  if ($podrazdelenia_arr['podrazdelenie']) $sql_array['podrazdelenie'] = $podrazdelenia_arr['podrazdelenie'];
  // if ($podrazdelenia_arr['smeta']) $sql_array['smeta'] = $podrazdelenia_arr['smeta'];

  if (($_SESSION['c_access'] == 'cfo' && $_SESSION['c_podrazdelenie_id'] == $sql_array['cfo_podrazdelenie_id']) || $_SESSION['c_access'] == 'full')
  {

    $Rows = GetRows('zayavka_quartal', $sql_array, null, 'quartal');

    if ($Rows)
    {
      // $create_date_array = ParseDateSQL($Row['create_date']);
      // $Row['create_year'] = $create_date_array['year'];

      // $Rows[0]['id'] = '';



      if ($podrazdelenia_arr['cfo_podrazdelenie_id'] != 'all') $Result = $Rows[0];
      else $Result = [];

      $Result['positions'] = [];
      $Result['addresses'] = [];

      foreach ($Rows as $Row)
      {
        // не уверен 100% что правильно
        if ($sent && !$SentCFOPlans[$Row['cfo_podrazdelenie_id']])
        {
          continue;
        }

        $Positions = GetTable('position_quartal', "`quartal_zayavka_id` = $Row[id] AND `mark` <> 'deleted'");
        if ($Positions)
        {
          foreach ($Positions as $ind => $position)
          {
            $position['podrazdelenie'] = $Row['podrazdelenie'];
            $position['cfo'] = $Cfos_sprav[$Row['cfo_podrazdelenie_id']]['title'];
            $Positions[$ind] = $position;
          }

          array_splice($Result['positions'], count($Result['positions']), 0, $Positions); //array_push($Result['positions'] $Positions;
        }

        // $Files = GetRows('zayavka_quartal_files', array('zayavka_id' => $id));
        // if ($Files) $Row['files'] = $Files;

        // $Addresses = GetTable('cfo_addresses', "`cfo_podrazdelenie_id` = '$Row[cfo_podrazdelenie_id]'", null, 'id');
        // if ($Addresses) array_splice($Result['addresses'], count($Result['addresses']), 0, $Addresses);

        // $Mode = GetRow('modes', ['year' => $Row['year']]);

        // EchoLog($Mode);

        // if ($Mode['plan_allowed']) $Result['editable'] = true;

      }

      // чтобы не создавалась подпапка = id заявки
      $Result['id'] = '';
      $Result['cfo_title'] = $cfo_title?$cfo_title:'';
      

      

    }
    // else return false;
  }
  else
  {

  }

  return $Result;
}



// получить все заявки для ЦФО в году и квартале
// для генерации XLS
// $cfo_podrazdelenie_id для делегата пусто
function GetZayavkiForQuartal($cfo_podrazdelenie_id, $year, $quartal)
{
  global $_SESSION;

  // $Cfos_sprav = GetTable('cfo', "`login` <> ''", null, 'cfo_podrazdelenie_id', 'cfo_podrazdelenie_id, title' );

  $sql_array = array('year' => $year, 'plan' => 0, 'quartal' => $quartal); //, 'cfo_podrazdelenie_id' => $cfo_podrazdelenie_id);

  if ($_SESSION['c_access'] == 'cfo' || $_SESSION['c_access'] == 'full')
  {
    $sql_array['cfo_podrazdelenie_id'] = $cfo_podrazdelenie_id;
  }
  elseif ($_SESSION['c_access'] == 'author')
  {
    $sql_array['helper_login'] = $_SESSION['c_login'];
  }

  if (($_SESSION['c_access'] == 'cfo' && $_SESSION['c_podrazdelenie_id'] == $cfo_podrazdelenie_id) 
    || $_SESSION['c_access'] == 'author'
    || $_SESSION['c_access'] == 'full')
  {

    $Rows = GetRows('zayavka_quartal', $sql_array);

    if ($Rows)
    {
      // if ($podrazdelenia_arr['cfo_podrazdelenie_id'] != 'all') $Result = $Rows[0];
      // else 
      $Result = $Rows[0];

      $Result['positions'] = [];
      $Result['addresses'] = [];

      foreach ($Rows as $Row)
      {
        $Positions = GetTable('position_quartal', "`quartal_zayavka_id` = $Row[id] AND `mark` <> 'deleted'");
        if ($Positions)
        {
          foreach ($Positions as $ind => $position)
          {
            $position['podrazdelenie'] = $Row['podrazdelenie'];
            $position['cfo'] = $_SESSION['c_fio'];
            $Positions[$ind] = $position;
          }

          array_splice($Result['positions'], count($Result['positions']), 0, $Positions); //array_push($Result['positions'] $Positions;
        }

      }

      // чтобы не создавалась подпапка = id заявки
      $Result['id'] = '';

      return $Result;

    }
    else return false;
  }

}


// $type: 'annual' / 'quartal_zayavka' / 'annual_plan' / 'quartal_zayavki'
// if $zayavka_id = 'all', склеить позиции всех заявок
// $year используется при склеивании
// $zayavka_id для планируемых заявок содержит podrazdelenie_id и/или cfo_podrazdelenie_id
// $zayavka_id для quartal_zayavki (все заявки ЦФО года и квартала) содержит cfo_podrazdelenie_id
// $zayavka_id для делегата пуста
// $sent - только планы подразделений в статусе "на проверке" или "проверен" soglasovanie / accepted
function GenerateXLS($type, $zayavka_id = null, $year = null, $quartal = null, $sent = false)
{
  global $mysqli;

  global $_cities, $_cities_okato, $_SESSION;

  if ($type != 'annual' && $type != 'quartal' && $type != 'quartal_print' && $type != 'quartal_zayavka' && $type != 'quartal_zayavki' && $type != 'annual_plan')
  {
    EchoLog("GenerateXLS wrong type", 'screen file');
    return false;
  } 

  global $_quartals;

  if ($type == 'annual')
  {
    if ($zayavka_id == 'all')
    {
      if (!$year) $year = date('Y');
      $data = GetAnnualZayavkiAll($year);

      $data['year'] = $year;
      $data['create_year'] = $year;
      $data['podrazdelenie'] = '';
      $data['person'] = '';
      $data['e_mail'] = '';
      $data['smeta'] = '';
      $data['contact_fio'] = '';
      // $data[''] = '';

    }
    else
    {
      $data = GetAnnualZayavkaFull($zayavka_id);
    }

    $zayavka_table_name = 'zayavka';
  }
  elseif ($type == 'quartal_zayavka' || $type == 'quartal' || $type == 'quartal_print')
  {

    $data = GetQuartalZayavkaFull($zayavka_id); // факт. - id заявка

    $data['contact_fio'] = $data['contact_fio'] ? $data['contact_fio'] : $data['helper_fio'];

    $zayavka_table_name = 'zayavka_quartal';
  }
  elseif ($type == 'annual_plan' && isset($zayavka_id))
  {
    $podrazdelenia_arr = $zayavka_id;

    $podrazdelenie_id = $podrazdelenia_arr['podrazdelenie_id'];
    $cfo_podrazdelenie_id = $podrazdelenia_arr['cfo_podrazdelenie_id'];

    if (!$year) $year = date('Y') + 1;
    $data = GetPlanForYear($podrazdelenia_arr, $year, $sent);
    $data['year'] = $year;
    $data['create_year'] = $year;

    // print_r($data['positions']);
    // return;
  }
  elseif ($type == 'quartal_zayavki' && $quartal)
  {
    if ($_SESSION['c_access'] == 'cfo' || $_SESSION['c_access'] == 'full')
    {
      $param = $cfo_podrazdelenie_id;
    }
    elseif ($_SESSION['c_access'] == 'author')
    {
      $param = '';
    }
    
    $data = GetZayavkiForQuartal($param, $year, $quartal);
    $data['year'] = $year;
    $data['create_year'] = $year;
  }

  // elseif ($type == '');

  $podr_short = $data['podrazdelenie_short'];
  $ukrup_short = $data['cfo_podrazdelenie_short'];

  if ($type == 'annual_plan')
  {
    if ($cfo_podrazdelenie_id == 'all')
    {
      // $template_file_name = 'plan_template.xlsx';
      $simple_template_file_name = 'simple_plan_all_template.xlsx';
    }
    else
    {
      // $template_file_name = 'plan_template.xlsx';
      $simple_template_file_name = 'simple_plan_template.xlsx';
    }
  }
  elseif ($type == 'quartal_zayavki')
  {
    $simple_template_file_name = 'simple_quartal_zayavki_template.xlsx';
  }
  elseif ($type == 'quartal_print')
  {
    $simple_template_file_name = 'simple_strange_template.xlsx';
  }
  else
  {
    $template_file_name = 'template.xlsx';
    $simple_template_file_name = 'simple_template.xlsx';
  }

  // $date = date("d_m_Y");

  $result_file_name = '';

  if ($ukrup_short) $result_file_name .= $ukrup_short;
  if ($podr_short && $ukrup_short != $podr_short) $result_file_name .= '_' . $podr_short;

  if (!$result_file_name || mb_strlen($result_file_name) <= 1)
  {
    if ($type == 'annual_plan') $result_file_name = "plan";
    elseif ($type == 'quartal_zayavki') $result_file_name = 'zayavki';
    else $result_file_name = "zayavka";
  }

  // print_r($data);


  if ($type == 'annual_plan')
  {
    $simple_result_file_name = "{$year}_$result_file_name";
    // if ($podrazdelenia_arr['smeta']) $simple_result_file_name .= "_$podrazdelenia_arr[smeta]";
    $simple_result_file_name .= ".xlsx";

    $result_file_name = "{$year}_$result_file_name";
    // if ($podrazdelenia_arr['smeta']) $result_file_name .= "_$podrazdelenia_arr[smeta]";
    $result_file_name .= "_full.xlsx";
  }
  elseif ($type == 'quartal_zayavki')
  {
    $simple_result_file_name = "{$year}_{$quartal}_{$result_file_name}_{$cfo_podrazdelenie_id}";
    $result_file_name = $result_file_name;

    $simple_result_file_name .= ".xlsx";
    $result_file_name .= "_full.xlsx";
  }
  else
  {
    $simple_result_file_name = $result_file_name . "_$zayavka_id.xlsx";
    $result_file_name .= "_{$zayavka_id}_full.xlsx";
  }

  $result_file_name = PrepareForFilename($result_file_name);
  $simple_result_file_name = PrepareForFilename($simple_result_file_name);

  if ($zayavka_id == 'all') $result_src_file_name = "{$year}_all";
  elseif ($type == 'annual_plan')
  {
    $result_src_file_name = "{$year}";
    if ($cfo_podrazdelenie_id) $result_src_file_name .= "_$cfo_podrazdelenie_id";
    if ($podrazdelenie_id) $result_src_file_name .= "_$podrazdelenie_id";
  }
  else $result_src_file_name = mymd5($zayavka_id, 10);

  $hash = $data['xlsx_hash']?$data['xlsx_hash']:uniq(32); // если хеш уже есть, не будем генерировать новый / crit. #/annual

  // для простого шаблона
  $simple_result_src_file_name = "{$result_src_file_name}_simple.xlsx";
  $result_src_file_name .= ".xlsx";
  // EchoLog($data);

  $dir_url = GetDir($data, true);

  $result = array('result' => 'failed');

  $data['phone'] = FormatPhone($data['phone']);
  
  // prevent from a PHP configuration problem when using mktime() and date() 
  if (version_compare(PHP_VERSION,'5.1.0')>=0) { 
      if (ini_get('date.timezone')=='') { 
          date_default_timezone_set('UTC'); 
      } 
  } 

  ini_set("mbstring.internal_encoding", "latin1"); //Sets internal encoding for 8bit encoding before the TBS instance reads the template

  $cost_itogo = 0;

  $quartals_arr = [];
  $complects = [];

  if ($data['positions']) 
  {
    //$counter = [1,1,1,1];  // счетчик позиций поквартальный для годового плана
    $counter = 1;

    foreach ($data['positions'] as $ind => $position)
    {
      $position['e_form'] = ""; //$position['e_form?'да':'нет';

      if ($position['term_quartal'])
      {
        $position['announce_month'] = $_quartals[$position['term_quartal']][0];
      }
      // определим announce_month как первый месяц квартала по term_month
      // БУДЕТ DEPR
      elseif ($position['term_month'])
      {
        foreach ($_quartals as $q => $quartal_months)
        {
          if (in_array($position['term_month'], $quartal_months))
          {
            $position['announce_month'] = $quartal_months[0];
            if (!in_array($q, $quartals_arr)) $quartals_arr[] = $q;
          }
        }
        $position['announce_year'] = $position['term_year'];
      }
      
      if (!$position['term_month'])
      {
        $position['term_month'] = $_quartals[$position['term_quartal']][2]; // последний месяц квартала
      }

      // $position['announce_year = $position['announce_month =  '';
      $position['sposob_zakupki'] = $position['region_code'] = $position['region_title'];
      // = $position['okpd_code'] = $position['okved_code'] = '';

      $position['cost'] = floatval($position['cost']);
      if ($position['mark'] != 'deleted') $cost_itogo += $position['cost'];
      //$position['cost'] = number_format($position['cost'], 2, ',', ' ');

      // $position_quartal = GetQuartalByMonth($position['term_month']);
      $position_quartal = $position['term_quartal'];

      $position['quartal'] = $position_quartal;
      $position['trebovania'] = htmlspecialchars($position['trebovania']);
      $position['predmet_dogovora'] = htmlspecialchars($position['predmet_dogovora']);
      $position['obosnovanie'] = htmlspecialchars($position['obosnovanie']);

      if ($type == 'annual_plan' || $type == 'quartal_print')
      {
        $position['number_out'] = $counter; //[$position_quartal-1];
        $counter/*[$position_quartal-1]*/++;
      }

      else
      {
        $position['number_out'] = $position['annual_position_num']?$position['annual_position_num']:$position['number'];
      }

      if ($position['address_id'])
      {
        $position['region_code'] = $_cities_okato[$data['addresses'][$position['address_id']]['city']];
        $position['region_title'] = $_cities[$data['addresses'][$position['address_id']]['city']];
      }
      else
      {
        $position['region_code'] = $position['region_title'] = '';
      }

      // $position['podrazdelenie'] = $data['podrazdelenie'];

      $data['positions'][$ind] = $position;

      // $complects[$position_quartal][] = $position;
    }

    for ($i=1; $i<=4; $i++)
    {
      foreach ($data['positions'] as $ind => $position)
      {
        if ($position['quartal'] == $i && $position['mark'] != 'deleted') $complects[] = $position;
      }
    }
  }

  // EchoLog($quartals_arr);


  sort($quartals_arr);
  
  if (sizeof($quartals_arr) && sizeof($quartals_arr) < 4) $quartals_rus = "(" . implode(',', $quartals_arr) . " квартал)";
  else $quartals_rus = '';

  // $quartals_rus = $data['quartals']?$quartals_rus:'';

  $data['quartals_rus'] = $quartals_rus;
  // для type = 'quartal_zayavki'
  if ($type == 'quartal_zayavki' && $quartal) 
  {
    $data['quartal_rus'] = $quartal . " квартал";
    $data['cfo_title'] = $_SESSION['c_fio'];
  }
  $data['cost_itogo'] = number_format($cost_itogo, 2, ',', ' ');


  // Initialize the TBS instance 
  $TBS = new clsTinyButStrong; // new instance of TBS 
  $SimpleTBS = new clsTinyButStrong;

  $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN); // load the OpenTBS plugin 
  $SimpleTBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

  if ($template_file_name) $TBS->LoadTemplate('../xlsx_templates/' . $template_file_name, OPENTBS_ALREADY_UTF8); // Also merge some [onload] automatic fields
  $SimpleTBS->LoadTemplate('../xlsx_templates/' . $simple_template_file_name, OPENTBS_ALREADY_UTF8);

  if ($template_file_name) $TBS->MergeBlock('positions', $complects);
  if ($template_file_name) $TBS->MergeField('data', $data);
  if ($zayavka_id != 'all') $SimpleTBS->MergeBlock('positions', $complects);
  if ($zayavka_id != 'all') $SimpleTBS->MergeField('data', $data);

  // Output the result as a file on the server. 
  if ($template_file_name) $TBS->Show(OPENTBS_FILE, "$dir_url$result_src_file_name"); // Also merges all [onshow] automatic fields. 
  if ($zayavka_id != 'all') $SimpleTBS->Show(OPENTBS_FILE, "$dir_url$simple_result_src_file_name");

  $result['result'] = 'success';
  $result['errCount'] = $TBS->ErrCount;
  $result['simple_errCount'] = $SimpleTBS->ErrCount;
  // $result['src_file_name'] = $template_file_name;
  // $result['simple_src_file_name'] = $simple_template_file_name;
  $result['result_src_file_name'] = $result_src_file_name;
  $result['simple_result_src_file_name'] = $simple_result_src_file_name;
  $result['result_file_name'] = $result_file_name;
  $result['simple_result_file_name'] = $simple_result_file_name;

  // если были ошибки, очистим поле файла, это нужно для зеленой таблицы
  if ($TBS->ErrCount)
  {
    $result_file_name = '';

    $result_src_file_name = '';
    EchoLog("Ошибка генерации полного XLSX, type=$type, zayavka_id=$zayavka_id");
    $result['result'] = 'failed';
  }

  if ($SimpleTBS->ErrCount)
  {
    $simple_result_file_name = '';
    $simple_result_src_file_name = '';
    EchoLog("Ошибка генерации простого XLSX, type=$type, zayavka_id=$zayavka_id");
    $result['result'] = 'failed';
  }
  
  if ($type != 'annual_plan' && $type != 'quartal_zayavki' && $TBS->ErrCount + $SimpleTBS->ErrCount == 0)
  {
    $result['xlsx_hash'] = $hash;



    $Result = $mysqli->query("UPDATE `$zayavka_table_name` SET `xlsx_file_name` = '$result_file_name', `simple_xlsx_file_name` = '$simple_result_file_name', `xlsx_file_src_name` = '$result_src_file_name', `xlsx_hash` = '$hash', `simple_xlsx_file_src_name` = '$simple_result_src_file_name', `xlsx_update_date` = NOW() WHERE `id` = $data[id]");

    if (!$Result) EchoLog("MySQL error in GenerateXLS($type, $zayavka_id): " . $mysqli->error);
  }

  // EchoLog($template_file_name);
  // EchoLog($simple_template_file_name);
  
  return $result;
}


// Сосчитать для заявки процент заполненности ОКПД
function CalcOKPDPercentage($zayavka)
{
  if ($zayavka)
  {
    $positions_num = 0; //sizeof($zayavka['positions']);
    $okpd_counter = 0;

    if ($zayavka['positions'])
    foreach ($zayavka['positions'] as $position)
    {
      if (is_object($position))
      {
        if ($position->mark != 'deleted') $positions_num++;
        if (strlen(trim($position->okpd_code)) > 0 && $position->mark != 'deleted') $okpd_counter++;
      }
      elseif (is_array($position))
      {
        if ($position['mark'] != 'deleted') $positions_num++;
        if (strlen(trim($position['okpd_code'])) > 0 && $position['mark'] != 'deleted') $okpd_counter++;
      }
    }

    if ($positions_num == 0) $percentage = 0;
    else $percentage = round(($okpd_counter / $positions_num) * 100, 1);

    return $percentage;
  }

  else return false;
}


// Human password
function randomPassword($len = 8){
  /* Programmed by Christian Haensel
  ** christian@chftp.com
  ** http://www.chftp.com
  **
  ** Exclusively published on weberdev.com.
  ** If you like my scripts, please let me know or link to me.
  ** You may copy, redistribute, change and alter my scripts as
  ** long as this information remains intact.
  **
  ** Modified by Josh Hartman on 12/30/2010.
  */
  if(($len%2)!==0){ // Length paramenter must be a multiple of 2
    $len=8;
  }
  $length=$len-2; // Makes room for the two-digit number on the end
  $conso=array('b','c','d','f','g','h','j','k','m','n','p','r','s','t','v','x','z');
  $vocal=array('a','e','i','o','u');
  $password='';
  srand ((double)microtime()*1000000);
  $max = $length/2;
  for($i=1; $i<=$max; $i++){
    $password.=$conso[rand(0,19)];
    $password.=$vocal[rand(0,4)];
  }
  $password.=rand(10,99);
  $newpass = $password;
  return $newpass;
}


// найти самый подходящий код ОКПД для позиции
// !! использование без последних двух массивов крайне не рекомендуется
function GuessOKPD($position, $OKPD_regexp = null, $OKPD = null)
{
  // справочник ОКПД
  if (!$OKPD) $OKPD = GetTable('okpd', null, null, 'code');
  // таблица рег. выражений для угадывания
  if (!$OKPD_regexp) $OKPD_regexp = GetTable('okpd_regexp');



  $max_regexp_length = 0; 
  $regexp_src = '';
  $okpd_code = '';

  foreach ($OKPD_regexp as $regexp_obj)
  {
    $regexp_obj['regexp'] = trim($regexp_obj['regexp']);
    $regexp_src = $regexp_obj['regexp'];  // иметь исходную длину
    $regexp_obj['regexp'] = str_replace("%", "[a-zA-Z0-9_а-яА-Я]?", $regexp_obj['regexp']);
    $regexp_obj['regexp'] = str_replace("\\", "\\\\", $regexp_obj['regexp']);
    $regexp_obj['regexp'] = str_replace("/", "\/", $regexp_obj['regexp']);

    $pattern = "/^$regexp_obj[regexp]" . '([^a-zA-Z0-9_а-яА-Я]+|$)/ui';  //i


    // echo '- ' . $regexp_obj['okpd_code'] . '<br>';
    // if ()
    // {
    //   echo "$pattern&nbsp;&nbsp;&nbsp;$position[predmet_dogovora]<br>";
    // }

    try 
    {
      if (preg_match($pattern, $position['predmet_dogovora']))
      {
        // if ()
        // {
        //   echo "$regexp_src, $position[predmet_dogovora] совпало => $regexp_obj[okpd_code]<br>";
        // }

        $length = strlen($regexp_src);
        if ($length > $max_regexp_length)
        {
          // echo "лучше<br>";
          $max_regexp_length = $length;
          
          //$position['okpd_code'] = $regexp_obj['okpd_code'];
          $okpd_code = $regexp_obj['okpd_code'];
          
        }
      }
    } catch (ErrorException $e) 
    {
      echo "$pattern, $position[predmet_dogovora], $regexp_obj[okpd_code]  ";
      echo $e->getMessage() . '<br>';
    }
  }

  if ($okpd_code && $okpd_code != $position['okpd_code'])
  {
    echo "$position[predmet_dogovora]: обновили код с $position[okpd_code] на $okpd_code<br>";
    $position['okpd_code'] = $okpd_code;
  }


  // Возьмём название для найденного кода ОКПД
  if ($OKPD[$position['okpd_code']]) $position['okpd_title'] = $OKPD[$position['okpd_code']]['title'];
  else
  {
    $OKPDArr = GetRow('okpd', ['code' => str_replace('.000', '', $position['okpd_code'])]);
    if ($OKPDArr) $position['okpd_title'] = $OKPDArr['title'];
  }

  return $position;
}


function GetCFO($cfo_podrazdelenie_id)
{
  $cfo_podrazdelenie_id = quote_smart($cfo_podrazdelenie_id);

  $Rows = GetSQL("SELECT * FROM `cfo` WHERE `cfo_podrazdelenie_id` = '$cfo_podrazdelenie_id' AND `login` <> ''");
  if (is_array($Rows)) return $Rows[0];
}


/**
 * Получить атрибуты записи LDAP из-под админа
 * $attrs - массив с именами атрибутов
 *
 */

function GetLdapAttrsByAdmin($login,  $attrs, $ou = 'unn_staff')
{
  global $_ldap_url, $_ldap_accmngr_login, $_ldap_accmngr_pass;

  $ldapconn = ldap_connect($_ldap_url);
  ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
  $ldapbind = ldap_bind($ldapconn, "cn=$_ldap_accmngr_login,ou=special,dc=unn,dc=global", $_ldap_accmngr_pass);  
  $dn = "cn=$login,ou=$ou,dc=unn,dc=global";
  $sr = @ldap_read($ldapconn, $dn, "objectclass=*", $attrs);
  
  $result = array();

  if ($sr)
  {
    $entry = ldap_get_entries($ldapconn, $sr);
    foreach ($attrs as $attr)
    {
      $result[$attr] = $entry[0][$attr][0]?$entry[0][$attr][0]:'';
    }
  }

  ldap_unbind($ldapconn);

  return $result;
}


/**
* Регистрация всех действий
*
* @param int $user_id 
*   код пользователя
* @param string $log
*   текст сообщения (описание события)
* @param string $action_name
*   внутреннее обозначение события
* @param boolean $internal
*   данное сообщение только для админов (1)
* @param string $date
*   дата события в человеческом формате (DE)
*/
// В ФУНКЦИЮ НУЖНО ПЕРЕДАВАТЬ УЖЕ ЭКРАНИРОВАННЫЕ ДАННЫЕ
function ActivityLog($load_base_UID, $log, $message = '', $action_name = '', $internal = 1, $status_change = 0)
{
  global $_SERVER, $_SESSION, $mysqli;

  // эти типы записей не должны повторяться для одного человоека
  // $uniq_types = array('result_positive', 'result_negative', 'refused', 'gaveup', 'accepted', 'publication');
//  $uniq_types_sql = JoinArrayElements($uniq_types, ', ', false, "'", "'");


  $user_browser_array = user_browser($_SERVER['HTTP_USER_AGENT']);
  $user_browser = "$user_browser_array[browser] $user_browser_array[version]";
  // if ($_SESSION['c_dissovet_id']) $dissovet_id = intval($_SESSION['c_dissovet_id']); else $dissovet_id = 'NULL';
  // if ($user_id) $user_id = intval($user_id); else $user_id = 'NULL';

  if ($date)
  {
    $mysql_date = DEToMysql($date);
    $date_sql = $mysql_date;
  }
  else $date_sql = 'NOW()';

  // $log = quote_smart($log);
  // $message = quote_smart($message);

  if (is_array($log))
  {
    $log0 = $log[0];
    if ($log[1]) $log_dop1 = $log[1];
    if ($log[2]) $log_dop2 = $log[2];
    if ($log[3]) $log_dop3 = $log[3];
  }
  else $log0 = $log;

  if ($load_base_UID) $load_base_UID_sql = ",`load_base_UID` = '$load_base_UID'";

  $query = "INSERT INTO `log` SET `message` = '$message',
    `datetime` = NOW(), 
    `log_dop2` = '$log_dop2',
    `log_dop1` = '$log_dop1',
    `log_dop3` = '$log_dop3',
    `user_login` = '$_SESSION[c_login]', 
    `user_title` = '$_SESSION[c_fio]',
    `user_role` = '$_SESSION[c_roles]',
    `chair_id` = '$_SESSION[c_chair_id]',
    `action_name` = '$action_name', 
    `internal` = '$internal',
    `ip` = '$_SERVER[REMOTE_ADDR]',
    `browser` = '$user_browser',
    `status_change` = '$status_change',
    `log` = '$log0'
    $load_base_UID_sql
    ";


  // EchoLog ("Current database: " . $mysqli->query("SELECT DATABASE()")->fetch_row()[0]);

  $Result = $mysqli->query($query);

  if (!$Result && $action_name != 'authorize prorector_kur')
  {
    EchoLog("Ошибка в ActivityLog(): " . $mysqli->error, 'file mail');
    EchoLog($query, 'file mail');
  }
  else
  {
    $log_id = $mysqli->insert_id;

    // if ($zayavka_id && !$internal)
    // {
    //   $mysqli->query("UPDATE `zayavka` SET `last_history_date` = NOW() WHERE `id` = '$zayavka_id'");
    // }

    return $log_id;
  }

}

// получить distinct адреса почты админа ЦФО, ответственного и контактного лица по квартальной заявке
function GetZayavkaContacts($zayavka)
{
  
  $mails = [];

  // адрес админа ЦФО
  $Cfo = GetCFO($zayavka['cfo_podrazdelenie_id']);
  $Cfo['e_mail'] = trim($Cfo['e_mail']);
  if ($Cfo['e_mail']) $mails[] = $Cfo['e_mail'];

  // адрес ответственного
  $user = GetLdapAttrsByAdmin($zayavka['helper_login'], ['unnmail', 'displayname']);
  $user['unnmail'] = trim($user['unnmail']);
  if ($user['unnmail'] && !in_array($user['unnmail'], $mails)) $mails[] = $user['unnmail'];

  // адрес контактного лица по заявке
  $zayavka['e_mail'] = trim($zayavka['e_mail']);
  if ($zayavka['e_mail'] && !in_array($zayavka['e_mail'], $mails)) $mails[] = $zayavka['e_mail'];

  return $mails;
}


// получить distinct адреса почты админа ЦФО, ответственных и контактных лиц по плану
function GetPlanContacts($CFOPlan)
{
  
  $mails = [];

  // адрес админа ЦФО
  $Cfo = GetCFO($CFOPlan['cfo_podrazdelenie_id']);
  $Cfo['e_mail'] = trim($Cfo['e_mail']);
  if ($Cfo['e_mail']) $mails[] = $Cfo['e_mail'];

  if ($CFOPlan['cfo_podrazdelenie_id'])
  {
    $Zayavki = GetRows('zayavka_quartal', ['cfo_podrazdelenie_id' => $CFOPlan['cfo_podrazdelenie_id'], 'year' => $CFOPlan['year'], 'plan' => 1]);

    if ($Zayavki) foreach ($Zayavki as $zayavka)
    {
      // адрес ответственного
      $user = GetLdapAttrsByAdmin($zayavka['helper_login'], ['unnmail', 'displayname']);
      $user['unnmail'] = trim($user['unnmail']);
      if ($user['unnmail'] && !in_array($user['unnmail'], $mails)) $mails[] = $user['unnmail'];

      // адрес контактного лица по заявке
      $zayavka['e_mail'] = trim($zayavka['e_mail']);
      if ($zayavka['e_mail'] && !in_array($zayavka['e_mail'], $mails)) $mails[] = $zayavka['e_mail'];
    }
  }
  else
  {
    EchoLog("Нет cfo_id в GetPlanContacts()");
  }

  return $mails;
}



// Создать таблицу `$to_table` по подобию таблицы `$from_table` (если её не существует) с данными, 
// заполнить её данными из таблицы `$from_table`
// @param boolean $drop_to_table - нужно ли удалить целевую таблицу 
// если $to_table таблица уже существует, и удалять не нужно, возвращаем true
// Возвращаем true, если отработали без ошибок

function DuplicateTable($from_table, $to_table, $drop_to_table = false)
{
  global $mysqli;

  $final_result = false;
  
  $to_table_exists = $mysqli->query("SELECT * FROM `$to_table` WHERE 1=0");

  if ($to_table_exists && $drop_to_table) { if (!$mysqli->query ("DROP TABLE `$to_table`")) return false ; $to_table_exists = false; }
  if ($to_table_exists && !$drop_to_table) return true;
  
  if (!$to_table_exists)
  // не существует, создадим копию
  {
    $Result2 = $mysqli->query("CREATE TABLE `$to_table` like `$from_table`");
    if (!$Result2) { InitError ("Ошибка №1 в DuplicateTable($from_table, $to_table, $drop_to_table): " . $mysqli->error); return false; }
    else
    {
      $final_result = $mysqli->query("INSERT INTO `$to_table` SELECT * FROM `$from_table`");
      if (!$final_result) InitError ("Ошибка №2 в DuplicateTable($from_table, $to_table, $drop_to_table): " . $mysqli->error);
      
    }
  }
  return $final_result;
}


function ToUpper($str)
{
  return mb_convert_case($str, MB_CASE_UPPER); 

}

function ToLower($str)
{
  return mb_convert_case($str, MB_CASE_LOWER); 
}

function UpperFirst($str, $others_to_lower = true)
{
  if ($others_to_lower) $str = ToLower($str);
  $FirstLetter = ToUpper(mb_substr($str, 0, 1));
  $Rest = mb_substr($str, 1);
  
  $str = $FirstLetter.$Rest;

  // в случае составной-фамилии
  $first_sostavnogo_ind = mb_strpos($str, '-');
  if ($first_sostavnogo_ind !== false)
  {
    $FirstPart = mb_substr($str, 0, $first_sostavnogo_ind+1);
    $FirstLetter = ToUpper(mb_substr($str, $first_sostavnogo_ind+1, 1));
    $SecondPart = mb_substr($str, $first_sostavnogo_ind+2);
    $str = $FirstPart.$FirstLetter.$SecondPart;

    //$str{$first_sostavnogo_ind+1} = ToUpper($str{$first_sostavnogo_ind+1});
  }

  return $str;
}


// из базы Сотрудник получить контакты по логину (из ИП)
// ! предварительно необходимо подключиться к БД Сотрудник
function GetLdapContacts($login)
{
  return GetRow('ldap_employees_contacts', ['alias' => $login]);
}

function mb_strcasecmp($str1, $str2, $encoding = null) {
     if (null === $encoding) { $encoding = mb_internal_encoding(); }
     return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
}


// Удалить сотрудников роли $role
function ClearRoleSotrudniki($role)
{
  global $mysqli;

  $mysqli->query("DELETE FROM `sotrudniki` WHERE `role` = '$role'");
}


function UpdatePodrazdelenieRukovoditel($role)
{
  global $mysqli;
  
  $podrazdelenia_table_name = 'podrazdelenia' . date('Y');

  $Podrazdelenie = GetRow('podrazdelenia', ['role' => $role]);

  include '../connect/sotrudnik.php';

  $SotrudnikPodrazdelenie = GetRow($podrazdelenia_table_name, ['id' => $Podrazdelenie['podrazdelenie_id']]);

  $chief_id = $SotrudnikPodrazdelenie['chief_id'];
  $chief_fio = $SotrudnikPodrazdelenie['chief_fio'];

  $Person = GetRow('person', ['id' => $chief_id]);
  $chief_login = $Person['alias'];

  include '../connect.php';

  $Result = $mysqli->query("UPDATE `podrazdelenia` SET `chief_fio` = '$chief_fio', `chief_id` = '$chief_id', `chief_login` = '$chief_login' WHERE `role` = '$role'");

  if (!$Result)
  {
    EchoLog("Error #819 in UpdatePodrazdelenieRukovoditel($role)");
    EchoLog($mysqli->error);
  }
  else
  {
    // Если сменился руководитель, удалим его ЗАМа
    if ($Podrazdelenie['chief_id'] != $chief_id)
    {
      $zam_role = "rukovoditel_$role";
      $mysqli->query("DELETE FROM `zam` WHERE `role` = $zam_role");
      // TODO log
      // ActivityLog();
    }
  }
}


function GetZayavkaHistory($zayavka_id)
{
  // return GetRows('log', ['opop_id' => $opop_id, 'internal' => 0], null, "`datetime` DESC, `id` DESC", "datetime, log, log_dop1, log_dop2, log_dop3, user_title, action_name, message");

  // GetTable($table, $where = '', $sort_field = '', $index_field = null, $fields = '*')
  return GetTable('log', "`zayavka_id` = '$zayavka_id' AND `internal` = '0' AND `action_name` <> ''", "`datetime` DESC", null, "datetime, log, log_dop1, log_dop2, log_dop3, user_title, user_login, action_name, message, status_change, file_src_name, file_name");
}


function GetZayavkaSoglasovania($zayavka_id)
{
  $Rows = GetTable('zayavka_soglasovania', "`zayavka_id` = '$zayavka_id'", "", "sogl_type");

  // $Result = [];

  foreach ($Rows as &$row)
  {
    $row['datetime'] = MysqlToDE($row['datetime']);
  }

  return $Rows;
}


// Сохранить факт одного из согласований заявки
// Это может быть и доработка, и прекращение
// $positive (0 / 1) - означает факт согласования - положительного решения
function SetZayavkaSoglasovanie($zayavka_id, $sogl_type, $status, $positive)
{
  global $mysqli, $_SESSION;

  if (!$positive)
  {
    $positive = '0';
  }

  $query = "REPLACE `zayavka_soglasovania` 
    SET 
    `user_roles` = '$_SESSION[c_roles]',
    `user_login` = '$_SESSION[c_login]',
    `user_title` = '$_SESSION[c_fio]',
    `zayavka_id` = '$zayavka_id', `sogl_type` = '$sogl_type', `status` = '$status', `datetime` = NOW(), `positive` = '$positive'
  ";

  $mysqli->query($query);

  // EchoLog($query);

  // return GetRow('zayavka_soglasovania', ['zayavka_id' => $zayavka_id, 'sogl_type' => '$sogl_type']);
}



function CalcZayavkaPositionsCostSum($zayavka_id)
{
  $Positions = GetRows('positions', ['zayavka_id' => $zayavka_id]);

  $cost_sum = 0;

  if ($Positions)
  {
    foreach ($Positions as $position)
    {
      $cost_sum += floatval($position['cost']);
    }
  }

  // EchoLog($cost_sum);
  
  return $cost_sum;
}


function SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION, $_SERVER, $mysqli;

  // EchoLog('SendMailToCFO');
  // EchoLog($Zayavka['id']);
  // EchoLog($message_subject);

  // EchoLog(__FILE__);

  // Научный (псевдо) ЦФО
  if ($CFO['science'] || $CFO['grants'])
  {
    $cfo_login = $CFO['admin_login'];
  }
  // Обычный ЦФО
  else
  {
    // Из Сотрудника получить логин руководителя ЦФО
    // EchoLog($_SERVER['DOCUMENT_ROOT'] . '/connect/sotrudnik.php');

    include $_SERVER['DOCUMENT_ROOT'] . '/connect/sotrudnik.php';

    $podrazdelenia_table_name = "podrazdelenia" . date('Y');

    $Podrazdelenie = GetRow($podrazdelenia_table_name, ['id' => $CFO['cfo_podrazdelenie_id']]);

    if ($Podrazdelenie['chief_id'])
    {
      $Person = GetRow('person', ['id' => $Podrazdelenie['chief_id']]);
      $cfo_login = $chief_login = $Person['alias'];
    }

    include $_SERVER['DOCUMENT_ROOT'] . '/connect.php';
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

  // EchoLog($cfo_login);

  // Получим e-mail руководителя ЦФО
  if ($cfo_login)
  {
    $LdapAttrs = GetLdapAttrsByAdmin($cfo_login, ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $cfo_login != $_SESSION['c_login'])
    {
      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
  }
  else
  {
    EchoLog("Не найден логин руководителя ЦФО $CFO[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file');
  }
}


function SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;


  // Получим e-mail руководителя ЦФО
  if ($Zayavka['dop_admin_login'])
  {

    // EchoLog('SendMailToDopAdmin');
    // EchoLog($Zayavka['id']);;
    // EchoLog($message_subject);

    $LdapAttrs = GetLdapAttrsByAdmin($Zayavka['dop_admin_login'], ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $Zayavka['dop_admin_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    
  }
  else
  {
    // EchoLog("Не найден логин доп. админа по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToEconomResponsible($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;


  // Получим e-mail отв. по экономике заявки
  if ($Zayavka['econom_responsible_login'])
  {
    $LdapAttrs = GetLdapAttrsByAdmin($Zayavka['econom_responsible_login'], ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $Zayavka['econom_responsible_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    
  }
  else
  {
    // EchoLog("Не найден логин доп. админа по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToSotrudnikOtdelZakupkiCFO($status, $Zayavka, $CFO, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;
  
  // ЦФО-подразделение с отв. по экономике  
  if ($CFO['econom_responsible_login'])
  {
    $Sotrudniki = GetRows('sotrudniki_otdel_zakupki_cfo', ['cfo_id' => $CFO['id']]);
  }
  // Возможно, наша заявка относится к проекту. Тогда нужно получить базовое ЦФО.
  // У базового д.б. отв. по экономике
  elseif ($CFO['main_cfo_id'])
  {
    $CFO = GetRow('cfo', ['id' => $CFO['main_cfo_id']]);
    $Sotrudniki = GetRows('sotrudniki_otdel_zakupki_cfo', ['cfo_id' => $CFO['id']]);
  }

  if ($Sotrudniki)
  {
    foreach ($Sotrudniki as $sotrudnik)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($sotrudnik['login'], ['unnmail']);

      if ($SEND_REAL_MAILS && $sotrudnik['login'] != $_SESSION['c_login'])
      {
        $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
      }
    }
  }
}


function SendMailToRukovoditelZakupki($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToRukovoditelZakupki');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  $RukovoditelZakupki = GetRow('podrazdelenia', ['role' => 'zakupki']);

  // Получим e-mail руководителя
  if ($RukovoditelZakupki['chief_login'])
  {
    // EchoLog($RukovoditelZakupki);

    $LdapAttrs = GetLdapAttrsByAdmin($RukovoditelZakupki['chief_login'], ['unnmail']);

    // EchoLog($RukovoditelZakupki);

    if ($SEND_REAL_MAILS && $RukovoditelZakupki['chief_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

    // Отправим заместителю руководителя
    $Zam = GetRow('zam', ['login' => $RukovoditelZakupki['chief_login'], 'role' => 'rukovoditel_zakupki']);

    if ($SEND_REAL_MAILS && $Zam)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($Zam['zam_login'], ['unnmail']);

      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
  }
  else
  {
    EchoLog("Не найден логин руководителя закупок по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

}


function SendMailToSotrudnikiZakupki($status, $Zayavka, $message_subject, $message_text, $dedicated_sotrudnik_login = false)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog("SendMailToSotrudnikiZakupki $status $message_subject $dedicated_sotrudnik_login");

  $filter = ['role' => 'sotrudnik_zakupki'];

  // ограничить только одним сотрудником (выделенный)
  if ($dedicated_sotrudnik_login)
  {
    $filter['login'] = $dedicated_sotrudnik_login;
  }

  $SotrudnikiZakupki = GetRows('sotrudniki', $filter);

  // EchoLog($SotrudnikiZakupki);

  // Получим e-mail руководителя
  if ($SotrudnikiZakupki)
  {
    // EchoLog($SotrudnikiZakupki);

    foreach ($SotrudnikiZakupki as $sotrudnik)
    {
      if ($sotrudnik['login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($sotrudnik['login'], ['unnmail']);

        // EchoLog($LdapAttrs);

        if ($SEND_REAL_MAILS && $sotrudnik['login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
      }
      else
      {
        EchoLog("Не найден логин сотрудника закупок $sotrudnik[fio] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
      }
    }
    
  }
  // else
  // {
    
  // }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToSotrudnikiRIU($status, $Zayavka, $message_subject, $message_text, $role)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  EchoLog("SendMailToSotrudnikiRIU $status $message_subject");

  $filter = ['role' => $role];

  $SotrudnikiRIU = GetRows('sotrudniki', $filter);

  EchoLog($SotrudnikiRIU);

  // Получим e-mail
  if ($SotrudnikiRIU)
  {
    // EchoLog($SotrudnikiRIU);

    foreach ($SotrudnikiRIU as $sotrudnik)
    {
      if ($sotrudnik['login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($sotrudnik['login'], ['unnmail']);

        // EchoLog($LdapAttrs);

        if ($SEND_REAL_MAILS && $sotrudnik['login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
      }
      else
      {
        EchoLog("Не найден логин сотрудника РИУ $sotrudnik[fio] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
      }
    }
    
  }
  // else
  // {
    
  // }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToRukovoditelFinance($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToRukovoditelFinance');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  $RukovoditelFinance = GetRow('podrazdelenia', ['role' => 'finance']);

  // Получим e-mail руководителя
  if ($RukovoditelFinance['chief_login'])
  {
    // EchoLog($RukovoditelFinance);

    $LdapAttrs = GetLdapAttrsByAdmin($RukovoditelFinance['chief_login'], ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $RukovoditelFinance['chief_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

    // Отправим заместителю руководителя
    $Zam = GetRow('zam', ['login' => $RukovoditelFinance['chief_login'], 'role' => 'rukovoditel_finance']);

    if ($SEND_REAL_MAILS && $Zam)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($Zam['zam_login'], ['unnmail']);

      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
    
  }
  else
  {
    EchoLog("Не найден логин руководителя финансов по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToRukovoditelRIU($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  EchoLog('SendMailToRukovoditelRIU');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  $RukovoditelRIU = GetRow('podrazdelenia', ['role' => 'riu']);

  // Получим e-mail руководителя
  if ($RukovoditelRIU['chief_login'])
  {
    // EchoLog($RukovoditelRIU);

    $LdapAttrs = GetLdapAttrsByAdmin($RukovoditelRIU['chief_login'], ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $RukovoditelRIU['chief_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

    // Отправим заместителю руководителя
    $Zam = GetRow('zam', ['login' => $RukovoditelRIU['chief_login'], 'role' => 'rukovoditel_riu']);

    if ($SEND_REAL_MAILS && $Zam)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($Zam['zam_login'], ['unnmail']);

      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
    
  }
  else
  {
    EchoLog("Не найден логин руководителя РИУ по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


// Отправить письма кураторам типа role (пример: sotrudnik_finance)
function SendMailToKurators($status, $Zayavka, $role, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToSotrudnikiFinance');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  if (!$role)
  {
    EchoLog("Не задана роль в SendMailToKurators($Zayavka[id]), письмо не отправлено");
    return;
  }

  $filter = ['role' => $role];

  // $SotrudnikiFinance = GetRows('sotrudniki', $filter);

  $Kurators = GetRows('zayavka_kurators', ['zayavka_id' => $Zayavka['id'], 'role' => $role]);

  // Получим e-mail
  if ($Kurators)
  {
    foreach ($Kurators as $sotrudnik)
    {
      // EchoLog($sotrudnik);

      if ($sotrudnik['login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($sotrudnik['login'], ['unnmail']);

        // EchoLog($LdapAttrs);

        if ($SEND_REAL_MAILS && $sotrudnik['login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

        // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject . " 1", $message_text, $Zayavka['id']);
      }
      else
      {
        EchoLog("SendMailToKurators(): Не найден логин сотрудника $sotrudnik[fio] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] куратору не отправлено", 'file mail');
      }
    }

    
  }
  else
  {
    // EchoLog("SendMailToKurators($Zayavka[id], $role): Нет кураторов у заявки $Zayavka[id]", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject . " 2", $message_text, $Zayavka['id']);
}



function SendMailToRukovoditelScience($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToRukovoditelScience');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  $RukovoditelScience = GetRow('podrazdelenia', ['role' => 'science']);

  // Получим e-mail руководителя
  if ($RukovoditelScience['chief_login'])
  {
    // EchoLog($RukovoditelScience);

    $LdapAttrs = GetLdapAttrsByAdmin($RukovoditelScience['chief_login'], ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $RukovoditelScience['chief_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

    // Отправим заместителю руководителя
    $Zam = GetRow('zam', ['login' => $RukovoditelScience['chief_login'], 'role' => 'rukovoditel_science']);

    if ($SEND_REAL_MAILS && $Zam)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($Zam['zam_login'], ['unnmail']);

      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
  }
  else
  {
    // EchoLog("Не найден логин руководителя науки по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

}



function SendMailToSotrudnikiScience($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToSotrudnikiScience');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  $filter = ['role' => 'sotrudnik_science'];

  $SotrudnikiScience = GetRows('sotrudniki', $filter);

  // Получим e-mail сотрудников
  if ($SotrudnikiScience)
  {
    foreach ($SotrudnikiScience as $sotrudnik)
    {
      // EchoLog($sotrudnik);

      if ($sotrudnik['login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($sotrudnik['login'], ['unnmail']);

        // EchoLog($LdapAttrs);

        if ($SEND_REAL_MAILS && $sotrudnik['login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
      }
      else
      {
        EchoLog("Не найден логин сотрудника науки $sotrudnik[fio] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
      }
    }
 
  }
  // else
  // {
    
  // }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToRukovoditelGrants($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToRukovoditelGrants');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);
  // EchoLog($prorector_type);

  $RukovoditelGrants = GetRow('podrazdelenia', ['role' => 'grants']);

  // Получим e-mail руководителя
  if ($RukovoditelGrants['chief_login'])
  {
    // EchoLog($RukovoditelGrants);

    $LdapAttrs = GetLdapAttrsByAdmin($RukovoditelGrants['chief_login'], ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $RukovoditelGrants['chief_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

    // Отправим заместителю руководителя
    $Zam = GetRow('zam', ['login' => $RukovoditelGrants['chief_login'], 'role' => 'rukovoditel_grants']);

    if ($SEND_REAL_MAILS && $Zam)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($Zam['zam_login'], ['unnmail']);

      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
  }
  else
  {
    // EchoLog("Не найден логин руководителя грантов по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

}



function SendMailToSotrudnikiGrants($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToProrector');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);
  // EchoLog($prorector_type);

  $filter = ['role' => 'sotrudnik_grants'];

  $SotrudnikiGrants = GetRows('sotrudniki', $filter);

  // Получим e-mail сотрудников
  if ($SotrudnikiGrants)
  {
    foreach ($SotrudnikiGrants as $sotrudnik)
    {
      if ($sotrudnik['login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($sotrudnik['login'], ['unnmail']);

        if ($SEND_REAL_MAILS && $sotrudnik['login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
      }
      else
      {
        EchoLog("Не найден логин сотрудника грантов $sotrudnik[fio] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
      }
    }
 
  }
  // else
  // {
    
  // }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


// $prorector_type: prorector_finance, prorector_science
function SendMailToProrector($status, $Zayavka, $message_subject, $message_text, $prorector_type)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToProrector');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);
  // EchoLog($prorector_type);

  $filter = ['param' => $prorector_type];

  $Prorector = GetRow('params', $filter);

  // EchoLog($Prorector);

  // Получим e-mail проректора
  if ($Prorector)
  {
    $value_arr = json_decode($Prorector['value']);

    if ($value_arr->login)
    {
      $LdapAttrs = GetLdapAttrsByAdmin($value_arr->login, ['unnmail']);
      // EchoLog($LdapAttrs);

      if ($SEND_REAL_MAILS && $value_arr->login != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }

  }
  // else
  // {
  //   EchoLog("Не найден логин сотрудника финансов $sotrudnik[fio] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  // }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToCCompetence($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  EchoLog('SendMailToCCompetence');
  EchoLog($Zayavka['id']);
  EchoLog($Zayavka);

  $Ccompetences = GetRows('ccompetence', ['podrazdelenie_id' => $Zayavka['ccompetence_podr_id']]);

  EchoLog($Ccompetences);

  // Получим e-mail руководителей центров компетенции
  if ($Ccompetences)
  {
    foreach ($Ccompetences as $ccompetence)
    {
      EchoLog($ccompetence);

      if ($ccompetence['chief_login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($ccompetence['chief_login'], ['unnmail']);

        EchoLog($LdapAttrs);
        // EchoLog($_SESSION['c_login']);

        if ($SEND_REAL_MAILS && $ccompetence['chief_login'] != $_SESSION['c_login'])
        {
          EchoLog("mail_utf8: $LdapAttrs[unnmail], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka[id]");

          // $LdapAttrs['unnmail'] = 'wwwlab@unn.ru';

          $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
        }

        // Отправим заместителю руководителя
        $Zam = GetRow('zam', ['login' => $ccompetence['chief_login'], 'role' => 'rukovoditel_ccompetence']);

        EchoLog($Zam);

        if ($SEND_REAL_MAILS && $Zam)
        {
          $LdapAttrs = GetLdapAttrsByAdmin($Zam['zam_login'], ['unnmail']);

          EchoLog($LdapAttrs);

          $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
        }
      }
    }
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToCCompetenceSotrudniki($status, $Zayavka, $message_subject, $message_text)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  EchoLog('SendMailToCCompetenceSotrudniki');
  EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);

  $SotrudnikiCcompetence = GetTable("sotrudniki", "`role` = 'sotrudnik_ccompetence' AND `podrazdelenia_chain` LIKE('%|$Zayavka[ccompetence_podr_id]|%')");

  EchoLog($SotrudnikiCcompetence);

  // Получим e-mail сотрудников центров компетенции
  if ($SotrudnikiCcompetence)
  {
    // EchoLog($SotrudnikiCcompetence);

    foreach ($SotrudnikiCcompetence as $scompetence)
    {
      if ($scompetence['chief_login'])
      {
        $LdapAttrs = GetLdapAttrsByAdmin($scompetence['chief_login'], ['unnmail']);

        if ($SEND_REAL_MAILS && $scompetence['chief_login'] != $_SESSION['c_login'])
        {
          // $LdapAttrs['unnmail'] = 'wwwlab@unn.ru';
          
          $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
        }
      }

      
    }
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}


function SendMailToProrectorKur($status, $Zayavka, $message_subject, $message_text)
{
  // $Zayavka['prorector_login'], $Zayavka[prorector_fio]
  // EchoLog('SendMailToProrectorKur');

  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  // EchoLog('SendMailToProrectorKur');
  // EchoLog($Zayavka['id']);;
  // EchoLog($message_subject);
  // EchoLog($Zayavka);

  // Получим e-mail проректора
  if ($Zayavka['prorector_login'])
  {
    $LdapAttrs = GetLdapAttrsByAdmin($Zayavka['prorector_login'], ['unnmail']);

    if ($LdapAttrs['unnmail'])
    {
      // EchoLog($LdapAttrs['unnmail']);
      
      if ($SEND_REAL_MAILS && $Zayavka['prorector_login'] != $_SESSION['c_login']) $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
    else
    {
      EchoLog("Не найден e-mail курирующего проректора $Zayavka[prorector_login] по заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
    }
  }
  else
  {
    EchoLog("Не указан курирующий проректор у заявки заявке $Zayavka[id], письмо по новому статусу $status для заявки $Zayavka[id] не отправлено", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);

}



// 1) set_zayavka_status.php: При переходе заявки в статус $status нужно отправить письма по определённым ролям исходя из статуса 
// 2) add_comment.php: при написании комментария к заявке отправить уведомление по тем же ролям, в зависимости от статуса заявки
// $send_to_cfo_despite_of_status - true для отправки уведомления по комментарию админу ЦФО и доп. админу по заявке
// $Zayavka['status'] содержит статус до перехода
function SendMessageInStatus($status, $Zayavka, $message_subject, $message_text, $send_to_cfo_despite_of_status = false)
{
  global $_SERVER;

  $CFO = GetRow('cfo', ['id' => $Zayavka['cfo_id']]);

  EchoLog('SendMessageInStatus');
  EchoLog("zayavka_id: $Zayavka[id], status: $Zayavka[status]");
  // EchoLog($message_subject);
  // EchoLog($message_text);
  // EchoLog("despite of status: . $send_to_cfo_despite_of_status");

  // от 28.05.2025
  // надо делать проверку, есть ли в этой заявке в привязанной смете направление расхода «договор ГПХ»
  if ($status == 'finished' && $Zayavka['status'] == 'placement_eis')
  {
    $NapravlenieGPH = GetRow('zayavka_sources', ['zayavka_id' => $Zayavka['id'], 'napravlenie' => 9]);

    // EchoLog($NapravlenieGPH);

    // Если есть, то нужно отправить емейл руководителю центра компетенций «Управление кадров» и всем сотрудникам этого центра компетенций 
    if ($NapravlenieGPH)
    {
      // хак, чтобы использовать две функции и отправить в центр. комп. отдел кадров
      $Zayavka['ccompetence_podr_id'] = '05050';

      $message_subject = "Заявка на закупку $Zayavka[number]";
      $message_text = "Договор ГПХ на сумму свыше 100 тыс.руб. по заявке в системе закупок номер $Zayavka[number] размещен в ЕИС.<br>
      Ссылка на заявку: <a href='https://zakupki.unn.ru/#/zayavka/$Zayavka[id]'>https://zakupki.unn.ru/#/zayavka/$Zayavka[id]</a>";

      SendMailToCCompetence($status, $Zayavka, $message_subject, $message_text);
      SendMailToCCompetenceSotrudniki($status, $Zayavka, $message_subject, $message_text);
    }

    return;
  }

  if ($status == 'dorabotka' || $status == 'accepted_tz_zakupki' || $status == 'single_supplier' || $status == 'stopped' || $status == 'not_finished' || $status == 'finished')
  {
    // cfo, zayavka_dop_admin ($Zayavka['dop_admin_login'])

    SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
    SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text);
    $sent_to_cfo = true;
  }

  // Утверждена
  if ($status == 'approved')
  {
    // cfo, zayavka_dop_admin ($Zayavka['dop_admin_login']), rukovoditel_zakupki

    SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
    SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text);
    $sent_to_cfo = true;
    SendMailToRukovoditelZakupki($status, $Zayavka, $message_subject, $message_text);
  }

  if ($status == 'prepare_eis')
  {
    SendMailToRukovoditelZakupki($status, $Zayavka, $message_subject, $message_text);
  }

  if ($status == 'placement_eis')
  {
    SendMailToKurators($status, $Zayavka, 'sotrudnik_zakupki', $message_subject, $message_text);
  }

  // Закупка размещена
  if ($status == 'placed')
  {
    // cfo, zayavka_dop_admin ($Zayavka['dop_admin_login']), rukovoditel_zakupki
    // sotrudnik_zakupki ($Zayavka['dedicated_sotrudnik_login'])

    SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
    SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text);
    $sent_to_cfo = true;
    SendMailToRukovoditelZakupki($status, $Zayavka, $message_subject, $message_text);
    SendMailToKurators($status, $Zayavka, 'sotrudnik_zakupki', $message_subject, $message_text);
  }

  // На согласовании (финансы)
  if ($status == 'soglasovanie_finance')
  {
    // rukovoditel_finance, sotrudnik_finance
    SendMailToRukovoditelFinance($status, $Zayavka, $message_subject, $message_text);
    SendMailToKurators($status, $Zayavka, 'sotrudnik_finance', $message_subject, $message_text);
    
  }


  // На согласовании (закупки)
  if ($status == 'soglasovanie_zakupki')
  {
    // rukovoditel_zakupki, sotrudnik_zakupki (any)

    SendMailToRukovoditelZakupki($status, $Zayavka, $message_subject, $message_text);
    SendMailToKurators($status, $Zayavka, 'sotrudnik_zakupki', $message_subject, $message_text);
  }

  // Согласование ТЗ
  if ($status == 'soglasovanie_tz')
  {
    // sotrudnik_zakupki ($Zayavka['dedicated_sotrudnik_login'])
    // prorector_science

    SendMailToKurators($status, $Zayavka, 'sotrudnik_zakupki', $message_subject, $message_text);
    SendMailToProrector($status, $Zayavka, $message_subject, $message_text, 'prorector_science');
  }

  // ТЗ согласовано (инициатор)
  if ($status == 'accepted_tz_initiator')
  {
    // sotrudnik_zakupki ($Zayavka['dedicated_sotrudnik_login'])

    SendMailToKurators($status, $Zayavka, 'sotrudnik_zakupki', $message_subject, $message_text);
  }


  // На согласовании (центр компетенций)
  if ($status == 'soglasovanie_ccompetence')
  {
    // rukovoditel_ccompetence ($Zayavka['ccompetence_podr_id'])
    // sotrudnik_ccompetence (всем таким в таблице sotrudniki в пределах подразделения $Zayavka['ccompetence_podr_id'])

    SendMailToCCompetence($status, $Zayavka, $message_subject, $message_text);
    SendMailToCCompetenceSotrudniki($status, $Zayavka, $message_subject, $message_text);
  }

  // На согласовании (курирующий проректор)
  if ($status == 'soglasovanie_prorector')
  {
    // prorector_kur ($Zayavka['prorector_login'], $Zayavka[prorector_fio])

    SendMailToProrectorKur($status, $Zayavka, $message_subject, $message_text);
  }


  // На согласовании (наука)
  if ($status == 'soglasovanie_science')
  {
    // rukovoditel_science, sotrudnik_science

    SendMailToRukovoditelScience($status, $Zayavka, $message_subject, $message_text);
    SendMailToKurators($status, $Zayavka, 'sotrudnik_science', $message_subject, $message_text);
  }

  // На согласовании (гранты)
  if ($status == 'soglasovanie_grants')
  {
    // rukovoditel_science, sotrudnik_science

    SendMailToRukovoditelGrants($status, $Zayavka, $message_subject, $message_text);
    SendMailToKurators($status, $Zayavka, 'sotrudnik_grants', $message_subject, $message_text);
  }


  // На утверждении
  if ($status == 'approving')
  {
    // prorector_finance
    SendMailToProrector($status, $Zayavka, $message_subject, $message_text, 'prorector_finance');
  }


  // Согласование ТЗ (проректор по науке)
  if ($status == 'soglasovanie_tz_prorector_science')
  {
    // prorector_science
    SendMailToProrector($status, $Zayavka, $message_subject, $message_text, 'prorector_science');
  }


  // Согласование ЦФО
  if ($status == 'soglasovanie_cfo')
  {
    // Письмо нужно отправить руководителю основного ЦФО подразделения, указанного для ЦФО (научное, грант) заявки
    $MainCFO = GetRow('cfo', ['id' => $CFO['main_cfo_id']]);

    SendMailToCFO($status, $Zayavka, $MainCFO, $message_subject, $message_text);
  }

  // Согласование научного финансирования
  if ($status == 'soglasovanie_science_funding')
  {
    SendMailToRukovoditelScience($status, $Zayavka, $message_subject, $message_text);
  }

  if ($status == 'limits_correction')
  { 
    // rukovoditel_finance, sotrudnik_finance
    SendMailToRukovoditelFinance($status, $Zayavka, $message_subject, $message_text);
    SendMailToKurators($status, $Zayavka, 'sotrudnik_finance', $message_subject, $message_text);
  } 

  if ($status == 'soglasovanie_econom_responsible')
  {
    SendMailToEconomResponsible($status, $Zayavka, $message_subject, $message_text);
  }

  if ($status == 'soglasovanie_otdel_zakupki_cfo')
  {
    SendMailToSotrudnikOtdelZakupkiCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
  }

  // РИУ

  if ($status == 'riu_calc_1')
  {
    SendMailToRukovoditelRIU($status, $Zayavka, $message_subject, $message_text);
    SendMailToSotrudnikiRIU($status, $Zayavka, $message_subject, $message_text, 'sotrudnik_riu_editor');
  }

  if ($status == 'riu_calc_2')
  {
    SendMailToRukovoditelRIU($status, $Zayavka, $message_subject, $message_text);
    SendMailToSotrudnikiRIU($status, $Zayavka, $message_subject, $message_text, 'sotrudnik_riu_materials');
  }

  if ($status == 'riu_calc_3')
  {
    SendMailToRukovoditelRIU($status, $Zayavka, $message_subject, $message_text);
    SendMailToSotrudnikiRIU($status, $Zayavka, $message_subject, $message_text, 'sotrudnik_riu_calculation');
  }

  if ($status == 'riu_calc_4')
  {
    SendMailToRukovoditelRIU($status, $Zayavka, $message_subject, $message_text);
  }

  if ($status == 'riu_calc_soglasovanie')
  {
    SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
    SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text);
  }

  if ($status == 'riu_print_production')
  {
    SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
    SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text);
    SendMailToRukovoditelRIU($status, $Zayavka, $message_subject, $message_text);
  }


  if ($send_to_cfo_despite_of_status && !$sent_to_cfo)
  {
    SendMailToCFO($status, $Zayavka, $CFO, $message_subject, $message_text);
    SendMailToDopAdmin($status, $Zayavka, $message_subject, $message_text);
  }

}



function SendMailToKurator($Zayavka, $message_subject, $message_text, $kurator_login)
{
  global $_site_domain, $_from_mail, $SEND_REAL_MAILS, $_SESSION;

  if ($kurator_login)
  {
    $LdapAttrs = GetLdapAttrsByAdmin($kurator_login, ['unnmail']);

    // EchoLog($LdapAttrs);

    if ($SEND_REAL_MAILS && $kurator_login != $_SESSION['c_login'])
    {
      $mail_result = mail_utf8($LdapAttrs['unnmail'], $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
    }
  }
  else
  {
    EchoLog("Нет логина куратора в SendMailToKurator($Zayavka, $message_subject, $message_text, $kurator_login)", 'file mail');
  }

  // TMP
  // mail_utf8('wwwlab@unn.ru', $_site_domain, $_from_mail, $message_subject, $message_text, $Zayavka['id']);
}



function UpdateZayavkaKuratorsField($zayavka_id)
{
  global $mysqli;

  // для удобства выборки в зелёной таблице будем в заявке хранить через палки всех её кураторов (по разным подразделениям)
  $ZayavkaKurators = GetRows('zayavka_kurators', ['zayavka_id' => $zayavka_id]);

  $kurators_logins_arr = [];

  if ($ZayavkaKurators)
  {
    foreach ($ZayavkaKurators as $kur)
    {
      if (!in_array($kur['login'], $kurators_logins_arr))
      {
        $kurators_logins_arr[] = $kur['login'];
      }
    }
  }

  $kurators_logins_palki = ImplodePalki($kurators_logins_arr);

  $mysqli->query("UPDATE `zayavka` SET `status_sign` = '', `kurators_logins` = '$kurators_logins_palki' WHERE `id` = '$zayavka_id'");

}

// Проверить, что логин является куратором заявки
function IsLoginKurator($Zayavka, $login)
{
  $kurators_arr = ExplodePalki($Zayavka['kurators_logins']);
  return in_array($login, $kurators_arr);
}


// из массива вида ['column': value, ...]
// получить [value, ...]
function ExtractColumn($arr, $column_name)
{
  $result = [];

  if ($arr)
  {
    foreach ($arr as $row)
    {
      if (isset($row[$column_name]))
      {
        $result[] = $row[$column_name];
      }
    }
  }

  return $result;
}


/**
 * Нормализует UID нагрузки - обрезает последний компонент если есть 2+ точек
 * 
 * @param string $uid UID из xml_content_of_load
 * @return string Базовый UID для JOIN с xml_content_of_load_staff
 */
/*
function get_base_uid($uid) {
    $dot_count = substr_count($uid, '.');
    
    // Если 2 или больше точек - обрезаем последний компонент
    if ($dot_count >= 2) {
        return substr($uid, 0, strrpos($uid, '.'));
    }
    
    // Если 1 точка или меньше - возвращаем как есть
    return $uid;
}
*/

/**
 * Нормализует UID нагрузки - обрезает всё после второй точки (не включая вторую точку)
 * возвращает то, что до второй точки
 * 
 * @param string $uid UID из xml_content_of_load
 * @return string Базовый UID для JOIN с xml_content_of_load_staff
 */
function get_base_uid($uid) {
    $pos1 = strpos($uid, '.');
    if ($pos1 === false) {
        return $uid; // Точек нет
    }
    $pos2 = strpos($uid, '.', $pos1 + 1);
    if ($pos2 === false) {
        return $uid; // Только одна точка
    }
    return substr($uid, 0, $pos2);
}

// Получить запрос к БД для получения строк нагрузки с доп. SQL фильтром $dop_sql
// В результате запроса получается join двух таблиц нагрузки
// Данные от запроса должны пропускаться через функцию PrepareNagruzka() для подготовки к выдаче в зелёную таблицу нагрузки
// с уникализацией по base_uid
function GetNagruzkaBaseQuery($dop_sql)
{
  // if ($chair_uid)
  // {
  //   $chair_sql = "xml_content_of_load.UID_Chair = '$chair_uid' AND";
  // }

  return $_nagruzka_base_query = 
  "
  SELECT 
  xml_content_of_load.UID as original_uid,
  #SUBSTRING(xml_content_of_load.UID, 1, LENGTH(xml_content_of_load.UID) - LOCATE('.', REVERSE(xml_content_of_load.UID))) as base_uid,
  xml_content_of_load.base_uid,
  xml_content_of_load.UID as xml_content_of_load_UID,
  xml_content_of_load_staff.UID as xml_content_of_load_staff_UID,
  xml_content_of_load.YearOfEducation,
  xml_content_of_load_staff.Abbr, 
  xml_group.Name as group_name,
  xml_discipline.UID as discipline_UID,
  xml_discipline.Name as discipline_name,
  xml_faculty.Name as department_name,
  xml_speciality.Name as napravlenie,
  xml_speciality.Code as napravlenie_code,
  xml_speciality.education_level,
  xml_specialization.Name as napravlennost,
  xml_language.Name as language,
  xml_content_of_load_staff.UID_FormOfEducation,
  xml_content_of_load.UID_Semester,
  xml_content_of_load.StudentAmount,
  xml_kind_of_work.Name as kind_of_work,
  xml_content_of_load.UID_Course,
  xml_content_of_load.amount,
  xml_lecturer.FIO as galaktika_lecturer_fio,
  nagruzka.lecturer_fio, nagruzka.lecturer_uid, nagruzka.lecturer_person_id, nagruzka.status, nagruzka.chair_id, nagruzka.chair_name, nagruzka.zavkaf_fio, nagruzka.chair_name

  FROM xml_content_of_load
  JOIN xml_content_of_load_staff ON 
  # SUBSTRING(xml_content_of_load.UID, 1, LENGTH(xml_content_of_load.UID) - LOCATE('.', REVERSE(xml_content_of_load.UID))) = xml_content_of_load_staff.UID_ContentOfLoad
  xml_content_of_load.base_uid = xml_content_of_load_staff.UID_ContentOfLoad
  LEFT JOIN xml_group ON xml_group.`UID` = xml_content_of_load_staff.`UID_Group`
  LEFT JOIN xml_discipline ON xml_discipline.UID = xml_content_of_load.UID_Discipline
  LEFT JOIN xml_faculty ON xml_faculty.UID = xml_content_of_load_staff.`UID_FacultyOwner`
  LEFT JOIN xml_speciality ON xml_speciality.UID = xml_content_of_load_staff.UID_Speciality
  LEFT JOIN xml_specialization ON xml_specialization.UID = xml_content_of_load_staff.UID_Specialization
  LEFT JOIN xml_language ON xml_language.UID = xml_content_of_load_staff.UID_Language
  LEFT JOIN xml_kind_of_work ON xml_kind_of_work.UID = xml_content_of_load.UID_KindOfWork
  LEFT JOIN xml_lecturer ON xml_lecturer.UID = xml_content_of_load.UID_Lecturer
  LEFT JOIN `nagruzka` ON nagruzka.load_base_UID = xml_content_of_load.base_uid
  WHERE 
  (xml_content_of_load_staff.`Abbr` LIKE ('Б1.%') OR xml_content_of_load_staff.`Abbr` LIKE ('Ф%') OR xml_content_of_load_staff.`Abbr` LIKE ('1.%') OR xml_content_of_load_staff.`Abbr` LIKE ('1.01%') OR xml_content_of_load_staff.`Abbr` LIKE ('2.1%') OR xml_content_of_load_staff.`Abbr` LIKE ('2.01%') OR xml_content_of_load_staff.`Abbr` LIKE ('С1%') OR xml_content_of_load_staff.`Abbr` LIKE ('С2%') OR xml_content_of_load_staff.`Abbr` LIKE ('С3%') OR xml_content_of_load_staff.`Abbr` LIKE ('С4%'))
    $dop_sql
  ";

}

// Данные от запроса GetNagruzkaBaseQuery() должны пропускаться через функцию PrepareNagruzka() для подготовки к выдаче в зелёную таблицу нагрузки
// с уникализацией по base_uid
function PrepareNagruzka($_Nagruzka)
{
  global $_forms_obuchenia;
  
  $Nagruzka = [];

  if ($_Nagruzka)
  {
    foreach ($_Nagruzka as $nagruzka)
    {
      if (/* $nagruzka['base_uid'] != $nagruzka['original_uid'] && */ $Nagruzka["$nagruzka[base_uid]"])
      {
        if (!in_array($nagruzka['discipline_name'], $Nagruzka["$nagruzka[base_uid]"]['discipline_name_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid]"]['discipline_name_arr'][] = $nagruzka['discipline_name'];
        }

        if (!in_array($nagruzka['discipline_UID'], $Nagruzka["$nagruzka[base_uid]"]['discipline_UID_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid]"]['discipline_UID_arr'][] = $nagruzka['discipline_UID'];
        }

        if (!in_array($nagruzka['group_name'], $Nagruzka["$nagruzka[base_uid]"]['group_name_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid]"]['group_name_arr'][] = $nagruzka['group_name'];
        }

        if (!in_array($nagruzka['Abbr'], $Nagruzka["$nagruzka[base_uid]"]['Abbr_arr']))
        {
          $Nagruzka["$nagruzka[base_uid]"]['Abbr_arr'][] = $nagruzka['Abbr'];
        }

        if (!in_array($nagruzka['napravlenie'], $Nagruzka["$nagruzka[base_uid]"]['napravlenie_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid]"]['napravlenie_arr'][] = $nagruzka['napravlenie'];
        }

        if ($nagruzka['napravlennost'] && !in_array($nagruzka['napravlennost'], $Nagruzka["$nagruzka[base_uid]"]['napravlennost_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid]"]['napravlennost_arr'][] = $nagruzka['napravlennost'];
        }

        if (!in_array($nagruzka['department_name'], $Nagruzka["$nagruzka[base_uid]"]['department_name_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid]"]['department_name_arr'][] = $nagruzka['department_name'];
        }
        
      }
      else
      {
        $Nagruzka["$nagruzka[base_uid]"] = $nagruzka;

        $Nagruzka["$nagruzka[base_uid]"]['discipline_name_arr'] = [$nagruzka['discipline_name']];
        $Nagruzka["$nagruzka[base_uid]"]['discipline_UID_arr'] = [$nagruzka['discipline_UID']];
        
        $Nagruzka["$nagruzka[base_uid]"]['group_name_arr'] = [$nagruzka['group_name']];
        $Nagruzka["$nagruzka[base_uid]"]['Abbr_arr'] = [$nagruzka['Abbr']];
        $Nagruzka["$nagruzka[base_uid]"]['napravlenie_arr'] = [$nagruzka['napravlenie']];

        
        $Nagruzka["$nagruzka[base_uid]"]['napravlennost_arr'] = [];

        if ($nagruzka['napravlennost']) $Nagruzka["$nagruzka[base_uid]"]['napravlennost_arr'][] = $nagruzka['napravlennost'];

        $Nagruzka["$nagruzka[base_uid]"]['department_name_arr'] = [$nagruzka['department_name']];
      }
      
    }

    unset($nagruzka);
    foreach ($Nagruzka as &$nagruzka)
    {
      $nagruzka['disciplines_UIDs_chain_str'] = ImplodePalki($nagruzka['discipline_UID_arr']);
      $nagruzka['disciplines_Names_chain_str'] = ImplodePalki($nagruzka['discipline_name_arr']);
      $nagruzka['discipline_name'] = implode('<br>', $nagruzka['discipline_name_arr']);
      $nagruzka['group_name'] = implode('<br>', $nagruzka['group_name_arr']);
      $nagruzka['Abbr'] = implode('<br>', $nagruzka['Abbr_arr']);
      $nagruzka['napravlenie'] = implode('<br>', $nagruzka['napravlenie_arr']);
      $nagruzka['napravlennost'] = implode('<br>', $nagruzka['napravlennost_arr']);
      $nagruzka['department_name'] = implode('<br>', $nagruzka['department_name_arr']);
      $nagruzka['form_obuchenia'] = $_forms_obuchenia[$nagruzka['UID_FormOfEducation']];
    }
    unset($nagruzka);
  }

  return $Nagruzka;
}


// Получить одну строку нагрузки по таблице 1 и таблице 2
function GetFullNagruzkaRow($base_uid)
{
  $nagruzka_query = GetNagruzkaBaseQuery("AND `load_base_UID` = '$base_uid'");
  $_Nagruzka = GetSQL($nagruzka_query);
  return array_pop(PrepareNagruzka($_Nagruzka));
}

// Принимает результат функции GetFullNagruzkaRow()
function GetNagruzkaFieldsForMail($nagruzka)
{
  $abbr_str = implode(', ', $nagruzka['Abbr_arr']);
  $department_name_str = implode(', ', $nagruzka['department_name_arr']);
  $discipline_name_str = implode(', ', $nagruzka['discipline_name_arr']);
  $group_name_str = implode(', ', $nagruzka['group_name_arr']);
  $napravlenie_str = implode(', ', $nagruzka['napravlenie_arr']);
  $napravlennost_str = implode(', ', $nagruzka['napravlennost_arr']);

  $message_text = '';

  $message_text .= "Факультет: $department_name_str<br>";
  $message_text .= "Аббр: $abbr_str<br>";
  $message_text .= "Дисциплина: $discipline_name_str<br>";
  $message_text .= "Группа: $group_name_str<br>";
  $message_text .= "Уровень образования: $nagruzka[education_level]<br>";
  $message_text .= "Направление подготовки: $napravlenie_str<br>";
  $message_text .= "Язык программы: $nagruzka[language]<br>";
  $message_text .= "Форма обучения: $nagruzka[form_obuchenia]<br>";
  $message_text .= "Семестр (номер семестра): $nagruzka[UID_Semester]<br>";
  $message_text .= "Количество студентов: $nagruzka[StudentAmount]<br>";
  $message_text .= "Вид работ: $nagruzka[kind_of_work]<br>";
  $message_text .= "Профиль/направленность программы: $napravlennost_str<br>";
  $message_text .= "Курс: $nagruzka[UID_Course]<br>";
  $message_text .= "Количество часов: $nagruzka[amount]<br>";
  $message_text .= "Преподаватель: $nagruzka[lecturer_fio]<br>";

  return $message_text;
}


?>