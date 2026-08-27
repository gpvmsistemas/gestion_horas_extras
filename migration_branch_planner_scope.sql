-- Alcance operativo por sucursal para empleados y planificación.
-- Ejecutar una vez, luego asignar cada empleado a su sucursal desde Admin > Usuarios.

ALTER TABLE users
    ADD COLUMN branch_id INT NULL AFTER company_id,
    ADD INDEX idx_users_company_branch (company_id, branch_id),
    ADD CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE SET NULL;

ALTER TABLE employee_schedules
    ADD COLUMN branch_id INT NULL AFTER user_id,
    ADD INDEX idx_employee_schedules_branch_date (branch_id, schedule_date),
    ADD CONSTRAINT fk_employee_schedules_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE SET NULL;
