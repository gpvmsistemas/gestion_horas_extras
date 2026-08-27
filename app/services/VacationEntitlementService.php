<?php

class VacationEntitlementService {
    private $db;
    private $agreementModel;
    private $balanceModel;
    private $userModel;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
        $this->agreementModel = new CollectiveAgreement($this->db);
        $this->balanceModel = new VacationBalance($this->db);
        $this->userModel = new User($this->db);
    }

    public function isReady() {
        return $this->agreementModel->isReady();
    }

    public function getEffectiveAgreement($user) {
        if (!$user) {
            return null;
        }
        if (!empty($user->agreement_id)) {
            return $this->agreementModel->getById((int)$user->agreement_id);
        }
        if (!empty($user->area_id)) {
            $area = (new Area($this->db))->getById((int)$user->area_id);
            if ($area && !empty($area->agreement_id)) {
                return $this->agreementModel->getById((int)$area->agreement_id);
            }
        }
        if (!empty($user->company_id)) {
            return $this->agreementModel->getDefaultForCompany((int)$user->company_id);
        }
        return null;
    }

    /**
     * Alertas de vacaciones para dashboard RRHH (sin hire_date, convenio, saldo bajo).
     */
    public function getCompanyVacationAlerts($companyId) {
        if (!$this->isReady()) {
            return ['ready' => false, 'no_hire_date' => [], 'no_agreement' => [], 'low_balance' => []];
        }
        $employees = $this->userModel->getActiveEmployeesForVacationLiquidation((int)$companyId);
        $noHire = [];
        $noAgreement = [];
        $lowBalance = [];
        foreach ($employees as $emp) {
            if (empty($emp->hire_date)) {
                $noHire[] = $emp;
                continue;
            }
            if (!$this->getEffectiveAgreement($emp)) {
                $noAgreement[] = $emp;
            }
            $pending = $this->balanceModel->getTotalPending((int)$emp->id);
            if ($pending > 0 && $pending < 3) {
                $lowBalance[] = (object)['user' => $emp, 'pending' => $pending];
            }
        }
        return [
            'ready' => true,
            'no_hire_date' => $noHire,
            'no_agreement' => $noAgreement,
            'low_balance' => $lowBalance,
        ];
    }

    public function getEffectiveAgreementId($userId) {
        $user = $this->userModel->getUserById((int)$userId);
        $ag = $this->getEffectiveAgreement($user);
        return $ag ? (int)$ag->id : 0;
    }

    public function getSeniorityMonths($userId, $asOfDate = null) {
        $user = $this->userModel->getUserById((int)$userId);
        if (!$user || empty($user->hire_date)) {
            return 0;
        }
        return $this->seniorityMonthsBetween($user->hire_date, $asOfDate);
    }

    public function seniorityMonthsBetween($hireDate, $asOfDate = null) {
        if (empty($hireDate)) {
            return 0;
        }
        $asOf = $asOfDate ? new DateTime($asOfDate) : new DateTime();
        $hire = new DateTime($hireDate);
        if ($hire > $asOf) {
            return 0;
        }
        $diff = $hire->diff($asOf);
        return ($diff->y * 12) + $diff->m;
    }

    /**
     * Calcula días que corresponden según convenio y antigüedad (sin guardar en BD).
     * @param int $userId
     * @param array{hire_date?:string,agreement_id?:int,as_of_date?:string,period_label?:string} $params
     */
    public function calculatePreview($userId, array $params = []) {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Módulo de vacaciones no instalado.'];
        }
        $user = $this->userModel->getUserById((int)$userId);
        if (!$user) {
            return ['ok' => false, 'message' => 'Usuario no encontrado.'];
        }

        $hireDate = trim($params['hire_date'] ?? $user->hire_date ?? '');
        if ($hireDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
            return ['ok' => false, 'message' => 'Indicá la fecha de ingreso formal para calcular.'];
        }

        $previewUser = clone $user;
        $previewUser->hire_date = $hireDate;
        if (array_key_exists('agreement_id', $params)) {
            $aid = (int)$params['agreement_id'];
            $previewUser->agreement_id = $aid > 0 ? $aid : null;
        }

        $agreement = $this->getEffectiveAgreement($previewUser);
        if (!$agreement) {
            return ['ok' => false, 'message' => 'Sin convenio (asigná uno al empleado o default en la empresa).'];
        }

        $refDate = trim($params['as_of_date'] ?? '') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $refDate)) {
            $refDate = date('Y-m-d');
        }

        $requestedLabel = trim($params['period_label'] ?? '');
        $year = preg_match('/^\d{4}$/', $requestedLabel)
            ? (int)$requestedLabel
            : (int)date('Y', strtotime($refDate));
        $bounds = vacation_period_bounds(
            $year,
            (int)$agreement->period_start_month,
            (int)$agreement->period_start_day
        );
        if ($requestedLabel !== '') {
            $bounds['period_label'] = $requestedLabel;
        }

        $cutDate = $bounds['period_end'];
        $months = $this->seniorityMonthsBetween($hireDate, $cutDate);
        $rules = $this->agreementModel->getRules((int)$agreement->id);
        $rule = null;
        foreach ($rules as $r) {
            $min = (int)$r->min_months;
            $max = $r->max_months !== null ? (int)$r->max_months : PHP_INT_MAX;
            if ($months >= $min && $months <= $max) {
                $rule = $r;
                break;
            }
        }
        if (!$rule) {
            return [
                'ok' => false,
                'message' => 'No hay regla de vacaciones para ' . $months . ' meses de antigüedad en el convenio ' . $agreement->code . '.',
                'seniority_months' => $months,
                'agreement_code' => $agreement->code,
            ];
        }

        $years = (int)floor($months / 12);
        $remMonths = $months % 12;
        $seniorityLabel = $years > 0
            ? $years . ' año' . ($years !== 1 ? 's' : '') . ($remMonths > 0 ? ' y ' . $remMonths . ' mes' . ($remMonths !== 1 ? 'es' : '') : '')
            : $months . ' mes' . ($months !== 1 ? 'es' : '');

        $modes = vacation_day_count_modes();
        $modeLabel = $modes[$rule->day_count_mode] ?? $rule->day_count_mode;

        return [
            'ok' => true,
            'message' => 'Antigüedad al 31 de diciembre (' . date('d/m/Y', strtotime($cutDate)) . '): '
                . $seniorityLabel . '. Corresponden '
                . vacation_format_days($rule->days_entitled) . ' días en el período '
                . $bounds['period_label'] . '.',
            'seniority_months' => $months,
            'seniority_label' => $seniorityLabel,
            'agreement_code' => $agreement->code,
            'agreement_name' => $agreement->name,
            'period_label' => $bounds['period_label'],
            'period_start' => $bounds['period_start'],
            'period_end' => $bounds['period_end'],
            'days_entitled' => (float)$rule->days_entitled,
            'day_count_mode' => $rule->day_count_mode,
            'day_count_mode_label' => $modeLabel,
            'rule_notes' => $rule->notes ?? '',
            'rule_range' => (int)$rule->min_months . '–' . ($rule->max_months !== null ? (int)$rule->max_months : '∞') . ' meses',
        ];
    }

    public function getApplicableRule($userId, $asOfDate = null) {
        $user = $this->userModel->getUserById((int)$userId);
        $agreement = $this->getEffectiveAgreement($user);
        if (!$agreement) {
            return null;
        }
        $reference = $asOfDate ?: date('Y-m-d');
        $months = $this->getSeniorityMonths($userId, date('Y-12-31', strtotime($reference)));
        $rules = $this->agreementModel->getRules((int)$agreement->id);
        foreach ($rules as $rule) {
            $min = (int)$rule->min_months;
            $max = $rule->max_months !== null ? (int)$rule->max_months : PHP_INT_MAX;
            if ($months >= $min && $months <= $max) {
                return $rule;
            }
        }
        return null;
    }

    public function getPeriodBoundsForDate($userId, $referenceDate = null) {
        $user = $this->userModel->getUserById((int)$userId);
        $agreement = $this->getEffectiveAgreement($user);
        if (!$agreement) {
            return null;
        }
        $ref = $referenceDate ?: date('Y-m-d');
        return vacation_period_for_date(
            $ref,
            (int)$agreement->period_start_month,
            (int)$agreement->period_start_day
        );
    }

    /**
     * Liquida un período para un empleado (crea o actualiza vacation_balance_periods).
     * @return array{ok:bool,message:string,period_id?:int}
     */
    public function liquidatePeriod($userId, $periodLabel = null, $adminId = 0, $asOfDate = null) {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Módulo de vacaciones no instalado. Ejecutá migration_collective_agreements.sql'];
        }
        $user = $this->userModel->getUserById((int)$userId);
        if (!$user) {
            return ['ok' => false, 'message' => 'Usuario no encontrado.'];
        }
        if (empty($user->hire_date)) {
            return ['ok' => false, 'message' => 'Indicá la fecha de ingreso formal del empleado (no la de plan de prueba) antes de liquidar vacaciones.'];
        }
        $agreement = $this->getEffectiveAgreement($user);
        if (!$agreement) {
            return ['ok' => false, 'message' => 'Sin convenio asignado (empleado o empresa).'];
        }

        $bounds = $this->getPeriodBoundsForDate($userId, $asOfDate);
        if (!$bounds) {
            return ['ok' => false, 'message' => 'No se pudo calcular el período.'];
        }
        if ($periodLabel) {
            $bounds['period_label'] = $periodLabel;
        }

        $cutDate = $bounds['period_end'];
        $rule = $this->getApplicableRule($userId, $cutDate);
        if (!$rule) {
            return ['ok' => false, 'message' => 'No hay regla de vacaciones para la antigüedad del empleado.'];
        }

        $daysEntitled = (float)$rule->days_entitled;
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $existing = $this->balanceModel->getPeriodByUserLabelForUpdate($userId, $bounds['period_label'], 'annual');
            $daysTaken = $existing ? (float)$existing->days_taken : 0;
            if ($existing) {
                $difference = $daysEntitled - (float)$existing->days_entitled;
                if (!$this->balanceModel->updatePeriodBalances((int)$existing->id, $daysEntitled, $daysTaken)) {
                    throw new RuntimeException('No se pudo actualizar el período.');
                }
                $periodId = (int)$existing->id;
                if (abs($difference) > 0.001) {
                    $this->balanceModel->addMovement([
                        'period_id'=>$periodId, 'user_id'=>$userId, 'movement_type'=>'adjustment',
                        'source'=>'liquidation', 'days'=>$difference,
                        'notes'=>'Recalculo del período ' . $bounds['period_label'],
                        'created_by'=>$adminId > 0 ? $adminId : (int)($_SESSION['user_id'] ?? 0),
                    ]);
                }
            } else {
                $periodId = $this->balanceModel->createPeriod([
                'user_id' => $userId,
                'period_label' => $bounds['period_label'],
                'period_start' => $bounds['period_start'],
                'period_end' => $bounds['period_end'],
                'agreement_id' => (int)$agreement->id,
                'agreement_rule_id' => (int)$rule->id,
                'count_mode_snapshot' => $rule->day_count_mode,
                'days_entitled' => $daysEntitled,
                'days_taken' => $daysTaken,
                'status' => 'open',
                'liquidated_at' => date('Y-m-d H:i:s'),
                'liquidated_by' => $adminId > 0 ? $adminId : null,
                ]);
                if ($periodId <= 0) throw new RuntimeException('Error al crear el período de vacaciones.');
                $this->balanceModel->addMovement([
                'period_id' => $periodId,
                'user_id' => $userId,
                'movement_type' => 'accrual',
                'source' => 'liquidation',
                'days' => $daysEntitled,
                'notes' => 'Liquidación período ' . $bounds['period_label'],
                'created_by' => $adminId > 0 ? $adminId : (int)($_SESSION['user_id'] ?? 0),
                ]);
            }
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) $this->db->commit();
            return [
                'ok' => true,
                'message' => 'Período ' . $bounds['period_label'] . ' liquidado: ' . vacation_format_days($daysEntitled) . ' días corresponden.',
                'period_id' => $periodId,
            ];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false, 'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'No se pudo liquidar el período.'];
        }
    }

    /**
     * Carga inicial: define entitled/taken para un período (importación).
     */
    public function importPeriodBalance($userId, $periodLabel, $daysEntitled, $daysTaken, $adminId, $notes = '') {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Módulo de vacaciones no instalado.'];
        }
        $user = $this->userModel->getUserById((int)$userId);
        if (!$user || empty($user->hire_date)) {
            return ['ok' => false, 'message' => 'Usuario o fecha de ingreso formal faltante.'];
        }
        $agreement = $this->getEffectiveAgreement($user);
        if (!$agreement) {
            return ['ok' => false, 'message' => 'Sin convenio asignado.'];
        }

        $parts = explode('-', $periodLabel);
        $startYear = (int)($parts[0] ?? date('Y'));
        $bounds = vacation_period_bounds($startYear, (int)$agreement->period_start_month, (int)$agreement->period_start_day);
        $bounds['period_label'] = $periodLabel;

        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $existing = $this->balanceModel->getPeriodByUserLabelForUpdate($userId, $periodLabel, 'annual');
            if ($existing) {
                if (!$this->balanceModel->updatePeriodBalances((int)$existing->id, $daysEntitled, $daysTaken)) {
                    throw new RuntimeException('No se pudo actualizar el período.');
                }
                $periodId = (int)$existing->id;
            } else {
                $rule = $this->getApplicableRule($userId, $bounds['period_start']);
                $periodId = $this->balanceModel->createPeriod([
                'user_id' => $userId,
                'period_label' => $periodLabel,
                'period_start' => $bounds['period_start'],
                'period_end' => $bounds['period_end'],
                'agreement_id' => (int)$agreement->id,
                'agreement_rule_id' => $rule ? (int)$rule->id : null,
                'count_mode_snapshot' => $rule ? $rule->day_count_mode : 'calendar',
                'days_entitled' => $daysEntitled,
                'days_taken' => $daysTaken,
                'status' => 'open',
                'liquidated_at' => date('Y-m-d H:i:s'),
                'liquidated_by' => $adminId,
                ]);
                if ($periodId <= 0) throw new RuntimeException('No se pudo crear el período.');
            }

            $this->balanceModel->addMovement([
            'period_id' => $periodId,
            'user_id' => $userId,
            'movement_type' => 'import',
            'source' => 'import',
            'days' => (float)$daysEntitled - (float)$daysTaken,
            'notes' => $notes ?: 'Carga inicial ' . $periodLabel,
            'created_by' => $adminId,
            ]);
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) $this->db->commit();
            return ['ok' => true, 'message' => 'Período ' . $periodLabel . ' actualizado.', 'period_id' => $periodId];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false, 'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'No se pudo importar el período.'];
        }
    }

    public function getSummaryForUser($userId) {
        return [
            'total_pending' => $this->balanceModel->getTotalPending($userId),
            'periods' => $this->balanceModel->getPeriodsByUser($userId),
            'seniority_months' => $this->getSeniorityMonths($userId),
            'rule' => $this->getApplicableRule($userId),
            'agreement' => $this->getEffectiveAgreement($this->userModel->getUserById($userId)),
        ];
    }

    /**
     * Cuenta empleados activos listos / omitidos para liquidación masiva.
     */
    public function getBatchLiquidationPreview($companyId) {
        $employees = $this->userModel->getActiveEmployeesForVacationLiquidation($companyId);
        $ready = 0;
        $noHire = 0;
        $noAgreement = 0;
        foreach ($employees as $emp) {
            if (empty($emp->hire_date)) {
                $noHire++;
                continue;
            }
            if (!$this->getEffectiveAgreement($emp)) {
                $noAgreement++;
                continue;
            }
            $ready++;
        }
        return [
            'total_active' => count($employees),
            'ready' => $ready,
            'no_hire_date' => $noHire,
            'no_agreement' => $noAgreement,
        ];
    }

    /**
     * Liquida el período vigente para todos los empleados activos de la empresa (no inactivos / baja).
     * @return array{ok:bool,period_label:string,liquidated:int,skipped:int,failed:int,details:array,message:string}
     */
    public function liquidateCompanyBatch($companyId, $periodLabel = null, $adminId = 0) {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Módulo de vacaciones no instalado.'];
        }
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return ['ok' => false, 'message' => 'Seleccioná una empresa.'];
        }

        $employees = $this->userModel->getActiveEmployeesForVacationLiquidation($companyId);
        $resolvedLabel = $periodLabel;
        if (($resolvedLabel === null || $resolvedLabel === '') && !empty($employees)) {
            $bounds = $this->getPeriodBoundsForDate((int)$employees[0]->id);
            $resolvedLabel = $bounds['period_label'] ?? '';
        }
        if ($resolvedLabel === '') {
            $resolvedLabel = (string)date('Y');
        }

        $liquidated = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($employees as $emp) {
            $uid = (int)$emp->id;
            if (empty($emp->hire_date)) {
                $skipped++;
                $details[] = [
                    'user_id' => $uid,
                    'name' => $emp->full_name,
                    'status' => 'skipped',
                    'message' => 'Sin fecha de ingreso formal',
                ];
                continue;
            }
            if (!$this->getEffectiveAgreement($emp)) {
                $skipped++;
                $details[] = [
                    'user_id' => $uid,
                    'name' => $emp->full_name,
                    'status' => 'skipped',
                    'message' => 'Sin convenio (empleado o empresa)',
                ];
                continue;
            }

            $result = $this->liquidatePeriod($uid, $resolvedLabel, $adminId);
            if ($result['ok']) {
                $liquidated++;
                $details[] = [
                    'user_id' => $uid,
                    'name' => $emp->full_name,
                    'status' => 'ok',
                    'message' => $result['message'],
                ];
            } else {
                $failed++;
                $details[] = [
                    'user_id' => $uid,
                    'name' => $emp->full_name,
                    'status' => 'failed',
                    'message' => $result['message'],
                ];
            }
        }

        return [
            'ok' => true,
            'period_label' => $resolvedLabel,
            'liquidated' => $liquidated,
            'skipped' => $skipped,
            'failed' => $failed,
            'details' => $details,
            'message' => 'Liquidación masiva período ' . $resolvedLabel . ': '
                . $liquidated . ' empleado(s) OK, ' . $skipped . ' omitido(s), ' . $failed . ' error(es).',
        ];
    }

    public function countDaysForUserRange($userId, $startDate, $endDate) {
        $rule = $this->getApplicableRule($userId, $startDate);
        $mode = $rule ? $rule->day_count_mode : 'weekdays';
        $user = $this->userModel->getUserById((int)$userId);
        return vacation_count_days_in_range(
            $startDate, $endDate, $mode, (int)($user->company_id ?? 0), $this->db, (int)($user->branch_id ?? 0)
        );
    }

    public function addHistoricalBalance($userId, $year, $days, $adminId, $reason) {
        $year = (int)$year;
        $days = (float)$days;
        $reason = trim($reason);
        if ($year < 1970 || $year > (int)date('Y') || $days <= 0 || $reason === '') {
            return ['ok'=>false,'message'=>'Indicá un año válido, días mayores a cero y el motivo.'];
        }
        $user = $this->userModel->getUserById((int)$userId);
        $agreement = $this->getEffectiveAgreement($user);
        if (!$user || !$agreement) {
            return ['ok'=>false,'message'=>'El empleado debe tener un convenio efectivo.'];
        }
        $bounds = vacation_period_bounds($year, 1, 1);
        $rule = $this->getApplicableRule($userId, $bounds['period_end']);
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }
        try {
            $period = $this->balanceModel->getPeriodByUserLabel($userId, (string)$year, 'historical');
            if ($period) {
                $adjustment = (float)($period->adjustment_days ?? 0) + $days;
                if (!$this->balanceModel->updatePeriodBalances((int)$period->id, (float)$period->days_entitled,
                    (float)$period->days_taken, $adjustment)) {
                    throw new RuntimeException('No se pudo actualizar el período.');
                }
                $periodId = (int)$period->id;
            } else {
                $periodId = $this->balanceModel->createPeriod([
                    'user_id'=>$userId,'period_label'=>(string)$year,
                    'period_start'=>$bounds['period_start'],'period_end'=>$bounds['period_end'],
                    'balance_type'=>'historical','agreement_id'=>(int)$agreement->id,
                    'agreement_rule_id'=>$rule ? (int)$rule->id : null,
                    'count_mode_snapshot'=>$rule ? $rule->day_count_mode : 'calendar',
                    'days_entitled'=>0,'days_taken'=>0,'adjustment_days'=>$days,
                    'origin_notes'=>$reason,'status'=>'open','liquidated_at'=>date('Y-m-d H:i:s'),
                    'liquidated_by'=>$adminId,
                ]);
            }
            if ($periodId <= 0) {
                throw new RuntimeException('No se pudo crear el período histórico.');
            }
            $this->balanceModel->addMovement([
                'period_id'=>$periodId,'user_id'=>$userId,'movement_type'=>'opening_balance',
                'source'=>'manual','days'=>$days,'notes'=>'Saldo reconocido ' . $year . ': ' . $reason,
                'created_by'=>$adminId,
                'operation_key'=>'historical:' . $userId . ':' . $year . ':' . str_replace('.', '', (string)microtime(true)),
            ]);
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) {
                $this->db->commit();
            }
            return ['ok'=>true,'message'=>'Se reconocieron ' . vacation_format_days($days) . ' días del período ' . $year . '.'];
        } catch (Throwable $e) {
            if ($ownTx) {
                $this->db->rollBack();
            }
            return ['ok'=>false,'message'=>'No se pudo registrar el saldo histórico.'];
        }
    }

    public function addConventionalCredit($userId, $year, $days, $expiresAt, $adminId, $reason) {
        $year = (int)$year;
        $days = (float)$days;
        $reason = trim($reason);
        if ($year < 1970 || $year > (int)date('Y') + 1 || $days <= 0 || $reason === ''
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$expiresAt)) {
            return ['ok'=>false,'message'=>'Indicá año, días, vencimiento y motivo válidos.'];
        }
        $user = $this->userModel->getUserById((int)$userId);
        $agreement = $this->getEffectiveAgreement($user);
        if (!$user || !$agreement) {
            return ['ok'=>false,'message'=>'El empleado debe tener un convenio efectivo.'];
        }
        $bounds = vacation_period_bounds($year, 1, 1);
        $rule = $this->getApplicableRule($userId, $bounds['period_end']);
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $period = $this->balanceModel->getPeriodByUserLabel($userId, (string)$year, 'conventional_credit');
            if ($period) {
                $adjustment = (float)$period->adjustment_days + $days;
                $this->balanceModel->updatePeriodBalances((int)$period->id, (float)$period->days_entitled,
                    (float)$period->days_taken, $adjustment);
                $periodId = (int)$period->id;
                $this->balanceModel->updatePeriodExpiry($periodId, $expiresAt);
            } else {
                $periodId = $this->balanceModel->createPeriod([
                    'user_id'=>$userId,'period_label'=>(string)$year,
                    'period_start'=>$bounds['period_start'],'period_end'=>$bounds['period_end'],
                    'balance_type'=>'conventional_credit','agreement_id'=>(int)$agreement->id,
                    'agreement_rule_id'=>$rule ? (int)$rule->id : null,
                    'count_mode_snapshot'=>$rule ? $rule->day_count_mode : 'calendar',
                    'days_entitled'=>0,'days_taken'=>0,'adjustment_days'=>$days,
                    'expires_at'=>$expiresAt,'origin_notes'=>$reason,'status'=>'open',
                    'liquidated_at'=>date('Y-m-d H:i:s'),'liquidated_by'=>$adminId,
                ]);
            }
            if ($periodId <= 0) throw new RuntimeException('No se pudo crear el crédito.');
            $this->balanceModel->addMovement([
                'period_id'=>$periodId,'user_id'=>$userId,'movement_type'=>'opening_balance','source'=>'manual',
                'days'=>$days,'notes'=>'Crédito convencional ' . $year . ': ' . $reason,'created_by'=>$adminId,
                'operation_key'=>'credit:' . $userId . ':' . $year . ':' . str_replace('.', '', (string)microtime(true)),
            ]);
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) $this->db->commit();
            return ['ok'=>true,'message'=>'Crédito convencional registrado por ' . vacation_format_days($days) . ' días.'];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false,'message'=>'No se pudo registrar el crédito convencional.'];
        }
    }

    public function convertPeriodBalance($userId, $periodId, $targetMode, $targetPending, $adminId, $reason) {
        $validModes = array_keys(vacation_day_count_modes());
        $reason = trim($reason);
        if (!in_array($targetMode, $validModes, true) || (float)$targetPending < 0 || $reason === '') {
            return ['ok'=>false,'message'=>'Indicá unidad, saldo convertido y motivo.'];
        }
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $old = $this->balanceModel->getPeriodByIdForUpdate($periodId);
            if (!$old || (int)$old->user_id !== (int)$userId) {
                throw new RuntimeException('Período no encontrado.');
            }
            $oldPending = (float)$old->days_pending;
            $oldMode = $old->count_mode_snapshot;
            if (!$this->balanceModel->convertPeriod($periodId, $targetMode, (float)$targetPending, $reason)) {
                throw new RuntimeException('No se pudo convertir el período.');
            }
            $this->balanceModel->addMovement([
                'period_id'=>$periodId,'user_id'=>$userId,'movement_type'=>'conversion','source'=>'manual',
                'days'=>(float)$targetPending - $oldPending,'created_by'=>$adminId,
                'notes'=>$oldMode . ' → ' . $targetMode . '; ' . $oldPending . ' → ' . (float)$targetPending . '. ' . $reason,
                'operation_key'=>'conversion:' . $periodId . ':' . str_replace('.', '', (string)microtime(true)),
            ]);
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) $this->db->commit();
            return ['ok'=>true,'message'=>'Conversión registrada y saldo actualizado.'];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }
}
