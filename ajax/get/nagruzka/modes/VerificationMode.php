<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Выверка"
 * Завкаф видит всё в read-only, но может отправлять запросы в УОУП
 * УОУП может редактировать привязки ППС
 * Сплиты очищаются при синхронизации, берём данные из Галактики
 */
class VerificationMode extends BaseNagruzkaProvider
{   
    // Возможно, править также в FillingMode
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
        // Редактировать привязки ППС может только УОУП
        return $this->userRole === 'uoup';
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

    // protected function getModeSpecificSql()
    // {
    //     // return ""; // Никаких дополнительных исключений по SQL нет
    //     return "AND `nagruzka_type` <> 'ksro'";
    // }

    protected function applyModeSplits($nagruzkaData)
    {
        // В режиме выверки приоритет сплитов над Галактикой
        return $this->processSplits($nagruzkaData, 'mode_verification');
    }

    protected function getExtraResponseData()
    {
        return [
            'can_edit_bindings' => $this->userRole === 'uoup',
            'can_send_requests' => $this->userRole === 'zavkaf'
        ];
    }
}
