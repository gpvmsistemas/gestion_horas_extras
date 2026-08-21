-- ─────────────────────────────────────────────────────────────────────────────
-- Vista por sucursal (port de branch_hours de hoursapp): horario de atención
-- de cada sucursal, mostrado bajo su nombre en la tabla semanal.
-- hoursapp lo tenía hardcodeado en BRANCH_SCHEDULES (report_routes.py:26-50);
-- acá vive en company_branches para poder editarlo sin tocar código.
--
-- Aplicar con:
--   /c/xampp/mysql/bin/mysql.exe -uroot -h127.0.0.1 -P3308 paviotti_lanaturaleza < scripts/migration_horario_atencion_sucursal.sql
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE company_branches
    ADD COLUMN IF NOT EXISTS schedule_text VARCHAR(255) NULL AFTER province;

UPDATE company_branches SET schedule_text = CASE name
    WHEN 'Farmacia Moderna 1'    THEN 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:30 a 13:30 y 16:30 a 21:30'
    WHEN 'Farmacia Moderna 2'    THEN 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:00 a 13:00'
    WHEN 'Farmacia Moderna 3'    THEN 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:00 a 13:00 y 16:30 a 21:30 | Domingo: 08:00 a 13:00'
    WHEN 'Farmacia Moderna 4'    THEN 'Lunes a Viernes: 08:00 a 22:00 | Sábado: 08:00 a 14:00 y 17:00 a 22:00'
    WHEN 'Farmacia Moderna 5'    THEN 'Lunes a Viernes: 08:00 a 22:30 | Sábado: 09:00 a 14:00 y 17:00 a 22:30 | Domingo: 17:00 a 22:00'
    WHEN 'Farmacia Moderna 7'    THEN 'Lunes a Sábado: 08:00 a 13:00 y 16:30 a 21:30'
    WHEN 'Farmacia Moderna 8'    THEN 'Lunes a Viernes: 08:00 a 20:30 | Sábado: 08:00 a 13:00'
    WHEN 'Farmacia Moderna 9'    THEN 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:00 | Sábado: 08:00 a 13:00 y 16:30 a 21:30'
    WHEN 'Farmacia Moderna 10'   THEN 'Lunes a Viernes: 08:00 a 13:00 y 17:00 a 21:30 | Sábado: 08:00 a 13:00 y 16:30 a 21:30'
    WHEN 'Farmacia Moderna 11'   THEN 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:30 | Sábado: 08:00 a 13:00 y 16:30 a 21:30'
    WHEN 'Farmacia Moderna 12'   THEN 'Lunes a Viernes: 08:30 a 12:30 y 17:00 a 21:00 | Sábado: 08:30 a 13:30'
    WHEN 'Marcellino'            THEN '24 hs'
    WHEN 'Farmacia del Condor'   THEN 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:30 a 13:30'
    WHEN 'Farmacia del Subnivel' THEN 'Lunes a Viernes: 08:00 a 21:00 | Sábado: 08:00 a 13:00'
    WHEN 'Farmacia Marañon'      THEN 'Lunes a Viernes: 08:00 a 20:30 | Sábado: 08:00 a 13:00'
    WHEN 'Farmacia Palermo'      THEN 'Lunes a Sábado: 08:30 a 13:30 y 16:30 a 21:30'
    WHEN 'Farmacia Plaza'        THEN 'Lunes a Sábado: 08:00 a 13:00 y 16:30 a 21:30'
    WHEN 'Farmacia Oulton'       THEN 'Lunes a Viernes: 08:00 a 21:00 | Sábado: 08:00 a 13:00'
    WHEN 'Farmacia Alladio'      THEN 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:00 | Sábado: 08:00 a 13:00'
    WHEN 'Farmacia Muscatelo'    THEN 'Lunes a Viernes: 08:30 a 13:00 y 17:00 a 21:00 | Sábado: 08:00 a 13:00'
    WHEN 'Santa Teresita'        THEN 'Lunes a Viernes: 08:30 a 13:00 y 17:00 a 22:00 | Sábado: 09:00 a 13:00'
    WHEN 'General Paz'           THEN 'Lunes a Viernes: 08:30 a 12:30 y 17:00 a 21:00 | Sábado: 09:00 a 13:00'
    WHEN 'Farmacia Novofarma'    THEN 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:00 | Sábado: 08:00 a 13:00'
    ELSE schedule_text
END
WHERE name IN (
    'Farmacia Moderna 1','Farmacia Moderna 2','Farmacia Moderna 3','Farmacia Moderna 4','Farmacia Moderna 5',
    'Farmacia Moderna 7','Farmacia Moderna 8','Farmacia Moderna 9','Farmacia Moderna 10','Farmacia Moderna 11',
    'Farmacia Moderna 12','Marcellino','Farmacia del Condor','Farmacia del Subnivel','Farmacia Marañon',
    'Farmacia Palermo','Farmacia Plaza','Farmacia Oulton','Farmacia Alladio','Farmacia Muscatelo',
    'Santa Teresita','General Paz','Farmacia Novofarma'
);
