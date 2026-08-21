-- ----------------------------------------------------------------------
-- Migración: Registro de Horas (Suite P&M) — módulo compartido
-- Fecha: 2026-08-18 · Rama: AXEL
--
-- 1) employee_schedules.branch_name: sucursal del bloque planificado.
--    El Registro de Horas (carga individual/masiva/duplicación) escribe
--    bloques con sucursal; el planificador semanal existente sigue
--    funcionando igual (columna nullable, no la usa).
-- 2) holidays.city: alcance de un feriado local (NULL = toda la empresa).
--    Lo usa el cálculo de extras Moderna (feriado nacional o local de la
--    ciudad del empleado => todas las horas del día son extra).
--
-- Idempotente (MariaDB 10.0+ / MySQL 8: IF NOT EXISTS).
-- ----------------------------------------------------------------------

ALTER TABLE employee_schedules
    ADD COLUMN IF NOT EXISTS branch_name VARCHAR(120) NULL DEFAULT NULL AFTER notes;

ALTER TABLE employee_schedules
    ADD INDEX IF NOT EXISTS idx_es_user_date (user_id, schedule_date);

ALTER TABLE holidays
    ADD COLUMN IF NOT EXISTS city VARCHAR(80) NULL DEFAULT NULL AFTER name;
