<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Выгрузка данных в Галактику" (переходный)
 * Специальный режим при переходе из Заполнения в Выверку
 * Нельзя выбрать в селекте - активируется автоматически
 * Показывает сообщение о выгрузке данных
 */
class ExportingMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        // Все роли могут "видеть" (но видят только сообщение)
        if ($this->userRole === 'zavkaf') {
            return !empty($this->session['c_chair_id']);
        }

        if ($this->userRole === 'uoup') {
            return true;
        }

        if ($this->userRole === 'sotrudnik') {
            return !empty($this->lecturerUid);
        }

        return false;
    }

    public function canEdit()
    {
        // В режиме выгрузки редактирование запрещено
        return false;
    }

    public function getNagruzkaTypeFilter()
    {
        return 'all';
    }

    public function getData()
    {
        // В этом режиме данные недоступны - идёт выгрузка в Галактику
        return [
            'nagruzka' => [],
            'stat' => new stdClass(),
            'lecturer_fio' => null,
            'message' => 'Выгрузка данных в Галактику. Информация будет доступна завтра.',
            'exporting_mode' => true,
            'system_mode_readonly' => true
        ];
    }

    /**
     * Получить URL для проверки статуса выгрузки
     * Внешняя система может вызывать этот URL для проверки
     */
    public function getExportStatusUrl()
    {
        return '/ajax/get/nagruzka/?check_export_status=1';
    }

    /**
     * Проверить статус выгрузки
     * Вызывается внешним сервисом
     */
    public function checkExportStatus()
    {
        // Проверяем, завершилась ли выгрузка данных в Галактику
        // Этот метод может быть вызван внешним скриптом синхронизации

        $exportStatus = $this->getRow('params', ['param' => 'export_status']);

        return [
            'mode' => 'mode_exporting',
            'export_status' => isset($exportStatus['value']) ? $exportStatus['value'] : 'in_progress',
            'timestamp' => isset($exportStatus['updated_at']) ? $exportStatus['updated_at'] : null
        ];
    }
}
