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
        $assignmentAgreementId = $this->getPrimaryAssignmentAgreementId((int)$user->id, (int)($user->company_id ?? 0));
        if ($assignmentAgreementId > 0) {
            return $this->agreementModel->getById($assignmentAgreementId);
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

    private function getPrimaryAssignmentAgreementId($userId, $companyId) {
        if ($userId <= 0) {
            return 0;
        }
        try {
            $this->db->query("SHOW TABLES LIKE 'employee_company_assignments'");
            if (!$this->db->single()) {
                return 0;
            }
            if ($companyId > 0) {
                $this->db->query('SELECT agreement_id FROM employee_company_assignments
                    WHERE user_id = :uid AND company_id = :cid AND is_primary = 1
                    ORDER BY id DESC LIMIT 1');
                $this->db->bind(':uid', $userId);
                $this->db->bind(':cid', $companyId);
            } else {
                $this->db->query('SELECT agreement_id FROM employee_company_assignments
                    WHERE user_id = :uid AND is_primary = 1
                    ORDER BY id DESC LIMIT 1');
                $this->db->bind(':uid', $userId);
            }
            $row = $this->db->single();
            return ($row && !empty($row->agreement_id)) ? (int)$row->agreement_id : 0;
        } catch (Throwable $e) {
            return 0;
        }
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
     * Meses de antigüedad a efectos de la ESCALA del convenio. Las escalas se
     * cortan por años cumplidos ("hasta 5 años inclusive" / "más de 5 años"):
     * 5 años y 15 días ya es "más de 5 años", así que un mes empezado cuenta
     * como cumplido al comparar contra min/max_months. Para mostrar la
     * antigüedad se sigue usando seniorityMonthsBetween (meses completos).
     */
    public function seniorityMonthsForScale($hireDate, $asOfDate = null) {
        if (empty($hireDate)) {
            return 0;
        }
        $asOf = $asOfDate ? new DateTime($asOfDate) : new DateTime();
        $hire = new DateTime($hireDate);
        if ($hire > $asOf) {
            return 0;
        }
        $diff = $hire->diff($asOf);
        return ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0);
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
        if ($hireDate > $cutDate) {
            return [
                'ok' => false,
                'message' => 'El empleado ingresó el ' . date('d/m/Y', strtotime($hireDate)) . ', después del cierre del período '
                    . $bounds['period_label'] . ': no corresponde liquidarlo.',
                'seniority_months' => 0,
                'agreement_code' => $agreement->code,
            ];
        }
        $months = $this->seniorityMonthsBetween($hireDate, $cutDate);
        $scaleMonths = $this->seniorityMonthsForScale($hireDate, $cutDate);
        $rules = $this->agreementModel->getRules((int)$agreement->id);
        $rule = null;
        foreach ($rules as $r) {
            $min = (int)$r->min_months;
            $max = $r->max_months !== null ? (int)$r->max_months : PHP_INT_MAX;
            if ($scaleMonths >= $min && $scaleMonths <= $max) {
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
        // LCT art. 150: antigüedad al 31 de diciembre del año del período.
        $cutDate = date('Y-12-31', strtotime($reference));
        if (empty($user->hire_date) || $user->hire_date > $cutDate) {
            // Sin ingreso, o ingresó después del cierre del período: no hay escala aplicable.
            return null;
        }
        $months = $this->seniorityMonthsForScale($user->hire_date, $cutDate);
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
     * Resuelve fechas + label de un período objetivo.
     * Con label (`2027` / `2026-2027`) recalcula bounds del convenio; sin label usa la fecha de referencia.
     * @return array{period_start:string,period_end:string,period_label:string}|null
     */
    public function resolvePeriodBounds($userId, $periodLabel = null, $asOfDate = null) {
        $user = $this->userModel->getUserById((int)$userId);
        $agreement = $this->getEffectiveAgreement($user);
        if (!$agreement) {
            return null;
        }
        $startMonth = (int)$agreement->period_start_month;
        $startDay = (int)$agreement->period_start_day;
        $label = trim((string)($periodLabel ?? ''));
        if ($label !== '') {
            $year = vacation_period_year_from_label($label);
            if ($year < 1970 || $year > 2100) {
                return null;
            }
            return vacation_period_bounds($year, $startMonth, $startDay);
        }
        $ref = $asOfDate ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$ref)) {
            $ref = date('Y-m-d');
        }
        return vacation_period_for_date($ref, $startMonth, $startDay);
    }

    /**
     * Período cuyo saldo vino de un importador (informe RRHH): es la fuente de
     * verdad y nunca se recalcula con la escala; se corrige desde Carga / ajustes.
     */
    public function isImportedPeriod($period) {
        if (!$period) {
            return false;
        }
        if (stripos((string)($period->origin_notes ?? ''), 'Importado') === 0) {
            return true;
        }
        $this->db->query("SELECT COUNT(*) AS c FROM vacation_balance_movements WHERE period_id = :pid AND source = 'import'");
        $this->db->bind(':pid', (int)$period->id);
        $row = $this->db->single();
        return (int)($row->c ?? 0) > 0;
    }

    /**
     * Liquida un período para un empleado (crea o actualiza vacation_balance_periods).
     * @return array{ok:bool,message:string,period_id?:int,skipped?:bool}
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

        $bounds = $this->resolvePeriodBounds($userId, $periodLabel, $asOfDate);
        if (!$bounds) {
            return ['ok' => false, 'message' => 'No se pudo calcular el período (revisá el año o el convenio).'];
        }

        $cutDate = $bounds['period_end'];
        $rule = $this->getApplicableRule($userId, $cutDate);
        if (!$rule) {
            return ['ok' => false, 'message' => 'No hay regla de vacaciones para la antigüedad del empleado.'];
        }

        $daysEntitled = (float)$rule->days_entitled;
        $pre = $this->balanceModel->getPeriodByUserLabel($userId, $bounds['period_label'], 'annual');
        if ($pre && $this->isImportedPeriod($pre)) {
            return [
                'ok' => false,
                'skipped' => true,
                'period_id' => (int)$pre->id,
                'message' => 'Período ' . $bounds['period_label'] . ' importado del informe RRHH: se conserva tal cual ('
                    . vacation_format_days($pre->days_entitled) . ' días corresponden, '
                    . vacation_format_days($pre->days_pending) . ' pendientes). Para corregirlo usá Carga / ajustes.',
            ];
        }
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
                // Repara fechas/convenio/regla si el período se creó mal (label ≠ fechas).
                $metaNeedsFix = ($existing->period_start ?? '') !== $bounds['period_start']
                    || ($existing->period_end ?? '') !== $bounds['period_end']
                    || (int)($existing->agreement_id ?? 0) !== (int)$agreement->id
                    || (int)($existing->agreement_rule_id ?? 0) !== (int)$rule->id
                    || (string)($existing->count_mode_snapshot ?? '') !== (string)$rule->day_count_mode;
                if ($metaNeedsFix) {
                    if (!$this->balanceModel->updatePeriodLiquidationMeta((int)$existing->id, [
                        'period_start' => $bounds['period_start'],
                        'period_end' => $bounds['period_end'],
                        'agreement_id' => (int)$agreement->id,
                        'agreement_rule_id' => (int)$rule->id,
                        'count_mode_snapshot' => $rule->day_count_mode,
                        'liquidated_at' => date('Y-m-d H:i:s'),
                        'liquidated_by' => $adminId > 0 ? $adminId : null,
                    ])) {
                        throw new RuntimeException('No se pudieron corregir las fechas del período.');
                    }
                }
                $periodId = (int)$existing->id;
                if (abs($difference) > 0.001) {
                    $this->balanceModel->addMovement([
                        'period_id'=>$periodId, 'user_id'=>$userId, 'movement_type'=>'adjustment',
                        'source'=>'liquidation', 'days'=>$difference,
                        'notes'=>'Recalculo del período ' . $bounds['period_label'],
                        'created_by'=>$adminId > 0 ? $adminId : (int)($_SESSION['user_id'] ?? 0),
                    ]);
                } elseif ($metaNeedsFix) {
                    $this->balanceModel->addMovement([
                        'period_id'=>$periodId, 'user_id'=>$userId, 'movement_type'=>'adjustment',
                        'source'=>'liquidation', 'days'=>0,
                        'notes'=>'Corrección de fechas/convenio del período ' . $bounds['period_label'],
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
    public function getBatchLiquidationPreview($companyId, $periodLabel = null) {
        $preview = $this->previewCompanyPeriod($companyId, $periodLabel);
        return [
            'total_active' => (int)($preview['stats']['total_active'] ?? 0),
            'ready' => (int)($preview['stats']['ready'] ?? 0),
            'no_hire_date' => (int)($preview['stats']['no_hire'] ?? 0),
            'no_agreement' => (int)($preview['stats']['no_agreement'] ?? 0),
            'already_liquidated' => (int)($preview['stats']['already_liquidated'] ?? 0),
            'to_create' => (int)($preview['stats']['to_create'] ?? 0),
            'days_total' => (float)($preview['stats']['days_total'] ?? 0),
            'period_label' => $preview['period_label'] ?? '',
        ];
    }

    /**
     * Roster de liquidación por empresa y período objetivo (días según convenio).
     * @return array{period_label:string,rows:array,stats:array}
     */
    public function previewCompanyPeriod($companyId, $periodLabel = null) {
        $companyId = (int)$companyId;
        $label = trim((string)($periodLabel ?? ''));
        if ($label === '' || vacation_period_year_from_label($label) <= 0) {
            $label = vacation_default_target_period_label();
        }

        $emptyStats = [
            'total_active' => 0,
            'ready' => 0,
            'no_hire' => 0,
            'no_agreement' => 0,
            'already_liquidated' => 0,
            'to_create' => 0,
            'with_takes' => 0,
            'days_total' => 0.0,
            'blocked' => 0,
        ];
        if ($companyId <= 0) {
            return [
                'period_label' => $label,
                'rows' => [],
                'stats' => $emptyStats,
                'error' => 'Seleccioná una empresa en el contexto de sesión.',
            ];
        }

        $employees = $this->userModel->getActiveEmployeesForVacationLiquidation($companyId);
        $modes = vacation_day_count_modes();
        $rows = [];
        $stats = [
            'total_active' => count($employees),
            'ready' => 0,
            'no_hire' => 0,
            'no_agreement' => 0,
            'already_liquidated' => 0,
            'to_create' => 0,
            'with_takes' => 0,
            'days_total' => 0.0,
            'blocked' => 0,
        ];

        foreach ($employees as $emp) {
            $uid = (int)$emp->id;
            $row = [
                'user_id' => $uid,
                'full_name' => $emp->full_name,
                'hire_date' => $emp->hire_date ?? null,
                'status' => 'ready',
                'agreement_id' => null,
                'agreement_code' => null,
                'agreement_name' => null,
                'seniority_months' => null,
                'rule_id' => null,
                'rule_range' => null,
                'rule_notes' => null,
                'days_entitled' => null,
                'day_count_mode' => null,
                'day_count_mode_label' => null,
                'period_label' => $label,
                'period_start' => null,
                'period_end' => null,
                'existing_period_id' => null,
                'existing_entitled' => null,
                'existing_taken' => null,
                'existing_pending' => null,
                'message' => '',
            ];

            if (empty($emp->hire_date)) {
                $row['status'] = 'missing_hire';
                $row['message'] = 'Sin fecha de ingreso formal';
                $stats['no_hire']++;
                $stats['blocked']++;
                $rows[] = $row;
                continue;
            }

            $agreement = $this->getEffectiveAgreement($emp);
            if (!$agreement) {
                $row['status'] = 'missing_agreement';
                $row['message'] = 'Sin convenio efectivo';
                $stats['no_agreement']++;
                $stats['blocked']++;
                $rows[] = $row;
                continue;
            }

            $bounds = $this->resolvePeriodBounds($uid, $label);
            if (!$bounds) {
                $row['status'] = 'blocked';
                $row['message'] = 'No se pudo calcular el período';
                $stats['blocked']++;
                $rows[] = $row;
                continue;
            }

            $cutDate = $bounds['period_end'];
            $months = $this->seniorityMonthsBetween($emp->hire_date, date('Y-12-31', strtotime($cutDate)));
            $rule = $this->getApplicableRule($uid, $cutDate);
            $row['agreement_id'] = (int)$agreement->id;
            $row['agreement_code'] = $agreement->code;
            $row['agreement_name'] = $agreement->name;
            $row['seniority_months'] = $months;
            $row['period_label'] = $bounds['period_label'];
            $row['period_start'] = $bounds['period_start'];
            $row['period_end'] = $bounds['period_end'];

            if (!$rule) {
                $row['status'] = 'blocked';
                $row['message'] = 'Sin regla para la antigüedad';
                $stats['blocked']++;
                $rows[] = $row;
                continue;
            }

            $days = (float)$rule->days_entitled;
            $row['rule_id'] = (int)$rule->id;
            $row['rule_range'] = (int)$rule->min_months . '–' . ($rule->max_months !== null ? (int)$rule->max_months : '∞') . ' meses';
            $row['rule_notes'] = $rule->notes ?? '';
            $row['days_entitled'] = $days;
            $row['day_count_mode'] = $rule->day_count_mode;
            $row['day_count_mode_label'] = $modes[$rule->day_count_mode] ?? $rule->day_count_mode;
            $stats['days_total'] += $days;

            $existing = $this->balanceModel->getPeriodByUserLabel($uid, $bounds['period_label'], 'annual');
            if ($existing) {
                $taken = (float)$existing->days_taken;
                $row['existing_period_id'] = (int)$existing->id;
                $row['existing_entitled'] = (float)$existing->days_entitled;
                $row['existing_taken'] = $taken;
                $row['existing_pending'] = vacation_period_pending($existing);
                if ($taken > 0.001) {
                    $row['status'] = 'exists_with_takes';
                    $row['message'] = 'Ya liquidado · con tomas';
                    $stats['with_takes']++;
                } else {
                    $row['status'] = 'exists';
                    $row['message'] = 'Ya liquidado';
                }
                $stats['already_liquidated']++;
            } else {
                $row['status'] = 'ready';
                $row['message'] = 'Listo para liquidar';
                $stats['to_create']++;
                $stats['ready']++;
            }
            $rows[] = $row;
        }

        usort($rows, static function ($a, $b) {
            return strcasecmp((string)$a['full_name'], (string)$b['full_name']);
        });

        return [
            'period_label' => $label,
            'rows' => $rows,
            'stats' => $stats,
        ];
    }

    /**
     * Liquida el período objetivo para empleados activos de la empresa (no inactivos / baja).
     * Recalcula fechas por convenio; no pisa solo el label.
     * @return array{ok:bool,period_label:string,liquidated:int,skipped:int,failed:int,details:array,message:string}
     */
    public function liquidateCompanyBatch($companyId, $periodLabel = null, $adminId = 0, $onlyMissing = false) {
        if (!$this->isReady()) {
            return ['ok' => false, 'message' => 'Módulo de vacaciones no instalado.'];
        }
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return ['ok' => false, 'message' => 'Seleccioná una empresa en el contexto de sesión.', 'liquidated' => 0, 'skipped' => 0, 'failed' => 0, 'details' => [], 'period_label' => ''];
        }

        $resolvedLabel = trim((string)($periodLabel ?? ''));
        if ($resolvedLabel === '' || vacation_period_year_from_label($resolvedLabel) <= 0) {
            $resolvedLabel = vacation_default_target_period_label();
        }

        if ($onlyMissing) {
            $preview = $this->previewCompanyPeriod($companyId, $resolvedLabel);
            if ((int)($preview['stats']['to_create'] ?? 0) <= 0) {
                return [
                    'ok' => true,
                    'period_label' => $resolvedLabel,
                    'liquidated' => 0,
                    'skipped' => (int)($preview['stats']['total_active'] ?? 0),
                    'failed' => 0,
                    'details' => [],
                    'message' => 'Nada para crear en el período ' . $resolvedLabel . ': no hay empleados listos sin liquidar.',
                ];
            }
        }

        $employees = $this->userModel->getActiveEmployeesForVacationLiquidation($companyId);
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

            if ($onlyMissing) {
                $bounds = $this->resolvePeriodBounds($uid, $resolvedLabel);
                $canonical = $bounds['period_label'] ?? $resolvedLabel;
                $existing = $this->balanceModel->getPeriodByUserLabel($uid, $canonical, 'annual');
                if ($existing) {
                    $skipped++;
                    $details[] = [
                        'user_id' => $uid,
                        'name' => $emp->full_name,
                        'status' => 'skipped',
                        'message' => 'Ya tiene período ' . $canonical,
                    ];
                    continue;
                }
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
            } elseif (!empty($result['skipped'])) {
                $skipped++;
                $details[] = [
                    'user_id' => $uid,
                    'name' => $emp->full_name,
                    'status' => 'skipped',
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
