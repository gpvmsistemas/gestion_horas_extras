<?php

class HrAlertsService {
    private $attendanceModel;
    private $requestModel;
    private $marcacionesCache;
    private $userModel;
    private $employeeIncidentModel;
    private $entitlement;

    public function __construct() {
        $this->attendanceModel = new AttendanceDaySummary();
        $this->requestModel = new Request();
        $this->marcacionesCache = new MarcacionesCache();
        $this->userModel = new User();
        $this->employeeIncidentModel = new EmployeeIncident();
        $this->entitlement = new VacationEntitlementService();
    }

    public function buildDashboard($companyId, $workDate = null) {
        $companyId = (int)$companyId;
        $workDate = $workDate ?: date('Y-m-d');

        $attendanceAlerts = [];
        $attendanceStats = null;
        if ($this->attendanceModel->isSchemaReady()) {
            try {
                $plan = new PlanVsActualService();
                if (!$this->attendanceModel->hasRowsForDate($companyId, $workDate)) {
                    $plan->recomputeRange($companyId, $workDate, $workDate);
                }
                $attendanceStats = $this->attendanceModel->getDashboardSummary($companyId, $workDate);
                $attendanceAlerts = $this->attendanceModel->getAlertsForDate($companyId, $workDate, 30);
            } catch (Throwable $e) {
                $attendanceAlerts = [];
            }
        }

        $pendingRequests = $this->requestModel->getPendingRequestsWithDetails($companyId);
        $pendingRequests = filterStaffRowsByUserArea($pendingRequests);

        $unmappedLegajos = $this->marcacionesCache->getUnmappedLegajosGrouped($companyId, 90);
        $unmappedCount = count($unmappedLegajos);

        $recentIncidents = [];
        if ($this->employeeIncidentModel->isSchemaReady()) {
            $recentIncidents = $this->employeeIncidentModel->getRecentByCompany($companyId, 8);
        }

        $vacationAlerts = $this->entitlement->getCompanyVacationAlerts($companyId);

        $octoberReminder = ((int)date('n') >= 10);

        return [
            'work_date' => $workDate,
            'attendance_stats' => $attendanceStats,
            'attendance_alerts' => filterStaffRowsByUserArea($attendanceAlerts),
            'pending_requests' => $pendingRequests,
            'pending_requests_count' => count($pendingRequests),
            'unmapped_legajos' => $unmappedLegajos,
            'unmapped_count' => $unmappedCount,
            'recent_incidents' => $recentIncidents,
            'vacation_alerts' => $vacationAlerts,
            'october_liquidation_reminder' => $octoberReminder,
        ];
    }
}
