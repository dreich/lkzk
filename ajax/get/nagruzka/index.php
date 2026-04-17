<?

/**
 * Точка входа для получения данных нагрузки
 * Роутинг по режимам работы системы
 */

session_name('lkzk');
session_start();

if (empty($_SESSION['c_roles'])) {
    echo json_encode(['error' => 'expired']);
    exit;
}

// Проверяем, что запрос пришел через AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

include '../../../functions.php';
include 'BaseNagruzkaProvider.php';

// Фабрика режимов
class NagruzkaModeFactory
{
    private static $modes = [
        'mode_closed' => 'ClosedMode',
        'mode_filling' => 'FillingMode',
        'mode_exporting' => 'ExportingMode',
        'mode_verification' => 'VerificationMode',
        'mode_archive' => 'ArchiveMode'
    ];

    private static $modeFiles = [
        'ClosedMode' => 'modes/ClosedMode.php',
        'FillingMode' => 'modes/FillingMode.php',
        'ExportingMode' => 'modes/ExportingMode.php',
        'VerificationMode' => 'modes/VerificationMode.php',
        'ArchiveMode' => 'modes/ArchiveMode.php'
    ];

    public static function create($mode, $session, $getParams)
    {
        $className = isset(self::$modes[$mode]) ? self::$modes[$mode] : 'ClosedMode';
        $filePath = self::$modeFiles[$className];

        if (!file_exists(__DIR__ . '/' . $filePath)) {
            throw new Exception("Mode file not found: $filePath");
        }

        include_once __DIR__ . '/' . $filePath;

        if (!class_exists($className)) {
            throw new Exception("Mode class not found: $className");
        }

        return new $className($session, $getParams);
    }
}

try {
    // Определяем режим работы системы
    $modeRow = GetRow('params', ['param' => 'system_mode']);
    $systemMode = isset($modeRow['value']) ? $modeRow['value'] : 'mode_closed';

    // Специальная проверка для режима выгрузки
    // Если система в режиме выгрузки, но запрошен check_export_status - возвращаем статус
    if (!empty($_GET['check_export_status']) && $systemMode === 'mode_exporting') {
        $provider = NagruzkaModeFactory::create($systemMode, $_SESSION, $_GET);
        $status = $provider->checkExportStatus();
        echo json_encode($status);
        exit;
    }

    // Создаем провайдер для текущего режима
    $provider = NagruzkaModeFactory::create($systemMode, $_SESSION, $_GET);

    // Проверка авторизации
    $authError = $provider->checkAuth();
    if ($authError) {
        echo json_encode($authError);
        exit;
    }

    // Проверка доступа к просмотру
    if (!$provider->canView()) {
        echo json_encode([
            'error' => 'access_denied',
            'mode' => $systemMode,
            'message' => 'Нет доступа к просмотру данных в текущем режиме'
        ]);
        exit;
    }

    // Получаем данные
    $result = $provider->getData();

    // Добавляем мета-информацию
    $result['system_mode'] = $systemMode;
    $result['can_edit'] = $provider->canEdit();
    $result['user_role'] = $provider->userRole;

    // Заголовки для кэширования
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'internal_error',
        'message' => $e->getMessage()
    ]);
}
