<?

/**
 * Helper для обработки сплитов (распределений нагрузки)
 * Выделяет логику работы с zavkaf_splits в отдельный класс
 */
class SplitProcessor
{
    private $splits;
    private $splitsByBaseUid;

    // $chairUIDs - для декана
    public function __construct($deleteFlag = '0', $chairId, $chairUIDs, $nagruzka_type)
    {
        $this->loadSplits($deleteFlag, $chairId, $chairUIDs, $nagruzka_type);
    }

    /**
     * Загрузить сплиты из базы
     $chairUIDs - для декана
     */
    private function loadSplits($deleteFlag, $chairId, $chairUIDs, $nagruzka_type)
    {
        global $_SESSION;

        $c_roles = ExplodePalki($_SESSION['c_roles'], true);

        // EchoLog($chairId);

        // !! нельзя фильтровать по zavkaf_chair_uid для нагрузки типа aspirantura_itog_exam, т.к. там нет кафедры
        // $c_roles['zavkaf'] && 
        if (!empty($chairId) && $nagruzka_type != 'aspirantura_itog_exam')
        {
          $XmlChairByCode = GetTable('xml_chair', "", "", "Code");

          if ($c_roles['zavkaf'] && !$chairId) $chair_id = $_SESSION['c_chair_id'];
          else $chair_id = $chairId;

          // EchoLog($chair_id);

          $ZavkafChair = $XmlChairByCode[$chair_id];

          if ($ZavkafChair)
          {
            $zavkaf_chair_uid = $ZavkafChair['UID'];
            $where_sql = "AND `zavkaf_chair_uid` = '$zavkaf_chair_uid'";
          }
        }
        // для декана возьмём все кафедры его факультета, чтобы ограничить загружаемые сплиты
        elseif (!empty($chairUIDs) && $nagruzka_type != 'aspirantura_itog_exam')
        {
            $chair_uids_str = JoinArrayElements($chairUIDs, ", ", false, "'", "'");
            if ($chair_uids_str)
            {
                $where_sql = "AND `zavkaf_chair_uid` IN($chair_uids_str)";
            }
        }

        // EchoLog($chair_id);
        // EchoLog($where_sql);

        // Все поля: 'id', 'content_of_load_uid', 'base_uid', 'base_uid2', 'base_uid2_new', 'LoadType', 'StudentAmount', 'Amount', 'lecturer_login', 'lecturer_person_id', 'lecturer_fio', 'lecturer_uid', 'chair_uid', 'zavkaf_login', 'zavkaf_fio', 'zavkaf_chair_uid', 'delete', 'date'
        // Исключённые поля: , `date`, `content_of_load_uid`, `base_uid2_new`, `zavkaf_chair_uid`

        // работа со сплитами является узким местом, будем брать только используемые поля
        $this->splits = GetTable('zavkaf_splits', "`delete` = '$deleteFlag' $where_sql ", null, null, "`id`, `base_uid`, `base_uid2`, `LoadType`, `StudentAmount`, `Amount`, `lecturer_login`, `lecturer_person_id`, `lecturer_fio`, `lecturer_uid`, `chair_uid`, `zavkaf_login`, `zavkaf_fio`, `delete`, `zavkaf_chair_uid`");

        // EchoLog(sizeof($this->splits));

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
            // $contentOfLoadUid = $split['content_of_load_uid'];

            if (!isset($this->splitsByBaseUid[$baseUid])) {
                $this->splitsByBaseUid[$baseUid] = [];
            }

            if (!isset($this->splitsByBaseUid[$baseUid][$baseUid2])) {
                $this->splitsByBaseUid[$baseUid][$baseUid2] = [];
            }

            // $this->splitsByBaseUid[$baseUid][$baseUid2][$contentOfLoadUid][] = $split;
            $this->splitsByBaseUid[$baseUid][$baseUid2][] = $split;
        }

        unset($this->splits);
    }

    /**
     * Применить сплиты к данным нагрузки
     * 
     * @param array $nagruzkaData Данные из Галактики
     * @param string $mode Режим работы: 'mode_filling'
     *                     В режиме 'mode_filling' лекторы из Галактики очищаются (как будто UID_Lecturer = -1)
     * @return array Обработанные данные с примененными сплитами
     */
    public function applySplits($nagruzkaData, $mode = '')
    {

        if (empty($nagruzkaData)) 
        {
            return $nagruzkaData;
        }

        // В режиме заполнения очищаем лекторов из Галактики и объединяем дубликаты
        if ($mode === 'mode_filling') 
        {
            $this->clearGalaxyLectors($nagruzkaData);
            // EchoLog($nagruzkaData);
            // $nagruzkaData = $this->consolidateLectors($nagruzkaData);
            // EchoLog($nagruzkaData);
        }

        // if (empty($this->splits)) {
        //     return $nagruzkaData;
        // }

        $result = [];
        $processedSplits = [];

        // $baseUid или $baseUid2 (?)
        foreach ($nagruzkaData as $baseUid2 => $item) 
        {
            // СРАЗУ ОСВОБОЖДАЕМ ПАМЯТЬ ИЗ СТАРОГО МАССИВА
            // unset($nagruzkaData[$baseUid2]);

            $baseUid = $item['base_uid'];

            // Агрегируем по base_uid
            if (!isset($result[$baseUid])) 
            {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
            } 
            else 
            {
                $result[$baseUid]['Amount'] += $item['Amount'];
            }

            // if ($baseUid === '26589.281474976773465')
            //     {
            //         EchoLog("HERE");
            //     }

            // Проверяем есть ли сплиты для этого base_uid
            if ($this->hasSplitsByBaseUID($baseUid)) //, $item['base_uid2'])) 
            {

                $splitRows = $this->getSplits($baseUid, $item['base_uid2']);

                // if ($baseUid === '26589.281474976827021')
                // {
                //     EchoLog($splitRows);
                // }

                // EchoLog("SPLITS:");
                // EchoLog($splitRows);

                if ($splitRows)
                foreach ($splitRows as $splitRow) 
                {
                    $lectorData = $this->createLectorDataFromSplit($item, $splitRow);
                    $result[$baseUid]['lectors'][] = $lectorData;
                    $processedSplits[$baseUid][$item['base_uid2']] = true;
                }

                // if ($baseUid === '26589.281474976827021')
                // {
                //     EchoLog($result);
                // }
            } 
            else 
            {
                // if (empty($processedSplits[$baseUid][$item['base_uid2']])) 
                {
                    $result[$baseUid]['lectors'][] = $item;
                }
            }
        }

        return $result;
    }


    /**
     * Применить сплиты с приоритетом (режим Выверки)
     * Если есть сплит - используем его, иначе берем из Галактики
     */
    /*
    public function applySplitsWithPriority($nagruzkaData)
    {
      // EchoLog($this->splits);
        if (empty($this->splits) || empty($nagruzkaData)) {
            // Просто группируем по base_uid без изменений
            return $this->groupByBaseUid($nagruzkaData);
        }

        $result = [];

        // вроде $baseUid вместо $baseUid2
        foreach ($nagruzkaData as $baseUid2 => $item) 
        {
            $baseUid = $item['base_uid'];

            // Агрегируем по base_uid
            if (!isset($result[$baseUid])) 
            {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
            } else {
                $result[$baseUid]['Amount'] += $item['Amount'];
            }

            // Проверяем есть ли сплит для этого base_uid
            // Есть сплит - используем сплиты (приоритет для правок)
            if ($this->hasSplitsByBaseUID($baseUid)) // , $item['base_uid2'])) 
            {
                
                // $split = $this->getFirstSplit($baseUid, $item['base_uid2']);
                // $lectorData = $this->createLectorDataFromSplit($item, $split);
                // $lectorData['from_split'] = true;
                // $result[$baseUid]['lectors'][] = $lectorData;
                // $result[$baseUid]['Amount'] += $lectorData['Amount'];
                $splitRows = $this->getSplits($baseUid, $item['base_uid2']);

                EchoLog("SPLITS:");
                EchoLog($splitRows);

                foreach ($splitRows as $splitRow) 
                {
                    $lectorData = $this->createLectorDataFromSplit($item, $splitRow);
                    $result[$baseUid]['lectors'][] = $lectorData;
                    $processedSplits[$baseUid][$item['base_uid2']] = true;
                }

            }
            else 
            {
              // Проверяем, не был ли base_uid2 создан из сплита
              // if (empty($processedSplits[$baseUid][$item['base_uid2']])) 
              //   {
              //       $result[$baseUid]['lectors'][] = $item;
              //   }

                // Нет сплита - берём из Галактики
                $result[$baseUid]['lectors'][] = $item;
                // $result[$baseUid]['Amount'] += $item['Amount'];
            }
        }

        return $result;
    }
  */

    /**
     * Очистить данные лекторов из Галактики (делаем как будто без привязки)
     * Используется в режиме заполнения
     */
    private function clearGalaxyLectors(&$nagruzkaData)
    {
        foreach ($nagruzkaData as $baseUid2 => &$item) 
        {
            // Очищаем поля лектора - делаем пустыми
            if (!empty($item['lecturer_fio'])) $item['lecturer_fio'] = null;
            if (!empty($item['lecturer_uid'])) $item['lecturer_uid'] = null;
            if (!empty($item['lecturer_person_id'])) $item['lecturer_person_id'] = null;
            if (!empty($item['lecturer_login'])) $item['lecturer_login'] = null;
            if (!empty($item['UID_Lecturer'])) $item['UID_Lecturer'] = null;

            // Очищаем суффикс лектора из base_uid2 через parse/glue
            // $baseUid2Obj = parseNagruzkaBaseUid2($item['base_uid2']);

            // Убираем lector_suffix (оставляем только base и potok_suffix)
            // $baseUid2Obj['lector_suffix'] = '';

            // Склеиваем обратно без лектора
            // $item['base_uid2'] = glueNagruzkaBaseUid2Parts($baseUid2Obj);
        }
        unset($item);

        // return $nagruzkaData;
    }

    /**
     * Объединить лекторов с одинаковым base_uid2 после очистки из Галактики
     * В режиме заполнения оставляем только одного лектора с суммарными Amount и StudentAmount
     */
    
    private function consolidateLectors($nagruzkaData)
    {
        $consolidated = [];

        foreach ($nagruzkaData as $item) 
        {
            // СРАЗУ ОСВОБОЖДАЕМ ПАМЯТЬ
            // unset($nagruzkaData[$key]);
            
            // Используем base_uid2 как ключ для группировки
            // После очистки лекторов base_uid2 не содержит информации о лекторе
            if (!isset($consolidated[$item['base_uid']])) {
                $consolidated[$item['base_uid']] = $item;
            } else {
                // Суммируем Amount и StudentAmount
                $consolidated[$item['base_uid']]['Amount'] += $item['Amount'];
                $consolidated[$item['base_uid']]['StudentAmount'] += $item['StudentAmount'];
            }
        }

        return $consolidated;
    }
    

        /**
     * Объединить лекторов с одинаковым base_uid после очистки из Галактики
     * 
     * Оптимизировано по памяти: работает in-place (по ссылке),
     * но код остался очень близким к оригиналу.
     */
        /*
    private function consolidateLectors(&$nagruzkaData)
    {
        if (empty($nagruzkaData)) {
            return;
        }

        $consolidated = [];

        foreach ($nagruzkaData as $item) {
            $baseUid = $item['base_uid'] ?? null;
            if ($baseUid === null) {
                continue;
            }

            if (!isset($consolidated[$baseUid])) {
                // Первый экземпляр — сохраняем полностью
                $consolidated[$baseUid] = $item;
            } else {
                // Дубликат — только суммируем часы
                $consolidated[$baseUid]['Amount']        += (float)($item['Amount'] ?? 0);
                $consolidated[$baseUid]['StudentAmount'] += (float)($item['StudentAmount'] ?? 0);
            }
        }

        // Заменяем исходный массив на результат
        $nagruzkaData = $consolidated;
    }
    */
    


    /**
     * Проверить есть ли сплиты для строки нагрузки
     */
    public function hasSplits($baseUid, $baseUid2)
    {
        return !empty($this->splitsByBaseUid[$baseUid][$baseUid2]);
    }

    public function hasSplitsByBaseUID($baseUid)
    {
        return !empty($this->splitsByBaseUid[$baseUid]);
    }

    /**
     * Получить сплиты для строки нагрузки.
     * Возвращает массив сплитов для baseUid2: например, мог быть из Галактики один преподаватель, а стало два
     */
    public function getSplits($baseUid, $baseUid2)
    {
        if (!$this->hasSplits($baseUid, $baseUid2)) {
            return [];
        }

        // -- Возвращаем первую группу по content_of_load_uid
        // return array_values($this->splitsByBaseUid[$baseUid][$baseUid2])[0];
        return $this->splitsByBaseUid[$baseUid][$baseUid2];
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
        $lectorData['chair_uid'] = $split['chair_uid'];

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
     * закомментировано, чтобы не использовать $this->splits после "индексации" для экономии памяти
     */
    /*
    public function getStats()
    {
        $total = count($this->splits);
        $byBaseUid = count($this->splitsByBaseUid);

        return [
            'total_splits' => $total,
            'unique_base_uids' => $byBaseUid
        ];
    }
    */
}
