-- Relojes: dispositivo, alcance por sucursal y mapeo inequívoco dispositivo + legajo.
-- Ejecutar una vez con backup previo. No elimina las tablas históricas.

CREATE TABLE IF NOT EXISTS clock_devices (
    id INT NOT NULL AUTO_INCREMENT,
    external_name VARCHAR(160) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uk_clock_devices_external_name (external_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clock_device_branches (
    clock_device_id INT NOT NULL,
    branch_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (clock_device_id, branch_id),
    CONSTRAINT fk_clock_device_branches_device FOREIGN KEY (clock_device_id) REFERENCES clock_devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_clock_device_branches_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_branch_assignments (
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, branch_id),
    CONSTRAINT fk_employee_branch_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_branch_assignments_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_clock_device_mappings (
    user_id INT NOT NULL,
    clock_device_id INT NOT NULL,
    employee_id VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clock_device_id, employee_id),
    KEY idx_user_clock_device_mappings_user (user_id),
    CONSTRAINT fk_user_clock_device_mappings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_clock_device_mappings_device FOREIGN KEY (clock_device_id) REFERENCES clock_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Descubre los dispositivos ya importados y conserva los registros históricos.
INSERT INTO clock_devices (external_name, display_name)
SELECT DISTINCT TRIM(device_name), TRIM(device_name)
FROM marcaciones_cache
WHERE device_name IS NOT NULL AND TRIM(device_name) <> ''
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

INSERT INTO employee_branch_assignments (user_id, branch_id, is_primary)
SELECT id, branch_id, 1 FROM users WHERE branch_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_primary = 1;
