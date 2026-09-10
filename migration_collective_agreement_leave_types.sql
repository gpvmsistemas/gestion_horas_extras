-- Licencias por convenio colectivo (catálogo por CCT).
-- Requiere collective_agreements y requests. Ejecutar con backup previo.

CREATE TABLE IF NOT EXISTS collective_agreement_leave_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    agreement_id INT NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    legal_reference VARCHAR(255) NULL,
    category ENUM('medical','family','maternity','paternity','study','gremial','special','other') NOT NULL DEFAULT 'other',
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    requires_certificate TINYINT(1) NOT NULL DEFAULT 0,
    max_days_per_year DECIMAL(6,1) NULL,
    max_days_per_event DECIMAL(6,1) NULL,
    min_notice_days SMALLINT UNSIGNED NULL,
    day_count_mode ENUM('weekdays','calendar','business_mon_sat') NOT NULL DEFAULT 'calendar',
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_calt_agreement_code (agreement_id, code),
    KEY idx_calt_agreement_active (agreement_id, is_active, sort_order),
    CONSTRAINT fk_calt_agreement FOREIGN KEY (agreement_id) REFERENCES collective_agreements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La columna en requests y el índice se aplican de forma idempotente desde
-- scripts/apply_agreement_leave_types_migration.php (MySQL 8 no admite IF NOT EXISTS en ALTER).

-- Tipo genérico para solicitudes vinculadas al catálogo del convenio.
INSERT INTO request_types (name, color)
SELECT 'Licencia', '#0dcaf0' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM request_types WHERE LOWER(name) = 'licencia');

-- Catálogo base LCT / práctica habitual (se replica en cada convenio precargado).
INSERT INTO collective_agreement_leave_types
    (agreement_id, code, name, description, legal_reference, category, is_paid, requires_certificate,
     max_days_per_year, max_days_per_event, min_notice_days, day_count_mode, sort_order, is_active, notes)
SELECT ca.id, v.code, v.name, v.description, v.legal_reference, v.category, v.is_paid, v.requires_certificate,
       v.max_days_per_year, v.max_days_per_event, v.min_notice_days, v.day_count_mode, v.sort_order, 1, v.notes
FROM collective_agreements ca
JOIN (
    SELECT 'ENFERMEDAD' AS code, 'Enfermedad' AS name,
           'Ausencia por enfermedad o accidente doméstico con certificado médico.' AS description,
           'LCT art. 212' AS legal_reference, 'medical' AS category, 0 AS is_paid, 1 AS requires_certificate,
           NULL AS max_days_per_year, NULL AS max_days_per_event, NULL AS min_notice_days,
           'calendar' AS day_count_mode, 10 AS sort_order,
           'La continuidad del pago depende de antigüedad y normativa aplicable.' AS notes
    UNION ALL SELECT 'ACCIDENTE', 'Accidente de trabajo o enfermedad profesional',
           'Ausencia por siniestro laboral o enfermedad profesional.', 'Ley 24.557 / LRT', 'medical', 1, 1,
           NULL, NULL, NULL, 'calendar', 20, 'Articular con ART y médico laboral.'
    UNION ALL SELECT 'MATERNIDAD', 'Maternidad',
           'Licencia por nacimiento, adopción o tenencia con fines de adopción.', 'LCT art. 177', 'maternity', 1, 1,
           NULL, 90, NULL, 'calendar', 30, NULL
    UNION ALL SELECT 'PATERNIDAD', 'Paternidad',
           'Licencia por nacimiento, adopción o tenencia con fines de adopción.', 'LCT art. 158', 'paternity', 1, 1,
           NULL, 2, NULL, 'calendar', 40, 'Ver ampliaciones convencionales si aplican.'
    UNION ALL SELECT 'MATRIMONIO', 'Matrimonio',
           'Licencia por contraer matrimonio o unión convivencial reconocida.', 'LCT art. 158', 'family', 1, 0,
           NULL, 10, NULL, 'calendar', 50, NULL
    UNION ALL SELECT 'FALLECIMIENTO_HIJO', 'Fallecimiento de hijo',
           'Licencia por fallecimiento de hijo.', 'LCT art. 158', 'family', 1, 1,
           NULL, 3, NULL, 'calendar', 60, NULL
    UNION ALL SELECT 'FALLECIMIENTO_FAMILIAR', 'Fallecimiento de familiar directo',
           'Fallecimiento de cónyuge, conviviente, padre, madre o hermano.', 'LCT art. 158', 'family', 1, 1,
           NULL, 3, NULL, 'calendar', 70, NULL
    UNION ALL SELECT 'NACIMIENTO', 'Nacimiento o adopción de hijo (padre/madre)',
           'Días adicionales convencionales o legales distintos de maternidad/paternidad base.', 'LCT / CCT', 'family', 1, 1,
           NULL, NULL, NULL, 'calendar', 80, 'Completar según CCT si supera la base legal.'
    UNION ALL SELECT 'EXAMEN', 'Día por examen',
           'Ausencia para rendir examen en establecimiento reconocido.', 'LCT art. 158', 'study', 1, 1,
           NULL, 2, 5, 'calendar', 90, 'Hasta 2 días por examen; aviso previo razonable.'
    UNION ALL SELECT 'DONACION_SANGRE', 'Donación de sangre',
           'Ausencia por donación de sangre u otros estudios de compatibilidad.', 'LCT art. 158', 'medical', 1, 1,
           NULL, 1, NULL, 'calendar', 100, NULL
    UNION ALL SELECT 'MUDANZA', 'Mudanza',
           'Licencia por cambio de domicilio.', 'LCT art. 158 / CCT', 'other', 1, 0,
           NULL, 1, NULL, 'calendar', 110, 'Un día por año calendario en la práctica LCT.'
    UNION ALL SELECT 'GREMIAL', 'Actividad gremial o sindical',
           'Asistencia a actos, congresos o delegación sindical convocada.', 'LCT art. 14 bis', 'gremial', 1, 1,
           NULL, NULL, NULL, 'calendar', 120, 'Requiere convocatoria del sindicato cuando corresponda.'
) AS v ON 1=1
WHERE ca.code IN ('CEC', 'FARMACIA-430-05', 'SOECRA-761-19', 'UTEDYC-2023', 'SANIDAD-122-75')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    legal_reference = VALUES(legal_reference),
    category = VALUES(category),
    is_paid = VALUES(is_paid),
    requires_certificate = VALUES(requires_certificate),
    max_days_per_year = VALUES(max_days_per_year),
    max_days_per_event = VALUES(max_days_per_event),
    min_notice_days = VALUES(min_notice_days),
    day_count_mode = VALUES(day_count_mode),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active),
    notes = VALUES(notes);

-- Sanidad: licencias convencionales especiales (separadas de vacaciones ordinarias).
INSERT INTO collective_agreement_leave_types
    (agreement_id, code, name, description, legal_reference, category, is_paid, requires_certificate,
     max_days_per_year, max_days_per_event, min_notice_days, day_count_mode, sort_order, is_active, notes)
SELECT ca.id, 'SANIDAD_ESPECIAL', 'Licencia convencional especial (Sanidad)',
       'Licencias especiales del CCT 122/75 distintas de la vacación anual ordinaria.',
       'CCT 122/75', 'special', 1, 1, NULL, NULL, NULL, 'calendar', 200, 1,
       'Registrar motivo y documentación. No consume saldo de vacaciones.'
FROM collective_agreements ca
WHERE ca.code = 'SANIDAD-122-75'
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    legal_reference = VALUES(legal_reference),
    notes = VALUES(notes),
    is_active = VALUES(is_active);

-- SOECRA: referencia a conteo hábil en licencias médicas prolongadas.
INSERT INTO collective_agreement_leave_types
    (agreement_id, code, name, description, legal_reference, category, is_paid, requires_certificate,
     max_days_per_year, max_days_per_event, min_notice_days, day_count_mode, sort_order, is_active, notes)
SELECT ca.id, 'SOECRA_PROLONGADA', 'Licencia médica prolongada (SOECRA)',
       'Ausencia médica con tratamiento prolongado según convenio cementerios.',
       'CCT 761/19', 'medical', 0, 1, NULL, NULL, NULL, 'business_mon_sat', 15, 1,
       'Contar días hábiles lun-sáb sin feriados cuando RRHH lo indique.'
FROM collective_agreements ca
WHERE ca.code = 'SOECRA-761-19'
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    day_count_mode = VALUES(day_count_mode),
    notes = VALUES(notes),
    is_active = VALUES(is_active);
