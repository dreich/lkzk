<?

include_once __DIR__ . '/helpers/SplitProcessor.php';

/**
 * Базовый класс для провайдеров нагрузки
 * Определяет общую логику для всех режимов работы системы
 */
abstract class BaseNagruzkaProvider
{
    protected $session;
    protected $getParams;
    public $userRole;
    protected $userRoles;
    protected $chairId;

    // кафедры для декана
    protected $chairIds;
    protected $chairUIDs;

    protected $lecturerUid;
    protected $lecturerFio;
    protected $systemMode;
    protected $isLite;
    protected $onlyStat;
    protected $nagruzkaType;
    protected $globalFilter;

    public function __construct($session, $getParams)
    {
        $this->session = $session;
        $this->getParams = $getParams;
        $this->initContext();
    }

    /**
     * Инициализация контекста пользователя
     */
    protected function initContext()
    {
        $this->userRoles = $this->explodePalki($this->session['c_roles'] ? $this->session['c_roles'] : '', true);
        $this->userRole = $this->determinePrimaryRole();


        $this->chairId = isset($this->getParams['chair_id']) && $this->getParams['chair_id'] != 'null' && $this->getParams['chair_id'] != 'all' ? $this->getParams['chair_id'] : null;

        if ($this->userRole === 'dean') 
        {
          $department_id = $this->session['c_department_id'];

          $faculty = GetRow('xml_faculty', ['Code' => $department_id]);
          $Chairs = GetRows('xml_chair', ['UID_Faculty' => $faculty['UID']]);
          $this->chairIds = [];
          $this->chairUIDs = [];

          if ($Chairs)
          {
            foreach ($Chairs as $chair)
            {
              $this->chairIds[] = $chair['Code'];
              $this->chairUIDs[] = $chair['UID'];
            }
          }

          // $chairUids = JoinArrayElements($chair_uids, ", ", false, "'", "'");
        }

        $this->lecturerUid = isset($this->getParams['lecturer_uid']) ? $this->getParams['lecturer_uid'] : null;
        $this->isLite = !empty($this->getParams['lite']);
        $this->onlyStat = !empty($this->getParams['only_stat']);
        $this->nagruzkaType = isset($this->getParams['type']) ? $this->getParams['type'] : 'all';
        $this->globalFilter = isset($_COOKIE['global_nagruzka_filter']) ? $_COOKIE['global_nagruzka_filter'] : null;

        if ($this->lecturerUid) 
        {
            $lecturer = $this->getRow('xml_lecturer', ['UID' => $this->lecturerUid]);
            $this->lecturerFio = isset($lecturer['FIO']) ? $lecturer['FIO'] : null;
        }

        $modeRow = $this->getRow('params', ['param' => 'system_mode']);
        $this->systemMode = isset($modeRow['value']) ? $modeRow['value'] : 'mode_closed';
    }

    /**
     * Определить основную роль пользователя
     */
    protected function determinePrimaryRole()
    {
        if (!empty($this->userRoles['dean'])) return 'dean';
        if (!empty($this->userRoles['zavkaf'])) return 'zavkaf';
        if (!empty($this->userRoles['uoup'])) return 'uoup';
        if (!empty($this->userRoles['ruk_aspirantura'])) return 'ruk_aspirantura';
        // сотрудник должен быть последним
        if (!empty($this->userRoles['sotrudnik'])) return 'sotrudnik'; 
        
        return null;
    }

    /**
     * Проверить авторизацию
     */
    public function checkAuth()
    {
        if (empty($this->session['c_roles'])) {
            return ['error' => 'expired'];
        }

        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            http_response_code(403);
            return ['error' => 'forbidden'];
        }

        return null;
    }

    /**
     * Получить SQL-условие для фильтрации по кафедре
     */
    protected function getChairFilter()
    {
      // Для аспирантской нагрузки в 1-й таблице (из Галактики) такие варианты:
      // 1) препода нет (-1), а кафедра - 25031.281474976763050 - центр аспирантуры
      // 2) TODO препод есть [другой режим работы, не Заполнение], а кафедра - где он работает (жду подтверждения от Горохова)
      if ($this->nagruzkaType == 'aspirantura_itog_exam')
      {
        // Это значит из NagruzkaCtrl запрос на получение статистики
        // и выбрана кафедра.
        // Т.к. в 1-й таблице по этой нагрузке, считай, кафедры нет, то должны будем фильтровать на уровне сплитов
        if (!empty($this->chairId))
        {
           return ""; // "AND xml_content_of_load.UID_Lecturer = '-1' AND xml_content_of_load.UID_Chair = '25031.281474976763050'";
        }
      }

      // Если получаем только одного преподавателя, то проверим, не ГПХ-шник ли он.
      // 1. Если ГПХ-шник, то нужно вместо фильтра кафедры использовать фильтр факультета.
      // 2. Если не ГПХ-шник, то оставляем фильтр кафедры
      if ($this->lecturerUid)
      {
        $Lecturer = GetRow('xml_lecturer', ['UID' => $this->lecturerUid]);
        if ($Lecturer)
        {   
            // 
            $lecturer_faculty_uid = $lecturer_chair_uid = $Lecturer['UID_Chair'];
            $person_id = $Lecturer['Tab_number'];
            // $lecturer_chair = GetRow('xml_chair', ['UID' => $lecturer_chair_uid]);
            $lecturer_faculty = GetRow('xml_faculty', ['UID' => $lecturer_faculty_uid]);
            $lecturer_department_id = $lecturer_faculty['Code'];
            $sotrudnik = GetRow('sotrudniki', ['person_id' => $person_id, 'department_id' => $lecturer_department_id]);

            if ($sotrudnik['type'] == 'gph')
            {
              return "AND nagruzka.`department_id` = '$lecturer_department_id'";
            }
        }
      }


      $chairUid = null;

      // для декана и одновременно завкафа подход "первичной роли" не годится, т.к. кафедры для него зависят от страницы, на которой он находится
      // ПОКА ТОЛЬКО ДЕКАН
      if ($this->userRole === 'dean') 
      {
        // Если идёт кафедра из $_GET, то используем её (т.е. декан выбрал кафедру; сюда также попадёт декан+завкаф)
        if ($this->chairId)
        {
          $chair = $this->getRow('xml_chair', ['Code' => $this->chairId]);
          $chairUid = isset($chair['UID']) ? $chair['UID'] : null;
        }
        else
        {
          $chairUids = JoinArrayElements($this->chairUIDs, ", ", false, "'", "'");
          // EchoLog($chairUids);
        }

        // EchoLog($chairUid);
        
      }
      elseif ($this->userRole === 'zavkaf') 
      {
        $cChairId = isset($this->session['c_chair_id']) ? $this->session['c_chair_id'] : null;
        if ($cChairId) 
        {
          $chair = $this->getRow('xml_chair', ['Code' => $cChairId]);
          $chairUid = isset($chair['UID']) ? $chair['UID'] : null;
        }
      } 
      elseif (($this->userRole === 'uoup' || $this->userRole === 'sotrudnik') && $this->chairId) 
      {
        $chair = $this->getRow('xml_chair', ['Code' => $this->chairId]);
        $chairUid = isset($chair['UID']) ? $chair['UID'] : null;
      }

      // EchoLog($chairUid);

      // TMP HACK
      // return "AND xml_content_of_load.UID_Chair = '25031.281474976710937'";

      if ($chairUid) 
      {
        return "AND xml_content_of_load.UID_Chair = '$chairUid'";
      }

      if ($chairUids)
      {
        return "AND xml_content_of_load.UID_Chair IN ($chairUids)";
      }

      return '';
    }

    /**
     * Получить базовые данные нагрузки
     */
    protected function getBaseData($dopSql = '', $type = 'all')
    {
        if ($this->onlyStat) $this->isLite = true;

        $query = GetNagruzkaBaseQuery($dopSql, $type, true, $this->isLite);

        if ($type == 'discipline')
        {
          // EchoLog($query);
        }

        $rawData = GetSQL($query);

        // if ($type == 'discipline')
        // EchoLog(memory_get_usage());

        $result = PrepareNagruzka($rawData, $this->isLite);

        if ($this->nagruzkaType == 'aspirantura_itog_exam')
        {
            // EchoLog($query);
        }

        // ✅ Явно удаляем $rawData, так как он больше не нужен
        unset($rawData);

        // Принудительный сбор мусора (если очень нужно)
        // if (function_exists('gc_collect_cycles')) {
        //     gc_collect_cycles();
        // }

        return $result;
    }

    /**
     * Обработка сплитов через SplitProcessor
     * 
     * @param array $nagruzkaData Данные нагрузки
     * @param string $mode Режим работы
     */
    
    protected function processSplits($nagruzkaData, $mode = '')
    {
        // return $nagruzkaData;
        $processor = new SplitProcessor('0', $this->chairId, $this->chairUIDs, $this->nagruzkaType, $mode);
        return $processor->applySplits($nagruzkaData);
    }
    

    /**
     * Обработка сплитов с приоритетом (для режима Выверки)
     */
    /*
    protected function processSplitsWithPriority($nagruzkaData)
    {
        $processor = new SplitProcessor('0');

        // EchoLog($processor->applySplitsWithPriority($nagruzkaData));
        return $processor->applySplitsWithPriority($nagruzkaData);
        // return $processor->applySplits($nagruzkaData, 'mode_verification');
    }
    */

    /**
     * Расчёт статистики по нагрузке
     */
    protected function calculateStats(&$nagruzkaData)
    {
        global $_SERVER;
        // $stat = [
        //     'assigned' => ['sum' => 0],
        //     'assigned_to_vacancy' => ['sum' => 0],
        //     'not_assigned' => ['sum' => 0],
        //     'total' => ['sum' => 0]
        // ];
        $statByChair = [];

        foreach ($nagruzkaData as $baseUid => $item) 
        {
            // if (empty($item['lectors'])) 
            // {
            //     continue;
            // }

            $hasAssigned = false;
            $hasVacancy = false;
            $hasNotAssigned = false;

            $item['lectors_sum'] = 0;

            if ($item['lectors'])
            foreach ($item['lectors'] as &$lector) 
            {
                $lector['delete'] = !empty($lector['delete']);
                if ($lector['delete']) continue;

                // Вакансия
                if ($lector['lecturer_fio'] && mb_strcasecmp($lector['lecturer_fio'], 'Вакансия') === 0) 
                {
                    $hasVacancy = true;
                    // если Вакансии включать в Распределено
                    $hasAssigned = true;

                    // $stat['assigned_to_vacancy']['sum'] += (float) $lector['Amount'];
                    safeAdd($stat['assigned_to_vacancy']['sum'], $lector['Amount']);
                    // $statByChair[$lector['chair_id']]['assigned_to_vacancy']['sum'] += (float) $lector['Amount'];
                    safeAdd($statByChair[$lector['chair_id']]['assigned_to_vacancy']['sum'], $lector['Amount']);

                    // если Вакансии включать в Распределено
                    safeAdd($stat['assigned']['sum'], $lector['Amount']);
                    safeAdd($statByChair[$lector['chair_id']]['assigned']['sum'], $lector['Amount']);
                }
                // Назначен преподаватель
                elseif ($lector['lecturer_fio'] && mb_strcasecmp($lector['lecturer_fio'], 'Вакансия') !== 0) 
                {
                    $hasAssigned = true;

                    if ($_SERVER['REMOTE_ADDR'] == '85.143.4.44' && $this->nagruzkaType == 'ruk_vkr')
                    {
                        if (intval($lector['Amount']) != $lector['Amount'])
                        {
                            // EchoLog("{$stat['assigned']['sum']} + $lector[Amount]");
                            // EchoLog($lector)
                        }
                    }


                    if ($_SERVER['REMOTE_ADDR'] == '85.143.4.44' && $this->nagruzkaType == 'ruk_vkr')
                    {
                        // EchoLog("Become {$stat['assigned']['sum']}");
                    }

                    // EchoLog($lector);

                    if ($lector['TypeWorkload'] == '0') 
                    {
                        // $stat['assigned_auditorium']['sum'] += (float) $lector['Amount'];
                        safeAdd($stat['assigned_auditorium']['sum'], $lector['Amount']);
                        // $statByChair[$lector['chair_id']]['assigned_auditorium']['sum'] += (float) $lector['Amount'];
                        safeAdd($statByChair[$lector['chair_id']]['assigned_auditorium']['sum'], $lector['Amount']);
                    }

                    if ($lector['UID_Language'] === '25031.945')
                    {
                        // $stat['assigned_english']['sum'] += (float) $lector['Amount'];
                        safeAdd($stat['assigned_english']['sum'], $lector['Amount']);
                        // $statByChair[$lector['chair_id']]['assigned_english']['sum'] += (float) $lector['Amount'];
                        safeAdd($statByChair[$lector['chair_id']]['assigned_english']['sum'], $lector['Amount']);
                    }

                    safeAdd($stat['assigned']['sum'], $lector['Amount']);
                    safeAdd($statByChair[$lector['chair_id']]['assigned']['sum'], $lector['Amount']);

                }
                // Не назначен
                else 
                {
                  // EchoLog($item);

                  $hasNotAssigned = true;
                  // $stat['not_assigned']['sum'] += (float) $lector['Amount'];
                  safeAdd($stat['not_assigned']['sum'], $lector['Amount']);
                  // $statByChair[$lector['chair_id']]['not_assigned']['sum'] += (float) $lector['Amount'];
                  safeAdd($statByChair[$lector['chair_id']]['not_assigned']['sum'], $lector['Amount']);
                }

                safeAdd($item['lectors_sum'], $lector['Amount']);
            }
            else
            {
                safeAdd($stat['not_assigned']['sum'], $item['Amount']);
                // safeAdd($statByChair[$lector['chair_id']]['not_assigned']['sum'], $item['Amount']);
            }

            if (!$hasAssigned && !$hasNotAssigned && $_SERVER['REMOTE_ADDR'] == '85.143.4.44')
            {
                EchoLog($item);
            }

            if ($item['lectors_sum'] != floatval($item['Amount']) && $this->nagruzkaType == 'discipline' && $_SERVER['REMOTE_ADDR'] == '85.143.4.44')
            {
                EchoLog($item);
            }

            $nagruzkaData[$baseUid]['assigned'] = $hasAssigned;
            $nagruzkaData[$baseUid]['assigned_to_vacancy'] = $hasVacancy;
            $nagruzkaData[$baseUid]['not_assigned'] = $hasNotAssigned;

            // $stat['total']['sum'] += $item['Amount'];
            safeAdd($stat['total']['sum'], $item['Amount']);
            // $statByChair[$item['chair_id']]['total']['sum'] += $item['Amount'];
            safeAdd($statByChair[$item['chair_id']]['total']['sum'], $item['Amount']);
        }

        return [
            'stat' => $stat,
            'statByChair' => $statByChair,
            // 'data' => $nagruzkaData
        ];
    }

    /**
     * Фильтрация по преподавателю
     */
    protected function filterByLecturer(&$nagruzkaData)
    {
        // EchoLog($this->lecturerUid);

        if (empty($this->lecturerUid)) 
        {
            return;
        }

        foreach ($nagruzkaData as $baseUid => $item) 
        {
          if ($this->nagruzkaType == 'discipline')
          {
            // EchoLog($item['lectors']);
          }

            $filteredLectors = array_filter($item['lectors'], function($lector) 
            {
                return $lector['lecturer_uid'] === $this->lecturerUid;
            });

            if (empty($filteredLectors)) 
            {
                unset($nagruzkaData[$baseUid]);
            } 
            else 
            {
                $nagruzkaData[$baseUid]['lectors'] = array_values($filteredLectors);
            }
        }
        // Убрать return
    }


    /*
    protected function filterByLecturer($nagruzkaData)
    {
      // EchoLog($this->lecturerUid);

      if (empty($this->lecturerUid)) 
      {
        return $nagruzkaData;
      }

      foreach ($nagruzkaData as $baseUid => $item) 
      {
        // EchoLog($item['lectors']);

        $filteredLectors = array_filter($item['lectors'], function($lector) 
        {
          if ($lector['lecturer_uid'])
          {
            // EchoLog($lector['lecturer_uid']);
            // EchoLog($this->lecturerUid);
            // EchoLog($lector['lecturer_uid'] === $this->lecturerUid);
          }

          return $lector['lecturer_uid'] === $this->lecturerUid;
        });

        // if ($filteredLectors)
        // EchoLog($filteredLectors);

        if (empty($filteredLectors)) 
        {
            unset($nagruzkaData[$baseUid]);
        } 
        else 
        {
            $nagruzkaData[$baseUid]['lectors'] = array_values($filteredLectors);
        }
      }

      return $nagruzkaData;
    }
    */



    /**
     * Глобальная фильтрация (assigned/not_assigned/assigned_to_vacancy)
     */
    protected function applyGlobalFilter(&$nagruzkaData)
    {
      if (empty($this->globalFilter)) 
      {
        return; // Убрать $nagruzkaData
      }

      // Переписываем array_filter на in-place модификацию
      foreach ($nagruzkaData as $key => $item) 
      {
        if (empty($item[$this->globalFilter])) 
        {
          unset($nagruzkaData[$key]);
        }
      }
        // Убрать return
    }
    /*
    protected function applyGlobalFilter($nagruzkaData)
    {

        if (empty($this->globalFilter)) {
            return $nagruzkaData;
        }

        return array_filter($nagruzkaData, function($item) {
          // EchoLog($item);
            return !empty($item[$this->globalFilter]);
        });
    }
    */

    /**
     * Группировка по кафедрам для УОУП
     */
    protected function groupByChair($nagruzkaData, $statByChair)
    {
        $result = [];

        foreach ($nagruzkaData as $item) 
        {
            $chairId = $item['chair_id'];

            if (!isset($result[$chairId])) 
            {
                $result[$chairId] = $item;
                $result[$chairId]['assigned_to_vacancy'] = isset($statByChair[$chairId]['assigned_to_vacancy']['sum']) ? $statByChair[$chairId]['assigned_to_vacancy']['sum'] : 0;
                $result[$chairId]['assigned'] = isset($statByChair[$chairId]['assigned']['sum']) ? $statByChair[$chairId]['assigned']['sum'] : 0;
                $result[$chairId]['not_assigned'] = isset($statByChair[$chairId]['not_assigned']['sum']) ? $statByChair[$chairId]['not_assigned']['sum'] : 0;
                $result[$chairId]['total'] = isset($statByChair[$chairId]['total']['sum']) ? $statByChair[$chairId]['total']['sum'] : 0;
            }
        }

        return $result;
    }

    /**
     * Парсинг base_uid2
     */
    protected function parseBaseUid2($baseUid2)
    {
        $parts = explode('.', $baseUid2);
        $lastPart = end($parts);

        // Проверяем есть ли суффикс лектора (после основного UID)
        if (strpos($lastPart, '_') !== false) {
            list($mainId, $lectorSuffix) = explode('_', $lastPart);
            return ['lector_suffix' => $lectorSuffix];
        }

        return ['lector_suffix' => null];
    }

    /**
     * Вспомогательные методы
     */
    protected function explodePalki($str, $asArray = false)
    {
        if (empty($str)) return $asArray ? [] : '';
        $parts = explode('|', $str);
        if ($asArray) {
            $result = [];
            foreach ($parts as $part) {
                $result[$part] = true;
            }
            return $result;
        }
        return $parts;
    }

    protected function getRow($table, $where)
    {
        return GetRow($table, $where);
    }

    // отфильтровать массив нагрузки по принципу: взять нагрузку только если внутри есть lectors (сплиты),
    // и внутри есть лектор с указанной кафедрой $target_chair_uid.
    // Если это декан, то у него есть $this->chairUIDs. Тогда фильтруем только его кафедры.
    // Это актуально для режима заполнения, когда есть сплиты.
    // TODO для другого режима нужно будет фильтровать по 1-й таблице (ЕСЛИ там будет кафедра препода вообще)
    final public function filterAspirantItogoNagruzkaByChairUid(&$nagruzkaData, $target_chair_uid = "", $filter_by_chair_uids = false)
    {
        foreach ($nagruzkaData as $key => &$item) 
        {
            // Если нет lectors - удаляем элемент
            if (empty($item['lectors'])) {
                unset($nagruzkaData[$key]);
                continue;
            }
            
            // Оставляем только лекторов с нужным chair_uid
            if ($target_chair_uid)
            {
              $item['lectors'] = array_filter($item['lectors'], function($lector) use ($target_chair_uid)
              {
                  return ($lector['chair_uid'] ? $lector['chair_uid'] : '') === $target_chair_uid;
              });
            }

            if ($filter_by_chair_uids && $this->chairUIDs)
            {
              $item['lectors'] = array_filter($item['lectors'], function($lector)
              {
                return !empty($lector['chair_uid']) && in_array($lector['chair_uid'], $this->chairUIDs);
              });
            }
            
            // Если после фильтрации lectors пуст - удаляем элемент
            if (empty($item['lectors'])) {
                unset($nagruzkaData[$key]);
            }
        }
        unset($item); // разрываем ссылку
    }

    public function getCommonDataSQL()
    {
      // данные типы нагрузки выдаются отдельными скриптами
      // return "";
      return "AND `nagruzka_type` NOT IN ('ksro', 'aspirantura_kand_exam', 'aspirantura_ruk_asp', 'aspirantura_ruk_soisk')";
    }


    /**
     * Единый пайплайн получения данных для всех режимов (Template Method)
     */
    final public function getData()
    {
        // 1. Проверка доступа
        if (!$this->canAccessData()) {
            return [
                'nagruzka' => [],
                'stat' => new stdClass(),
                'lecturer_fio' => null
            ];
        }

        // 2. Получение базовых данных (условия SQL отдают дочерние классы)
        $chairFilter = $this->getChairFilter();

        if ($this->nagruzkaType == 'discipline')
        {
          // EchoLog($chairFilter);
        }


        $dopSql = "$chairFilter AND `chair_id` IS NOT NULL AND `chair_id` <> '' AND `valid` = '1' " . $this->getCommonDataSQL();
        // EchoLog($dopSql);

        // EchoLog($this->nagruzkaType);

        // if ($this->nagruzkaType == 'discipline')
        // {
        //     EchoLog(sizeof($nagruzkaData));
        // }

        $nagruzkaData = $this->getBaseData($dopSql, $this->getNagruzkaTypeFilter());

        if ($this->nagruzkaType == 'ruk_practice')
        {
          // EchoLog($nagruzkaData);
        }

        // if ($this->nagruzkaType == 'discipline')
        // EchoLog(memory_get_usage());

        // 3. Обработка сплитов (Логику определяют дочерние классы)
        // Если хотим не применять сплиты, то нельзя просто закомментить строку
        $nagruzkaData = $this->applyModeSplits($nagruzkaData);

        if ($this->nagruzkaType == 'discipline')
        {
            // EchoLog(sizeof($nagruzkaData));
        }

        // если нужно получить данную нагрузку по конкретной кафедре (а в таблице 1 кафедры по ней нет), то нужно отфильтровать, используя сплиты.
        // если сплитов для нагрузки нет, то не берём её
        if ($this->nagruzkaType == 'aspirantura_itog_exam') //  && $this->systemMode == 'mode_filling')
        {
          if (!empty($this->chairId))
          {
            $chair = $this->getRow('xml_chair', ['Code' => $this->chairId]);
            $chairUid = isset($chair['UID']) ? $chair['UID'] : null;
            $this->filterAspirantItogoNagruzkaByChairUid($nagruzkaData, $chairUid);

            // EchoLog($this->chairId);

            if ($this->nagruzkaType == 'aspirantura_itog_exam')
            {
              // EchoLog(sizeof($nagruzkaData));
            }
          }
          // декан
          elseif ($this->chairUIDs)
          {
            $this->filterAspirantItogoNagruzkaByChairUid($nagruzkaData, "", true);
          }
        }

        if ($this->nagruzkaType == 'aspirantura_itog_exam')
        {
            // EchoLog($dopSql);
            // EchoLog(sizeof($nagruzkaData));
            // EchoLog($nagruzkaData);
        }

        // if ($this->nagruzkaType == 'discipline')
        // EchoLog($nagruzkaData);

        // 4. Переиндексация лекторов (общая логика)
        $this->reindexLectors($nagruzkaData);

        // if ($this->nagruzkaType == 'discipline')
        // EchoLog($nagruzkaData);

        if ($this->nagruzkaType == 'discipline')
        EchoLog(sizeof($nagruzkaData));
      
        // 5. Фильтрация по преподавателю
        $this->filterByLecturer($nagruzkaData);

        // if ($this->nagruzkaType == 'discipline')
        // EchoLog(sizeof($nagruzkaData));

        if ($this->nagruzkaType == 'discipline' && $this->chairId == '05419')
        {
            // EchoLog($dopSql);
            // EchoLog(sizeof($nagruzkaData));
            // EchoLog($nagruzkaData);
        }

        if ($this->nagruzkaType == 'discipline')
        EchoLog(sizeof($nagruzkaData));

        // 6. Расчет статистики
        $stats_obj = $this->calculateStats($nagruzkaData);
        $stat = $stats_obj['stat'];
        $statByChair = $stats_obj['statByChair'];

        // if ($this->nagruzkaType == 'all')
        // EchoLog($stats_obj['statByChair']);

        // 7. Глобальная фильтрация
        $this->applyGlobalFilter($nagruzkaData);

        // 8. Специфичная логика для УОУП (Группировка)
        if (($this->userRole === 'uoup' || $this->userRole === 'dean') && ($this->onlyStat || $this->isLite)) 
        {
          $nagruzkaData = $this->groupByChair($nagruzkaData, $statByChair);

          // if ($this->nagruzkaType == 'all')
          // EchoLog($nagruzkaData);

          // EchoLog($this->nagruzkaType);
          // Хук для добавления КСРО (-- используется только в FillingMode)
          // Предположительно, это добавляется когда вызывается nagruzka/?lite=1
          if (!$this->nagruzkaType || $this->nagruzkaType == 'all')
          {
            // EchoLog('jere');
            $this->applyExtraUoupTransformations($nagruzkaData, $statByChair);
          }
        }

        // 9. Финальная сборка ответа
        if ($this->onlyStat /* && $this->nagruzkaType != 'ruk_vkr' */) 
        {
            $nagruzkaData = [];
        }
        elseif ($this->nagruzkaType == 'ruk_vkr' && $_SERVER['REMOTE_ADDR'] == '85.143.4.44')
        {
            // EchoLog($nagruzkaData);
        }

        return array_merge([
            'nagruzka' => array_values($nagruzkaData),
            'stat' => $stat ?: new stdClass(),
            'lecturer_fio' => $this->lecturerFio
        ], $this->getExtraResponseData());
    }

    /**
     * Выносим общую переиндексацию в отдельный метод
     */
    protected function reindexLectors(&$nagruzkaData)
    {
        foreach ($nagruzkaData as $baseUid => &$item) {
            if (!empty($item['lectors'])) {
                foreach ($item['lectors'] as &$lector) {
                    $lector['delete'] = !!$lector['delete'];
                }
                $item['lectors'] = array_values($item['lectors']);
            } else {
                $item['lectors'] = [];
            }
        }
        unset($item);
    }

    // --- АБСТРАКТНЫЕ МЕТОДЫ И ХУКИ ДЛЯ ДОЧЕРНИХ КЛАССОВ ---
    
    abstract public function canView();
    abstract public function canEdit();
    abstract public function getNagruzkaTypeFilter();
    
    abstract protected function canAccessData();
    // пока не исп.
    // abstract protected function getModeSpecificSql();
    abstract protected function applyModeSplits($nagruzkaData);
    abstract protected function getExtraResponseData();
    
    // -- Пустой хук по умолчанию (переопределяется в FillingMode)
    // protected function applyExtraUoupTransformations(&$nagruzkaData, &$statByChair) {}

    // для УОУП и декана добавить в статистику КСРО и аспирантуру (ручную, т.е. без nagruzka_itog_exam: из таблиц aspirantura_*)
    protected function applyExtraUoupTransformations(&$nagruzkaData, &$statByChair)
    {
        // Добавляем данные КСРО из таблицы ksro для статистики
        $this->addKsroToStats($nagruzkaData, $statByChair);

        // Добавляем данные аспирантуры из таблиц aspirantura_* для статистики
        $this->addAspiranturaToStats($nagruzkaData, $statByChair);
    }

    /**
     * Добавить данные КСРО в статистику (?) (для УОУП)
        В режиме заполнения в группировку по кафедрам для #/uoup_nagruzka нужно взять данные из таблицы ksro 
        ! Т.к. в таблице ksro нет таких полей как название кафедры, факультета, то отображение этих данных в зелёной таблице у УОУП полагается на другие виды нагрузки в $NagruzkaByBaseUID1. Т.е. если не будет других видов нагрузки, то КСРО не будет отображаться в таблице; 
     */
    protected function addKsroToStats(&$nagruzkaData, &$statByChair)
    {
      global $mysqli;

      // Получаем SQL-условие для кафедры и лектора
      $chairSql = $this->getChairSqlKSRO();
      $lecturerSql = $this->getLecturerSql();

      // EchoLog($lecturerSql);

      $rows = GetTable('ksro', "1 $chairSql $lecturerSql");

      // EchoLog($rows);

      if (!empty($rows)) 
      {
        foreach ($rows as $row) 
        {
          $chairId = $row['chair_id'];
          $amount = (float) $row['Amount'];

          // Добавляем в статистику по кафедре
          if (!isset($statByChair[$chairId]['assigned']['sum'])) 
          {
              $statByChair[$chairId]['assigned']['sum'] = 0;
          }
          if (!isset($statByChair[$chairId]['total']['sum'])) 
          {
              $statByChair[$chairId]['total']['sum'] = 0;
          }

          $statByChair[$chairId]['assigned']['sum'] += $amount;
          $statByChair[$chairId]['total']['sum'] += $amount;

          // Обновляем данные в nagruzkaData для этой кафедры
          if (!$nagruzkaData[$chairId]) $nagruzkaData[$chairId] = [];

          if (!$nagruzkaData[$chairId]['assigned']) $nagruzkaData[$chairId]['assigned'] = 0;
          if (!$nagruzkaData[$chairId]['total']) $nagruzkaData[$chairId]['total'] = 0;

          // Обновляем данные в nagruzkaData для этой кафедры

          safeAdd($nagruzkaData[$chairId]['assigned'], $amount);
          safeAdd($nagruzkaData[$chairId]['total'], $amount);

          // Не уверен, что КСРО может быть на английском
          if ($row['UID_Language'] === '25031.945')
          {
              safeAdd($statByChair[$chairId]['assigned_english']['sum'], $amount);
              safeAdd($nagruzkaData[$chairId]['assigned_english'], $amount);
          }
            
        }
      }
    }


    protected function addAspiranturaToStats(&$nagruzkaData, &$statByChair)
    {
      global $mysqli, $_aspirantura_hours_per_student, $_aspirantura_ruk_asp_hours, $_aspirantura_ruk_soisk_hours;

      // Получаем SQL-условие для кафедры и лектора
      $chairSqlKSRO = $this->getChairSqlKSRO(); // временное, пока столбцы в таблице названы криво
      $chairSqlAspirantura = $this->getChairSqlAspirantura();
      $lecturerSql = $this->getLecturerSql();

      $aspirantura_kand_exam = GetTable('aspirantura_kand_exam', "`deleted` <> '1' $chairSqlKSRO $lecturerSql");
      $aspirantura_ruk_asp = GetTable('aspirantura_ruk_asp', "`deleted` <> '1' $chairSqlAspirantura $lecturerSql");
      $aspirantura_ruk_soisk = GetTable('aspirantura_ruk_soisk', "`deleted` <> '1' $chairSqlAspirantura $lecturerSql");

      if (!empty($aspirantura_kand_exam)) 
      {
        foreach ($aspirantura_kand_exam as $row) 
        {
          // если нет препода, то мы такую строку не учитываем
          if (empty($row['chair_id'])) continue;

          $chairId = $row['chair_id'];
          $amount = $row['students_num'] * $_aspirantura_hours_per_student;

          // Добавляем в статистику по кафедре
          if (!isset($statByChair[$chairId]['assigned']['sum'])) 
          {
              $statByChair[$chairId]['assigned']['sum'] = 0;
          }
          if (!isset($statByChair[$chairId]['total']['sum'])) 
          {
              $statByChair[$chairId]['total']['sum'] = 0;
          }

          $statByChair[$chairId]['assigned']['sum'] += $amount;
          $statByChair[$chairId]['total']['sum'] += $amount;

          // Обновляем данные в nagruzkaData для этой кафедры
          // TODO !!!
          // if (!$nagruzkaData[$chairId]) $nagruzkaData[$chairId] = [];

          if ($nagruzkaData[$chairId])
          {
            if (!$nagruzkaData[$chairId]['assigned']) $nagruzkaData[$chairId]['assigned'] = 0;
            if (!$nagruzkaData[$chairId]['total']) $nagruzkaData[$chairId]['total'] = 0;

            // Обновляем данные в nagruzkaData для этой кафедры

            safeAdd($nagruzkaData[$chairId]['assigned'], $amount);
            safeAdd($nagruzkaData[$chairId]['total'], $amount);
          }
        }
      }

      // EchoLog($nagruzkaData);
      // return;

      if (!empty($aspirantura_ruk_asp)) 
      {
        foreach ($aspirantura_ruk_asp as $row) 
        {
          // если нет препода, то мы такую строку не учитываем
          if (empty($row['lecturer_chair_id'])) continue;

          $chairId = $row['lecturer_chair_id'];
          $amount = $_aspirantura_ruk_asp_hours / 2;

          // Добавляем в статистику по кафедре
          if (!isset($statByChair[$chairId]['assigned']['sum'])) 
          {
              $statByChair[$chairId]['assigned']['sum'] = 0;
          }
          if (!isset($statByChair[$chairId]['total']['sum'])) 
          {
              $statByChair[$chairId]['total']['sum'] = 0;
          }

          if ($row['lecturer_uid'])
          {
            $statByChair[$chairId]['assigned']['sum'] += $amount;
          }

          $statByChair[$chairId]['total']['sum'] += $amount;

          // Обновляем данные в nagruzkaData для этой кафедры
          // TODO !!!
          // if (!$nagruzkaData[$chairId]) $nagruzkaData[$chairId] = [];

          if ($nagruzkaData[$chairId])
          {
            if (!$nagruzkaData[$chairId]['assigned']) $nagruzkaData[$chairId]['assigned'] = 0;
            if (!$nagruzkaData[$chairId]['total']) $nagruzkaData[$chairId]['total'] = 0;

            // Обновляем данные в nagruzkaData для этой кафедры

            if ($row['lecturer_uid'])
            {
              safeAdd($nagruzkaData[$chairId]['assigned'], $amount);
            }

            safeAdd($nagruzkaData[$chairId]['total'], $amount);
          }
        }
      }


      if (!empty($aspirantura_ruk_soisk)) 
      {
        foreach ($aspirantura_ruk_soisk as $row) 
        {
          // если нет препода, то мы такую строку не учитываем
          if (empty($row['lecturer_chair_id'])) continue;

          $chairId = $row['lecturer_chair_id'];
          $amount = $_aspirantura_ruk_soisk_hours / 2;

          // Добавляем в статистику по кафедре
          if (!isset($statByChair[$chairId]['assigned']['sum'])) 
          {
              $statByChair[$chairId]['assigned']['sum'] = 0;
          }
          if (!isset($statByChair[$chairId]['total']['sum'])) 
          {
              $statByChair[$chairId]['total']['sum'] = 0;
          }

          if ($row['lecturer_uid'])
          {
            $statByChair[$chairId]['assigned']['sum'] += $amount;
          }
          $statByChair[$chairId]['total']['sum'] += $amount;

          // Обновляем данные в nagruzkaData для этой кафедры
          // TODO !!!
          // if (!$nagruzkaData[$chairId]) $nagruzkaData[$chairId] = [];
          if ($nagruzkaData[$chairId])
          {
            if (!$nagruzkaData[$chairId]['assigned']) $nagruzkaData[$chairId]['assigned'] = 0;
            if (!$nagruzkaData[$chairId]['total']) $nagruzkaData[$chairId]['total'] = 0;

            // Обновляем данные в nagruzkaData для этой кафедры

            if ($row['lecturer_uid'])
            {
              safeAdd($nagruzkaData[$chairId]['assigned'], $amount);
            }
            
            safeAdd($nagruzkaData[$chairId]['total'], $amount);
          }
        }
      }

    }

    /**
     * Получить SQL-условие для кафедры (для аспирантских таблиц)
     */
    protected function getChairSqlAspirantura()
    {   
      // если есть кафедра в GET, то она, наверно, приоритетнее
      if ($this->chairId) 
      {
        return "AND `lecturer_chair_id` = '{$this->chairId}'";
      }

      // роль декана, его кафедры
      if ($this->chairUIDs)
      {
        $chairIds = JoinArrayElements($this->chairIds, ", ", false, "'", "'");

        return "AND `lecturer_chair_id` IN($chairIds)";
      }

      return '';
    }

    /**
     * Получить SQL-условие для кафедры (для КСРО)
     */
    protected function getChairSqlKSRO()
    {   
      // если есть кафедра в GET, то она, наверно, приоритетнее
      if ($this->chairId) 
      {
        return "AND `chair_id` = '{$this->chairId}'";
      }

      // роль декана, его кафедры
      if ($this->chairUIDs)
      {
        $chairIds = JoinArrayElements($this->chairIds, ", ", false, "'", "'");

        return "AND `chair_id` IN($chairIds)";
      }

      return '';
    }

    /**
     * Получить SQL-условие для преподавателя (для КСРО)
     */
    protected function getLecturerSql()
    {
        if ($this->lecturerUid) 
        {
            return "AND `lecturer_uid` = '{$this->lecturerUid}'";
        }
        return '';
    }


}
