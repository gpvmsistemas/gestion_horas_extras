<?php

/**
 * Compara turnos planificados (employee_schedules) con fichadas (clock_events).
 * Soporta varios bloques el mismo día (turno partido): suma minutos planificados
 * y compara 1ª entrada real vs inicio planificado más temprano.
 */
class PlanVsActualService {
    private $db;
    private $scheduleModel;
    private $requestModel;
    private $summaryModel;
    private $leaveCache = [];

    public function __construct() {
        $this->db = new Database;
        $this->scheduleModel = new Schedule();
        $this->requestModel = new Request();
        $this->summaryModel = new AttendanceDaySummary();
    }

    public function recomputeRange($companyId, $startDate, $endDate) {
        $pairs = $this->getUserDatesInRange($companyId, $startDate, $endDate);
        $this->preloadLeave($companyId, $startDate, $endDate);

        $count = 0;
        foreach ($pairs as $pair) {
            $this->computeAndSave((int)$pair->user_id, $pair->work_date, (int)$companyId);
            $count++;
        }
        return $count;
    }

    private function getUserDatesInRange($companyId, $startDate, $endDate) {
        $this->db->query('SELECT DISTINCT es.user_id, es.schedule_date AS work_date
            FROM employee_schedules es
            JOIN users u ON es.user_id = u.id
            WHERE u.company_id = :company_id
              AND es.schedule_date BETWEEN :start_date AND :end_date
            UNION
            SELECT DISTINCT ce.user_id, DATE(ce.event_time) AS work_date
            FROM clock_events ce
            JOIN users u ON ce.user_id = u.id
            WHERE u.company_id = :company_id2
              AND DATE(ce.event_time) BETWEEN :start_date2 AND :end_date2');
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':start_date', $startDate);
        $this->db->bind(':end_date', $endDate);
        $this->db->bind(':company_id2', $companyId);
        $this->db->bind(':start_date2', $startDate);
        $this->db->bind(':end_date2', $endDate);
        return $this->db->resultSet();
    }

    private function preloadLeave($companyId, $startDate, $endDate) {
        $this->leaveCache = [];
        $rows = $this->requestModel->getApprovedRequestsForPeriod($startDate, $endDate, $companyId);
        foreach ($rows as $r) {
            $uid = (int)$r->user_id;
            $d = new DateTime($r->start_date);
            $end = new DateTime(!empty($r->end_date) ? $r->end_date : $r->start_date);
            while ($d <= $end) {
                $this->leaveCache[$uid][$d->format('Y-m-d')] = true;
                $d->modify('+1 day');
            }
        }
    }

    private function isOnLeave($userId, $workDate) {
        return !empty($this->leaveCache[$userId][$workDate]);
    }

    private function getPlannedBlocks($userId, $workDate) {
        $workSchedule = new WorkSchedule();
        $rows = $workSchedule->getSchedulesForUserOnDate($userId, $workDate);
        $blocks = [];
        foreach ($rows as $row) {
            if (empty($row->start_time) || empty($row->end_time)) {
                continue;
            }
            $blocks[] = (object)[
                'start_time' => $row->start_time,
                'end_time'   => $row->end_time,
                'type'       => $row->type ?? null,
            ];
        }
        return $blocks;
    }

    private function aggregatePlanned(array $blocks) {
        $plannedMinutes = 0;
        $starts = [];
        $ends = [];
        $validBlocks = 0;

        foreach ($blocks as $b) {
            if (empty($b->start_time) || empty($b->end_time)) {
                continue;
            }
            $st = strtotime($b->start_time);
            $en = strtotime($b->end_time);
            if ($en <= $st) {
                continue;
            }
            $plannedMinutes += (int)round(($en - $st) / 60);
            $starts[] = $b->start_time;
            $ends[] = $b->end_time;
            $validBlocks++;
        }

        if ($validBlocks === 0) {
            return null;
        }

        sort($starts);
        sort($ends);

        return [
            'planned_minutes' => $plannedMinutes,
            'planned_start'   => $starts[0],
            'planned_end'     => $ends[count($ends) - 1],
            'planned_blocks'  => $validBlocks,
        ];
    }

    private function getPlannedAbsenceType($userId, $workDate) {
        $rows = (new WorkSchedule())->getSchedulesForUserOnDate($userId, $workDate);
        foreach ($rows as $row) {
            $t = $row->type ?? '';
            if ($t === 'vacation' || $t === 'leave') {
                return $t;
            }
        }
        return null;
    }

    private function attendanceControlMode($userId) {
        try {
            $this->db->query("SHOW COLUMNS FROM users LIKE 'attendance_control_mode'");
            if (!$this->db->single()) return 'required';
            $this->db->query('SELECT attendance_control_mode FROM users WHERE id = ?');
            $row = $this->db->single([(int)$userId]);
            return User::normalizeAttendanceControlMode($row->attendance_control_mode ?? 'required');
        } catch (Throwable $e) { return 'required'; }
    }

    public function computeAndSave($userId, $workDate, $companyId) {
        $controlMode = $this->attendanceControlMode($userId);
        $plannedAbsence = $this->getPlannedAbsenceType($userId, $workDate);
        if ($plannedAbsence || $this->isOnLeave($userId, $workDate)) {
            return $this->summaryModel->upsert([
                'company_id'      => $companyId,
                'user_id'         => $userId,
                'work_date'       => $workDate,
                'planned_minutes' => null,
                'planned_start'   => null,
                'planned_end'     => null,
                'planned_blocks'  => 0,
                'actual_minutes'  => null,
                'actual_entry'    => null,
                'actual_exit'     => null,
                'delta_minutes'   => null,
                'status'          => 'on_leave',
                'alert_codes'     => json_encode(['ON_LEAVE']),
            ]);
        }

        $blocks = $this->getPlannedBlocks($userId, $workDate);
        $planned = $this->aggregatePlanned($blocks);

        $clockings = $this->scheduleModel->getRawClockingsForUserAndDay($userId, $workDate);
        $summary = $this->scheduleModel->computeDaySummaryFromClockings($clockings);

        $actualEntry = null;
        $actualExit = null;
        if (!empty($clockings)) {
            $first = $clockings[0]->event_time;
            $last  = $clockings[count($clockings) - 1]->event_time;
            $actualEntry = $first;
            $actualExit  = count($clockings) > 1 ? $last : null;
            if ($summary['exit_time']) {
                $actualExit = $workDate . ' ' . $summary['exit_time'];
            }
            if ($summary['entry_time']) {
                $actualEntry = $workDate . ' ' . $summary['entry_time'];
            }
        }

        $actualMinutes = $summary['total_hours'] !== null
            ? (int)round($summary['total_hours'] * 60)
            : null;

        $lateTol = function_exists('setting_int') ? setting_int('attendance_late_tolerance_min', 5) : (defined('ATTENDANCE_LATE_TOLERANCE_MIN') ? (int)ATTENDANCE_LATE_TOLERANCE_MIN : 5);
        $earlyTol = function_exists('setting_int') ? setting_int('attendance_early_leave_tolerance_min', 5) : (defined('ATTENDANCE_EARLY_LEAVE_TOLERANCE_MIN') ? (int)ATTENDANCE_EARLY_LEAVE_TOLERANCE_MIN : 5);

        $alerts = [];
        $status = 'ok';

        $hasPlanned = $planned !== null;
        $hasActual  = !empty($clockings);

        if ($hasPlanned && !$hasActual) {
            $status = 'no_show';
            $alerts[] = 'NO_SHOW';
        } elseif (!$hasPlanned && $hasActual) {
            $status = 'unplanned_clocking';
            $alerts[] = 'UNPLANNED';
        } elseif ($hasPlanned && $hasActual) {
            $plannedStartDt = new DateTime($workDate . ' ' . $planned['planned_start']);
            $plannedEndDt   = new DateTime($workDate . ' ' . $planned['planned_end']);

            if ($actualEntry) {
                $entryDt = new DateTime($actualEntry);
                $lateSeconds = $entryDt->getTimestamp() - $plannedStartDt->getTimestamp();
                if ($lateSeconds > $lateTol * 60) {
                    $status = 'late';
                    $alerts[] = 'LATE';
                }
            } else {
                $status = 'incomplete';
                $alerts[] = 'INCOMPLETE';
            }

            if ($summary['exit_time'] === null && $summary['entries_count'] > $summary['exits_count']) {
                if ($status === 'ok') {
                    $status = 'missing_out';
                }
                $alerts[] = 'MISSING_OUT';
            } elseif ($actualExit) {
                $exitDt = new DateTime($actualExit);
                $earlySeconds = $plannedEndDt->getTimestamp() - $exitDt->getTimestamp();
                if ($earlySeconds > $earlyTol * 60 && !in_array('MISSING_OUT', $alerts, true)) {
                    if ($status === 'ok' || $status === 'late') {
                        $status = $status === 'late' ? 'late' : 'early_leave';
                    }
                    if ($status !== 'late') {
                        $status = 'early_leave';
                    }
                    $alerts[] = 'EARLY_LEAVE';
                }
            }

            if ($summary['entries_count'] !== $summary['exits_count'] && $summary['calc_method'] !== 'paired') {
                if ($status === 'ok') {
                    $status = 'incomplete';
                }
                if (!in_array('MISSING_OUT', $alerts, true)) {
                    $alerts[] = 'INCOMPLETE';
                }
            }

            if ($status === 'late' && in_array('EARLY_LEAVE', $alerts, true)) {
                // mantener late como principal; ambos en alert_codes
            }

            if (empty($alerts)) {
                $status = 'ok';
            }
        }

        $delta = null;
        if ($planned !== null && $actualMinutes !== null) {
            $delta = $actualMinutes - $planned['planned_minutes'];
        }

        if ($controlMode === 'flexible') {
            $status = 'flexible';
            $alerts = ['FLEXIBLE_CONTROL'];
        } elseif ($controlMode === 'no_clock') {
            $status = 'no_clock';
            $alerts = ['NO_CLOCK_CONTROL'];
        }

        return $this->summaryModel->upsert([
            'company_id'      => $companyId,
            'user_id'         => $userId,
            'work_date'       => $workDate,
            'planned_minutes' => $planned['planned_minutes'] ?? null,
            'planned_start'   => $planned['planned_start'] ?? null,
            'planned_end'     => $planned['planned_end'] ?? null,
            'planned_blocks'  => $planned['planned_blocks'] ?? 0,
            'actual_minutes'  => $actualMinutes,
            'actual_entry'    => $actualEntry,
            'actual_exit'     => $actualExit,
            'delta_minutes'   => $delta,
            'status'          => $status,
            'alert_codes'     => json_encode(array_values(array_unique($alerts))),
        ]);
    }
}
