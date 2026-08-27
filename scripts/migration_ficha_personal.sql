-- ─────────────────────────────────────────────────────────────────────────────
-- Ficha personal del colaborador — campos de la nómina de RRHH Moderna que el
-- esquema aún no tenía: estado civil, cantidad de hijos, parentesco del
-- contacto de emergencia y observaciones de RRHH (columna "Detalle" del Excel).
-- El resto de la nómina ya tiene lugar: legajo = employee_company_assignments.
-- employee_number, ingreso = users.hire_date/start_date, ingreso recibo =
-- seniority_date, cargo = job_positions, obra social = health_insurers,
-- domicilio = employee_addresses.
--
-- Aplicar (local y luego en el VPS):
--   mysql -u... paviotti_lanaturaleza < scripts/migration_ficha_personal.sql
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS marital_status VARCHAR(30) NULL AFTER birth_date,
    ADD COLUMN IF NOT EXISTS children_count TINYINT UNSIGNED NULL AFTER marital_status,
    ADD COLUMN IF NOT EXISTS emergency_contact_relationship VARCHAR(60) NULL AFTER emergency_contact_name,
    ADD COLUMN IF NOT EXISTS hr_notes TEXT NULL AFTER emergency_contact_phone;
