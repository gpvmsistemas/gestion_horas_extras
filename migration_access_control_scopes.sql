-- Perfiles, alcances organizacionales y permisos del portal.
-- Ejecutar luego de migration_employee_record_complete.sql y migration_ecofarma_branches.sql.

CREATE TABLE IF NOT EXISTS user_access_scopes (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    branch_id INT NULL,
    access_role ENUM('operario','encargado','coordinador','rrhh','administrador') NOT NULL DEFAULT 'operario',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_on DATE NULL,
    ends_on DATE NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_access_scope (user_id, company_id, branch_id, access_role, starts_on),
    KEY idx_uas_user_active (user_id, is_active, starts_on, ends_on),
    KEY idx_uas_company_branch_role (company_id, branch_id, access_role, is_active),
    CONSTRAINT fk_uas_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_uas_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_uas_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_uas_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_feature_policies (
    id BIGINT NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL,
    branch_id INT NULL,
    feature_key VARCHAR(80) NOT NULL,
    decision ENUM('allow','deny') NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_org_feature_policy (company_id, branch_id, feature_key),
    KEY idx_ofp_company_branch (company_id, branch_id),
    CONSTRAINT fk_ofp_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_ofp_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_ofp_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_feature_overrides (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    branch_id INT NULL,
    feature_key VARCHAR(80) NOT NULL,
    decision ENUM('allow','deny') NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_feature_override (user_id, company_id, branch_id, feature_key),
    KEY idx_ufo_user_scope (user_id, company_id, branch_id),
    CONSTRAINT fk_ufo_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ufo_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_ufo_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_ufo_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_audit_log (
    id BIGINT NOT NULL AUTO_INCREMENT,
    actor_user_id INT NULL,
    subject_user_id INT NULL,
    event_type VARCHAR(80) NOT NULL,
    company_id INT NULL,
    branch_id INT NULL,
    payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_access_audit_subject (subject_user_id, created_at),
    KEY idx_access_audit_company (company_id, created_at),
    CONSTRAINT fk_aal_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_aal_subject FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Compatibilidad de los roles históricos. Nunca elimina ni reemplaza users.role.
INSERT INTO user_access_scopes (user_id, company_id, branch_id, access_role, is_primary, is_active, starts_on)
SELECT u.id, u.company_id, u.branch_id,
       CASE u.role WHEN 'admin' THEN 'administrador' WHEN 'supervisor' THEN 'encargado' ELSE 'operario' END,
       1, u.is_active, COALESCE(u.hire_date, CURDATE())
FROM users u
WHERE u.company_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM user_access_scopes uas WHERE uas.user_id = u.id AND uas.is_primary = 1);
