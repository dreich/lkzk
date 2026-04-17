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

  session_name('lkzk');
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

  session_name('lkzk');
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
      // TMP hack вход без пароля
      // elseif (substr_count($login, '#') > 1)
      else
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
          if (substr_count($login, '#') < 2 && $password)
          {
            $attrs = AuthorizeLDAP($login, $password);
          }
          // вошли по #
          // TMP временный коммент, чтобы можно было входить без пароля
          //else // if (substr_count($login, '#') > 1)
          // if (!is_array($attrs))

          // TMP hack вход без пароля
          // if (substr_count($login, '#') > 1)
          if (!$attrs)
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
              // has_real_chief означает, что chief действительно является руководителем этого подразделения, а не прописан здесь руководитель вышестоящий
              $ChairsWithThisChief = GetTable($podrazdelenia_table_name, "`chief_id` = $Person[id] AND `pname` LIKE ('Кафедра%') AND `has_real_chief` = '1'");
              $Podrazdelenia = GetTable($podrazdelenia_table_name, "", "", "id");

              include 'connect.php';

              // Сотрудник является зав. кафедрой
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
                $_SESSION['c_department_id'] = $ChairsWithThisChief[0]['ukrup_code'];
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
              // Это просто сотрудник (кафедры)
              else
              {
                
                $SotrudnikRows = GetRows('sotrudniki', ['person_id' => $Person['id']]);

                $chairs_ids = [];
                $chairs_titles = [];
                $lecturer_uids = [];
                

                if ($SotrudnikRows)
                {
                  foreach ($SotrudnikRows as $sotrudnik_row)
                  {
                    $chairs_ids[] = $sotrudnik_row['chair_id'];
                    $chairs_titles[] = $Podrazdelenia[$sotrudnik_row['chair_id']]['pname'];
                    $lecturer_uids[] = $sotrudnik_row['lecturer_uid'];
                  }

                  $_SESSION['c_sotrudnik_chairs_ids'] = ImplodePalki($chairs_ids);
                  $_SESSION['c_sotrudnik_chairs_titles'] = ImplodePalki($chairs_titles);
                  $_SESSION['c_sotrudnik_lecturer_uids'] = ImplodePalki($lecturer_uids);

                  $_SESSION['c_login'] = $clean_login;
                  $_SESSION['c_fio'] = $attrs['displayname'];
                  $result = true;

                  if ($_SESSION['c_roles'])
                  {
                    $_SESSION['c_roles'] .= 'sotrudnik|';
                  }
                  else
                  {
                    $_SESSION['c_roles'] = '|sotrudnik|';
                  }
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
      $result = "У вас нет доступа в систему";
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

  if (!empty(trim($where))) $where_sql = "WHERE $where";
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
    EchoLog($query);
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
}

/*
function PrepareForJSON($values)
{
  if (is_array($values))
  {
    while (list($id, $value) = each($values))
    {
      $values[$id] = $value;
      
      $values[$id] = str_replace("\\", '', $values[$id]);
      $values[$id] = str_replace("\r\n", '\\n', $values[$id]);
      $values[$id] = str_replace('"', '\"', $values[$id]);
      
    }
    reset($values);
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
function ActivityLog($load_base_UID2, $log, $message = '', $action_name = '', $internal = 1, $status_change = 0)
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
    if ($log[4]) $log_dop4 = $log[4];
  }
  else $log0 = $log;

  if ($load_base_UID1) $load_base_UID1_sql = ", `load_base_UID1` = '$load_base_UID1'";
  if ($load_base_UID2) $load_base_UID2_sql = ", `load_base_UID2` = '$load_base_UID2'";

  $query = "INSERT INTO `log` SET `message` = '$message',
    `datetime` = NOW(), 
    `log_dop2` = '$log_dop2',
    `log_dop1` = '$log_dop1',
    `log_dop3` = '$log_dop3',
    `log_dop4` = '$log_dop4',
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
    $load_base_UID1_sql
    $load_base_UID2_sql
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
  // база
  226589.28147497677397
  // база.дисциплина
  226589.28147497677397.1
  // база.препод
  26589.281474976773927.26115.281474976816519
  // база.препод.дисциплина
  26589.281474976773927.26115.281474976894467.1


 * Нормализует UID нагрузки - обрезает всё после второй точки (не включая вторую точку)
 * возвращает то, что до второй точки
 * 
 * @param string $uid UID из xml_content_of_load
 * @return string Базовый UID для JOIN с xml_content_of_load_staff
 */
function get_base_uid1($uid) {
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


/**
  // база
  226589.28147497677397
  // база.дисциплина
  226589.28147497677397.1
  // база.препод
  26589.281474976773927.26115.281474976816519
  // база.препод.дисциплина
  26589.281474976773927.26115.281474976894467.1


 * если в строке $uid встречается три точки, то нужно вырезать и вернуть от начала строки до четвёртой точки либо конца строки. 
 * В противном случае (если не встречается), то просто от $uid получить и вернуть get_base_uid()
 * 
 * @param string $uid UID из xml_content_of_load
 * @return string Базовый UID для JOIN с xml_content_of_load_staff
 */
function get_base_uid2($uid) {
    // Используем explode для разделения по точкам
    $parts = explode('.', $uid);
    
    // Если частей меньше 4 (точек меньше 3), используем get_base_uid
    if (count($parts) < 4) {
        return get_base_uid1($uid);
    }
    
    // Собираем первые 4 части обратно
    return implode('.', array_slice($parts, 0, 4));
}

// Получить запрос к БД для получения строк нагрузки с доп. SQL фильтром $dop_sql
// В результате запроса получается join двух таблиц нагрузки
// Данные от запроса должны пропускаться через функцию PrepareNagruzka() для подготовки к выдаче в зелёную таблицу нагрузки
// с уникализацией по base_uid
// $lite_query введён для uoup_nagruzka, где берётся вся нагрузка, что очень тяжело работает
function GetNagruzkaBaseQuery($dop_sql, $nagruzka_type = 'all', $department_from_first_table = true, $lite_query = false)
{
  // if ($chair_uid)
  // {
  //   $chair_sql = "xml_content_of_load.UID_Chair = '$chair_uid' AND";
  // }

  // if ($department_from_first_table)
  // {
  //   $department_sql = ", nagruzka.department_name";
  // }

  if ($nagruzka_type)
  {
    if ($nagruzka_type == 'all')
    {
      $nagruzka_type_sql = "AND (`nagruzka_type` IS NOT NULL AND `nagruzka_type` <> '')";
    }
    elseif ($nagruzka_type == 'empty')
    {
      $nagruzka_type_sql = "AND (`nagruzka_type` IS NULL OR `nagruzka_type` = '')";
    }
    else
    {
      $nagruzka_type_sql = "AND `nagruzka_type` = '$nagruzka_type'";
    }
  }
  else
  {
    EchoLog("GetNagruzkaBaseQuery() не используется без nagruzka_type");
    return "";
  }

  // в противном случае возьмётся из джоина по второй таблице
  // (xml_faculty.Name as department_name,)

  if (!$lite_query)
  {
    $sql_part1 = "
      ,
      nagruzka.status, 
      nagruzka.comment_to_admin,
      xml_content_of_load.YearOfEducation,
      xml_content_of_load.LoadType,
      xml_content_of_load_staff.Abbr, 
      xml_group.Name as group_name,
      xml_discipline.UID as discipline_UID,
      xml_discipline.Name as discipline_name,
      #xml_faculty.Name as department_name,
      xml_speciality.Name as napravlenie,
      xml_speciality.Code as napravlenie_code,
      xml_speciality.education_level,
      xml_specialization.Name as napravlennost,
      xml_language.Name as language,
      xml_content_of_load_staff.UID_FormOfEducation,
      xml_content_of_load.UID_Language,
      xml_content_of_load.UID_Semester,
      xml_content_of_load.StudentAmount,
      xml_kind_of_work.Name as kind_of_work,
      xml_content_of_load.UID_KindOfWork,
      xml_content_of_load.UID_Course,
      xml_post.Name as dolzhnost,
      xml_lecturer.Rate as stavka


    ";

    $sql_part2 = "
      LEFT JOIN xml_content_of_load_staff ON xml_content_of_load.base_uid = xml_content_of_load_staff.base_uid
      LEFT JOIN xml_group ON xml_group.`UID` = xml_content_of_load_staff.`UID_Group`
      LEFT JOIN xml_discipline ON xml_discipline.UID = xml_content_of_load.UID_Discipline
      LEFT JOIN xml_chair ON xml_chair.UID = xml_content_of_load.`UID_Chair`
      LEFT JOIN xml_faculty ON xml_faculty.UID = xml_chair.`UID_Faculty`
      LEFT JOIN xml_speciality ON xml_speciality.UID = xml_content_of_load_staff.UID_Speciality
      LEFT JOIN xml_specialization ON xml_specialization.UID = xml_content_of_load_staff.UID_Specialization
      LEFT JOIN xml_language ON xml_language.UID = xml_content_of_load_staff.UID_Language
      LEFT JOIN xml_kind_of_work ON xml_kind_of_work.UID = xml_content_of_load.UID_KindOfWork
      LEFT JOIN xml_post ON xml_post.UID = xml_lecturer.UID_Post
    ";
  }

  $_nagruzka_base_query = 
  "
  SELECT 
  xml_content_of_load.UID as original_uid,
  xml_content_of_load.base_uid,
  xml_content_of_load.base_uid2,
  xml_content_of_load.UID as xml_content_of_load_UID,
  xml_content_of_load.Amount,
  xml_lecturer.FIO as lecturer_fio,
  xml_lecturer.UID as lecturer_uid,
  # именно здесь можно различить -1 и Вакансию 
  xml_content_of_load.UID_Lecturer,
  xml_lecturer.Tab_number as lecturer_person_id,
  nagruzka.chair_id, nagruzka.chair_name, nagruzka.zavkaf_fio, nagruzka.zavkaf_login, nagruzka.department_name
  $sql_part1
  
  #$department_sql

  FROM xml_content_of_load
  LEFT JOIN xml_lecturer ON xml_lecturer.UID = xml_content_of_load.UID_Lecturer
  LEFT JOIN `nagruzka` ON nagruzka.load_base_UID2 = xml_content_of_load.base_uid2
  --
  $sql_part2
  WHERE 
  1
  $nagruzka_type_sql

  -- AND (xml_content_of_load_staff.`Abbr` LIKE ('Б1.%') OR xml_content_of_load_staff.`Abbr` LIKE ('Ф%') OR xml_content_of_load_staff.`Abbr` LIKE ('1.%') OR xml_content_of_load_staff.`Abbr` LIKE ('1.01%') OR xml_content_of_load_staff.`Abbr` LIKE ('2.1%') OR xml_content_of_load_staff.`Abbr` LIKE ('2.01%') OR xml_content_of_load_staff.`Abbr` LIKE ('С1%') OR xml_content_of_load_staff.`Abbr` LIKE ('С2%') OR xml_content_of_load_staff.`Abbr` LIKE ('С3%') OR xml_content_of_load_staff.`Abbr` LIKE ('С4%'))
  -- AND xml_content_of_load.UID_Lecturer = '26115.281474976793608'
    AND xml_content_of_load.`base_uid` = '26589.281474976763945'
    -- AND xml_content_of_load.`base_uid` = '26589.281474976787074'
    -- AND xml_content_of_load.`base_uid` = '26589.281474976763950'
    -- AND xml_content_of_load.`base_uid` = '26589.281474976773449'
    -- AND LoadType = '0'
    $dop_sql
  ";

  // EchoLog($_nagruzka_base_query);

  return $_nagruzka_base_query;

}

// Данные от запроса GetNagruzkaBaseQuery() должны пропускаться через функцию PrepareNagruzka() для подготовки к выдаче в зелёную таблицу нагрузки
// с уникализацией по base_uid2
function PrepareNagruzka($_Nagruzka)
{
  global $_forms_obuchenia;
  
  $Nagruzka = [];

  if ($_Nagruzka)
  {
    foreach ($_Nagruzka as $nagruzka)
    {
      if (/* $nagruzka['base_uid'] != $nagruzka['original_uid'] && */ $Nagruzka["$nagruzka[base_uid2]"])
      {
        if (!in_array($nagruzka['discipline_name'], $Nagruzka["$nagruzka[base_uid2]"]['discipline_name_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['discipline_name_arr'][] = $nagruzka['discipline_name'];
        }

        if (!in_array($nagruzka['discipline_UID'], $Nagruzka["$nagruzka[base_uid2]"]['discipline_UID_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['discipline_UID_arr'][] = $nagruzka['discipline_UID'];
        }

        if (!in_array($nagruzka['group_name'], $Nagruzka["$nagruzka[base_uid2]"]['group_name_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['group_name_arr'][] = $nagruzka['group_name'];
        }

        if (!in_array($nagruzka['Abbr'], $Nagruzka["$nagruzka[base_uid2]"]['Abbr_arr']))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['Abbr_arr'][] = $nagruzka['Abbr'];
        }

        if (!in_array($nagruzka['napravlenie'], $Nagruzka["$nagruzka[base_uid2]"]['napravlenie_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['napravlenie_arr'][] = $nagruzka['napravlenie'];
        }

        if ($nagruzka['napravlennost'] && !in_array($nagruzka['napravlennost'], $Nagruzka["$nagruzka[base_uid2]"]['napravlennost_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['napravlennost_arr'][] = $nagruzka['napravlennost'];
        }

        if (!in_array($nagruzka['department_name'], $Nagruzka["$nagruzka[base_uid2]"]['department_name_arr'], true))
        {
          $Nagruzka["$nagruzka[base_uid2]"]['department_name_arr'][] = $nagruzka['department_name'];
        }
        
      }
      else
      {
        $Nagruzka["$nagruzka[base_uid2]"] = $nagruzka;

        $Nagruzka["$nagruzka[base_uid2]"]['discipline_name_arr'] = [$nagruzka['discipline_name']];
        $Nagruzka["$nagruzka[base_uid2]"]['discipline_UID_arr'] = [$nagruzka['discipline_UID']];
        
        $Nagruzka["$nagruzka[base_uid2]"]['group_name_arr'] = [$nagruzka['group_name']];
        $Nagruzka["$nagruzka[base_uid2]"]['Abbr_arr'] = [$nagruzka['Abbr']];
        $Nagruzka["$nagruzka[base_uid2]"]['napravlenie_arr'] = [$nagruzka['napravlenie']];

        
        $Nagruzka["$nagruzka[base_uid2]"]['napravlennost_arr'] = [];

        if ($nagruzka['napravlennost']) $Nagruzka["$nagruzka[base_uid2]"]['napravlennost_arr'][] = $nagruzka['napravlennost'];

        $Nagruzka["$nagruzka[base_uid2]"]['department_name_arr'] = [$nagruzka['department_name']];
      }
      
    }

    unset($nagruzka);
    foreach ($Nagruzka as &$nagruzka)
    {
      // $nagruzka['disciplines_UIDs_chain_str'] = ImplodePalki($nagruzka['discipline_UID_arr']);
      // $nagruzka['disciplines_Names_chain_str'] = ImplodePalki($nagruzka['discipline_name_arr']);
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
function GetFullNagruzkaRow($base_uid2)
{
  $nagruzka_query = GetNagruzkaBaseQuery("AND `load_base_UID2` = '$base_uid2'", 'all');
  $_Nagruzka = GetSQL($nagruzka_query);
  return array_pop(PrepareNagruzka($_Nagruzka));
}

// Принимает результат функции GetFullNagruzkaRow()
// TODO: lecturer_fio - необходимо перечислить всех лекторов
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
  $message_text .= "Количество часов: $nagruzka[Amount]<br>";
  $message_text .= "Преподаватель: $nagruzka[lecturer_fio]<br>";

  return $message_text;
}


// Получить параметр из таблицы params
function GetSystemParam($param)
{
  $param_row = GetRow('params', ['param' => $param]);
  return $param_row['value'];
}


// Разбить uid на base, lector_suffix и potok_suffix
// Пример: 26589.281474976787058.26589.281474976787058.1
function parseNagruzkaBaseUid2($uid) 
{
    $result = [
        'base' => '',
        'lector_suffix' => '',
        'potok_suffix' => ''
    ];
    
    $parts = explode('.', $uid);
    $partCount = count($parts);
    
    // Base is always the first two parts
    if ($partCount >= 2) {
        $result['base'] = $parts[0] . '.' . $parts[1];
    }

    if ($partCount == 3)
    {
      // The last part is the potok suffix if it's a single number
      $lastPart = end($parts);
      if (is_numeric($lastPart) && strpos($lastPart, '.') === false) {
          $result['potok_suffix'] = $lastPart;
      }
    }
    
    // If we have 4 or more parts, we have a lecturer suffix
    if ($partCount >= 4) {
        $result['lector_suffix'] = $parts[2] . '.' . $parts[3];
    }

    if ($partCount == 5)
    {
      // The last part is the potok suffix if it's a single number
      $lastPart = end($parts);
      if (is_numeric($lastPart) && strpos($lastPart, '.') === false) {
          $result['potok_suffix'] = $lastPart;
      }
    }
    return $result;
}

// склеить части base_uid2 объекта
// вернуть строку - base_uid2
function glueNagruzkaBaseUid2Parts($base_uid2_obj)
{
  $result = $base_uid2_obj['base'];

  if ($base_uid2_obj['lector_suffix']) $result .= ".$base_uid2_obj[lector_suffix]";
  if ($base_uid2_obj['potok_suffix']) $result .= ".$base_uid2_obj[potok_suffix]";


  return $result;
}


function GetLecturer($person_id, $post_uid, $chair_uid, $department_uid, $person_type)
{
  // У некоторых ГПХ-шников указана кафедра, сначала поищем с кафедрой
  $lecturer_rows = GetRows('xml_lecturer', ['Tab_number' => $person_id, 'UID_Post' => $post_uid, 'UID_Chair' => $chair_uid], null, "`Archive` ASC, `DateContractEnd` DESC");

  if ($lecturer_rows)
  {
    $lecturer = $lecturer_rows[0];
  }
  elseif ($person_type == 'gph')
  {
    // у тех, кто без кафедры, в UID_Chair прописан факультет
    $lecturer_rows = GetRows('xml_lecturer', ['Tab_number' => $person_id, 'UID_Post' => $post_uid, 'UID_Chair' => $department_uid], null, "`Archive` ASC, `DateContractEnd` DESC");

    if ($lecturer_rows)
    {
      $lecturer = $lecturer_rows[0];
    }
  }

  return $lecturer;
}


/**
 * Проверяет, является ли аббревиатура дисциплиной по заданным шаблонам
 * Соответствует SQL: LIKE ('Б1.%') OR LIKE ('Ф%') OR LIKE ('1.%') OR LIKE ('1.01%') 
 * OR LIKE ('2.1%') OR LIKE ('2.01%') OR LIKE ('С1%') OR LIKE ('С2%') 
 * OR LIKE ('С3%') OR LIKE ('С4%')
 * 
 * @param string $abbr Аббревиатура для проверки
 * @return bool true если дисциплина, false если нет
 */
function IsNagruzkaDiscipline($abbr) {
    // Все шаблоны из SQL запроса
    $patterns = [
        '/^Б1\./u',      // Б1.%
        '/^Ф/u',         // Ф%
        '/^1\./u',       // 1.%
        '/^1\.01/u',     // 1.01%
        '/^2\.1/u',      // 2.1%
        '/^2\.01/u',     // 2.01%
        '/^С1/u',        // С1%
        '/^С2/u',        // С2%
        '/^С3/u',        // С3%
        '/^С4/u',        // С4%
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $abbr)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Проверяет, соответствует ли аббревиатура одному из шаблонов:
 * - "Б2.*" - начинается с "Б2."
 * - "2.2.1(П)" - точное совпадение
 * - "2.2.01(П)" - точное совпадение
 * - "С5*" - начинается с "С5"
 * 
 * @param string $abbr Аббревиатура для проверки
 * @return bool true если соответствует, false если нет
 */
function IsNagruzkaRukPractice($abbr) {
    // Шаблоны для проверки
    $patterns = [
        '/^Б2\./u',           // Б2.* - начинается с "Б2."
        '/^2\.2\.1\(П\)$/u',  // 2.2.1(П) - точное совпадение
        '/^2\.2\.01\(П\)$/u', // 2.2.01(П) - точное совпадение
        '/^С5/u',              // С5* - начинается с "С5"
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $abbr)) {
            return true;
        }
    }
    
    return false;
}



function loadXMLSafe($filename, $logErrors = true) 
{
  // 1. Проверяем существование файла
  if (!file_exists($filename)) {
      if ($logErrors) {
          EchoLog("Файл не найден: $filename", 'file mail');
          EchoLog("Текущая директория: " . getcwd(), 'file mail');
          EchoLog("Полный путь: " . realpath($filename) ?: "не удалось определить", 'file mail');
      }
      return false;
  }
  
  // 2. Проверяем права на чтение
  if (!is_readable($filename)) {
      if ($logErrors) {
          EchoLog("Нет прав на чтение файла: $filename", 'file mail');
          EchoLog("Права доступа: " . substr(sprintf('%o', fileperms($filename)), -4), 'file mail');
      }
      return false;
  }
  
  // 3. Читаем содержимое
  $content = file_get_contents($filename);
  if ($content === false) {
      if ($logErrors) {
          EchoLog("Не удалось прочитать файл: $filename", 'file mail');
          EchoLog("Последняя ошибка PHP: " . (error_get_last()['message'] ? error_get_last()['message'] : 'неизвестно'), 'file mail');
      }
      return false;
  }
  
  // 4. Проверяем, что файл не пустой
  if (empty($content)) {
      if ($logErrors) {
          EchoLog("Файл пуст: $filename", 'file mail');
          EchoLog("Размер файла: " . filesize($filename) . " байт", 'file mail');
      }
      return false;
  }
  
  // 5. Включаем внутреннюю обработку ошибок libxml
  libxml_use_internal_errors(true);
  
  // 6. Загружаем XML
  $XML = simplexml_load_string($content);
  
  // 7. Проверяем результат и собираем ошибки
  if ($XML === false) {
      if ($logErrors) {
          $errors = libxml_get_errors();
          $error_messages = [];
          
          foreach ($errors as $error) {
              $error_messages[] = sprintf(
                  "[%s] %s (строка %d, колонка %d)",
                  $error->level == LIBXML_ERR_WARNING ? "WARNING" : 
                  ($error->level == LIBXML_ERR_ERROR ? "ERROR" : "FATAL"),
                  trim($error->message),
                  $error->line,
                  $error->column
              );
          }
          
          EchoLog("XML ошибки в $filename:", 'file mail');
          foreach ($error_messages as $msg) {
              EchoLog("  " . $msg, 'file mail');
          }
          
          // Показываем проблемный фрагмент
          if (!empty($errors)) {
              $first_error = $errors[0];
              if ($first_error->line > 0) {
                  $lines = explode("\n", $content);
                  $start = max(0, $first_error->line - 3);
                  $end = min(count($lines), $first_error->line + 2);
                  
                  EchoLog("Контекст ошибки:", 'file mail');
                  for ($i = $start; $i < $end; $i++) {
                      $prefix = ($i + 1 == $first_error->line) ? ">>> " : "    ";
                      EchoLog($prefix . ($i + 1) . ": " . rtrim($lines[$i]), 'file mail');
                  }
              }
          }
          
          // Проверка кодировки
          $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
          if ($encoding !== 'UTF-8') {
              EchoLog("Файл имеет кодировку: $encoding (ожидается UTF-8)", 'file mail');
          }
      }
      
      libxml_clear_errors();
      libxml_use_internal_errors(false);
      return false;
  }
  
  // 8. Очищаем ошибки
  libxml_clear_errors();
  libxml_use_internal_errors(false);
  
  return $XML;
}


function safeAdd(&$target, $value) {
    // Приводим к float, но обрабатываем некорректные значения как 0
    $numericValue = is_numeric($value) ? (float) $value : 0;
    $target = ((float) $target) + $numericValue;
}

?>