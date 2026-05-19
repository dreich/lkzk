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
    public function canView()
    {
        // Все роли могут просматривать
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
        // Редактировать привязки ППС может только УОУП
        return $this->userRole === 'uoup';
    }

    public function getNagruzkaTypeFilter()
    {
        return $this->nagruzkaType ?: 'all';
    }

    protected function canAccessData()
    {
        return !($this->userRole === 'sotrudnik' && empty($this->lecturerUid));
    }

    protected function getModeSpecificSql()
    {
        return ""; // Никаких дополнительных исключений по SQL нет
    }

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
