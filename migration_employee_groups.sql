-- Grupo organizacional de cada usuario.
-- Todos los registros existentes quedan en Paviotti de forma segura.
-- MariaDB 10.4+; ejecutar con backup previo.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS employee_group ENUM('paviotti', 'moderna') NOT NULL DEFAULT 'paviotti' AFTER company_id,
    ADD INDEX IF NOT EXISTS idx_users_employee_group (employee_group);

UPDATE users
SET employee_group = 'paviotti'
WHERE employee_group IS NULL OR employee_group NOT IN ('paviotti', 'moderna');
