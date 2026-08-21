-- ─────────────────────────────────────────────────────────────────────────────
-- Registro de Horas — bloqueo por estado del empleado (port de hoursapp).
--
-- hoursapp guardaba en Employee: state ('Activo'|'Vacaciones'|'Guardia'|'Licencia')
-- + inicio_estado/fin_estado, y record_hours rechazaba cargas que solaparan el
-- período. La suite ya bloquea Vacaciones/Licencia vía bloques vacation/leave de
-- employee_schedules; esta tabla agrega los períodos declarados por RRHH
-- (en particular GUARDIA, que no tenía ningún soporte) sin depender del módulo
-- de vacaciones.
--
-- Aplicar con:
--   /c/xampp/mysql/bin/mysql.exe -uroot -h127.0.0.1 -P3308 paviotti_lanaturaleza < scripts/migration_estados_empleado.sql
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS employee_status_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('guardia','vacaciones','licencia') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_esp_user_dates (user_id, start_date, end_date),
    KEY idx_esp_dates (start_date, end_date),
    CONSTRAINT fk_esp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
