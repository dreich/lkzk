<?

/**
 * Helper для обработки сплитов (распределений нагрузки)
 * Выделяет логику работы с zavkaf_splits в отдельный класс
 */
class SplitProcessor
{
    private $splits;
    private $splitsByBaseUid;

    public function __construct($deleteFlag = '0')
    {
        $this->loadSplits($deleteFlag);
    }

    /**
     * Загрузить сплиты из базы
     */
    private function loadSplits($deleteFlag)
    {
        $this->splits = GetTable('zavkaf_splits', "`delete` = '$deleteFlag'");
        $this->indexSplits();
    }

    /**
     * Индексировать сплиты для быстрого поиска
     */
    private function indexSplits()
    {
        $this->splitsByBaseUid = [];

        if (empty($this->splits)) {
            return;
        }

        foreach ($this->splits as $split) {
            $baseUid = $split['base_uid'];
            $baseUid2 = $split['base_uid2'];
            $contentOfLoadUid = $split['content_of_load_uid'];

            if (!isset($this->splitsByBaseUid[$baseUid])) {
                $this->splitsByBaseUid[$baseUid] = [];
            }

            if (!isset($this->splitsByBaseUid[$baseUid][$baseUid2])) {
                $this->splitsByBaseUid[$baseUid][$baseUid2] = [];
            }

            $this->splitsByBaseUid[$baseUid][$baseUid2][$contentOfLoadUid][] = $split;
        }
    }

    /**
     * Применить сплиты к данным нагрузки
     * 
     * @param array $nagruzkaData Данные из Галактики
     * @param string $mode Режим работы: 'filling', 'verification', etc.
     *                      В режиме 'filling' лекторы из Галактики очищаются (как будто UID_Lecturer = -1)
     * @return array Обработанные данные с примененными сплитами
     */
    public function applySplits($nagruzkaData, $mode = 'default')
    {
        if (empty($nagruzkaData)) {
            return $nagruzkaData;
        }

        // В режиме заполнения очищаем лекторов из Галактики
        if ($mode === 'filling') {
            $nagruzkaData = $this->clearGalaxyLectors($nagruzkaData);
        }

        if (empty($this->splits)) {
            return $nagruzkaData;
        }

        $result = [];
        $processedSplits = [];

        foreach ($nagruzkaData as $baseUid2 => $item) {
            $baseUid = $item['base_uid'];

            // Агрегируем по base_uid
            if (!isset($result[$baseUid])) 
            {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
            } else {
                $result[$baseUid]['Amount'] += $item['Amount'];
            }

            // Проверяем есть ли сплиты для этой строки
            if ($this->hasSplits($baseUid, $item['base_uid2'])) {
                $splitRows = $this->getSplits($baseUid, $item['base_uid2']);

                foreach ($splitRows as $splitRow) {
                    $lectorData = $this->createLectorDataFromSplit($item, $splitRow);
                    $result[$baseUid]['lectors'][] = $lectorData;
                    $processedSplits[$baseUid][$item['base_uid2']] = true;
                }
            } else {
                // Проверяем, не был ли base_uid2 создан из сплита
                if (empty($processedSplits[$baseUid][$item['base_uid2']])) {
                    $result[$baseUid]['lectors'][] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Очистить данные лекторов из Галактики (делаем как будто без привязки)
     * Используется в режиме заполнения
     */
    private function clearGalaxyLectors($nagruzkaData)
    {
        foreach ($nagruzkaData as $baseUid2 => &$item) {
            // Очищаем поля лектора - делаем пустыми
            $item['lecturer_fio'] = null;
            $item['lecturer_uid'] = null;
            $item['lecturer_person_id'] = null;
            $item['lecturer_login'] = null;
            $item['UID_Lecturer'] = null;

            // Очищаем суффикс лектора из base_uid2 через parse/glue
            $baseUid2Obj = parseNagruzkaBaseUid2($item['base_uid2']);

            // Убираем lector_suffix (оставляем только base и potok_suffix)
            $baseUid2Obj['lector_suffix'] = '';

            // Склеиваем обратно без лектора
            $item['base_uid2'] = glueNagruzkaBaseUid2Parts($baseUid2Obj);
        }
        unset($item);

        return $nagruzkaData;
    }

    /**
     * Применить сплиты с приоритетом (режим Выверки)
     * Если есть сплит - используем его, иначе берем из Галактики
     */
    public function applySplitsWithPriority($nagruzkaData)
    {
        if (empty($this->splits) || empty($nagruzkaData)) {
            // Просто группируем по base_uid без изменений
            return $this->groupByBaseUid($nagruzkaData);
        }

        $result = [];

        foreach ($nagruzkaData as $baseUid2 => $item) {
            $baseUid = $item['base_uid'];

            if (!isset($result[$baseUid])) {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
                $result[$baseUid]['Amount'] = 0;
            }

            // Проверяем есть ли сплит для этой строки
            if ($this->hasSplits($baseUid, $item['base_uid2'])) {
                // Есть сплит - используем его (приоритет для правок)
                $split = $this->getFirstSplit($baseUid, $item['base_uid2']);
                $lectorData = $this->createLectorDataFromSplit($item, $split);
                $lectorData['from_split'] = true;
                $result[$baseUid]['lectors'][] = $lectorData;
                $result[$baseUid]['Amount'] += $lectorData['Amount'];
            } else {
                // Нет сплита - берём из Галактики
                $result[$baseUid]['lectors'][] = $item;
                $result[$baseUid]['Amount'] += $item['Amount'];
            }
        }

        return $result;
    }

    /**
     * Проверить есть ли сплиты для строки нагрузки
     */
    public function hasSplits($baseUid, $baseUid2)
    {
        return !empty($this->splitsByBaseUid[$baseUid][$baseUid2]);
    }

    /**
     * Получить сплиты для строки нагрузки
     */
    public function getSplits($baseUid, $baseUid2)
    {
        if (!$this->hasSplits($baseUid, $baseUid2)) {
            return [];
        }

        // Возвращаем первую группу по content_of_load_uid
        return array_values($this->splitsByBaseUid[$baseUid][$baseUid2])[0];
    }

    /**
     * Получить первый сплит для строки
     */
    public function getFirstSplit($baseUid, $baseUid2)
    {
        $splits = $this->getSplits($baseUid, $baseUid2);
        return isset($splits[0]) ? $splits[0] : null;
    }

    /**
     * Создать данные лектора из сплита
     */
    private function createLectorDataFromSplit($originalData, $split)
    {
        $lectorData = $originalData;

        $lectorData['lecturer_login'] = $split['lecturer_login'];
        $lectorData['lecturer_person_id'] = $split['lecturer_person_id'];
        $lectorData['lecturer_uid'] = $split['lecturer_uid'];
        $lectorData['lecturer_fio'] = $split['lecturer_fio'];

        if (isset($split['LoadType'])) {
            $lectorData['LoadType'] = $split['LoadType'];
        }

        if (isset($split['StudentAmount'])) {
            $lectorData['StudentAmount'] = $split['StudentAmount'];
        }

        if (isset($split['Amount'])) {
            $lectorData['Amount'] = $split['Amount'];
        }

        $lectorData['delete'] = $split['delete'];
        $lectorData['zs'] = true; // флаг завкаф-сплита

        return $lectorData;
    }

    /**
     * Просто группировка по base_uid без применения сплитов
     */
    private function groupByBaseUid($nagruzkaData)
    {
        $result = [];

        foreach ($nagruzkaData as $baseUid2 => $item) {
            $baseUid = $item['base_uid'];

            if (!isset($result[$baseUid])) {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
                $result[$baseUid]['Amount'] = 0;
            }

            $result[$baseUid]['lectors'][] = $item;
            $result[$baseUid]['Amount'] += $item['Amount'];
        }

        return $result;
    }

    /**
     * Получить статистику по сплитам
     */
    public function getStats()
    {
        $total = count($this->splits);
        $byBaseUid = count($this->splitsByBaseUid);

        return [
            'total_splits' => $total,
            'unique_base_uids' => $byBaseUid
        ];
    }
}
