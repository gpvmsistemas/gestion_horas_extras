-- Programa integral RRHH, Operaciones y Talento. MariaDB 10.4+.
-- Requiere users, companies, company_branches, areas, user_access_scopes,
-- attendance_day_summary, employee_incidents y tablas de capacitación.

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_key VARCHAR(160) PRIMARY KEY, checksum_sha256 CHAR(64) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, applied_by VARCHAR(120) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_capabilities (
  capability_key VARCHAR(100) PRIMARY KEY, label VARCHAR(160) NOT NULL, module_key VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS access_scope_capabilities (
  access_scope_id BIGINT NOT NULL, capability_key VARCHAR(100) NOT NULL, decision ENUM('allow','deny') NOT NULL,
  updated_by INT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(access_scope_id,capability_key),
  FOREIGN KEY(access_scope_id) REFERENCES user_access_scopes(id) ON DELETE CASCADE,
  FOREIGN KEY(capability_key) REFERENCES access_capabilities(capability_key) ON DELETE CASCADE,
  FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO access_capabilities(capability_key,label,module_key) VALUES
('attendance.review','Revisar asistencia','attendance'),('attendance.justify','Justificar novedades','attendance'),('attendance.prepare','Preparar cierre','attendance'),('attendance.close','Cerrar o reabrir período','attendance'),
('ppe.catalog','Administrar catálogo y stock EPP','ppe'),('ppe.issue','Entregar EPP','ppe'),('ppe.receive','Recibir o reemplazar EPP','ppe'),
('assets.catalog','Administrar inventario','assets'),('assets.assign','Asignar y transferir activos','assets'),('assets.maintain','Mantenimiento, daño, pérdida y baja','assets'),
('discipline.create','Crear sanciones','discipline'),('discipline.review','Revisar y notificar sanciones','discipline'),('discipline.view','Consultar sanciones','discipline'),
('expirations.manage','Administrar vencimientos','expirations'),('recruiting.publish','Publicar vacantes','recruiting'),('recruiting.review','Revisar candidatos','recruiting'),('recruiting.ai','Ejecutar ranking IA','recruiting'),
('performance.manage','Configurar evaluaciones','performance'),('performance.evaluate','Evaluar colaboradores','performance'),('performance.calibrate','Calibrar y cerrar ciclos','performance'),
('metrics.operational','Ver métricas operativas','metrics'),('metrics.strategic','Ver métricas estratégicas','metrics'),('audit.view','Consultar auditoría','audit')
ON DUPLICATE KEY UPDATE label=VALUES(label),module_key=VALUES(module_key);

CREATE TABLE IF NOT EXISTS audit_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  actor_user_id INT NULL, company_id INT NULL, branch_id INT NULL, action_key VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL, entity_id VARCHAR(80) NULL, reason VARCHAR(500) NULL,
  before_json LONGTEXT NULL, after_json LONGTEXT NULL, request_ip VARCHAR(45) NULL,
  correlation_id CHAR(36) NOT NULL, previous_hash CHAR(64) NULL, event_hash CHAR(64) NOT NULL,
  INDEX idx_audit_scope_date(company_id,occurred_at), INDEX idx_audit_entity(entity_type,entity_id),
  FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE SET NULL,
  FOREIGN KEY(branch_id) REFERENCES company_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TRIGGER IF NOT EXISTS trg_audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_events es append-only';
CREATE TRIGGER IF NOT EXISTS trg_audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_events requiere procedimiento administrativo de retención';

CREATE TABLE IF NOT EXISTS attendance_closures (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, period_month CHAR(7) NOT NULL,
  version_no INT NOT NULL DEFAULT 1, status ENUM('draft','closed','reopened') NOT NULL DEFAULT 'draft',
  snapshot_json LONGTEXT NULL, snapshot_hash CHAR(64) NULL, prepared_by INT NULL, closed_by INT NULL,
  prepared_at DATETIME NULL, closed_at DATETIME NULL, reopened_at DATETIME NULL, reopen_reason VARCHAR(500) NULL,
  UNIQUE KEY uq_attendance_closure_version(company_id,period_month,version_no),
  FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(prepared_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE employee_incidents ADD COLUMN IF NOT EXISTS workflow_status ENUM('draft','reviewed','notified','received','refused','void') NOT NULL DEFAULT 'draft', ADD COLUMN IF NOT EXISTS reviewed_by INT NULL, ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL, ADD COLUMN IF NOT EXISTS notified_at DATETIME NULL, ADD COLUMN IF NOT EXISTS document_hash CHAR(64) NULL, ADD COLUMN IF NOT EXISTS void_reason VARCHAR(500) NULL;
CREATE TABLE IF NOT EXISTS employee_incident_acknowledgements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, incident_id INT NOT NULL, user_id INT NOT NULL,
  decision ENUM('received','refused') NOT NULL, statement_text VARCHAR(1000) NULL,
  document_hash CHAR(64) NOT NULL, password_verified TINYINT(1) NOT NULL DEFAULT 1,
  request_ip VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_incident_ack(incident_id,user_id), FOREIGN KEY(incident_id) REFERENCES employee_incidents(id),
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expiration_types (
  id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(140) NOT NULL,
  default_notice_days INT NOT NULL DEFAULT 30, escalation_days INT NOT NULL DEFAULT 7, is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_expiration_type(company_id,code), FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS employee_expirations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, company_id INT NOT NULL, branch_id INT NULL,
  expiration_type_id INT NOT NULL, reference_no VARCHAR(100) NULL, issued_on DATE NULL, expires_on DATE NOT NULL,
  status ENUM('active','renewed','expired','cancelled') NOT NULL DEFAULT 'active', responsible_user_id INT NULL,
  notes VARCHAR(500) NULL, attachment_path VARCHAR(255) NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expiration_due(company_id,status,expires_on), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(branch_id) REFERENCES company_branches(id) ON DELETE SET NULL,
  FOREIGN KEY(expiration_type_id) REFERENCES expiration_types(id), FOREIGN KEY(responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO expiration_types(company_id,code,name,default_notice_days) VALUES
(NULL,'probation','Período de prueba',30),(NULL,'fixed_contract','Contrato a plazo fijo',30),(NULL,'driver_license','Licencia de conducir',60),(NULL,'health_card','Carnet sanitario',30),(NULL,'certification','Certificación',30)
ON DUPLICATE KEY UPDATE name=VALUES(name);

CREATE TABLE IF NOT EXISTS ppe_items (
 id INT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, name VARCHAR(140) NOT NULL, category VARCHAR(80) NULL,
 requires_size TINYINT(1) NOT NULL DEFAULT 0, useful_life_days INT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_ppe_item(company_id,name), FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ppe_stock (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, branch_id INT NULL, size_label VARCHAR(40) NOT NULL DEFAULT '', lot_no VARCHAR(80) NOT NULL DEFAULT '', quantity INT NOT NULL DEFAULT 0,
 UNIQUE KEY uq_ppe_stock(item_id,branch_id,size_label,lot_no), FOREIGN KEY(item_id) REFERENCES ppe_items(id), FOREIGN KEY(branch_id) REFERENCES company_branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS ppe_deliveries (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, branch_id INT NULL, user_id INT NOT NULL, item_id INT NOT NULL,
 size_label VARCHAR(40) NULL, lot_no VARCHAR(80) NULL, quantity INT NOT NULL, delivered_on DATE NOT NULL,
 next_replacement_on DATE NULL, status ENUM('issued','acknowledged','returned','replaced','lost','discarded') NOT NULL DEFAULT 'issued',
 delivered_by INT NOT NULL, document_version INT NOT NULL DEFAULT 1, document_hash CHAR(64) NOT NULL,
 acknowledged_at DATETIME NULL, acknowledged_ip VARCHAR(45) NULL, notes VARCHAR(500) NULL,
 INDEX idx_ppe_user(user_id,delivered_on), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(branch_id) REFERENCES company_branches(id) ON DELETE SET NULL,
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(item_id) REFERENCES ppe_items(id), FOREIGN KEY(delivered_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS asset_categories (id INT AUTO_INCREMENT PRIMARY KEY,company_id INT NOT NULL,name VARCHAR(120) NOT NULL,UNIQUE KEY uq_asset_category(company_id,name),FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS assets (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, company_id INT NOT NULL, branch_id INT NULL, category_id INT NOT NULL,
 asset_tag VARCHAR(80) NOT NULL, brand VARCHAR(100) NULL, model VARCHAR(100) NULL, serial_no VARCHAR(120) NULL,
 license_plate VARCHAR(30) NULL, status ENUM('available','assigned','maintenance','repair','lost','damaged','retired') NOT NULL DEFAULT 'available',
 condition_notes VARCHAR(500) NULL, current_custodian_user_id INT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_asset_tag(company_id,asset_tag), FOREIGN KEY(company_id) REFERENCES companies(id), FOREIGN KEY(branch_id) REFERENCES company_branches(id) ON DELETE SET NULL,
 FOREIGN KEY(category_id) REFERENCES asset_categories(id), FOREIGN KEY(current_custodian_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS asset_movements (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, asset_id BIGINT NOT NULL, movement_type ENUM('create','assign','transfer','return','maintenance','repair','lost','damaged','retire') NOT NULL,
 from_user_id INT NULL,to_user_id INT NULL,actor_user_id INT NOT NULL,moved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 condition_notes VARCHAR(500) NULL,evidence_path VARCHAR(255) NULL,document_hash CHAR(64) NOT NULL,
 INDEX idx_asset_movement(asset_id,moved_at),FOREIGN KEY(asset_id) REFERENCES assets(id),FOREIGN KEY(from_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(to_user_id) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS career_consents (id INT AUTO_INCREMENT PRIMARY KEY,version_no INT NOT NULL UNIQUE,content_text LONGTEXT NOT NULL,content_hash CHAR(64) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS job_vacancies (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,company_id INT NOT NULL,branch_id INT NULL,position_id INT NULL,title VARCHAR(180) NOT NULL,slug VARCHAR(190) NOT NULL UNIQUE,
 description LONGTEXT NOT NULL,requirements_json LONGTEXT NOT NULL,pipeline_json LONGTEXT NOT NULL,status ENUM('draft','published','paused','closed') NOT NULL DEFAULT 'draft',
 published_at DATETIME NULL,closes_at DATETIME NULL,created_by INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(company_id) REFERENCES companies(id),FOREIGN KEY(branch_id) REFERENCES company_branches(id) ON DELETE SET NULL,FOREIGN KEY(position_id) REFERENCES job_positions(id) ON DELETE SET NULL,FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS candidates (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL,full_name VARCHAR(180) NOT NULL,phone VARCHAR(60) NULL,token_hash CHAR(64) NOT NULL,
 retention_until DATE NOT NULL,anonymized_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_candidate_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS job_applications (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,vacancy_id BIGINT NOT NULL,candidate_id BIGINT NOT NULL,current_stage VARCHAR(60) NOT NULL DEFAULT 'received',status ENUM('active','hired','rejected','withdrawn') NOT NULL DEFAULT 'active',
 cv_path VARCHAR(255) NOT NULL,cv_original_name VARCHAR(255) NOT NULL,cv_sha256 CHAR(64) NOT NULL,tracking_token_hash CHAR(64) NULL,consent_id INT NOT NULL,consent_ip VARCHAR(45) NULL,
 ai_score DECIMAL(5,2) NULL,ai_result_json LONGTEXT NULL,ai_model VARCHAR(80) NULL,ai_criteria_hash CHAR(64) NULL,ai_scored_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_application(vacancy_id,candidate_id),FOREIGN KEY(vacancy_id) REFERENCES job_vacancies(id),FOREIGN KEY(candidate_id) REFERENCES candidates(id),FOREIGN KEY(consent_id) REFERENCES career_consents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE job_applications ADD COLUMN IF NOT EXISTS tracking_token_hash CHAR(64) NULL AFTER cv_sha256;
CREATE INDEX IF NOT EXISTS idx_application_tracking ON job_applications(tracking_token_hash);
CREATE TABLE IF NOT EXISTS application_events (id BIGINT AUTO_INCREMENT PRIMARY KEY,application_id BIGINT NOT NULL,event_type VARCHAR(60) NOT NULL,from_stage VARCHAR(60) NULL,to_stage VARCHAR(60) NULL,notes VARCHAR(1000) NULL,actor_user_id INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(application_id) REFERENCES job_applications(id) ON DELETE CASCADE,FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS career_rate_limits (id BIGINT AUTO_INCREMENT PRIMARY KEY,ip_hash CHAR(64) NOT NULL,window_start DATETIME NOT NULL,request_count INT NOT NULL DEFAULT 1,UNIQUE KEY uq_career_rate(ip_hash,window_start)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO career_consents(version_no,content_text,content_hash) SELECT 1,'Autorizo el tratamiento de mis datos para procesos de selección y su conservación por hasta 24 meses. Comprendo que puede utilizarse IA como asistencia con revisión humana y sin rechazo automático.',SHA2('career-consent-v1',256) WHERE NOT EXISTS(SELECT 1 FROM career_consents);

CREATE TABLE IF NOT EXISTS performance_templates (id INT AUTO_INCREMENT PRIMARY KEY,company_id INT NOT NULL,position_id INT NULL,name VARCHAR(160) NOT NULL,method ENUM('90','180') NOT NULL DEFAULT '180',objectives_weight DECIMAL(5,2) NOT NULL DEFAULT 50,competencies_weight DECIMAL(5,2) NOT NULL DEFAULT 50,definition_json LONGTEXT NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,FOREIGN KEY(company_id) REFERENCES companies(id),FOREIGN KEY(position_id) REFERENCES job_positions(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS performance_cycles (id BIGINT AUTO_INCREMENT PRIMARY KEY,company_id INT NOT NULL,template_id INT NOT NULL,name VARCHAR(160) NOT NULL,starts_on DATE NOT NULL,ends_on DATE NOT NULL,status ENUM('draft','open','calibration','closed') NOT NULL DEFAULT 'draft',created_by INT NOT NULL,closed_by INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(company_id) REFERENCES companies(id),FOREIGN KEY(template_id) REFERENCES performance_templates(id),FOREIGN KEY(created_by) REFERENCES users(id),FOREIGN KEY(closed_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS performance_reviews (id BIGINT AUTO_INCREMENT PRIMARY KEY,cycle_id BIGINT NOT NULL,subject_user_id INT NOT NULL,reviewer_user_id INT NOT NULL,reviewer_type ENUM('self','manager') NOT NULL,status ENUM('pending','submitted','calibrated') NOT NULL DEFAULT 'pending',answers_json LONGTEXT NULL,raw_score DECIMAL(6,2) NULL,calibrated_score DECIMAL(6,2) NULL,submitted_at DATETIME NULL,calibrated_by INT NULL,calibrated_at DATETIME NULL,UNIQUE KEY uq_review(cycle_id,subject_user_id,reviewer_user_id,reviewer_type),FOREIGN KEY(cycle_id) REFERENCES performance_cycles(id),FOREIGN KEY(subject_user_id) REFERENCES users(id),FOREIGN KEY(reviewer_user_id) REFERENCES users(id),FOREIGN KEY(calibrated_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE courses ADD COLUMN IF NOT EXISTS duration_hours DECIMAL(6,2) NULL,ADD COLUMN IF NOT EXISTS certificate_valid_days INT NULL;
ALTER TABLE course_enrollments ADD COLUMN IF NOT EXISTS is_required TINYINT(1) NOT NULL DEFAULT 0,ADD COLUMN IF NOT EXISTS certificate_issued_at DATETIME NULL,ADD COLUMN IF NOT EXISTS certificate_expires_on DATE NULL;

CREATE TABLE IF NOT EXISTS onboarding_checklists (id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,company_id INT NOT NULL,source_application_id BIGINT NULL,status ENUM('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',created_by INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,completed_at DATETIME NULL,FOREIGN KEY(user_id) REFERENCES users(id),FOREIGN KEY(company_id) REFERENCES companies(id),FOREIGN KEY(source_application_id) REFERENCES job_applications(id) ON DELETE SET NULL,FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE users ADD COLUMN IF NOT EXISTS employment_status ENUM('preingreso','activo','egresado') NOT NULL DEFAULT 'activo' AFTER is_active;
CREATE TABLE IF NOT EXISTS onboarding_tasks (id BIGINT AUTO_INCREMENT PRIMARY KEY,checklist_id BIGINT NOT NULL,task_key VARCHAR(80) NOT NULL,label VARCHAR(180) NOT NULL,responsible_user_id INT NULL,due_on DATE NULL,status ENUM('pending','completed','waived') NOT NULL DEFAULT 'pending',completed_by INT NULL,completed_at DATETIME NULL,UNIQUE KEY uq_onboarding_task(checklist_id,task_key),FOREIGN KEY(checklist_id) REFERENCES onboarding_checklists(id) ON DELETE CASCADE,FOREIGN KEY(responsible_user_id) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(completed_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheduled_job_runs (id BIGINT AUTO_INCREMENT PRIMARY KEY,job_key VARCHAR(100) NOT NULL,operation_key VARCHAR(160) NOT NULL,status ENUM('running','success','failed') NOT NULL,started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,finished_at DATETIME NULL,details_json LONGTEXT NULL,UNIQUE KEY uq_job_operation(job_key,operation_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hr_feature_flags (feature_key VARCHAR(80) NOT NULL,company_id INT NOT NULL,is_enabled TINYINT(1) NOT NULL DEFAULT 0,enabled_at DATETIME NULL,enabled_by INT NULL,PRIMARY KEY(feature_key,company_id),FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE CASCADE,FOREIGN KEY(enabled_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations(migration_key,checksum_sha256,applied_by) VALUES('2026_08_hr_operations_talent',NULL,'migration_hr_operations_talent.sql') ON DUPLICATE KEY UPDATE migration_key=VALUES(migration_key);
