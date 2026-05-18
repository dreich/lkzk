<?php

// Получение текущего режима работы системы извне

require_once '../functions.php';
require_once '../data.php';


$CurrentModeRow = GetRow('params', ['param' => 'system_mode']);

echo $CurrentModeRow['value'];

?>