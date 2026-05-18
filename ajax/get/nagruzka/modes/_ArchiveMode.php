<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Архив"
 * Все данные в режиме read-only для всех ролей
 * Исторические данные для просмотра
 */
class ArchiveMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        // Все роли могут просматривать архив
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
        // В архиве редактирование запрещено для всех
        return false;
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

        // В архиве показываем данные как есть из Галактики
        // Сплиты не применяются (архив - это состояние после выверки)
        $result = [];
        foreach ($nagruzkaData as $baseUid2 => $item) {
            $baseUid = $item['base_uid'];

            if (!isset($result[$baseUid])) {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
                $result[$baseUid]['Amount'] = 0;
            } else {
                $result[$baseUid]['Amount'] += $item['Amount'];
            }

            $result[$baseUid]['lectors'][] = $item;
        }

        // Расчёт статистики
        $stats = $this->calculateStats($result);
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
            'read_only' => true,
            'archive_mode' => true
        ];
    }
}
