-- Un empleado puede trabajar en varias sucursales de su empresa.
-- Conserva users.branch_id como sucursal principal para compatibilidad.

CREATE TABLE IF NOT EXISTS employee_branch_assignments (
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, branch_id),
    INDEX idx_eba_branch_user (branch_id, user_id),
    CONSTRAINT fk_eba_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_eba_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO employee_branch_assignments (user_id, branch_id, is_primary)
SELECT id, branch_id, 1 FROM users WHERE branch_id IS NOT NULL;
