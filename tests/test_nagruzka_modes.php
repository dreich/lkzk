<?

/**
 * Тесты для проверки работы режимов нагрузки
 * Запуск: php tests/test_nagruzka_modes.php
 */

include_once __DIR__ . '/../functions.php';
include_once __DIR__ . '/../ajax/get/nagruzka/BaseNagruzkaProvider.php';
include_once __DIR__ . '/../ajax/get/nagruzka/helpers/SplitProcessor.php';

class NagruzkaModesTest
{
    private $testResults = [];
    private $passed = 0;
    private $failed = 0;

    public function run()
    {
        EchoLog("=== Тестирование режимов нагрузки ===\n");

        $this->testModeFactory();
        $this->testAccessControl();
        $this->testSplitProcessor();
        $this->testModeLogic();

        $this->printSummary();

        return $this->failed === 0;
    }

    private function testModeFactory()
    {
        EchoLog("\n--- Тестирование фабрики режимов ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/FillingMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ClosedMode.php';

        $session = ['c_roles' => 'zavkaf', 'c_chair_id' => '123'];
        $getParams = [];

        $mode = new FillingMode($session, $getParams);
        $this->assert($mode instanceof FillingMode, 'FillingMode создан');
        $this->assert($mode->canView() === true, 'Zavkaf может просматривать в FillingMode');
    }

    private function testAccessControl()
    {
        EchoLog("\n--- Тестирование контроля доступа ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/FillingMode.php';

        // Zavkaf без chair_id
        $session = ['c_roles' => 'zavkaf'];
        $getParams = [];
        $mode = new FillingMode($session, $getParams);
        $this->assert($mode->canView() === false, 'Zavkaf без chair_id не может просматривать');
    }

    private function testSplitProcessor()
    {
        EchoLog("\n--- Тестирование SplitProcessor ---\n");

        $nagruzkaData = [
            'base1.uid2.1' => [
                'base_uid' => 'base1',
                'base_uid2' => 'base1.uid2.1',
                'Amount' => 10,
                'lecturer_fio' => 'Иванов',
                'lecturer_uid' => 'lecturer1'
            ]
        ];

        $processor = new SplitProcessor('1');
        $result = $processor->applySplits($nagruzkaData);
        $this->assert(count($result) === 1, 'Группировка по base_uid работает');
    }

    private function testModeLogic()
    {
        EchoLog("\n--- Тестирование логики режимов ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ClosedMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/VerificationMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ArchiveMode.php';

        $getParams = [];

        // ClosedMode - Zavkaf не видит
        $session = ['c_roles' => 'zavkaf', 'c_chair_id' => '123'];
        $mode = new ClosedMode($session, $getParams);
        $this->assert($mode->canView() === false, 'Zavkaf не видит в ClosedMode');

        // VerificationMode - UOUP может редактировать
        $session = ['c_roles' => 'uoup'];
        $mode = new VerificationMode($session, $getParams);
        $this->assert($mode->canEdit() === true, 'УОУП может редактировать в VerificationMode');

        // ArchiveMode - никто не может редактировать
        $session = ['c_roles' => 'uoup'];
        $mode = new ArchiveMode($session, $getParams);
        $this->assert($mode->canEdit() === false, 'В ArchiveMode никто не может редактировать');
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            $this->passed++;
            EchoLog("✓ PASS: $message\n");
        } else {
            $this->failed++;
            EchoLog("✗ FAIL: $message\n");
        }
    }

    private function printSummary()
    {
        EchoLog("\n=== Итоги тестирования ===\n");
        EchoLog("Пройдено: {$this->passed}\n");
        EchoLog("Ошибок: {$this->failed}\n");
    }
}

// Запуск тестов
$test = new NagruzkaModesTest();
$test->run();
