<?php

class AttendanceDaySummary {
    private $db;

    public function __construct() {
        $this->db = new Database;
    }

    public function isSchemaReady() {
        try {
            $this->db->query("SHOW TABLES LIKE 'attendance_day_summary'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function upsert(array $row) {
        $this->db->query('INSERT INTO attendance_day_summary (
            company_id, user_id, work_date,
            planned_minutes, planned_start, planned_end, planned_blocks,
            actual_minutes, actual_entry, actual_exit, delta_minutes,
            status, alert_codes
        ) VALUES (
            :company_id, :user_id, :work_date,
            :planned_minutes, :planned_start, :planned_end, :planned_blocks,
            :actual_minutes, :actual_entry, :actual_exit, :delta_minutes,
            :status, :alert_codes
        ) ON DUPLICATE KEY UPDATE
            planned_minutes = VALUES(planned_minutes),
            planned_start = VALUES(planned_start),
            planned_end = VALUES(planned_end),
            planned_blocks = VALUES(planned_blocks),
            actual_minutes = VALUES(actual_minutes),
            actual_entry = VALUES(actual_entry),
            actual_exit = VALUES(actual_exit),
            delta_minutes = VALUES(delta_minutes),
            status = VALUES(status),
            alert_codes = VALUES(alert_codes),
            computed_at = CURRENT_TIMESTAMP');

        $this->db->bind(':company_id', $row['company_id']);
        $this->db->bind(':user_id', $row['user_id']);
        $this->db->bind(':work_date', $row['work_date']);
        $this->db->bind(':planned_minutes', $row['planned_minutes']);
        $this->db->bind(':planned_start', $row['planned_start']);
        $this->db->bind(':planned_end', $row['planned_end']);
        $this->db->bind(':planned_blocks', $row['planned_blocks']);
        $this->db->bind(':actual_minutes', $row['actual_minutes']);
        $this->db->bind(':actual_entry', $row['actual_entry']);
        $this->db->bind(':actual_exit', $row['actual_exit']);
        $this->db->bind(':delta_minutes', $row['delta_minutes']);
        $this->db->bind(':status', $row['status']);
        $this->db->bind(':alert_codes', $row['alert_codes']);
        return $this->db->execute();
    }

    public function hasRowsForDate($companyId, $workDate) {
        $this->db->query('SELECT 1 FROM attendance_day_summary
                          WHERE company_id = :company_id AND work_date = :work_date LIMIT 1');
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':work_date', $workDate);
        $this->db->single();
        return $this->db->rowCount() > 0;
    }

    public function getDashboardSummary($companyId, $workDate) {
        $this->db->query('SELECT
                COUNT(*) AS total_rows,
                SUM(status = "ok") AS count_ok,
                SUM(status = "on_leave") AS count_leave,
                SUM(status NOT IN ("ok", "on_leave", "flexible", "no_clock")) AS count_alerts
            FROM attendance_day_summary
            WHERE company_id = :company_id AND work_date = :work_date');
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':work_date', $workDate);
        return $this->db->single();
    }

    public function getAlertsForDate($companyId, $workDate, $limit = 20) {
        $this->db->query('SELECT ads.*, u.full_name, u.profile_picture
            FROM attendance_day_summary ads
            JOIN users u ON ads.user_id = u.id
            WHERE ads.company_id = :company_id
              AND ads.work_date = :work_date
              AND ads.status NOT IN ("ok", "on_leave", "flexible", "no_clock")
            ORDER BY FIELD(ads.status, "no_show", "missing_out", "late", "early_leave", "incomplete", "unplanned_clocking"),
                     u.full_name ASC
            LIMIT ' . (int)$limit);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':work_date', $workDate);
        return $this->db->resultSet();
    }

    public function getAllForDate($companyId, $workDate, $statusFilter = '') {
        $sql = 'SELECT ads.*, u.full_name, u.profile_picture
            FROM attendance_day_summary ads
            JOIN users u ON ads.user_id = u.id
            WHERE ads.company_id = :company_id AND ads.work_date = :work_date';

        if ($statusFilter === 'alerts') {
            $sql .= ' AND ads.status NOT IN ("ok", "on_leave", "flexible", "no_clock")';
        } elseif ($statusFilter !== '' && $statusFilter !== 'all') {
            $sql .= ' AND ads.status = :status';
        }

        $sql .= ' ORDER BY ads.status <> "ok", u.full_name ASC';

        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':work_date', $workDate);
        if ($statusFilter !== '' && $statusFilter !== 'all' && $statusFilter !== 'alerts') {
            $this->db->bind(':status', $statusFilter);
        }
        return $this->db->resultSet();
    }

    /** Resumen mensual por empleado para export / reportes. */
    public function getMonthReportByCompany($companyId, $month) {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [];
        }
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $this->db->query('SELECT ads.user_id, u.full_name,
                SUM(ads.status = "ok") AS days_ok,
                SUM(ads.status = "late") AS days_late,
                SUM(ads.status = "no_show") AS days_no_show,
                SUM(ads.status = "on_leave") AS days_leave,
                SUM(ads.status NOT IN ("ok", "on_leave")) AS days_alerts,
                COUNT(*) AS days_total
            FROM attendance_day_summary ads
            JOIN users u ON u.id = ads.user_id
            WHERE ads.company_id = :company_id
              AND ads.work_date >= :start_date
              AND ads.work_date <= :end_date
              AND u.is_active = 1
              AND u.role = \'empleado\'
            GROUP BY ads.user_id, u.full_name
            ORDER BY u.full_name ASC');
        $this->db->bind(':company_id', (int)$companyId);
        $this->db->bind(':start_date', $start);
        $this->db->bind(':end_date', $end);
        return $this->db->resultSet();
    }

    public function deleteOutsideRange($companyId, $startDate, $endDate) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$startDate)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$endDate)) {
            return false;
        }
        $this->db->query('DELETE FROM attendance_day_summary
            WHERE company_id = :company_id
              AND (work_date < :start_date OR work_date > :end_date)');
        $this->db->bind(':company_id', (int)$companyId);
        $this->db->bind(':start_date', $startDate);
        $this->db->bind(':end_date', $endDate);
        return $this->db->execute();
    }
}
