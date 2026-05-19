<?

include_once __DIR__ . '/../BaseNagruzkaProvider.php';

/**
 * Режим "Закрыто"
 * Галактика формирует первичные данные
 * У завкафов ничего не показывается, УОУП видит read-only
 */
class ClosedMode extends BaseNagruzkaProvider
{
    // ВОЗВРАЩЕНО ВАШЕ ОРИГИНАЛЬНОЕ УСЛОВИЕ
    public function canView()
    {
        // Завкаф ничего не видит в этом режиме
        if ($this->userRole === 'zavkaf') {
            return false;
        }

        // УОУП видит для контроля
        if ($this->userRole === 'uoup') {
            return true;
        }

        // Сотрудник не видит
        if ($this->userRole === 'sotrudnik') {
            return false;
        }

        return false;
    }

    public function canEdit()
    {
        return false;
    }

    public function getNagruzkaTypeFilter()
    {
        return $this->nagruzkaType ?: 'all';
    }

    protected function canAccessData()
    {
        // Здесь мы оставляем true для uoup, чтобы работал основной пайплайн,
        // а zavkaf и sotrudnik будут перехвачены ниже в getData()
        return $this->userRole === 'uoup'; 
    }

    /**
     * Перехватываем getData() для возврата кастомного сообщения нужным ролям
     */
    public function getData()
    {
        // Завкафы и сотрудники имеют право "видеть" страницу (canView = true), 
        // но вместо данных получают сообщение о закрытии:
        if ($this->userRole === 'zavkaf' || $this->userRole === 'sotrudnik') {
            return [
                'nagruzka' => [],
                'stat' => new stdClass(),
                'lecturer_fio' => null,
                'message' => 'Подготовка данных для распределения нагрузки',
                'system_closed' => true
            ];
        }

        // Для УОУП запускаем стандартный пайплайн
        return parent::getData();
    }

    protected function getModeSpecificSql()
    {
        return "";
    }

    protected function applyModeSplits($nagruzkaData)
    {
        // В режиме закрыто сплиты не применяются - показываем чистые данные из Галактики
        $result = [];
        
        foreach ($nagruzkaData as $baseUid2 => $item) {
            $baseUid = $item['base_uid'];

            if (!isset($result[$baseUid])) {
                $result[$baseUid] = $item;
                $result[$baseUid]['lectors'] = [];
            } else {
                $result[$baseUid]['Amount'] += $item['Amount'];
            }

            // Добавляем лектора из Галактики
            $result[$baseUid]['lectors'][] = $item;
        }

        return $result ? $result : [];
    }

    protected function getExtraResponseData()
    {
        return [
            'read_only' => true
        ];
    }
}
