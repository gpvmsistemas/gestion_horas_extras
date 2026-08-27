<?php
// ----------------------------------------------------------------------
// ARCHIVO: app/models/Template.php (NUEVO ARCHIVO)
// ----------------------------------------------------------------------

class Template {
    private $db;

    public function __construct(){
        $this->db = new Database;
    }

    public function getTemplatesByCompany($companyId){
        $this->db->query("SELECT * FROM schedule_templates WHERE company_id = :company_id ORDER BY template_name ASC");
        $this->db->bind(':company_id', $companyId);
        return $this->db->resultSet();
    }

    public function createTemplateFromWeek($data){
        $this->db->beginTransaction();
        try {
            // 1. Crear la plantilla principal
            $this->db->query("INSERT INTO schedule_templates (template_name, company_id) VALUES (:template_name, :company_id)");
            $this->db->bind(':template_name', $data['template_name']);
            $this->db->bind(':company_id', $data['company_id']);
            $this->db->execute();
            $templateId = $this->db->lastInsertId();

            // 2. Guardar cada entrada de horario en la plantilla
            $this->db->query("INSERT INTO schedule_template_entries (template_id, user_id, day_of_week, shift_id, start_time, end_time, type, notes) 
                             VALUES (:template_id, :user_id, :day_of_week, :shift_id, :start_time, :end_time, :type, :notes)");
            
            foreach($data['entries'] as $entry){
                $this->db->bind(':template_id', $templateId);
                $this->db->bind(':user_id', $entry->user_id);
                $this->db->bind(':day_of_week', date('N', strtotime($entry->schedule_date))); // 1=Lunes, 7=Domingo
                $this->db->bind(':shift_id', $entry->shift_id);
                $this->db->bind(':start_time', $entry->start_time);
                $this->db->bind(':end_time', $entry->end_time);
                $this->db->bind(':type', $entry->type);
                $this->db->bind(':notes', $entry->notes);
                $this->db->execute();
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function applyTemplateToWeek($templateId, $weekStartDate, $companyId, $branchId = 0){
        $this->db->query('SELECT id FROM schedule_templates WHERE id = :tid AND company_id = :cid LIMIT 1');
        $this->db->bind(':tid', (int)$templateId);
        $this->db->bind(':cid', (int)$companyId);
        if (!$this->db->single()) {
            return false;
        }

        $branchReady = (new User())->isBranchAssignmentReady() && (new WorkSchedule())->isBranchScheduleReady();
        $multipleBranches = (new User())->isMultipleBranchAssignmentsReady();
        $branchJoin = ($branchReady && (int)$branchId > 0) ? ' INNER JOIN users u ON u.id = ste.user_id' : '';
        $branchWhere = ($branchReady && (int)$branchId > 0)
            ? ($multipleBranches ? ' AND EXISTS (SELECT 1 FROM employee_branch_assignments eba WHERE eba.user_id = u.id AND eba.branch_id = :branch_id)' : ' AND u.branch_id = :branch_id')
            : '';
        $this->db->query("SELECT ste.* FROM schedule_template_entries ste
            INNER JOIN schedule_templates st ON st.id = ste.template_id
            {$branchJoin}
            WHERE ste.template_id = :template_id AND st.company_id = :company_id{$branchWhere}");
        $this->db->bind(':template_id', (int)$templateId);
        $this->db->bind(':company_id', (int)$companyId);
        if ($branchReady && (int)$branchId > 0) {
            $this->db->bind(':branch_id', (int)$branchId);
        }
        $templateEntries = $this->db->resultSet();
        
        if(empty($templateEntries)) return true; // No hay nada que aplicar

        $this->db->beginTransaction();
        try {
            // Borrar los horarios existentes de la semana para los usuarios de la plantilla
            $userIds = array_unique(array_map(function($o){ return $o->user_id; }, $templateEntries));
            $weekEndDate = date('Y-m-d', strtotime($weekStartDate . ' +6 days'));
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            
            $this->db->query("DELETE FROM employee_schedules WHERE user_id IN ($placeholders) AND schedule_date BETWEEN ? AND ?");
            $params = array_merge($userIds, [$weekStartDate, $weekEndDate]);
            $this->db->execute($params);

            // Insertar los nuevos horarios desde la plantilla
            $this->db->query($branchReady
                ? 'INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes, branch_id) VALUES (:user_id, :schedule_date, :shift_id, :start_time, :end_time, :type, :notes, :branch_id)'
                : 'INSERT INTO employee_schedules (user_id, schedule_date, shift_id, start_time, end_time, type, notes) VALUES (:user_id, :schedule_date, :shift_id, :start_time, :end_time, :type, :notes)');
            
            foreach($templateEntries as $entry){
                $date = date('Y-m-d', strtotime($weekStartDate . ' +' . ($entry->day_of_week - 1) . ' days'));
                $this->db->bind(':user_id', $entry->user_id);
                $this->db->bind(':schedule_date', $date);
                $this->db->bind(':shift_id', $entry->shift_id);
                $this->db->bind(':start_time', $entry->start_time);
                $this->db->bind(':end_time', $entry->end_time);
                $this->db->bind(':type', $entry->type);
                $this->db->bind(':notes', $entry->notes);
                if ($branchReady) {
                    $this->db->bind(':branch_id', (int)$branchId > 0 ? (int)$branchId : null);
                }
                $this->db->execute();
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function deleteTemplate($id){
        $this->db->query('DELETE FROM schedule_templates WHERE id = :id');
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function deleteTemplateForCompany($id, $companyId) {
        $this->db->query('DELETE FROM schedule_templates WHERE id = :tpl_id AND company_id = :p_company_id');
        $this->db->bind(':tpl_id', $id);
        $this->db->bind(':p_company_id', $companyId);
        return $this->db->execute();
    }
}
?>
