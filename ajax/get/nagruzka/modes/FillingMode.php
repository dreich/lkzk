<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Заполнение"
 * Данные по нагрузке доступны для редактирования завкафу
 * Привязки из Галактики игнорируются, работают только сплиты
 */
class FillingMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        // Завкаф видит свою кафедру, УОУП видит все, сотрудник видит только если есть lecturer_uid
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
        // Редактировать может только завкаф
        return $this->userRole === 'zavkaf';
    }

    public function getNagruzkaTypeFilter()
    {
        // Используем type из GET-параметров (all, empty, или конкретный тип)
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

        // Получаем базовые данные
        $chairFilter = $this->getChairFilter();
        $nagruzkaTypeFilter = $this->getNagruzkaTypeFilter();

        // В режиме заполнения исключаем КСРО из основного запроса
        $ksroSql = "AND `nagruzka_type` <> 'ksro'";

        $dopSql = "$chairFilter
            AND `chair_id` IS NOT NULL AND `valid` = '1'
            $ksroSql
        ";

        $nagruzkaData = $this->getBaseData($dopSql, $nagruzkaTypeFilter);

        EchoLog($nagruzkaData);

        // В режиме заполнения игнорируем лекторов из Галактики
        // Очищаем их и применяем только сплиты
        $processor = new SplitProcessor('0');
        $nagruzkaData = $processor->applySplits($nagruzkaData, 'filling');

        // Переиндексируем lectors для каждого base_uid
        foreach ($nagruzkaData as $baseUid => &$item) 
        {
            if (!empty($item['lectors'])) 
            {
            	foreach ($item['lectors'] as &$lector)
            	{
            		$lector['delete'] = !!$lector['delete'];
            	}
            	
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

        // Для УОУП в режиме lite/only_stat - группировка по кафедрам + данные КСРО
        if ($this->userRole === 'uoup' && ($this->onlyStat || $this->isLite)) {
            $nagruzkaData = $this->groupByChair($nagruzkaData, $statByChair);

            // Добавляем данные КСРО из таблицы ksro для статистики
            $this->addKsroToStats($nagruzkaData, $statByChair);
        }

        // Если только статистика - очищаем данные
        if ($this->onlyStat) {
            $nagruzkaData = [];
        }

        return [
            'nagruzka' => array_values($nagruzkaData),
            'stat' => $stat ?: new stdClass(),
            'lecturer_fio' => $this->lecturerFio
        ];
    }

    /**
     * Добавить данные КСРО в статистику (для УОУП)
     */
    protected function addKsroToStats(&$nagruzkaData, &$statByChair)
    {
        global $mysqli;

        // Получаем SQL-условие для кафедры
        $chairSql = $this->getChairSqlForKsro();
        $lecturerSql = $this->getLecturerSqlForKsro();

        $rows = GetTable('ksro', "$chairSql $lecturerSql");

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $chairId = $row['chair_id'];
                $amount = (float) $row['Amount'];

                // Добавляем в статистику по кафедре
                if (!isset($statByChair[$chairId]['assigned']['sum'])) {
                    $statByChair[$chairId]['assigned']['sum'] = 0;
                }
                if (!isset($statByChair[$chairId]['total']['sum'])) {
                    $statByChair[$chairId]['total']['sum'] = 0;
                }

                $statByChair[$chairId]['assigned']['sum'] += $amount;
                $statByChair[$chairId]['total']['sum'] += $amount;

                // Обновляем данные в nagruzkaData для этой кафедры
                if (isset($nagruzkaData[$chairId])) {
                    $nagruzkaData[$chairId]['assigned'] = (isset($nagruzkaData[$chairId]['assigned']) ? $nagruzkaData[$chairId]['assigned'] : 0) + $amount;
                    $nagruzkaData[$chairId]['total'] = (isset($nagruzkaData[$chairId]['total']) ? $nagruzkaData[$chairId]['total'] : 0) + $amount;
                }
            }
        }
    }

    /**
     * Получить SQL-условие для кафедры (для КСРО)
     */
    protected function getChairSqlForKsro()
    {
        if ($this->chairId) {
            return "AND `chair_id` = '{$this->chairId}'";
        }
        return '';
    }

    /**
     * Получить SQL-условие для преподавателя (для КСРО)
     */
    protected function getLecturerSqlForKsro()
    {
        if ($this->lecturerUid) {
            return "AND `lecturer_uid` = '{$this->lecturerUid}'";
        }
        return '';
    }
}
