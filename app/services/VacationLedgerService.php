<?php

class VacationLedgerService {
    private $db;
    private $balanceModel;
    private $entitlement;
    private $workSchedule;
    private $userModel;
    private $requestModel;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
        $this->balanceModel = new VacationBalance($this->db);
        $this->entitlement = new VacationEntitlementService($this->db);
        $this->workSchedule = new WorkSchedule($this->db);
        $this->userModel = new User($this->db);
        $this->requestModel = new Request($this->db);
    }

    public function previewRequest($request) {
        $user = $this->userModel->getUserById((int)$request->user_id);
        $agreement = $this->entitlement->getEffectiveAgreement($user);
        $rule = $this->entitlement->getApplicableRule((int)$request->user_id, $request->start_date);
        if (!$user) return ['ok'=>false,'message'=>'Empleado no encontrado.'];
        if (!$agreement) return ['ok'=>false,'message'=>'Falta convenio efectivo para el empleado.'];
        if (!$rule) return ['ok'=>false,'message'=>'Falta una regla de vacaciones aplicable para el empleado.'];
        $dates = vacation_dates_in_range(
            $request->start_date,
            $request->end_date ?: $request->start_date,
            $rule->day_count_mode,
            (int)$user->company_id,
            $this->db,
            (int)($user->branch_id ?? 0)
        );
        $days = count($dates);
        $warnings = [];
        $hardErrors = [];
        $minimum = (float)($agreement->minimum_request_days ?? 7);
        if ($days < $minimum) {
            $hardErrors[] = 'La solicitud debe comprender al menos ' . vacation_format_days($minimum) . ' días computables.';
        }
        $noticeDays = (int)($agreement->notice_days ?? 30);
        $noticeReference = !empty($request->created_at) ? date('Y-m-d', strtotime($request->created_at)) : date('Y-m-d');
        $notice = (int)floor((strtotime($request->start_date) - strtotime($noticeReference)) / 86400);
        if ($notice < $noticeDays) {
            $warnings[] = 'No cumple la anticipación de ' . $noticeDays . ' días del convenio.';
        }
        if (($agreement->start_rule ?? '') === 'monday_or_next_business'
            && !$this->isMondayOrNextBusinessDay($request->start_date, (int)$user->company_id, (int)($user->branch_id ?? 0))) {
            $warnings[] = 'El convenio exige comenzar un lunes o el siguiente hábil cuando sea feriado.';
        }

        $periods = $this->balanceModel->getPeriodsByUser((int)$request->user_id, true);
        usort($periods, function($a,$b){ return strcmp($a->period_start, $b->period_start) ?: ((int)$a->id <=> (int)$b->id); });
        $totalAvailable = array_sum(array_map(function($period) { return (float)$period->days_pending; }, $periods));
        $remaining = $days;
        $allocations = [];
        foreach ($periods as $period) {
            if ($remaining <= 0) break;
            $take = min((float)$period->days_pending, $remaining);
            if ($take <= 0) continue;
            if (($period->count_mode_snapshot ?? $rule->day_count_mode) !== $rule->day_count_mode) {
                $hardErrors[] = 'El saldo ' . $period->period_label . ' usa otra unidad de cómputo y requiere conversión de RRHH.';
                break;
            }
            $allocations[] = ['period_id'=>(int)$period->id,'period_label'=>$period->period_label,'days'=>$take];
            $remaining -= $take;
        }
        if ($remaining > 0.001) {
            $hardErrors[] = 'Saldo insuficiente. Pendientes: ' . vacation_format_days($days - $remaining)
                . ', solicitado: ' . vacation_format_days($days) . '.';
        }
        if (($agreement->split_policy ?? '') === 'soecra_14_plus_7' && $days < 14) {
            $validRemainder = false;
            foreach ($periods as $period) {
                if ((float)$period->days_pending <= 7.001 && (float)$period->days_taken >= 13.999) {
                    $validRemainder = true;
                    break;
                }
            }
            if (!$validRemainder) {
                $warnings[] = 'SOECRA admite el tramo de 7 días como remanente; esta solicitud inicial requiere excepción de RRHH.';
            }
        }
        return [
            'ok'=>empty($hardErrors),'message'=>$hardErrors ? implode(' ', array_unique($hardErrors)) : '',
            'days'=>$days,'counted_dates'=>$dates,'allocations'=>$allocations,
            'total_available'=>$totalAvailable,
            'remaining_after'=>empty($hardErrors) ? max(0, $totalAvailable - $days) : $totalAvailable,
            'warnings'=>array_values(array_unique($warnings)),'requires_override'=>!empty($warnings),
            'agreement_snapshot'=>[
                'id'=>(int)$agreement->id,'code'=>$agreement->code,'name'=>$agreement->name,
                'rule_id'=>(int)$rule->id,'day_count_mode'=>$rule->day_count_mode,
                'notice_days'=>$noticeDays,'split_policy'=>$agreement->split_policy ?? 'lct_7',
            ],
        ];
    }

    public function applyTake($userId, array $dates, $source, $adminId, $requestId = null, $notes = '', $skipScheduleSave = false) {
        $dates = array_values(array_unique(array_filter($dates)));
        if (!$dates) {
            return ['ok'=>false,'message'=>'El rango no contiene días de vacaciones computables.'];
        }
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $expiryResult = $this->expireConventionalCredits($userId, $adminId);
            if (!$expiryResult['ok']) throw new RuntimeException($expiryResult['message']);
            if ($requestId && $this->balanceModel->getTakeMovementsForRequest($requestId, true)) {
                throw new RuntimeException('La solicitud ya fue descontada.');
            }
            $periods = $this->balanceModel->getOpenPeriodsForUpdate($userId);
            $remainingDates = $dates;
            $allocations = [];
            $previousSchedules = [];
            if (!$skipScheduleSave) {
                foreach ($dates as $date) {
                    $previousSchedules[$date] = $this->workSchedule->entriesToPlannerArrays(
                        $this->workSchedule->getPlannerEntriesForUserOnDate($userId, $date)
                    );
                }
            }
            foreach ($periods as $period) {
                if (!$remainingDates) break;
                $take = min((int)round((float)$period->days_pending), count($remainingDates));
                if ($take <= 0) continue;
                $allocatedDates = array_splice($remainingDates, 0, $take);
                $newTaken = (float)$period->days_taken + $take;
                if (!$this->balanceModel->updatePeriodBalances((int)$period->id, (float)$period->days_entitled,
                    $newTaken, (float)($period->adjustment_days ?? 0))) {
                    throw new RuntimeException('No se pudo actualizar el período.');
                }
                $operationKey = $requestId ? 'request:' . (int)$requestId . ':take:' . (int)$period->id : null;
                $this->balanceModel->addMovement([
                    'period_id'=>(int)$period->id,'user_id'=>$userId,'movement_type'=>'take','source'=>$source,
                    'days'=>$take,'request_id'=>$requestId,'operation_key'=>$operationKey,
                    'schedule_dates'=>$allocatedDates,'notes'=>$notes,'created_by'=>$adminId,
                    'schedule_snapshot'=>array_intersect_key($previousSchedules, array_flip($allocatedDates)),
                ]);
                $allocations[] = ['period_id'=>(int)$period->id,'period_label'=>$period->period_label,
                    'days'=>$take,'dates'=>$allocatedDates];
            }
            if ($remainingDates) {
                throw new RuntimeException('Saldo insuficiente para completar la solicitud.');
            }
            if (!$skipScheduleSave) {
                foreach ($dates as $date) {
                    if (!$this->workSchedule->saveDaySchedule($userId, $date, [vacation_schedule_entry(
                        $requestId ? 'Vacaciones (sol. #' . $requestId . ')' : 'Vacaciones'
                    )])) {
                        throw new RuntimeException('No se pudo actualizar el planificador.');
                    }
                }
            }
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) $this->db->commit();
            return ['ok'=>true,'message'=>'Se descontaron ' . vacation_format_days(count($dates)) . ' día(s).',
                'days'=>count($dates),'allocations'=>$allocations];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false,'message'=>$e instanceof RuntimeException ? $e->getMessage() : 'No se pudo aplicar el descuento de vacaciones.'];
        }
    }

    public function applyTakeFromRequest($request, $adminId, $exceptionReason = '') {
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
        $preview = $this->previewRequest($request);
        if (!$preview['ok']) {
            if ($ownTx) $this->db->rollBack();
            return $preview;
        }
        if ($preview['requires_override'] && trim($exceptionReason) === '') {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false,'message'=>'La solicitud requiere una excepción justificada: ' . implode(' ', $preview['warnings']),
                'preview'=>$preview];
        }
        if (!$this->requestModel->saveVacationReview((int)$request->id, $preview['days'], $preview,
            trim($exceptionReason), $adminId)) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false,'message'=>'No se pudo guardar la revisión de convenio.'];
        }
        $take = $this->applyTake((int)$request->user_id, $preview['counted_dates'], 'request', $adminId,
            (int)$request->id, 'Solicitud #' . (int)$request->id, false);
        if (!$take['ok']) {
            if ($ownTx) $this->db->rollBack();
            return $take;
        }
        if (trim($exceptionReason) !== '' && !empty($take['allocations'][0]['period_id'])) {
            $this->balanceModel->addMovement([
                'period_id'=>(int)$take['allocations'][0]['period_id'], 'user_id'=>(int)$request->user_id,
                'movement_type'=>'exception', 'source'=>'request', 'days'=>0, 'request_id'=>(int)$request->id,
                'operation_key'=>'request:' . (int)$request->id . ':exception',
                'notes'=>trim($exceptionReason), 'created_by'=>$adminId,
            ]);
        }
        if ($ownTx) $this->db->commit();
        return $take;
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false, 'message'=>'No se pudo registrar la aprobación de vacaciones.'];
        }
    }

    public function reverseRequest($requestId, $adminId, $notes = 'Cancelación de solicitud') {
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $movements = $this->balanceModel->getTakeMovementsForRequest($requestId, true);
            if (!$movements) {
                throw new RuntimeException('La solicitud no tiene consumos para revertir.');
            }
            $allDates = [];
            $userId = 0;
            foreach ($movements as $movement) {
                $userId = (int)$movement->user_id;
                $newTaken = max(0, (float)$movement->days_taken - (float)$movement->days);
                $this->balanceModel->updatePeriodBalances((int)$movement->period_id,
                    (float)$movement->days_entitled, $newTaken, (float)$movement->adjustment_days);
                $dates = json_decode($movement->schedule_dates ?: '[]', true) ?: [];
                $snapshot = json_decode($movement->schedule_snapshot ?: '{}', true) ?: [];
                $allDates = array_merge($allDates, $dates);
                $this->balanceModel->addMovement([
                    'period_id'=>(int)$movement->period_id,'user_id'=>$userId,'movement_type'=>'reversal',
                    'source'=>'cancellation','days'=>(float)$movement->days,'request_id'=>$requestId,
                    'operation_key'=>'request:' . $requestId . ':reversal:' . (int)$movement->period_id,
                    'schedule_dates'=>$dates,'notes'=>$notes,'created_by'=>$adminId,
                ]);
            }
            foreach ($movements as $movement) {
                $dates = json_decode($movement->schedule_dates ?: '[]', true) ?: [];
                $snapshot = json_decode($movement->schedule_snapshot ?: '{}', true) ?: [];
                foreach ($dates as $date) {
                    if (!$this->workSchedule->saveDaySchedule($userId, $date, $snapshot[$date] ?? [])) {
                        throw new RuntimeException('No se pudo restaurar el planificador.');
                    }
                }
            }
            $this->balanceModel->syncUserVacationCache($userId);
            if ($ownTx) $this->db->commit();
            return ['ok'=>true,'message'=>'Se restauraron los días a sus períodos originales.'];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }

    public function applyReversal($userId, array $dates, $source, $adminId, $notes = '', $skipScheduleSave = false) {
        $dates = array_values(array_unique($dates));
        if (!$dates) return ['ok'=>true,'message'=>'Sin cambios.'];
        $periods = $this->balanceModel->getPeriodsByUser($userId, false);
        usort($periods, function($a,$b){ return strcmp($b->period_start, $a->period_start); });
        $remaining = count($dates);
        foreach ($periods as $period) {
            if ($remaining <= 0) break;
            $restore = min((float)$period->days_taken, $remaining);
            if ($restore <= 0) continue;
            $this->balanceModel->updatePeriodBalances((int)$period->id, (float)$period->days_entitled,
                (float)$period->days_taken - $restore, (float)($period->adjustment_days ?? 0));
            $this->balanceModel->addMovement([
                'period_id'=>(int)$period->id,'user_id'=>$userId,'movement_type'=>'reversal','source'=>$source,
                'days'=>$restore,'schedule_dates'=>array_slice($dates, 0, (int)$restore),
                'notes'=>$notes ?: 'Reversión de vacaciones','created_by'=>$adminId,
            ]);
            $remaining -= $restore;
        }
        if (!$skipScheduleSave) {
            foreach ($dates as $date) $this->workSchedule->saveDaySchedule($userId, $date, []);
        }
        $this->balanceModel->syncUserVacationCache($userId);
        return ['ok'=>true,'message'=>'Se revirtieron ' . vacation_format_days(count($dates)) . ' día(s).'];
    }

    public function processPlannerDayChange($userId, $date, array $oldEntries, array $newEntries, $adminId) {
        $oldV = vacation_count_vacation_days_in_entries($oldEntries);
        $newV = vacation_count_vacation_days_in_entries($newEntries);
        if ($newV > $oldV) {
            return $this->applyTake($userId, [$date], 'planner', $adminId, null, 'Planificador ' . $date, true);
        }
        if ($newV < $oldV) {
            return $this->applyReversal($userId, [$date], 'planner', $adminId, 'Quitar vacaciones planificador', true);
        }
        return ['ok'=>true,'message'=>''];
    }

    public function expireConventionalCredits($userId = null, $adminId = 0) {
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            $expired = $this->balanceModel->getExpiredCreditsForUpdate($userId);
            $affectedUsers = [];
            foreach ($expired as $period) {
                $pending = (float)$period->days_pending;
                if (!$this->balanceModel->closeExpiredCredit((int)$period->id)) {
                    throw new RuntimeException('No se pudo vencer el crédito #' . (int)$period->id . '.');
                }
                $this->balanceModel->addMovement([
                    'period_id'=>(int)$period->id, 'user_id'=>(int)$period->user_id,
                    'movement_type'=>'expiry', 'source'=>'system', 'days'=>$pending,
                    'notes'=>'Vencimiento automático del crédito al ' . $period->expires_at,
                    'created_by'=>(int)$adminId,
                    'operation_key'=>'expiry:' . (int)$period->id,
                ]);
                $affectedUsers[(int)$period->user_id] = true;
            }
            foreach (array_keys($affectedUsers) as $uid) $this->balanceModel->syncUserVacationCache($uid);
            if ($ownTx) $this->db->commit();
            return ['ok'=>true, 'expired'=>count($expired), 'message'=>count($expired) . ' crédito(s) vencido(s).'];
        } catch (Throwable $e) {
            if ($ownTx) $this->db->rollBack();
            return ['ok'=>false, 'expired'=>0, 'message'=>$e->getMessage()];
        }
    }

    private function isMondayOrNextBusinessDay($date, $companyId, $branchId = 0) {
        $dt = new DateTime($date);
        $monday = clone $dt;
        $monday->modify('monday this week');
        $holiday = new Holiday($this->db);
        $candidate = $monday;
        while ((int)$candidate->format('N') >= 6
            || $holiday->isHolidayForCompany($companyId, $candidate->format('Y-m-d'), $branchId)) {
            $candidate->modify('+1 day');
        }
        return $candidate->format('Y-m-d') === $dt->format('Y-m-d');
    }
}
