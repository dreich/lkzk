<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Заполнение"
 * Данные по нагрузке доступны
 * Привязки из Галактики игнорируются, работают только сплиты
 */
class FillingMode extends BaseNagruzkaProvider
{ 
    // Возможно, править также в VerificationMode
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

    
}
