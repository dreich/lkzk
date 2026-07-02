<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Заполнение"
 * Данные по нагрузке доступны
 * Привязки из Галактики игнорируются, работают только сплиты
 */
class FillingMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        global $_SERVER;

        // EchoLog($this->userRole);
        
        // EchoLog($this->session['c_chair_id']);

        if ($this->userRole === 'dean') 
        {
            return !empty($this->session['c_department_id']);
        }

        // Завкаф видит свою кафедру, УОУП видит все, сотрудник видит только если есть lecturer_uid
        if ($this->userRole === 'zavkaf') 
        {
            return !empty($this->session['c_chair_id']);
        }

        if ($_SERVER['REMOTE_ADDR'] == '85.143.4.44')
        {
            // EchoLog($this->userRole);
        }

        if ($this->userRole === 'uoup' || $this->userRole === 'ruk_aspirantura') 
        {
            return true;
        }

        if ($this->userRole === 'sotrudnik') 
        {
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

    
    protected function canAccessData()
    {
        return !($this->userRole === 'sotrudnik' && empty($this->lecturerUid));
    }

    protected function getModeSpecificSql()
    {
        // В режиме заполнения исключаем КСРО из основного запроса
        return "AND `nagruzka_type` <> 'ksro'";
    }

    protected function applyModeSplits($nagruzkaData)
    {
        // Очищаем лекторов и применяем сплиты
        return $this->processSplits($nagruzkaData, 'mode_filling');
    }

    protected function getExtraResponseData()
    {
        return [];
    }

    // для УОУП и декана добавить в статистику КСРО и аспирантуру (ручную, т.е. без nagruzka_itog_exam: из таблиц aspirantura_*)
    protected function applyExtraUoupTransformations(&$nagruzkaData, &$statByChair)
    {
        // Добавляем данные КСРО из таблицы ksro для статистики (Только в FillingMode)
        $this->addKsroToStats($nagruzkaData, $statByChair);

        // Добавляем данные аспирантуры из таблиц aspirantura_* для статистики (Только в FillingMode)
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
          $amount = $_aspirantura_ruk_asp_hours;

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
          $amount = $_aspirantura_ruk_soisk_hours;

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
