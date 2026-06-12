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
        // EchoLog($this->userRole);
        
        // EchoLog($this->session['c_chair_id']);
        // Завкаф видит свою кафедру, УОУП видит все, сотрудник видит только если есть lecturer_uid
        if ($this->userRole === 'zavkaf') 
        {
            return !empty($this->session['c_chair_id']);
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

    protected function applyExtraUoupTransformations(&$nagruzkaData, &$statByChair)
    {
        // Добавляем данные КСРО из таблицы ksro для статистики (Только в FillingMode)
        $this->addKsroToStats($nagruzkaData, $statByChair);
    }

    /**
     * Добавить данные КСРО в статистику (?) (для УОУП)
        В режиме заполнения в группировку по кафедрам для #/uoup_nagruzka нужно взять данные из таблицы ksro 
        ! Т.к. в таблице ksro нет таких полей как название кафедры, факультета, то отображение этих данных в зелёной таблице у УОУП полагается на другие виды нагрузки в $NagruzkaByBaseUID1. Т.е. если не будет других видов нагрузки, то КСРО не будет отображаться в таблице; 
     */
    protected function addKsroToStats(&$nagruzkaData, &$statByChair)
    {
        global $mysqli;

        // Получаем SQL-условие для кафедры
        $chairSql = $this->getChairSqlForKsro();
        $lecturerSql = $this->getLecturerSqlForKsro();

        // EchoLog($lecturerSql);

        $rows = GetTable('ksro', "$chairSql $lecturerSql");

        if (!empty($rows)) {
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

    /**
     * Получить SQL-условие для кафедры (для КСРО)
     */
    protected function getChairSqlForKsro()
    {
        if ($this->chairId) {
            return "`chair_id` = '{$this->chairId}'";
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
