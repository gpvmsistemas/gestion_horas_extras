<?php

/**
 * Resuelve destinatarios (empleados activos) según targeting company/area/group/user.
 */
class NotificationTargetService {

    public function resolveUserIds(array $options) {
        $targetAll = !empty($options['target_all']);
        $companyIds = isset($options['company_ids']) ? array_map('intval', (array)$options['company_ids']) : [];
        $areaIds = isset($options['area_ids']) ? array_map('intval', (array)$options['area_ids']) : [];
        $employeeGroups = isset($options['employee_groups']) ? (array)$options['employee_groups'] : [];
        $userIds = isset($options['user_ids']) ? array_map('intval', (array)$options['user_ids']) : [];

        $companyIds = array_values(array_filter($companyIds));
        $areaIds = array_values(array_filter($areaIds));
        $employeeGroups = array_values(array_unique(array_filter(array_map(['User', 'normalizeOrganizationGroup'], $employeeGroups))));
        $userIds = array_values(array_filter($userIds));

        if ($targetAll) {
            return $this->getAllActiveEmployeeIds();
        }

        $ids = [];
        foreach ($companyIds as $cid) {
            $ids = array_merge($ids, $this->getEmployeeIdsByCompany($cid));
        }
        foreach ($areaIds as $aid) {
            $ids = array_merge($ids, $this->getEmployeeIdsByArea($aid));
        }
        foreach ($employeeGroups as $group) {
            $ids = array_merge($ids, $this->getEmployeeIdsByOrganizationGroup($group));
        }
        foreach ($userIds as $uid) {
            $ids[] = $uid;
        }

        $ids = array_unique(array_map('intval', $ids));
        return array_values(array_filter($ids, function ($id) {
            return $id > 0 && $this->isActiveEmployee($id);
        }));
    }

    public function targetsFromPost(array $post) {
        return [
            'target_all' => !empty($post['target_all']),
            'company_ids' => isset($post['company_ids']) ? (array)$post['company_ids'] : [],
            'area_ids' => isset($post['area_ids']) ? (array)$post['area_ids'] : [],
            'employee_groups' => isset($post['employee_groups']) ? (array)$post['employee_groups'] : [],
            'user_ids' => isset($post['user_ids']) ? (array)$post['user_ids'] : [],
        ];
    }

    private function getAllActiveEmployeeIds() {
        $db = new Database();
        $db->query("SELECT id FROM users WHERE role = 'empleado' AND is_active = 1");
        return array_map(function ($r) {
            return (int)$r->id;
        }, $db->resultSet());
    }

    private function getEmployeeIdsByCompany($companyId) {
        $db = new Database();
        $db->query("SELECT id FROM users WHERE company_id = :cid AND role = 'empleado' AND is_active = 1");
        $db->bind(':cid', (int)$companyId);
        return array_map(function ($r) {
            return (int)$r->id;
        }, $db->resultSet());
    }

    private function getEmployeeIdsByArea($areaId) {
        $areaId = (int)$areaId;
        $area = class_exists('Area') ? (new Area())->getById($areaId) : null;
        $db = new Database();
        $sql = "SELECT u.id FROM users u WHERE u.area_id = :aid AND u.role = 'empleado' AND u.is_active = 1";
        $companyId = 0;
        if ($area && $area->company_id !== null && (int)$area->company_id > 0) {
            $companyId = (int)$area->company_id;
        } elseif (function_exists('adminCompanyId') && adminCompanyId() > 0) {
            $companyId = adminCompanyId();
        }
        if ($companyId > 0) {
            $sql .= ' AND u.company_id = :cid';
        }
        $db->query($sql);
        $db->bind(':aid', $areaId);
        if ($companyId > 0) {
            $db->bind(':cid', $companyId);
        }
        return array_map(function ($r) {
            return (int)$r->id;
        }, $db->resultSet());
    }

    private function getEmployeeIdsByOrganizationGroup($group) {
        if (!(new User())->isOrganizationGroupReady()) {
            return [];
        }
        $db = new Database();
        $db->query("SELECT id FROM users WHERE employee_group = :grp AND role = 'empleado' AND is_active = 1");
        $db->bind(':grp', User::normalizeOrganizationGroup($group));
        return array_map(function ($r) {
            return (int)$r->id;
        }, $db->resultSet());
    }

    public function filterActiveEmployeeIds(array $ids) {
        return array_values(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0 && $this->isActiveEmployee($id);
        }));
    }

    /** Destinatarios elegidos en vista previa: empleados activos o cualquier usuario activo agregado manualmente. */
    public function filterRecipientIds(array $ids) {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $out = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            if ($this->isActiveEmployee($id) || $this->isActiveUser($id)) {
                $out[] = $id;
            }
        }
        return $out;
    }

    private function isActiveUser($userId) {
        $db = new Database();
        $db->query("SELECT id FROM users WHERE id = :id AND is_active = 1 AND role IN ('empleado', 'admin')");
        $db->bind(':id', (int)$userId);
        return (bool)$db->single();
    }

    private function isActiveEmployee($userId) {
        $db = new Database();
        $db->query("SELECT id FROM users WHERE id = :id AND role = 'empleado' AND is_active = 1");
        $db->bind(':id', (int)$userId);
        return (bool)$db->single();
    }
}
