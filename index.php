<?

include 'functions.php';

session_name('lkzk');
session_start();

$c_access = $_SESSION['c_access'];
$c_login = $_SESSION['c_login'];
$u = $_GET['u'];


// Авторизация
if ($_POST['action'] == 'log-in')
{

  $result = Authorize($_POST['login'], $_POST['password']);

  if ($result !== true)  
  {
    $_SESSION['error_message'] = $result; // "Вы ввели неправильный логин или пароль";
  }

  // if ($_SESSION['c_access'] == 'cfo' || $_SESSION['c_access'] == 'author')
  // {
  //   header('Location: ./');
  // }
  // else
  {
    header('Location: ./'); 
  }

  exit();
}




if (isset($_GET['logout']))
{
  LogOut();
  header('Location: ./');
  exit();
}

if (isset($_GET['index']))
{
  header('Location: ./');
  exit();
}

if ($u && IsGoodInclude($u))
{
  $u = TrimTrailingSlash($u);
  $u = strip_tags($u);
    
  $path_array = explode('/', $u);
  $main = $path_array[0];
  $sub = $path_array[1];
}


// обработка возможных переходов
if ($main == 'uoup_chairs_refused')
{
  header("Location: /#/uoup_chairs_refused");
}
elseif ($main == 'uoup_nagruzka_to_change')
{
  header("Location: /#/uoup_nagruzka_to_change");
}
else
// если после .htaccess пришли сюда, то это 404
if ($u)
{
  header("HTTP/1.0 404 Not Found");
  include '404.php';
  exit;
}

// if ($main == 'zayavki')
// {
//   // echo $sub;
//   if (!$sub || !intval($sub)) $sub = date('Y');
//   header("Location: /#/zayavki/$sub");
//   exit;
// }

if ($_SESSION['error_message'])
{
  $error_message = $_SESSION['error_message'];
  $_SESSION['error_message'] = '';
}

if ($_SESSION['success_message'])
{
  $success_message = $_SESSION['success_message'];
  $_SESSION['success_message'] = '';
}



?>

<!doctype html>
<html>
<head>
  <title><?=$_from_title?></title>
  <meta charset="utf-8">
  <link rel="stylesheet" href="js/smoothness/jquery-ui-1.10.4.custom.min.css">
  <link rel="stylesheet" href="js/total.css?<?=rand()?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  
</head>
<body ng-app="app" style='padding: 0px;' ng-controller="AppCtrl" id="top_anchor">

<?

// авторизованы
if ($_SESSION['c_login'])
{


?>

<!-- МЕНЮ -->
<div class="navbar navbar-expand-sm  navbar-dark bg-primary" ng-cloak>
  <div class="container" style="width: 100%; max-width: 100%">
  <div class="navbar-collapse collapse">
    <ul class="navbar-nav">

<!--       <li class="nav-item" ng-if="c_roles.full">
        <a href="#/uoup" class="nav-link" ng-class="{active: page == 'uoup'}"><span class="glyphicon glyphicon-user"></span> Администраторы УОУП</a>
      </li> -->

      <li class="nav-item" ng-if="c_roles.zavkaf || c_roles.sotrudnik">
        <a href="#/nagruzka" class="nav-link" ng-class="{active: page == 'nagruzka'}" ><span class="glyphicon glyphicon-list"></span> Нагрузка</a>
      </li>

      <li class="nav-item" ng-if="c_roles.uoup">
        <a href="#/uoup_nagruzka" class="nav-link" ng-class="{active: page == 'uoup_nagruzka' || page == 'nagruzka'}" ><span class="glyphicon glyphicon-list"></span> Нагрузка</a>
      </li>

      <li class="nav-item" ng-if="c_roles.uoup">
        <a href="#/uoup_chairs_refused" class="nav-link" ng-class="{active: page == 'uoup_chairs_refused'}" ><span class="glyphicon glyphicon-list"></span> Отказ кафедр</a>
      </li>

      <li class="nav-item" ng-if="c_roles.uoup">
        <a href="#/uoup_nagruzka_to_change" class="nav-link" ng-class="{active: page == 'uoup_nagruzka_to_change'}" ><span class="glyphicon glyphicon-list"></span> Нагрузка на изменение</a>
      </li>

      <li class="nav-item" ng-if="c_roles.uoup">
        <a href="#/uoup_nagruzka_no_chair" class="nav-link" ng-class="{active: page == 'uoup_nagruzka_no_chair'}" ><span class="glyphicon glyphicon-list"></span> Нагрузка без кафедры</a>
      </li>

      <li class="nav-item" ng-if="c_roles.uoup">
        <a href="#/uoup_nagruzka_no_type" class="nav-link" ng-class="{active: page == 'uoup_nagruzka_no_type'}" ><span class="glyphicon glyphicon-list"></span> Нагрузка без типа</a>
      </li>

      <li class="nav-item" ng-if="c_roles.zavkaf">
        <a href="#/sotrudniki" class="nav-link" ng-class="{active: page == 'sotrudniki'}" ><span class="glyphicon glyphicon-user"></span> Сотрудники</a>
      </li>

<!--       <li class="nav-item" ng-if="c_roles.zavkaf">
        <a href="#/nagruzka_columns" class="nav-link" ng-class="{active: page == 'nagruzka_columns'}" ><span class="glyphicon glyphicon-th-list"></span> Порядок столбцов</a>
      </li> -->

      <? if ($_SESSION['c_access'] == 'full' || $_SESSION['c_access'] == 'lite'): ?>

      <li class="nav-item">
        <a href="#/podrazdelenia" class="nav-link" ng-class="{active: page ==  'podrazdelenia'}"><span class="glyphicon glyphicon-menu-hamburger"></span> Подразделения</a>
      </li>

      <li class="nav-item">
        <a href="#/sotrudniki" class="nav-link" ng-class="{active: page ==  'sotrudniki'}"><span class="glyphicon glyphicon-user"></span> Сотрудники</a>
      </li>


      <? endif ?>

<!--       <li class="nav-item">
        <a href="#/ksro" class="nav-link" ng-class="{active: page ==  'ksro'}"><span class="glyphicon glyphicon-list"></span> Индивидуальные консультации и КСРО</a>
      </li>


       -->
      <li class="nav-item" ng-if="c_roles.uoup">
        <a href="#/system_mode" class="nav-link" ng-class="{active: page == 'system_mode'}" ><span class="glyphicon glyphicon-cog"></span> Режим работы</a>
      </li>


    </ul>

    <ul class="ms-md-auto navbar-nav">
      <? if (true): ?>
      <li class="nav-item">
        <a style='font-size: 80%;' class="nav-link">
          <?=$_SESSION['c_fio']?>
          <div ng-if="!c_roles.zavkaf"><?=$_SESSION['c_roles_str']?></div>
          <br><?=$_SESSION['c_chair_name']?>
        </a>

      </li>
      <? endif ?>

<!--       <li>
        <a href="/" class="" target="_blank">На сайт <span class="glyphicon glyphicon-arrow-up"></span> </a>
      </li> -->
      <li class="nav-item">
        <a href="?logout" class="nav-link active">Выйти <span class="glyphicon glyphicon-log-out"></span> </a>
      </li>
    </ul>

  </div>

  </div>

</div>

<div class="clearfix"></div>


<div ng-view style="padding: 15px; margin-top: 10px">
</div>

<div ng-if="!page" style='position:fixed; left: 47%; top: 47%;'>
  <img src="/images/hourglass.png" />
</div>



<script src="js/total.js?<?=rand()?>"></script>
<script src="app.js?<?=rand()?>"></script>

<script>
  $(function()
  {
   $("a[href='#']").click(function(e) {
      e.preventDefault();
    });

  });
</script>

<? }
// не авторизованы, форма авторизации
else { ?>

<form method='post' style='padding:0; padding-bottom: 5px; margin-top:50px' class="form-horizontal">
  <input type='hidden' name='action' value='log-in'>
  
  <fieldset style='width: 180px; margin: 0 auto'>

  <h3 style='text-align: center;'>Авторизация</h3>

  <div class="form-group">
    <label class="col-lg-4 control-label">Логин:</label>
    <div class="col-lg-6"> <input type='text' name='login' class="form-control" id="login"> </div>
  </div>

  <div class="form-group">
    <label class="col-lg-4 control-label">Пароль:</label>
    <div class="col-lg-6"> <input type='password' name='password' class="form-control"> </div>
  </div>

  
  <div class="form-group">
    <div class="col-lg-3 col-lg-offset-4 ">
    <button type='submit' value='Войти' name="submit" class="btn btn-primary">Войти</button>
    </div>
  </div>
    
   <br/>
  <?Error($error_message)?>
  <?Success($success_message)?>

  </fieldset>

  </form>

<script src="js/jquery.js"></script>

<script>
  $(function()
  {
    $('input#login').focus();
  });
</script>

<? } ?>
</body>
</html>