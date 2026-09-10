<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/models/Request.php (VERSIÓN COMPLETA Y FINAL)
// ----------------------------------------------------------------------

class Request {
    private $db;

    public function __construct($db = null){
        $this->db = $db instanceof Database ? $db : new Database;
    }

    /**
     * Obtiene todos los tipos de solicitud desde la base de datos.
     * @return array Un array de objetos con los tipos de solicitud.
     */
    public function getRequestTypes(){
        $this->db->query("SELECT * FROM request_types ORDER BY name ASC");
        return $this->db->resultSet();
    }

    /** ID del tipo Vacaciones (no asumir siempre 1). */
    public function getVacationRequestTypeId() {
        static $id = null;
        if ($id !== null) {
            return $id ?: null;
        }
        $this->db->query("SELECT id FROM request_types
            WHERE LOWER(name) LIKE '%vacac%'
            ORDER BY id ASC LIMIT 1");
        $row = $this->db->single();
        $id = $row ? (int)$row->id : 0;
        return $id > 0 ? $id : null;
    }

    /** ID del tipo genérico Licencia (solicitudes ligadas al catálogo del convenio). */
    public function getLicenseRequestTypeId() {
        static $id = null;
        if ($id !== null) {
            return $id ?: null;
        }
        $this->db->query("SELECT id FROM request_types WHERE LOWER(name) = 'licencia' ORDER BY id ASC LIMIT 1");
        $row = $this->db->single();
        if (!$row) {
            $this->db->query("SELECT id FROM request_types WHERE LOWER(name) LIKE '%licencia%' ORDER BY id ASC LIMIT 1");
            $row = $this->db->single();
        }
        $id = $row ? (int)$row->id : 0;
        return $id > 0 ? $id : null;
    }

    public function supportsAgreementLeaveTypes() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $this->db->query("SHOW COLUMNS FROM requests LIKE 'agreement_leave_type_id'");
        $ready = (bool)$this->db->single();
        return $ready;
    }

    public function supportsCertificateBack() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $this->db->query("SHOW COLUMNS FROM requests LIKE 'certificate_back_path'");
        $ready = (bool)$this->db->single();
        return $ready;
    }

    private function requestTypeSelectSql() {
        if (!$this->supportsAgreementLeaveTypes()) {
            return 'rt.name AS type_name, rt.color';
        }
        $approvalCol = function_exists('agreement_leave_type_supports_requires_approval') && agreement_leave_type_supports_requires_approval()
            ? 'alt.requires_approval AS agreement_leave_requires_approval,'
            : 'NULL AS agreement_leave_requires_approval,';
        return "COALESCE(alt.name, rt.name) AS type_name,
                CASE WHEN alt.id IS NOT NULL THEN '#0dcaf0' ELSE rt.color END AS color,
                alt.name AS agreement_leave_name,
                alt.code AS agreement_leave_code,
                alt.requires_certificate AS agreement_leave_requires_certificate,
                {$approvalCol}
                alt.legal_reference AS agreement_leave_legal_reference,
                alt.max_days_per_year AS agreement_leave_max_days_per_year,
                alt.max_days_per_event AS agreement_leave_max_days_per_event";
    }

    private function requestLeaveTypeJoinSql() {
        if (!$this->supportsAgreementLeaveTypes()) {
            return '';
        }
        return ' LEFT JOIN collective_agreement_leave_types alt ON alt.id = r.agreement_leave_type_id';
    }

    /**
     * Crea una nueva solicitud en la base de datos.
     * @param array $data Los datos de la solicitud a crear.
     * @return bool True si se creó con éxito, false si no.
     */
    public function createRequest($data){
        $leaveTypeId = !empty($data['agreement_leave_type_id']) ? (int)$data['agreement_leave_type_id'] : 0;
        $hasCert = !empty($data['certificate_path']);
        if ($this->supportsAgreementLeaveTypes() && $leaveTypeId > 0) {
            if ($hasCert) {
                $this->db->query('INSERT INTO requests (user_id, request_type_id, agreement_leave_type_id, start_date, end_date, reason, certificate_path)
                    VALUES (:user_id, :request_type_id, :agreement_leave_type_id, :start_date, :end_date, :reason, :certificate_path)');
                $this->db->bind(':certificate_path', $data['certificate_path']);
            } else {
                $this->db->query('INSERT INTO requests (user_id, request_type_id, agreement_leave_type_id, start_date, end_date, reason)
                    VALUES (:user_id, :request_type_id, :agreement_leave_type_id, :start_date, :end_date, :reason)');
            }
            $this->db->bind(':agreement_leave_type_id', $leaveTypeId);
        } elseif ($hasCert) {
            $this->db->query('INSERT INTO requests (user_id, request_type_id, start_date, end_date, reason, certificate_path)
                VALUES (:user_id, :request_type_id, :start_date, :end_date, :reason, :certificate_path)');
            $this->db->bind(':certificate_path', $data['certificate_path']);
        } else {
            $this->db->query('INSERT INTO requests (user_id, request_type_id, start_date, end_date, reason) VALUES (:user_id, :request_type_id, :start_date, :end_date, :reason)');
        }
        $this->db->bind(':user_id', $data['user_id']);
        $this->db->bind(':request_type_id', $data['request_type_id']);
        $this->db->bind(':start_date', $data['start_date']);
        $this->db->bind(':end_date', $data['end_date']);
        $this->db->bind(':reason', $data['reason']);
        return $this->db->execute();
    }

    public function lastInsertId() {
        return $this->db->lastInsertId();
    }

    /**
     * Suma días computables de una licencia de convenio en el año calendario (pendientes + aprobadas).
     */
    public function sumAgreementLeaveDaysForYear($userId, $leaveTypeId, $year, $excludeRequestId = 0) {
        if (!$this->supportsAgreementLeaveTypes() || (int)$leaveTypeId <= 0) {
            return 0.0;
        }
        $leaveType = (new CollectiveAgreement($this->db))->getLeaveTypeById($leaveTypeId);
        if (!$leaveType) {
            return 0.0;
        }
        $user = (new User($this->db))->getUserById((int)$userId);
        $companyId = (int)($user->company_id ?? 0);
        $branchId = (int)($user->branch_id ?? 0);
        $yearStart = sprintf('%04d-01-01', (int)$year);
        $yearEnd = sprintf('%04d-12-31', (int)$year);
        $this->db->query("SELECT r.id, r.start_date, r.end_date
            FROM requests r
            WHERE r.user_id = :uid
              AND r.agreement_leave_type_id = :lid
              AND r.status IN ('Pendiente', 'Aprobado')
              AND r.id <> :exclude
              AND r.start_date <= :yend
              AND IFNULL(r.end_date, r.start_date) >= :ystart");
        $this->db->bind(':uid', (int)$userId);
        $this->db->bind(':lid', (int)$leaveTypeId);
        $this->db->bind(':exclude', (int)$excludeRequestId);
        $this->db->bind(':yend', $yearEnd);
        $this->db->bind(':ystart', $yearStart);
        $total = 0.0;
        foreach ($this->db->resultSet() as $row) {
            $rangeStart = $row->start_date < $yearStart ? $yearStart : $row->start_date;
            $rangeEnd = ($row->end_date ?: $row->start_date) > $yearEnd ? $yearEnd : ($row->end_date ?: $row->start_date);
            $total += vacation_count_days_in_range(
                $rangeStart,
                $rangeEnd,
                $leaveType->day_count_mode ?? 'calendar',
                $companyId,
                $this->db,
                $branchId
            );
        }
        return $total;
    }

    /**
     * Obtiene todas las solicitudes de un usuario específico.
     * @param int $userId El ID del usuario.
     * @return array Un array de objetos con las solicitudes del usuario.
     */
    public function getRequestsByUserId($userId){
        $this->db->query("
            SELECT r.*, {$this->requestTypeSelectSql()}
            FROM requests r
            JOIN request_types rt ON r.request_type_id = rt.id
            {$this->requestLeaveTypeJoinSql()}
            WHERE r.user_id = :user_id 
            ORDER BY r.start_date DESC
        ");
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }
    
    /**
     * Obtiene todas las solicitudes de todos los usuarios.
     * @return array Un array de objetos con todas las solicitudes.
     */
    public function getAllRequests(){
        return $this->getAllRequestsByCompany(null);
    }

    public function getAllRequestsByCompany($companyId) {
        $sql = "SELECT r.*, u.full_name, u.profile_picture, {$this->requestTypeSelectSql()}
                FROM requests r
                JOIN users u ON r.user_id = u.id
                JOIN request_types rt ON r.request_type_id = rt.id
                {$this->requestLeaveTypeJoinSql()}";
        if ($companyId !== null) {
            $sql .= " WHERE u.company_id = :company_id";
        }
        $sql .= " ORDER BY FIELD(r.status, 'Pendiente', 'Aprobado', 'Rechazado'), r.start_date DESC";
        $this->db->query($sql);
        if ($companyId !== null) {
            $this->db->bind(':company_id', $companyId);
        }
        return $this->db->resultSet();
    }

    /** Versión multi-empresa (contexto "Todas las empresas del grupo"). */
    public function getAllRequestsByCompanies(array $companyIds) {
        $in = implode(',', array_map('intval', $companyIds ?: [0]));
        $sql = "SELECT r.*, u.full_name, u.profile_picture, {$this->requestTypeSelectSql()}, c.name AS company_name
                FROM requests r
                JOIN users u ON r.user_id = u.id
                JOIN request_types rt ON r.request_type_id = rt.id
                LEFT JOIN companies c ON c.id = u.company_id
                {$this->requestLeaveTypeJoinSql()}
                WHERE u.company_id IN ($in)
                ORDER BY FIELD(r.status, 'Pendiente', 'Aprobado', 'Rechazado'), r.start_date DESC";
        $this->db->query($sql);
        return $this->db->resultSet();
    }

    /** Una solicitud si pertenece a alguna de las empresas del contexto. */
    public function getRequestByIdForCompanies($id, array $companyIds) {
        $in = implode(',', array_map('intval', $companyIds ?: [0]));
        $this->db->query("SELECT r.*, u.full_name, u.company_id, {$this->requestTypeSelectSql()}
                FROM requests r
                JOIN users u ON r.user_id = u.id
                JOIN request_types rt ON r.request_type_id = rt.id
                {$this->requestLeaveTypeJoinSql()}
                WHERE r.id = :id AND u.company_id IN ($in)");
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    private static function pendingQueueWhere() {
        return "r.status = 'Pendiente' AND r.admin_dismissed_at IS NULL";
    }
    
    /**
     * Obtiene una única solicitud por su ID.
     * @param int $id El ID de la solicitud.
     * @return object|false El objeto de la solicitud o false si no se encuentra.
     */
    public function getRequestById($id){
        $this->db->query("
            SELECT r.*, u.full_name, u.profile_picture, u.company_id, {$this->requestTypeSelectSql()}
            FROM requests r
            JOIN users u ON r.user_id = u.id
            JOIN request_types rt ON r.request_type_id = rt.id
            {$this->requestLeaveTypeJoinSql()}
            WHERE r.id = :id
        ");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function getRequestByIdForUpdate($id) {
        $this->db->query("SELECT r.*, u.company_id, rt.name AS type_name
            FROM requests r
            JOIN users u ON u.id = r.user_id
            JOIN request_types rt ON rt.id = r.request_type_id
            WHERE r.id = :id FOR UPDATE");
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function saveVacationReview($id, $days, array $snapshot, $exceptionReason = '', $adminId = 0) {
        $this->db->query('UPDATE requests SET vacation_counted_days = :days,
            vacation_rule_snapshot = :snapshot, vacation_exception_reason = :reason,
            vacation_exception_by = :exception_by, vacation_exception_at = :exception_at
            WHERE id = :id');
        $this->db->bind(':days', (float)$days);
        $this->db->bind(':snapshot', json_encode($snapshot, JSON_UNESCAPED_UNICODE));
        $this->db->bind(':reason', $exceptionReason !== '' ? $exceptionReason : null);
        $this->db->bind(':exception_by', $exceptionReason !== '' ? (int)$adminId : null);
        $this->db->bind(':exception_at', $exceptionReason !== '' ? date('Y-m-d H:i:s') : null);
        $this->db->bind(':id', (int)$id);
        return $this->db->execute();
    }

    public function getRequestByIdForCompany($id, $companyId) {
        $this->db->query("
            SELECT r.*, u.full_name, u.profile_picture, rt.name AS type_name, rt.color
            FROM requests r
            JOIN users u ON r.user_id = u.id
            JOIN request_types rt ON r.request_type_id = rt.id
            WHERE r.id = :id AND u.company_id = :company_id
        ");
        $this->db->bind(':id', $id);
        $this->db->bind(':company_id', $companyId);
        return $this->db->single();
    }

    /**
     * Actualiza una solicitud existente.
     * @param array $data Los nuevos datos de la solicitud.
     * @return bool True si se actualizó con éxito, false si no.
     */
    public function updateRequest($data){
        $this->db->query('UPDATE requests SET request_type_id = :request_type_id, start_date = :start_date, end_date = :end_date, reason = :reason, status = :status WHERE id = :id');
        $this->db->bind(':id', $data['id']);
        $this->db->bind(':request_type_id', $data['request_type_id']);
        $this->db->bind(':start_date', $data['start_date']);
        $this->db->bind(':end_date', $data['end_date']);
        $this->db->bind(':reason', $data['reason']);
        $this->db->bind(':status', $data['status']);
        return $this->db->execute();
    }

    /**
     * Actualiza solo el estado de una solicitud (Aprobado/Rechazado).
     * @param int $id El ID de la solicitud.
     * @param string $status El nuevo estado.
     * @return bool True si se actualizó con éxito, false si no.
     */
    public function updateRequestStatus($id, $status){
        $this->db->query('UPDATE requests SET status = :status, admin_dismissed_at = NULL WHERE id = :id');
        $this->db->bind(':id', $id);
        $this->db->bind(':status', $status);
        return $this->db->execute();
    }

    public function dismissFromQueue($id) {
        $this->db->query('UPDATE requests SET admin_dismissed_at = NOW() WHERE id = :id');
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function updateAdminMeta($id, array $data) {
        $sets = [];
        if (array_key_exists('admin_notes', $data)) {
            $sets[] = 'admin_notes = :admin_notes';
        }
        if (array_key_exists('certificate_path', $data)) {
            $sets[] = 'certificate_path = :certificate_path';
        }
        if ($this->supportsCertificateBack() && array_key_exists('certificate_back_path', $data)) {
            $sets[] = 'certificate_back_path = :certificate_back_path';
        }
        if (empty($sets)) {
            return true;
        }
        $this->db->query('UPDATE requests SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $this->db->bind(':id', $id);
        if (array_key_exists('admin_notes', $data)) {
            $this->db->bind(':admin_notes', $data['admin_notes']);
        }
        if (array_key_exists('certificate_path', $data)) {
            $this->db->bind(':certificate_path', $data['certificate_path']);
        }
        if ($this->supportsCertificateBack() && array_key_exists('certificate_back_path', $data)) {
            $this->db->bind(':certificate_back_path', $data['certificate_back_path']);
        }
        return $this->db->execute();
    }

    /**
     * Elimina una solicitud por su ID.
     * @param int $id El ID de la solicitud.
     * @return bool True si se eliminó con éxito, false si no.
     */
    public function deleteRequest($id){
        $this->db->query('DELETE FROM requests WHERE id = :id');
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function getActiveApprovedRequestsForToday(){
        $this->db->query("
            SELECT r.id, u.full_name, u.profile_picture, rt.name as type_name
            FROM requests r
            JOIN users u ON r.user_id = u.id
            JOIN request_types rt ON r.request_type_id = rt.id
            WHERE r.status = 'Aprobado'
            AND CURDATE() BETWEEN r.start_date AND IFNULL(r.end_date, r.start_date)
        ");
        return $this->db->resultSet();
    }


    public function getApprovedRequestsForUserCalendar($userId){
        $this->db->query("
            SELECT r.*, rt.name as type_name, rt.color 
            FROM requests r
            JOIN request_types rt ON r.request_type_id = rt.id
            WHERE r.user_id = :user_id AND r.status = 'Aprobado'
        ");
        $this->db->bind(':user_id', $userId);
        return $this->db->resultSet();
    }



    public function getMonthlyRequestSummary($companyId, $month) {
        // CORRECCIÓN: Se cambió 'GROUP BY rt.type_name' por 'GROUP BY rt.name'
        $sql = "SELECT rt.name as type_name, COUNT(r.id) as count
                FROM requests r
                JOIN request_types rt ON r.request_type_id = rt.id
                JOIN users u ON r.user_id = u.id
                WHERE u.company_id = :company_id AND r.status = 'Aprobado' AND DATE_FORMAT(r.start_date, '%Y-%m') = :month
                GROUP BY rt.name"; // <-- La corrección está aquí
        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':month', $month);
        return $this->db->resultSet();
    }

    /**
     * Obtiene las solicitudes aprobadas para el planificador.
     */
    public function getApprovedRequestsForPeriod($startDate, $endDate, $companyId, $branchId = null) {
        $userModel = new User();
        $branchReady = $userModel->isBranchAssignmentReady();
        $multipleBranchReady = $userModel->isMultipleBranchAssignmentsReady();
        $branchWhere = $multipleBranchReady && (int)$branchId > 0
            ? ' AND EXISTS (SELECT 1 FROM employee_branch_assignments eba WHERE eba.user_id = u.id AND eba.branch_id = :branch_id)'
            : (($branchReady && (int)$branchId > 0) ? ' AND u.branch_id = :branch_id' : '');
        $sql = "SELECT 
                    r.*, 
                    u.full_name,
                    rt.name as type_name,
                    rt.color
                FROM requests r
                JOIN users u ON r.user_id = u.id
                JOIN request_types rt ON r.request_type_id = rt.id
                WHERE u.company_id = :company_id
                AND r.status = 'Aprobado'
                AND r.start_date <= :end_date 
                AND IFNULL(r.end_date, r.start_date) >= :start_date{$branchWhere}";

        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        $this->db->bind(':start_date', $startDate);
        $this->db->bind(':end_date', $endDate);
        if (($branchReady || $multipleBranchReady) && (int)$branchId > 0) {
            $this->db->bind(':branch_id', (int)$branchId);
        }
        
        return $this->db->resultSet();
    }

    public function countOnLeaveTodayByCompany($companyId) {
    $sql = "SELECT COUNT(DISTINCT r.user_id) as count
            FROM requests r
            JOIN users u ON r.user_id = u.id
            WHERE u.company_id = :company_id 
            AND r.status = 'Aprobado'
            AND CURDATE() BETWEEN r.start_date AND IFNULL(r.end_date, r.start_date)";
    $this->db->query($sql);
    $this->db->bind(':company_id', $companyId);
    $row = $this->db->single();
    return $row ? $row->count : 0;
}

public function countPendingByCompany($companyId) {
        $where = self::pendingQueueWhere();
        $this->db->query("
            SELECT COUNT(r.id) AS cnt
            FROM requests r
            JOIN users u ON r.user_id = u.id
            WHERE u.company_id = :company_id AND {$where}
        ");
        $this->db->bind(':company_id', $companyId);
        $row = $this->db->single();
        return $row ? (int)$row->cnt : 0;
    }

    public function getPendingRequestsWithDetails($companyId) {
        $where = self::pendingQueueWhere();
        $sql = "SELECT r.id, r.reason, r.certificate_path, r.admin_notes, r.admin_dismissed_at,
                       u.full_name, u.profile_picture, rt.name AS type_name,
                       r.start_date, r.end_date
                FROM requests r
                JOIN users u ON r.user_id = u.id
                JOIN request_types rt ON r.request_type_id = rt.id
                WHERE u.company_id = :company_id AND {$where}
                ORDER BY r.start_date ASC";
        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        return $this->db->resultSet();
    }
    

}
?>
