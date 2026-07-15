<?

// В режиме "Выверка" заполнить таблицы КСРО-ИК и Аспирантуры нагрузкой из ГУВ
// В режиме "Заполение" запускать нельзя: будут уничтожены вручную заполненные данные

include '../functions.php';

$_system_mode = GetSystemMode();

if ($_system_mode == 'mode_verification')
{
  include 'do_ksro.php';
  include 'do_aspirantura_kand_exam.php';
  include 'do_aspirantura_ruk_asp.php';
  include 'do_aspirantura_ruk_soisk.php';
}




?>
