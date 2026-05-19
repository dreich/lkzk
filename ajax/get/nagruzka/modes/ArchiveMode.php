<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Выверка"
 * Завкаф видит всё в read-only, но может отправлять запросы в УОУП
 * УОУП может редактировать привязки ППС
 * Сплиты очищаются при синхронизации, берём данные из Галактики
 */
class ArchiveMode extends BaseNagruzkaProvider
{
    public function canView()
    {
        if ($this->userRole === 'zavkaf') return !empty($this->session['c_chair_id']);
        if ($this->userRole === 'uoup') return true;
        if ($this->userRole === 'sotrudnik') return !empty($this->lecturerUid);
        return false;
    }

    public function canEdit()
    {
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
        return "";
    }

    protected function applyModeSplits($nagruzkaData)
    {
        return $this->processSplits($nagruzkaData, 'mode_archive');
    }

    protected function getExtraResponseData()
    {
        // Строго сохраняем вашу ролевую логику
        return [
            'can_edit_bindings' => $this->userRole === 'uoup',
            'can_send_requests' => $this->userRole === 'zavkaf'
        ];
    }
}
