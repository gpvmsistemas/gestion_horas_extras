-- Sucursales de Ecofarma y su ubicación para aplicar futuras reglas locales.
-- MariaDB 10.4+; ejecutar con backup previo.

CREATE TABLE IF NOT EXISTS company_branches (
    id INT NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    locality VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_company_branch_name (company_id, name),
    INDEX idx_company_branches_geography (company_id, province, locality),
    CONSTRAINT fk_company_branches_company
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO company_branches (company_id, name, locality, province, is_active)
SELECT c.id, seed.name, seed.locality, 'Córdoba', 1
FROM companies c
INNER JOIN (
    SELECT 'Ecofarma Central' AS name, 'Villa María' AS locality
    UNION ALL SELECT 'Ecofarma Azul', 'Villa María'
    UNION ALL SELECT 'Ecofarma Cruz Verde Central', 'San Francisco'
    UNION ALL SELECT 'Ecofarma Cruz Verde Catedral', 'San Francisco'
    UNION ALL SELECT 'Ecofarma Cruz Verde Jujuy', 'San Francisco'
    UNION ALL SELECT 'Ecofarma Dermolife', 'San Francisco'
) AS seed
WHERE c.name = 'Ecofarma'
ON DUPLICATE KEY UPDATE locality = VALUES(locality), province = VALUES(province), is_active = VALUES(is_active);
