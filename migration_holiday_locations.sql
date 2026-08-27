-- Ubicaciones de empresas y reglas de feriados por alcance geografico.
-- MariaDB 10.4+; ejecutar con backup previo.

CREATE TABLE IF NOT EXISTS company_locations (
    company_id INT NOT NULL,
    locality VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    PRIMARY KEY (company_id),
    CONSTRAINT fk_company_locations_company
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_locations_geography (province, locality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holiday_rules (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    month_day CHAR(5) NOT NULL,
    scope_type ENUM('national', 'province', 'locality') NOT NULL,
    province VARCHAR(120) NULL,
    locality VARCHAR(120) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_holiday_rule_scope_date (scope_type, province, locality, month_day),
    INDEX idx_holiday_rules_scope (is_active, scope_type, province, locality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anniversary of Villa Maria (Cordoba). Hex literals preserve UTF-8 independently of the SQL console encoding.
UPDATE holiday_rules
SET name = CONVERT(0x416E69766572736172696F2064652056696C6C61204D6172C3AD61 USING utf8mb4),
    province = CONVERT(0x43C3B372646F6261 USING utf8mb4),
    locality = CONVERT(0x56696C6C61204D6172C3AD61 USING utf8mb4),
    is_active = 1
WHERE month_day = '09-27' AND scope_type = 'locality';

INSERT INTO holiday_rules (name, month_day, scope_type, province, locality, is_active)
VALUES (CONVERT(0x416E69766572736172696F2064652056696C6C61204D6172C3AD61 USING utf8mb4),
        '09-27', 'locality', CONVERT(0x43C3B372646F6261 USING utf8mb4),
        CONVERT(0x56696C6C61204D6172C3AD61 USING utf8mb4), 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = VALUES(is_active);
