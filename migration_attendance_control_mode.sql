-- Modalidad individual de control de asistencia.
-- required: alerta automática; flexible: informa sin alertar; no_clock: excluye de control.
ALTER TABLE users
    ADD COLUMN attendance_control_mode ENUM('required','flexible','no_clock') NOT NULL DEFAULT 'required' AFTER branch_id,
    ADD INDEX idx_users_attendance_control (company_id, attendance_control_mode);
