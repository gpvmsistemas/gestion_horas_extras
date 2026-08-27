<?php
// SyncModel — sincronización vía API Relojes (JWT) → marcaciones_cache + clock_events

class SyncModel {
    private $db;
    private $userModel;
    private $scheduleModel;
    private $marcacionesCache;
    private $clockApi;
    private $legajoNameCache = [];
    private $legajoMapPreloaded = false;

    public function __construct(){
        $this->db = new Database;
        $this->userModel = new User();
        $this->scheduleModel = new Schedule();
        $this->marcacionesCache = new MarcacionesCache();
        $this->clockApi = new ClockApiClient();
    }

    /** @deprecated */
    public function runSyncForClock($clockConfig, $startDate, $endDate) {
        $events = [];
        switch ($clockConfig['type']) {
            case 'hikvision':
                $events = $this->_syncHikvision($clockConfig, $startDate, $endDate);
                break;
            case 'anviz':
                $events = $this->_syncAnviz($clockConfig, $startDate, $endDate);
                break;
        }

        if ($events === false) {
            return ['success' => false, 'message' => 'Error al obtener datos del reloj: ' . $clockConfig['name']];
        }

        return $this->processAndSaveData($events, $startDate, $endDate);
    }

    private function _syncHikvision($clockConfig, $startDate, $endDate) {
        $apiUrl = 'http://' . $clockConfig['ip'] . '/ISAPI/AccessControl/AcsEvent?format=json';
        $username = $clockConfig['user'];
        $password = $clockConfig['pass'];

        $startTime = $startDate . 'T00:00:00-03:00';
        $endTime = $endDate . 'T23:59:59-03:00';

        $allEvents = [];
        $searchResultPosition = 0;
        $maxResults = 500;
        $totalMatches = 0;
        $searchID = uniqid();

        do {
            $requestBody = json_encode([
                'AcsEventCond' => [
                    'searchID' => $searchID, 'searchResultPosition' => $searchResultPosition,
                    'maxResults' => $maxResults, 'major' => 5, 'minor' => 75,
                    'startTime' => $startTime, 'endTime' => $endTime
                ]
            ]);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => "$username:$password",
                CURLOPT_HTTPAUTH => CURLAUTH_DIGEST, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_HTTPHEADER => array('Content-Type: application/json')
            ));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode != 200 || !$response) {
                return false;
            }

            $responseData = json_decode($response, true);
            
            if (isset($responseData['AcsEvent']['InfoList'])) {
                $eventsInBatch = $responseData['AcsEvent']['InfoList'];
                $allEvents = array_merge($allEvents, $eventsInBatch);
                $numOfMatches = isset($responseData['AcsEvent']['numOfMatches']) ? $responseData['AcsEvent']['numOfMatches'] : 0;
                $totalMatches = isset($responseData['AcsEvent']['totalMatches']) ? $responseData['AcsEvent']['totalMatches'] : 0;
                $searchResultPosition += $numOfMatches;
            } else {
                break;
            }

        } while ($searchResultPosition < $totalMatches && $totalMatches > 0);

        return $allEvents;
    }

    private function _syncAnviz($clockConfig, $startDate, $endDate) {
        $dbPath = $clockConfig['db_path'];
        if (!file_exists($dbPath)) {
            return false;
        }

        try {
            $anvizDb = new PDO('sqlite:' . $dbPath);
            $anvizDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $query = "SELECT id, USERID, CHECKTIME FROM CHECKINOUT WHERE CHECKTIME BETWEEN :start_date AND :end_date";
            
            $stmt = $anvizDb->prepare($query);
            $stmt->execute([
                ':start_date' => $startDate . ' 00:00:00',
                ':end_date' => $endDate . ' 23:59:59'
            ]);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $anvizDb = null;

            $standardizedEvents = [];
            foreach ($results as $row) {
                $standardizedEvents[] = [
                    'serialNo' => 'anviz-' . $row['id'],
                    'employeeNoString' => (string)$row['USERID'],
                    'time' => $row['CHECKTIME']
                ];
            }

            return $standardizedEvents;

        } catch (PDOException $e) {
            return false;
        }
    }

    private function _callApi($startDate, $endDate, $employeeID = null, $deviceName = null) {
        return $this->clockApi->getMarcaciones($startDate, $endDate, $employeeID, $deviceName);
    }

    /**
     * Mapa codigo_reloj → nombreApellido (GET /api/v1/legajos, una sola petición).
     */
    private function _preloadLegajoMap() {
        if ($this->legajoMapPreloaded) {
            return;
        }
        $this->legajoMapPreloaded = true;
        $this->legajoNameCache = array_merge(
            $this->legajoNameCache,
            $this->clockApi->buildLegajoCodigoMap()
        );
    }

    private function _resolveLegajoName($employeeId) {
        $empId = trim((string)$employeeId);
        if ($empId === '') {
            return '';
        }
        if (array_key_exists($empId, $this->legajoNameCache)) {
            return $this->legajoNameCache[$empId];
        }

        $name = $this->clockApi->getLegajoNombreByCodigo($empId);
        if ($name !== null && $name !== '') {
            $this->legajoNameCache[$empId] = $name;
            return $name;
        }

        $this->_preloadLegajoMap();
        $name = $this->legajoNameCache[$empId] ?? '';
        $this->legajoNameCache[$empId] = $name;
        return $name;
    }

    /** Aplica nombreApellido de API 0.2.0 y completa vacíos desde usuarios/legajos. */
    private function _enrichMarcacionesPersonNames(array &$marcaciones) {
        foreach ($marcaciones as &$m) {
            $display = ClockApiClient::marcacionPersonName($m);
            if ($display !== '') {
                $m['personName'] = $display;
                continue;
            }

            $empId = (string)($m['employeeID'] ?? '');
            if ($empId === '') {
                continue;
            }

            $user = $this->userModel->findUserByClockId($empId, $m['deviceName'] ?? null);
            if ($user && !empty($user->full_name)) {
                $m['personName'] = $user->full_name;
                continue;
            }

            $fromLegajo = $this->_resolveLegajoName($empId);
            if ($fromLegajo !== '') {
                $m['personName'] = $fromLegajo;
            }
        }
        unset($m);
    }

    private function _formatDescargaInfoMessage($descargaInfo) {
        if (empty($descargaInfo) || !is_array($descargaInfo)) {
            return '';
        }
        $parts = [];
        if (!empty($descargaInfo['mensaje'])) {
            $parts[] = $descargaInfo['mensaje'];
        }
        if (!empty($descargaInfo['relojes']) && is_array($descargaInfo['relojes'])) {
            $parts[] = 'Relojes: ' . implode(', ', $descargaInfo['relojes']);
        }
        return $parts ? ' ' . implode('. ', $parts) : '';
    }

    /**
     * Actualiza person_name en caché local cuando ya existía el registro pero sin nombre.
     */
    private function _backfillCachePersonNames(array $marcaciones) {
        $byKey = [];

        foreach ($marcaciones as $m) {
            $empId = (string)($m['employeeID'] ?? '');
            if ($empId === '') {
                continue;
            }

            $name = ClockApiClient::marcacionPersonName($m);
            if ($name === '') {
                $name = $this->_resolveLegajoName($empId);
            }
            if ($name === '') {
                continue;
            }

            $device = (string)($m['deviceName'] ?? '');
            $byKey[$empId . '|' . $device] = ['employee_id' => $empId, 'device_name' => $device, 'name' => $name];

            $serial = isset($m['id']) ? 'ext_' . (string)$m['id'] : '';
            if ($serial !== '') {
                $this->marcacionesCache->updatePersonNameIfEmpty($serial, $name);
            }
        }

        foreach ($byKey as $row) {
            $this->marcacionesCache->updatePersonNameIfEmptyByEmployee(
                $row['employee_id'],
                $row['name'],
                $row['device_name'] !== '' ? $row['device_name'] : null
            );
        }
    }

    private function _backfillCacheFromLegajoMap() {
        $this->_preloadLegajoMap();
        foreach ($this->legajoNameCache as $empId => $name) {
            if ($name === '') {
                continue;
            }
            $this->marcacionesCache->updatePersonNameIfEmptyByEmployee($empId, $name);
        }
    }

    /**
     * Guarda todas las marcaciones en marcaciones_cache (mapeadas o no).
     */
    public function saveMarcacionesToCache(array $marcaciones, $syncBatchId) {
        $newCount = 0;
        $notMapped = [];
        $deviceCatalog = new ClockDevice();

        foreach ($marcaciones as $m) {
            $empId    = (string)($m['employeeID'] ?? '');
            $dateTime = $m['authDateTime'] ?? null;
            if (empty($empId) || empty($dateTime)) {
                continue;
            }
            $deviceCatalog->getOrCreate($m['deviceName'] ?? '');

            $serial = 'ext_' . (string)($m['id'] ?? uniqid());
            $personName = ClockApiClient::marcacionPersonName($m);
            if ($this->marcacionesCache->existsBySerial($serial)) {
                if ($personName !== '') {
                    $this->marcacionesCache->updatePersonNameIfEmpty($serial, $personName);
                }
                continue;
            }

            $user = $this->userModel->findUserByClockId($empId, $m['deviceName'] ?? null);
            if (!$user) {
                $name = ClockApiClient::marcacionPersonName($m);
                $notMapped[$empId] = $name !== '' ? "{$name} (ID: {$empId})" : "Legajo {$empId}";
            }

            try {
                $dt = new DateTime($dateTime);
            } catch (Exception $e) {
                continue;
            }

            $this->marcacionesCache->insert([
                'api_event_id'     => isset($m['id']) ? (int)$m['id'] : null,
                'employee_id'      => $empId,
                'person_name'      => $personName !== '' ? $personName : null,
                'user_id'          => $user ? $user->id : null,
                'event_time'       => $dt->format('Y-m-d H:i:s'),
                'device_name'      => $m['deviceName'] ?? null,
                'direction'        => $m['direction'] ?? null,
                'direction_label'  => $m['direction_label'] ?? null,
                'event_serial_no'  => $serial,
                'sync_batch_id'    => $syncBatchId,
            ]);
            $newCount++;
        }

        return ['count' => $newCount, 'not_mapped' => $notMapped];
    }

    public function processAndSaveData($events, $startDate, $endDate) {
        if (empty($events)) {
            return ['success' => true, 'message' => 'No se encontraron nuevas marcaciones mapeadas.', 'count' => 0];
        }

        $affectedUserDates = [];
        $newEventsCount = 0;
        $syncBatchId = uniqid('sync_');

        if (is_array($events)) {
            foreach ($events as $event) {
                if (isset($event['serialNo'])) {
                    $serialNo   = $event['serialNo'];
                    $deviceName = $event['device_name'] ?? null;

                    if (!$this->scheduleModel->doesEventExistBySerial($serialNo)) {
                        $clockId = $event['employeeNoString'];
                        $user = $this->userModel->findUserByClockId($clockId, $deviceName);
                        
                        if ($user) {
                            $dateTime  = new DateTime($event['time']);
                            $eventTime = $dateTime->format('Y-m-d H:i:s');
                            $direction      = $event['direction']       ?? null;
                            $directionLabel = $event['direction_label'] ?? null;
                            
                            $this->scheduleModel->insertClockEvent($user->id, $clockId, $eventTime, $syncBatchId, $serialNo, $deviceName, $direction, $directionLabel);
                            $newEventsCount++;
                            
                            $date = $dateTime->format('Y-m-d');
                            $affectedUserDates[$user->id][$date] = true;
                        }
                    }
                }
            }
        }

        foreach ($affectedUserDates as $userId => $dates) {
            foreach (array_keys($dates) as $date) {
                $this->scheduleModel->recalcDayScheduleForUser($userId, $date);
            }
        }
        
        $message = "Horarios actualizados: {$newEventsCount} marcación(es) de empleados mapeados.";
        return ['success' => true, 'message' => $message, 'count' => $newEventsCount];
    }

    public function getApiEmployees($startDate, $endDate) {
        $result = $this->_callApi($startDate, $endDate);
        if (isset($result['error'])) {
            return $result['error'];
        }

        $marcaciones = $result['marcaciones'];
        $this->_enrichMarcacionesPersonNames($marcaciones);
        $employees   = [];

        foreach ($marcaciones as $m) {
            $empId = (string)($m['employeeID'] ?? '');
            if (empty($empId)) continue;

            $employeeKey = $empId . '|' . (string)($m['deviceName'] ?? '');
            if (!isset($employees[$employeeKey])) {
                $employees[$employeeKey] = [
                    'employeeID' => $empId,
                    'personName' => ClockApiClient::marcacionPersonName($m),
                    'devices'    => [],
                    'count'      => 0,
                    'lastSeen'   => null,
                ];
            }

            $employees[$employeeKey]['count']++;

            $dn = $m['deviceName'] ?? null;
            if ($dn && !in_array($dn, $employees[$employeeKey]['devices'])) {
                $employees[$employeeKey]['devices'][] = $dn;
            }

            $date = !empty($m['authDate']) ? $m['authDate'] : substr($m['authDateTime'] ?? '', 0, 10);
            if ($date > ($employees[$employeeKey]['lastSeen'] ?? '')) {
                $employees[$employeeKey]['lastSeen'] = $date;
            }

            $pn = ClockApiClient::marcacionPersonName($m);
            if ($pn !== '' && ($employees[$employeeKey]['personName'] ?? '') === '') {
                $employees[$employeeKey]['personName'] = $pn;
            }
        }

        // Incluir legajos del catálogo API aunque no hayan fichado en el período
        $this->_preloadLegajoMap();
        foreach ($this->legajoNameCache as $codigo => $nombre) {
            $codigo = trim((string)$codigo);
            $nombre = trim((string)$nombre);
            if ($codigo === '') {
                continue;
            }
            if (!isset($employees[$codigo])) {
                $employees[$codigo] = [
                    'employeeID'  => $codigo,
                    'personName'  => $nombre,
                    'devices'     => [],
                    'count'       => 0,
                    'lastSeen'    => null,
                    'legajo_only' => true,
                ];
            } elseif (($employees[$codigo]['personName'] ?? '') === '' && $nombre !== '') {
                $employees[$codigo]['personName'] = $nombre;
            }
        }

        foreach ($employees as &$emp) {
            if (($emp['personName'] ?? '') === '') {
                $fromLegajo = $this->_resolveLegajoName($emp['employeeID']);
                if ($fromLegajo !== '') {
                    $emp['personName'] = $fromLegajo;
                }
            }
            $emp['mapped_user'] = $this->userModel->findUserByClockId($emp['employeeID'], $emp['devices'][0] ?? null);
        }
        unset($emp);

        usort($employees, function($a, $b) {
            $am = $a['mapped_user'] ? 1 : 0;
            $bm = $b['mapped_user'] ? 1 : 0;
            if ($am !== $bm) return $am - $bm;
            return strcmp($a['personName'], $b['personName']);
        });

        return array_values($employees);
    }

    public function syncFromExternalApi($startDate, $endDate, $employeeID = null, $companyId = null) {
        $result = $this->_callApi($startDate, $endDate, $employeeID);
        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error'], 'count' => 0, 'cache_count' => 0, 'not_mapped' => []];
        }

        $marcaciones = $result['marcaciones'];
        $this->_enrichMarcacionesPersonNames($marcaciones);

        if (empty($marcaciones)) {
            $msg = 'La API no devolvió marcaciones para el período seleccionado.';
            $msg .= $this->_formatDescargaInfoMessage($result['descarga_info'] ?? null);
            return [
                'success' => true,
                'message' => $msg,
                'count' => 0,
                'cache_count' => 0,
                'not_mapped' => [],
            ];
        }

        $syncBatchId = uniqid('sync_');
        $cacheResult = $this->saveMarcacionesToCache($marcaciones, $syncBatchId);
        $this->_backfillCachePersonNames($marcaciones);
        $this->_backfillCacheFromLegajoMap();

        $notMapped = [];
        foreach ($marcaciones as $m) {
            $empId = (string)($m['employeeID'] ?? '');
            if ($empId === '' || $this->userModel->findUserByClockId($empId, $m['deviceName'] ?? null)) {
                continue;
            }
            $name = ClockApiClient::marcacionPersonName($m);
            $notMapped[$empId] = $name !== '' ? "{$name} (ID: {$empId})" : "Legajo {$empId}";
        }

        $standardized = [];
        foreach ($marcaciones as $m) {
            $empId    = (string)($m['employeeID'] ?? '');
            $serial   = 'ext_' . (string)($m['id'] ?? uniqid());
            $dateTime = $m['authDateTime'] ?? null;

            if (empty($empId) || empty($dateTime)) continue;

            $user = $this->userModel->findUserByClockId($empId, $m['deviceName'] ?? null);
            if (!$user) continue;

            $standardized[] = [
                'serialNo'          => $serial,
                'employeeNoString'  => $empId,
                'time'              => $dateTime,
                'device_name'       => $m['deviceName'] ?? null,
                'direction'         => $m['direction'] ?? null,
                'direction_label'   => $m['direction_label'] ?? null,
            ];
        }

        $mappedResult = $this->processAndSaveData($standardized, $startDate, $endDate);

        $recalcDays = $this->scheduleModel->recalcSchedulesForDateRange($startDate, $endDate);

        if (!empty($companyId)) {
            try {
                $attendance = new PlanVsActualService();
                $attendance->recomputeRange((int)$companyId, $startDate, $endDate);
            } catch (Exception $e) {
                // Tabla attendance_day_summary puede no existir aún
            }
        }

        $cacheCount = $cacheResult['count'];
        $mappedCount = $mappedResult['count'];
        $message = "Importadas {$cacheCount} marcación(es) al registro general.";
        if ($mappedCount > 0) {
            $message .= " {$mappedCount} nueva(s) de empleados mapeados.";
        }
        if ($recalcDays > 0) {
            $message .= " Horarios recalculados (entrada/salida P10→P20) en {$recalcDays} día(es).";
        }
        if (!empty($notMapped)) {
            $message .= ' ' . count($notMapped) . ' legajo(s) sin mapear en el sistema.';
        }
        $message .= $this->_formatDescargaInfoMessage($result['descarga_info'] ?? null);

        return [
            'success'     => true,
            'message'     => $message,
            'count'       => $mappedCount,
            'cache_count' => $cacheCount,
            'not_mapped'  => $notMapped,
            'api_total'   => $result['total'] ?? count($marcaciones),
        ];
    }
}
?>
