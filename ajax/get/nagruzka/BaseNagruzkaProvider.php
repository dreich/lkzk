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
        $this->chairId = isset($this->getParams['chair_id']) ? $this->getParams['chair_id'] : null;
        $this->lecturerUid = isset($this->getParams['lecturer_uid']) ? $this->getParams['lecturer_uid'] : null;
        $this->isLite = !empty($this->getParams['lite']);
        $this->onlyStat = !empty($this->getParams['only_stat']);
        $this->nagruzkaType = isset($this->getParams['type']) ? $this->getParams['type'] : 'all';
        $this->globalFilter = isset($_COOKIE['global_nagruzka_filter']) ? $_COOKIE['global_nagruzka_filter'] : null;

        if ($this->lecturerUid) {
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
        if (!empty($this->userRoles['zavkaf'])) return 'zavkaf';
        if (!empty($this->userRoles['uoup'])) return 'uoup';
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

      if ($this->userRole === 'zavkaf') {
          $cChairId = isset($this->session['c_chair_id']) ? $this->session['c_chair_id'] : null;
          if ($cChairId) {
              $chair = $this->getRow('xml_chair', ['Code' => $cChairId]);
              $chairUid = isset($chair['UID']) ? $chair['UID'] : null;
          }
      } elseif (($this->userRole === 'uoup' || $this->userRole === 'sotrudnik') && $this->chairId) {
          $chair = $this->getRow('xml_chair', ['Code' => $this->chairId]);
          $chairUid = isset($chair['UID']) ? $chair['UID'] : null;
      }

      if ($chairUid) {
          return "AND xml_content_of_load.UID_Chair = '$chairUid'";
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
        // EchoLog($query);
        $rawData = GetSQL($query);
        return PrepareNagruzka($rawData);
    }

    /**
     * Обработка сплитов через SplitProcessor
     * 
     * @param array $nagruzkaData Данные нагрузки
     * @param string $mode Режим работы ('filling')
     */
    
    protected function processSplits($nagruzkaData, $mode = '')
    {
        $processor = new SplitProcessor('0');
        return $processor->applySplits($nagruzkaData, $mode);
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
        // $stat = [
        //     'assigned' => ['sum' => 0],
        //     'assigned_to_vacancy' => ['sum' => 0],
        //     'not_assigned' => ['sum' => 0],
        //     'total' => ['sum' => 0]
        // ];
        $statByChair = [];

        foreach ($nagruzkaData as $baseUid => $item) {
            if (empty($item['lectors'])) {
                continue;
            }

            $hasAssigned = false;
            $hasVacancy = false;
            $hasNotAssigned = false;

            foreach ($item['lectors'] as &$lector) {
                $lector['delete'] = !empty($lector['delete']);
                if ($lector['delete']) continue;

                // Вакансия
                if ($lector['lecturer_fio'] && mb_strcasecmp($lector['lecturer_fio'], 'Вакансия') === 0) {
                    $hasVacancy = true;
                    $stat['assigned_to_vacancy']['sum'] += (float) $lector['Amount'];
                    $statByChair[$lector['chair_id']]['assigned_to_vacancy']['sum'] += (float) $lector['Amount'];
                }
                // Назначен преподаватель
                elseif ($lector['lecturer_fio'] && mb_strcasecmp($lector['lecturer_fio'], 'Вакансия') !== 0) {
                    $hasAssigned = true;
                    $stat['assigned']['sum'] += (float) $lector['Amount'];
                    $statByChair[$lector['chair_id']]['assigned']['sum'] += (float) $lector['Amount'];
                }
                // Не назначен
                else {
                    $hasNotAssigned = true;
                    $stat['not_assigned']['sum'] += (float) $lector['Amount'];
                    $statByChair[$lector['chair_id']]['not_assigned']['sum'] += (float) $lector['Amount'];
                }
            }

            $nagruzkaData[$baseUid]['assigned'] = $hasAssigned;
            $nagruzkaData[$baseUid]['assigned_to_vacancy'] = $hasVacancy;
            $nagruzkaData[$baseUid]['not_assigned'] = $hasNotAssigned;

            $stat['total']['sum'] += $item['Amount'];
            $statByChair[$item['chair_id']]['total']['sum'] += $item['Amount'];
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
        if (empty($this->lecturerUid)) {
            return; // Убрать $nagruzkaData
        }

        foreach ($nagruzkaData as $baseUid => $item) {
            $filteredLectors = array_filter($item['lectors'], function($lector) {
                return $lector['lecturer_uid'] === $this->lecturerUid;
            });

            if (empty($filteredLectors)) {
                unset($nagruzkaData[$baseUid]);
            } else {
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
        if (empty($this->globalFilter)) {
            return; // Убрать $nagruzkaData
        }

        // Переписываем array_filter на in-place модификацию
        foreach ($nagruzkaData as $key => $item) {
            if (empty($item[$this->globalFilter])) {
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

            if (!isset($result[$chairId])) {
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

    /**
     * Абстрактные методы для реализации в конкретных режимах
     */
    abstract public function canView();
    abstract public function canEdit();
    abstract public function getData();
    abstract public function getNagruzkaTypeFilter();
}
