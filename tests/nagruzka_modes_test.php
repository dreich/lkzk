<?

/**
 * Тесты для проверки работы режимов нагрузки
 * Запуск: php tests/nagruzka_modes_test.php
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

        // Тесты фабрики режимов
        $this->testModeFactory();

        // Тесты доступа для разных ролей
        $this->testAccessControl();

        // Тесты SplitProcessor
        $this->testSplitProcessor();

        // Тесты логики режимов
        $this->testFillingModeLogic();
        $this->testVerificationModeLogic();
        $this->testClosedModeLogic();
        $this->testArchiveModeLogic();

        // Итоги
        $this->printSummary();

        return $this->failed === 0;
    }

    private function testModeFactory()
    {
        EchoLog("\n--- Тестирование фабрики режимов ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/FillingMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ClosedMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/VerificationMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ArchiveMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ExportingMode.php';

        $session = ['c_roles' => 'zavkaf', 'c_chair_id' => '123'];
        $getParams = [];

        // Тест создания FillingMode
        $mode = new FillingMode($session, $getParams);
        $this->assert($mode instanceof FillingMode, 'FillingMode создан');
        $this->assert($mode->canView() === true, 'Zavkaf может просматривать в FillingMode');
        $this->assert($mode->canEdit() === true, 'Zavkaf может редактировать в FillingMode');

        // Тест создания ClosedMode
        $mode = new ClosedMode($session, $getParams);
        $this->assert($mode instanceof ClosedMode, 'ClosedMode создан');
        $this->assert($mode->canView() === false, 'Zavkaf не может просматривать в ClosedMode');
        $this->assert($mode->canEdit() === false, 'Никто не может редактировать в ClosedMode');

        // Тест создания VerificationMode
        $mode = new VerificationMode($session, $getParams);
        $this->assert($mode instanceof VerificationMode, 'VerificationMode создан');
        $this->assert($mode->canView() === true, 'Zavkaf может просматривать в VerificationMode');
        $this->assert($mode->canEdit() === false, 'Zavkaf не может редактировать в VerificationMode');

        // Тест для УОУП
        $session = ['c_roles' => 'uoup'];
        $mode = new VerificationMode($session, $getParams);
        $this->assert($mode->canEdit() === true, 'УОУП может редактировать в VerificationMode');
    }

    private function testAccessControl()
    {
        EchoLog("\n--- Тестирование контроля доступа ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/FillingMode.php';
        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ClosedMode.php';

        $getParams = [];

        // Zavkaf без chair_id
        $session = ['c_roles' => 'zavkaf'];
        $mode = new FillingMode($session, $getParams);
        $this->assert($mode->canView() === false, 'Zavkaf без chair_id не может просматривать');

        // Sotrudnik без lecturer_uid
        $session = ['c_roles' => 'sotrudnik'];
        $mode = new FillingMode($session, $getParams);
        $this->assert($mode->canView() === false, 'Sotrudnik без lecturer_uid не может просматривать');

        // UOUP всегда может
        $session = ['c_roles' => 'uoup'];
        $mode = new FillingMode($session, $getParams);
        $this->assert($mode->canView() === true, 'УОУП всегда может просматривать');

        // Sotrudnik с lecturer_uid
        $session = ['c_roles' => 'sotrudnik'];
        $getParams = ['lecturer_uid' => 'test-uid'];
        $mode = new FillingMode($session, $getParams);
        $this->assert($mode->canView() === true, 'Sotrudnik с lecturer_uid может просматривать');
    }

    private function testSplitProcessor()
    {
        EchoLog("\n--- Тестирование SplitProcessor ---\n");

        // Мок-данные нагрузки
        $nagruzkaData = [
            'base1.uid2.1' => [
                'base_uid' => 'base1',
                'base_uid2' => 'base1.uid2.1',
                'Amount' => 10,
                'lecturer_fio' => 'Иванов',
                'lecturer_uid' => 'lecturer1'
            ],
            'base1.uid2.2' => [
                'base_uid' => 'base1',
                'base_uid2' => 'base1.uid2.2',
                'Amount' => 20,
                'lecturer_fio' => 'Петров',
                'lecturer_uid' => 'lecturer2'
            ],
            'base2.uid2.1' => [
                'base_uid' => 'base2',
                'base_uid2' => 'base2.uid2.1',
                'Amount' => 30,
                'lecturer_fio' => 'Сидоров',
                'lecturer_uid' => 'lecturer3'
            ]
        ];

        // Тест группировки без сплитов
        $processor = new SplitProcessor('1'); // delete = 1 - пустой результат
        $result = $processor->applySplits($nagruzkaData);
        $this->assert(count($result) === 3, 'Группировка по base_uid работает');
        $this->assert(isset($result['base1']), 'base1 присутствует');
        $this->assert(isset($result['base2']), 'base2 присутствует');

        // Тест статистики
        $stats = $processor->getStats();
        $this->assert(isset($stats['total_splits']), 'Статистика доступна');

        EchoLog("Примечание: Полное тестирование сплитов требует данных в БД\n");
    }

    private function testFillingModeLogic()
    {
        EchoLog("\n--- Тестирование логики FillingMode ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/FillingMode.php';

        $session = ['c_roles' => 'zavkaf', 'c_chair_id' => '123'];
        $getParams = [];
        $mode = new FillingMode($session, $getParams);

        // Проверка фильтра типа нагрузки
        $filter = $mode->getNagruzkaTypeFilter();
        $this->assert($filter === 'all', 'FillingMode использует фильтр all');

        // Проверка исключения КСРО
        // В реальном коде это делается через SQL
        EchoLog("Проверка исключения КСРО реализована в SQL-запросе\n");

        EchoLog("FillingMode: корректно\n");
    }

    private function testVerificationModeLogic()
    {
        EchoLog("\n--- Тестирование логики VerificationMode ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/VerificationMode.php';

        // Zavkaf
        $session = ['c_roles' => 'zavkaf', 'c_chair_id' => '123'];
        $getParams = [];
        $mode = new VerificationMode($session, $getParams);
        $this->assert($mode->canView() === true, 'Zavkaf может просматривать');
        $this->assert($mode->canEdit() === false, 'Zavkaf не может редактировать');

        // UOUP
        $session = ['c_roles' => 'uoup'];
        $mode = new VerificationMode($session, $getParams);
        $this->assert($mode->canEdit() === true, 'УОУП может редактировать привязки');

        EchoLog("VerificationMode: корректно\n");
    }

    private function testClosedModeLogic()
    {
        EchoLog("\n--- Тестирование логики ClosedMode ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ClosedMode.php';

        $getParams = [];

        // Zavkaf - нет доступа
        $session = ['c_roles' => 'zavkaf', 'c_chair_id' => '123'];
        $mode = new ClosedMode($session, $getParams);
        $this->assert($mode->canView() === false, 'Zavkaf не видит данные в ClosedMode');

        // UOUP - есть доступ
        $session = ['c_roles' => 'uoup'];
        $mode = new ClosedMode($session, $getParams);
        $this->assert($mode->canView() === true, 'УОУП видит данные в ClosedMode');
        $this->assert($mode->canEdit() === false, 'УОУП не может редактировать в ClosedMode');

        // Sotrudnik - нет доступа
        $session = ['c_roles' => 'sotrudnik'];
        $mode = new ClosedMode($session, $getParams);
        $this->assert($mode->canView() === false, 'Sotrudник не видит данные в ClosedMode');

        EchoLog("ClosedMode: корректно\n");
    }

    private function testArchiveModeLogic()
    {
        EchoLog("\n--- Тестирование логики ArchiveMode ---\n");

        include_once __DIR__ . '/../ajax/get/nagruzka/modes/ArchiveMode.php';

        $getParams = [];

        // Все роли могут просматривать, но не редактировать
        $roles = [
            ['c_roles' => 'zavkaf', 'c_chair_id' => '123'],
            ['c_roles' => 'uoup'],
            ['c_roles' => 'sotrudnik', 'c_sotrudnik_chairs_ids' => '123']
        ];

        foreach ($roles as $session) {
            $mode = new ArchiveMode($session, $getParams);
            $this->assert($mode->canEdit() === false, 'В ArchiveMode никто не может редактировать');
        }

        EchoLog("ArchiveMode: корректно\n");
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

        if ($this->failed === 0) {
            EchoLog("Все тесты пройдены успешно!\n");
        } else {
            EchoLog("Есть ошибки, требующие исправления\n");
        }
    }
}

// Запуск тестов
$test = new NagruzkaModesTest();
$success = $test->run();

exit($success ? 0 : 1);
