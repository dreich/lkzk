<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Выверка"
 * Завкаф видит всё в read-only, но может отправлять запросы в УОУП
 * УОУП может редактировать привязки ППС
 * Сплиты очищаются при синхронизации, берём данные из Галактики
 */
class VerificationMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        // Все роли могут просматривать
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
        // Редактировать привязки ППС может только УОУП
        return $this->userRole === 'uoup';
    }

    public function getNagruzkaTypeFilter()
    {
        return $this->nagruzkaType ?: 'all';
    }

    public function getData()
    {
        // Для сотрудника без lecturer_uid - пустой результат
        if ($this->userRole === 'sotrudnik' && empty($this->lecturerUid)) {
            return [
                'nagruzka' => [],
                'stat' => new stdClass(),
                'lecturer_fio' => null
            ];
        }

        $chairFilter = $this->getChairFilter();

        $dopSql = "$chairFilter
            AND `chair_id` IS NOT NULL AND `valid` = '1'
        ";

        $nagruzkaData = $this->getBaseData($dopSql, 'all');

        // В режиме выверки:
        // - Если есть сплит - приоритет на него (для правок УОУП)
        // - Если нет сплита - берём из Галактики
        $nagruzkaData = $this->processSplitsWithPriority($nagruzkaData);

        // Переиндексируем lectors
        foreach ($nagruzkaData as $baseUid => &$item) {
            if (!empty($item['lectors'])) {
                $item['lectors'] = array_values($item['lectors']);
            } else {
                $item['lectors'] = [];
            }
        }
        unset($item);

        // Расчёт статистики
        $stats = $this->calculateStats($nagruzkaData);
        $nagruzkaData = $stats['data'];
        $stat = $stats['stat'];
        $statByChair = $stats['statByChair'];

        // Фильтрация по преподавателю
        $nagruzkaData = $this->filterByLecturer($nagruzkaData);

        // Глобальная фильтрация
        $nagruzkaData = $this->applyGlobalFilter($nagruzkaData);

        // Для УОУП в режиме lite/only_stat - группировка по кафедрам
        if ($this->userRole === 'uoup' && ($this->onlyStat || $this->isLite)) {
            $nagruzkaData = $this->groupByChair($nagruzkaData, $statByChair);
        }

        if ($this->onlyStat) {
            $nagruzkaData = [];
        }

        return [
            'nagruzka' => array_values($nagruzkaData),
            'stat' => $stat ?: new stdClass(),
            'lecturer_fio' => $this->lecturerFio,
            'can_edit_bindings' => $this->userRole === 'uoup',
            'can_send_requests' => $this->userRole === 'zavkaf'
        ];
    }
}
