-- Hijos/as del colaborador: fecha de nacimiento y sexo por cada uno.
-- Sincroniza users.children_count al guardar desde la aplicación.
-- Ejecutar con backup previo; idempotente vía scripts/apply_employee_children_migration.php

CREATE TABLE IF NOT EXISTS employee_children (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    birth_date DATE NOT NULL,
    sex CHAR(1) NOT NULL COMMENT 'M=Masculino, F=Femenino',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_employee_children_user (user_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
