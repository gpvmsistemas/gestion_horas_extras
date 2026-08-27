-- Legajo integral del empleado. Compatible con el esquema legacy: users.company_id,
-- users.area_id y users.branch_id continúan representando la asignación principal.

CREATE TABLE IF NOT EXISTS job_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_position_company_name (company_id, name),
    KEY idx_job_positions_company_active (company_id, is_active),
    CONSTRAINT fk_job_positions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS health_insurers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    legal_name VARCHAR(180) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    insurer_type ENUM('obra_social','prepaga','mutual','otra') NOT NULL DEFAULT 'obra_social',
    official_code VARCHAR(40) NULL,
    tax_id VARCHAR(20) NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_health_insurer_display_name (display_name),
    KEY idx_health_insurer_active (is_active, display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS health_insurance_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    health_insurer_id INT NOT NULL,
    code VARCHAR(50) NULL,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_health_plan (health_insurer_id, name),
    CONSTRAINT fk_health_plan_insurer FOREIGN KEY (health_insurer_id) REFERENCES health_insurers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_company_assignments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    employee_number VARCHAR(50) NULL,
    area_id INT NULL,
    position_id INT NULL,
    agreement_id INT NULL,
    employment_type ENUM('permanente','plazo_fijo','eventual','temporario','practica','otro') NOT NULL DEFAULT 'permanente',
    work_mode ENUM('presencial','hibrido','remoto') NOT NULL DEFAULT 'presencial',
    status ENUM('preingreso','activo','licencia','suspendido','finalizado') NOT NULL DEFAULT 'activo',
    start_date DATE NULL,
    seniority_date DATE NULL,
    end_date DATE NULL,
    termination_reason VARCHAR(255) NULL,
    cost_center VARCHAR(80) NULL,
    supervisor_user_id INT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_employee_company_start (user_id, company_id, start_date),
    KEY idx_eca_company_status (company_id, status),
    CONSTRAINT fk_eca_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_eca_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_eca_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    CONSTRAINT fk_eca_position FOREIGN KEY (position_id) REFERENCES job_positions(id) ON DELETE SET NULL,
    CONSTRAINT fk_eca_agreement FOREIGN KEY (agreement_id) REFERENCES collective_agreements(id) ON DELETE SET NULL,
    CONSTRAINT fk_eca_supervisor FOREIGN KEY (supervisor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_addresses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address_type ENUM('residencial','legal','temporal') NOT NULL DEFAULT 'residencial',
    street VARCHAR(140) NULL,
    street_number VARCHAR(30) NULL,
    floor_unit VARCHAR(40) NULL,
    neighborhood VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    locality VARCHAR(120) NULL,
    administrative_area VARCHAR(120) NULL,
    province VARCHAR(120) NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'AR',
    reference_notes VARCHAR(255) NULL,
    original_text VARCHAR(255) NULL,
    normalized_address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    geocode_provider VARCHAR(50) NULL,
    geocode_precision VARCHAR(40) NULL,
    verification_status ENUM('pendiente','confirmada','manual') NOT NULL DEFAULT 'pendiente',
    verified_at DATETIME NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_employee_address_user_primary (user_id, is_primary),
    CONSTRAINT fk_employee_address_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employee_health_coverages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_company_assignment_id BIGINT NULL,
    health_insurer_id INT NOT NULL,
    health_plan_id INT NULL,
    affiliate_number VARCHAR(80) NULL,
    member_role ENUM('titular','adherente') NOT NULL DEFAULT 'titular',
    contribution_redirected TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('en_tramite','activa','suspendida','finalizada') NOT NULL DEFAULT 'activa',
    start_date DATE NULL,
    end_date DATE NULL,
    notes VARCHAR(500) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_employee_health_user_status (user_id, status),
    CONSTRAINT fk_employee_health_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_health_assignment FOREIGN KEY (employee_company_assignment_id) REFERENCES employee_company_assignments(id) ON DELETE SET NULL,
    CONSTRAINT fk_employee_health_insurer FOREIGN KEY (health_insurer_id) REFERENCES health_insurers(id),
    CONSTRAINT fk_employee_health_plan FOREIGN KEY (health_plan_id) REFERENCES health_insurance_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nombres comerciales iniciales. Los códigos oficiales, razón social y planes deben
-- validarse por RR. HH. antes de usarlos para derivación de aportes.
INSERT INTO health_insurers (legal_name, display_name, insurer_type) VALUES
('Sancor Salud', 'Sancor Salud', 'prepaga'),
('OSDE', 'OSDE', 'prepaga'),
('AMMA Salud', 'AMMA Salud', 'otra'),
('Maradona Salud', 'Maradona Salud', 'otra')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- Migra la relación principal existente sin alterar el aislamiento actual.
INSERT INTO employee_company_assignments
    (user_id, company_id, area_id, agreement_id, status, start_date, seniority_date, is_primary)
SELECT id, company_id, area_id, agreement_id,
       IF(is_active = 1, 'activo', 'finalizado'), hire_date, hire_date, 1
FROM users u
WHERE company_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM employee_company_assignments eca
      WHERE eca.user_id = u.id AND eca.company_id = u.company_id
  );
