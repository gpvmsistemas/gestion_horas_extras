<?php
// ----------------------------------------------------------------------
// ARCHIVO 2: app/models/Company.php (NUEVO ARCHIVO)
// Este nuevo modelo manejará toda la lógica de la base de datos
// para las empresas. Debes CREAR este archivo.
// ----------------------------------------------------------------------

class Company {
    private $db;

    public function __construct(){
        $this->db = new Database;
    }

    public function getAllCompanies() {
        if ($this->locationReady()) {
            $this->db->query('SELECT c.*, cl.locality, cl.province FROM companies c
                LEFT JOIN company_locations cl ON cl.company_id = c.id ORDER BY c.name ASC');
        } else {
            $this->db->query('SELECT * FROM companies ORDER BY name ASC');
        }
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT * FROM companies WHERE id = :id');
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function getNameById($id) {
        if (!$id) {
            return null;
        }
        $row = $this->getById($id);
        return $row ? $row->name : null;
    }

    public function getIdByName($name) {
        $name = trim((string)$name);
        if ($name === '') {
            return null;
        }
        $this->db->query('SELECT id FROM companies WHERE name = :name LIMIT 1');
        $this->db->bind(':name', $name);
        $row = $this->db->single();
        return $row ? (int)$row->id : null;
    }

    public function getDefaultCompanyId() {
        $name = defined('DEFAULT_COMPANY_NAME') ? DEFAULT_COMPANY_NAME : 'Ecofarma';
        return $this->getIdByName($name);
    }

    public static function organizationGroupOptions() {
        return ['paviotti' => 'PAVIOTTI', 'moderna' => 'MODERNA'];
    }

    public static function normalizeOrganizationGroup($group) {
        $group = strtolower(trim((string)$group));
        return array_key_exists($group, self::organizationGroupOptions()) ? $group : 'paviotti';
    }

    public function organizationGroupReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $this->db->query("SHOW COLUMNS FROM `companies` LIKE 'organization_group'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    public function createCompany($name, $organizationGroup = 'paviotti'){
        if ($this->organizationGroupReady()) {
            $this->db->query('INSERT INTO companies (name, organization_group) VALUES (:name, :organization_group)');
            $this->db->bind(':organization_group', self::normalizeOrganizationGroup($organizationGroup));
        } else {
            $this->db->query('INSERT INTO companies (name) VALUES (:name)');
        }
        $this->db->bind(':name', $name);
        return $this->db->execute() ? (int)$this->db->lastInsertId() : 0;
    }

    public function updateCompany($id, $name, $showOvertime = null, $showCpExtras = null) {
        $id = (int)$id;
        $name = trim((string)$name);
        $sets = ['name = :name'];
        if ($showOvertime !== null && $this->hasShowOvertimeColumn()) {
            $sets[] = 'show_overtime = :show_overtime';
        }
        if ($showCpExtras !== null && $this->hasShowCpExtrasColumn()) {
            $sets[] = 'show_cp_extras = :show_cp_extras';
        }
        $this->db->query('UPDATE companies SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $this->db->bind(':name', $name);
        if ($showOvertime !== null && $this->hasShowOvertimeColumn()) {
            $this->db->bind(':show_overtime', $showOvertime ? 1 : 0);
        }
        if ($showCpExtras !== null && $this->hasShowCpExtrasColumn()) {
            $this->db->bind(':show_cp_extras', $showCpExtras ? 1 : 0);
        }
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }

    public function brandingReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $this->db->query("SHOW COLUMNS FROM `companies` LIKE 'brand_color'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    public function getBranding($companyId) {
        if (!$this->brandingReady()) return null;
        $this->db->query('SELECT brand_color, logo_path FROM companies WHERE id = ?');
        return $this->db->single([(int)$companyId]);
    }

    public function saveBranding($companyId, $color, $logoPath = null) {
        if (!$this->brandingReady()) return false;
        $color = strtoupper(trim((string)$color));
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) return false;
        if ($logoPath === null) {
            $this->db->query('UPDATE companies SET brand_color = ? WHERE id = ?');
            return $this->db->execute([$color, (int)$companyId]);
        }
        $this->db->query('UPDATE companies SET brand_color = ?, logo_path = ? WHERE id = ?');
        return $this->db->execute([$color, $logoPath ?: null, (int)$companyId]);
    }

    public function locationReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW TABLES LIKE 'company_locations'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public function getLocation($companyId) {
        if (!$this->locationReady()) {
            return null;
        }
        $this->db->query('SELECT locality, province FROM company_locations WHERE company_id = ?');
        return $this->db->single([(int)$companyId]);
    }

    public function saveLocation($companyId, $locality, $province) {
        if (!$this->locationReady()) {
            return false;
        }
        $locality = trim((string)$locality);
        $province = trim((string)$province);
        if ($locality === '' || $province === '') {
            $this->db->query('DELETE FROM company_locations WHERE company_id = ?');
            return $this->db->execute([(int)$companyId]);
        }
        $this->db->query('INSERT INTO company_locations (company_id, locality, province) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE locality = VALUES(locality), province = VALUES(province)');
        return $this->db->execute([(int)$companyId, $locality, $province]);
    }

    /** Sucursales operativas de una empresa, si el módulo está instalado. */
    public function branchesReady() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW TABLES LIKE 'company_branches'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    public function getBranches($companyId, $includeInactive = true) {
        if (!$this->branchesReady()) {
            return [];
        }
        $sql = 'SELECT * FROM company_branches WHERE company_id = ?';
        if (!$includeInactive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, locality ASC, name ASC';
        $this->db->query($sql);
        return $this->db->resultSet([(int)$companyId]);
    }

    /** Obtiene una sucursal de la empresa indicada; evita aceptar IDs de otra empresa. */
    public function getBranchByIdForCompany($branchId, $companyId, $activeOnly = false) {
        if (!$this->branchesReady() || (int)$branchId <= 0 || (int)$companyId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM company_branches WHERE id = ? AND company_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $this->db->query($sql);
        return $this->db->single([(int)$branchId, (int)$companyId]);
    }

    /**
     * Reemplaza el catálogo de sucursales preservando sus IDs. Las filas que
     * ya no llegan desde el formulario se desactivan, no se eliminan.
     */
    public function saveBranches($companyId, array $branches) {
        if (!$this->branchesReady()) {
            return false;
        }

        $companyId = (int)$companyId;
        $keptIds = [];
        foreach ($branches as $branch) {
            $id = (int)($branch['id'] ?? 0);
            $name = trim((string)($branch['name'] ?? ''));
            $locality = trim((string)($branch['locality'] ?? ''));
            $province = trim((string)($branch['province'] ?? ''));
            if ($name === '' && $locality === '' && $province === '') {
                continue;
            }
            if ($name === '' || $locality === '' || $province === '') {
                return false;
            }
            $active = !empty($branch['is_active']) ? 1 : 0;

            if ($id > 0) {
                $this->db->query('UPDATE company_branches SET name = ?, locality = ?, province = ?, is_active = ? WHERE id = ? AND company_id = ?');
                if (!$this->db->execute([$name, $locality, $province, $active, $id, $companyId])) {
                    return false;
                }
                $keptIds[] = $id;
            } else {
                $this->db->query('INSERT INTO company_branches (company_id, name, locality, province, is_active) VALUES (?, ?, ?, ?, ?)');
                if (!$this->db->execute([$companyId, $name, $locality, $province, $active])) {
                    return false;
                }
                $keptIds[] = (int)$this->db->lastInsertId();
            }
        }

        $this->db->query('SELECT id FROM company_branches WHERE company_id = ?');
        foreach ($this->db->resultSet([$companyId]) as $current) {
            if (!in_array((int)$current->id, $keptIds, true)) {
                $this->db->query('UPDATE company_branches SET is_active = 0 WHERE id = ? AND company_id = ?');
                if (!$this->db->execute([(int)$current->id, $companyId])) {
                    return false;
                }
            }
        }
        return true;
    }

    public function hasShowOvertimeColumn() {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $this->db->query("SHOW COLUMNS FROM `companies` LIKE 'show_overtime'");
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
            $this->db->query("SHOW COLUMNS FROM `companies` LIKE 'show_cp_extras'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }
}
?>
