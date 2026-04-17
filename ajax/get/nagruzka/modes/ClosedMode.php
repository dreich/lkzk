<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Закрыто"
 * Галактика формирует первичные данные
 * У завкафов ничего не показывается, УОУП видит read-only
 */
class ClosedMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        // Завкаф ничего не видит в этом режиме
        if ($this->userRole === 'zavkaf') {
            return false;
        }

        // УОУП видит для контроля
        if ($this->userRole === 'uoup') {
            return true;
        }

        // Сотрудник не видит
        if ($this->userRole === 'sotrudnik') {
            return false;
        }

        return false;
    }

    public function canEdit()
    {
        // В режиме закрыто редактирование запрещено для всех
        return false;
    }

    public function getNagruzkaTypeFilter()
    {
        return $this->nagruzkaType ?: 'all';
    }

    public function getData()
    {
        // Для завкафа и сотрудника - сообщение о подготовке
        if ($this->userRole === 'zavkaf' || $this->userRole === 'sotrudnik') {
            return [
                'nagruzka' => [],
                'stat' => new stdClass(),
                'lecturer_fio' => null,
                'message' => 'Подготовка данных для распределения нагрузки',
                'system_closed' => true
            ];
        }

        // Для УОУП - данные в режиме read-only
        $chairFilter = $this->getChairFilter();

        $dopSql = "$chairFilter
            AND `chair_id` IS NOT NULL AND `valid` = '1'
        ";

        $nagruzkaData = $this->getBaseData($dopSql, 'all');

        // В режиме закрыто сплиты не применяются - показываем чистые данные из Галактики
        // Но lectors формируем из данных Галактики
        foreach ($nagruzkaData as $baseUid2 => $item) {
            $baseUid = $item['base_uid'];

            if (!isset($result[$baseUid])) {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
            } else {
                $result[$baseUid]['Amount'] += $item['Amount'];
            }

            // Добавляем лектора из Галактики
            $result[$baseUid]['lectors'][] = $item;
        }

        // Расчёт статистики
        $stats = $this->calculateStats(isset($result) ? $result : []);
        $nagruzkaData = $stats['data'];
        $stat = $stats['stat'];
        $statByChair = $stats['statByChair'];

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
            'read_only' => true
        ];
    }
}
