<?


// Фабрика режимов
class NagruzkaModeFactory
{
    private static $modes = [
        // 'mode_closed' => 'ClosedMode',
        'mode_filling' => 'FillingMode',
        'mode_exporting' => 'ExportingMode',
        'mode_verification' => 'VerificationMode',
        'mode_archive' => 'ArchiveMode'
    ];

    private static $modeFiles = [
        // 'ClosedMode' => 'modes/ClosedMode.php',
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



?>