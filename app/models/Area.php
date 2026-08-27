<?php

class Area {
    private $db;
    private static $globalReady = null;

    public function __construct($db = null) {
        $this->db = $db instanceof Database ? $db : new Database();
    }

    public function isSchemaReady() {
        try {
            $this->db->query("SHOW TABLES LIKE 'areas'");
            return (bool)$this->db->single();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** company_id NULL permitido (área para todas las empresas). */
    public function isGlobalAreasReady() {
        if (self::$globalReady !== null) {
            return self::$globalReady;
        }
        try {
            $this->db->query("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'areas' AND COLUMN_NAME = 'company_id'");
            $row = $this->db->single();
            self::$globalReady = $row && strtoupper((string)$row->IS_NULLABLE) === 'YES';
        } catch (Throwable $e) {
            self::$globalReady = false;
        }
        return self::$globalReady;
    }

    public static function scopeLabel($area) {
        if (!$area) {
            return '';
        }
        if ($area->company_id === null || $area->company_id === '' || (int)$area->company_id === 0) {
            return 'Todas las empresas';
        }
        return !empty($area->company_name) ? $area->company_name : ('Empresa #' . (int)$area->company_id);
    }

    public function getAll($activeOnly = true) {
        $sql = 'SELECT a.*, c.name AS company_name
            FROM areas a
            LEFT JOIN companies c ON c.id = a.company_id
            WHERE 1=1';
        if ($activeOnly) {
            $sql .= ' AND a.is_active = 1';
        }
        $sql .= ' ORDER BY (a.company_id IS NULL) DESC, c.name ASC, a.name ASC';
        $this->db->query($sql);
        return $this->db->resultSet();
    }

    /**
     * Áreas que puede usar un empleado de la empresa: globales + las de su empresa.
     */
    public function getAvailableForCompany($companyId, $activeOnly = true) {
        $companyId = (int)$companyId;
        if (!$this->isGlobalAreasReady()) {
            return $this->getLegacyByCompany($companyId, $activeOnly);
        }
        $sql = 'SELECT a.*, c.name AS company_name
            FROM areas a
            LEFT JOIN companies c ON c.id = a.company_id
            WHERE (a.company_id IS NULL OR a.company_id = :company_id)';
        if ($activeOnly) {
            $sql .= ' AND a.is_active = 1';
        }
        $sql .= ' ORDER BY (a.company_id IS NULL) DESC, a.name ASC';
        $this->db->query($sql);
        $this->db->bind(':company_id', $companyId);
        return $this->db->resultSet();
    }

    /** @deprecated use getAvailableForCompany */
    public function getByCompany($companyId, $activeOnly = true) {
        return $this->getAvailableForCompany($companyId, $activeOnly);
    }

    private function getLegacyByCompany($companyId, $activeOnly = true) {
        $sql = 'SELECT a.*, c.name AS company_name
            FROM areas a
            LEFT JOIN companies c ON c.id = a.company_id
            WHERE a.company_id = :company_id';
        if ($activeOnly) {
            $sql .= ' AND a.is_active = 1';
        }
        $sql .= ' ORDER BY a.name ASC';
        $this->db->query($sql);
        $this->db->bind(':company_id', (int)$companyId);
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT a.*, c.name AS company_name FROM areas a
            LEFT JOIN companies c ON c.id = a.company_id WHERE a.id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->single();
    }

    public function isAvailableForCompany($areaId, $companyId) {
        $area = $this->getById($areaId);
        if (!$area || !(int)$area->is_active) {
            return false;
        }
        if (!$this->isGlobalAreasReady()) {
            return (int)$area->company_id === (int)$companyId;
        }
        return $area->company_id === null || (int)$area->company_id === (int)$companyId;
    }

    public function nameExists($name, $companyId, $excludeId = 0) {
        $name = trim($name);
        $sql = 'SELECT id FROM areas WHERE name = :name AND ';
        if ($companyId === null || (int)$companyId <= 0) {
            $sql .= 'company_id IS NULL';
        } else {
            $sql .= 'company_id = :company_id';
        }
        if ($excludeId > 0) {
            $sql .= ' AND id != :exclude_id';
        }
        $this->db->query($sql);
        $this->db->bind(':name', $name);
        if ($companyId !== null && (int)$companyId > 0) {
            $this->db->bind(':company_id', (int)$companyId);
        }
        if ($excludeId > 0) {
            $this->db->bind(':exclude_id', (int)$excludeId);
        }
        return (bool)$this->db->single();
    }

    public function create($name, $companyId = null) {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        if ($this->isGlobalAreasReady()) {
            $cid = ($companyId === null || (int)$companyId <= 0) ? null : (int)$companyId;
            if ($this->nameExists($name, $cid)) {
                return false;
            }
            if ($cid === null) {
                $this->db->query('INSERT INTO areas (company_id, name) VALUES (NULL, :name)');
            } else {
                $this->db->query('INSERT INTO areas (company_id, name) VALUES (:company_id, :name)');
                $this->db->bind(':company_id', $cid);
            }
            $this->db->bind(':name', $name);
        } else {
            $cid = (int)$companyId;
            if ($cid <= 0) {
                return false;
            }
            if ($this->nameExists($name, $cid)) {
                return false;
            }
            $this->db->query('INSERT INTO areas (company_id, name) VALUES (:company_id, :name)');
            $this->db->bind(':company_id', $cid);
            $this->db->bind(':name', $name);
        }
        if ($this->db->execute()) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }

    public function hasShowOvertimeColumn() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW COLUMNS FROM `areas` LIKE 'show_overtime'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public function hasShowCpExtrasColumn() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW COLUMNS FROM `areas` LIKE 'show_cp_extras'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public function update($id, $name, $isActive, $companyId = false, $showOvertime = false, $showCpExtras = false) {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $area = $this->getById($id);
        if (!$area) {
            return false;
        }

        $newCompanyId = $area->company_id;
        if ($companyId !== false) {
            if ($this->isGlobalAreasReady()) {
                $newCompanyId = ($companyId === null || (int)$companyId <= 0) ? null : (int)$companyId;
            }
        }

        $checkCompany = $newCompanyId === null || $newCompanyId === '' ? null : (int)$newCompanyId;
        if ($this->nameExists($name, $checkCompany, (int)$id)) {
            return false;
        }

        $sql = 'UPDATE areas SET name = :name, is_active = :is_active';
        if ($companyId !== false && $this->isGlobalAreasReady()) {
            $sql .= ', company_id = :company_id';
        }
        if ($showOvertime !== false && $this->hasShowOvertimeColumn()) {
            $sql .= ', show_overtime = :show_overtime';
        }
        if ($showCpExtras !== false && $this->hasShowCpExtrasColumn()) {
            $sql .= ', show_cp_extras = :show_cp_extras';
        }
        $sql .= ' WHERE id = :id';

        $this->db->query($sql);
        $this->db->bind(':name', $name);
        $this->db->bind(':is_active', $isActive ? 1 : 0);
        if ($showOvertime !== false && $this->hasShowOvertimeColumn()) {
            if ($showOvertime === null || $showOvertime === '') {
                $this->db->bind(':show_overtime', null, PDO::PARAM_NULL);
            } else {
                $this->db->bind(':show_overtime', $showOvertime ? 1 : 0);
            }
        }
        if ($showCpExtras !== false && $this->hasShowCpExtrasColumn()) {
            if ($showCpExtras === null || $showCpExtras === '') {
                $this->db->bind(':show_cp_extras', null, PDO::PARAM_NULL);
            } else {
                $this->db->bind(':show_cp_extras', $showCpExtras ? 1 : 0);
            }
        }
        if ($companyId !== false && $this->isGlobalAreasReady()) {
            if ($newCompanyId === null) {
                $this->db->bind(':company_id', null, PDO::PARAM_NULL);
            } else {
                $this->db->bind(':company_id', (int)$newCompanyId);
            }
        }
        $this->db->bind(':id', (int)$id);
        return $this->db->execute();
    }

    public function delete($id) {
        $this->db->query('UPDATE areas SET is_active = 0 WHERE id = :id');
        $this->db->bind(':id', (int)$id);
        return $this->db->execute();
    }
}
