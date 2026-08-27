<?php

/** Dispositivos de fichada y su alcance organizacional. */
class ClockDevice {
    private $db;

    public function __construct() { $this->db = new Database; }

    public function isReady() {
        static $ready = null;
        if ($ready !== null) return $ready;
        try {
            $this->db->query("SHOW TABLES LIKE 'clock_devices'");
            $ready = (bool)$this->db->single();
        } catch (Throwable $e) { $ready = false; }
        return $ready;
    }

    public function getOrCreate($externalName) {
        if (!$this->isReady()) return null;
        $externalName = trim((string)$externalName);
        if ($externalName === '') return null;
        $this->db->query('INSERT INTO clock_devices (external_name, display_name) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
        $this->db->execute([$externalName, $externalName]);
        return (int)$this->db->lastInsertId();
    }

    public function getAllWithScopes() {
        if (!$this->isReady()) return [];
        $this->db->query('SELECT cd.*, GROUP_CONCAT(CONCAT(c.name, " — ", cb.name) ORDER BY c.name, cb.name SEPARATOR " | ") AS scopes
            FROM clock_devices cd
            LEFT JOIN clock_device_branches cdb ON cdb.clock_device_id = cd.id AND cdb.is_active = 1
            LEFT JOIN company_branches cb ON cb.id = cdb.branch_id
            LEFT JOIN companies c ON c.id = cb.company_id
            GROUP BY cd.id ORDER BY cd.is_active DESC, cd.display_name ASC');
        return $this->db->resultSet();
    }

    public function getById($id) {
        $this->db->query('SELECT * FROM clock_devices WHERE id = ?');
        return $this->db->single([(int)$id]);
    }

    public function getByExternalName($externalName) {
        if (!$this->isReady()) return null;
        $this->db->query('SELECT * FROM clock_devices WHERE external_name = ?');
        return $this->db->single([trim((string)$externalName)]);
    }

    public function getBranchesForDevice($deviceId, $companyId = null) {
        if (!$this->isReady()) return [];
        $sql = 'SELECT cb.*, c.name AS company_name FROM clock_device_branches cdb
                JOIN company_branches cb ON cb.id = cdb.branch_id
                JOIN companies c ON c.id = cb.company_id
                WHERE cdb.clock_device_id = ? AND cdb.is_active = 1';
        $params = [(int)$deviceId];
        if ((int)$companyId > 0) { $sql .= ' AND cb.company_id = ?'; $params[] = (int)$companyId; }
        $sql .= ' ORDER BY c.name, cb.name';
        $this->db->query($sql);
        return $this->db->resultSet($params);
    }

    public function save($id, $externalName, $displayName, $branchIds, $isActive = true) {
        $externalName = trim((string)$externalName);
        $displayName = trim((string)$displayName) ?: $externalName;
        if ($externalName === '') return false;
        if ((int)$id > 0) {
            $this->db->query('UPDATE clock_devices SET external_name = ?, display_name = ?, is_active = ? WHERE id = ?');
            if (!$this->db->execute([$externalName, $displayName, $isActive ? 1 : 0, (int)$id])) return false;
            $id = (int)$id;
        } else {
            $this->db->query('INSERT INTO clock_devices (external_name, display_name, is_active) VALUES (?, ?, ?)');
            if (!$this->db->execute([$externalName, $displayName, $isActive ? 1 : 0])) return false;
            $id = (int)$this->db->lastInsertId();
        }
        $branchIds = array_values(array_unique(array_filter(array_map('intval', (array)$branchIds))));
        $this->db->query('UPDATE clock_device_branches SET is_active = 0 WHERE clock_device_id = ?');
        $this->db->execute([$id]);
        foreach ($branchIds as $branchId) {
            $this->db->query('INSERT INTO clock_device_branches (clock_device_id, branch_id, is_active) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE is_active = 1');
            if (!$this->db->execute([$id, $branchId])) return false;
        }
        return true;
    }
}
