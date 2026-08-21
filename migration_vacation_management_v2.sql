-- Vacaciones v2: convenios, saldos historicos, auditoria y reportes.
-- Compatible con MariaDB 10.4+. Ejecutar con backup previo.

ALTER TABLE collective_agreements
    ADD COLUMN IF NOT EXISTS jurisdiction VARCHAR(180) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS legal_reference VARCHAR(255) NULL AFTER jurisdiction,
    ADD COLUMN IF NOT EXISTS notice_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER period_start_day,
    ADD COLUMN IF NOT EXISTS start_rule VARCHAR(40) NOT NULL DEFAULT 'lct' AFTER notice_days,
    ADD COLUMN IF NOT EXISTS split_policy VARCHAR(40) NOT NULL DEFAULT 'lct_7' AFTER start_rule,
    ADD COLUMN IF NOT EXISTS minimum_request_days DECIMAL(5,1) NOT NULL DEFAULT 7.0 AFTER split_policy;

ALTER TABLE collective_agreement_rules
    MODIFY COLUMN day_count_mode ENUM('weekdays','calendar','business_mon_sat') NOT NULL DEFAULT 'calendar',
    ADD UNIQUE INDEX IF NOT EXISTS uk_car_agreement_min (agreement_id, min_months);

ALTER TABLE vacation_balance_periods
    ADD COLUMN IF NOT EXISTS balance_type ENUM('annual','historical','conventional_credit') NOT NULL DEFAULT 'annual' AFTER period_end,
    ADD COLUMN IF NOT EXISTS adjustment_days DECIMAL(5,1) NOT NULL DEFAULT 0.0 AFTER days_taken,
    ADD COLUMN IF NOT EXISTS expires_at DATE NULL AFTER status,
    ADD COLUMN IF NOT EXISTS count_mode_snapshot ENUM('weekdays','calendar','business_mon_sat') NOT NULL DEFAULT 'calendar' AFTER agreement_rule_id,
    ADD COLUMN IF NOT EXISTS origin_notes VARCHAR(500) NULL AFTER expires_at,
    ADD INDEX IF NOT EXISTS idx_vbp_reporting (status, balance_type, period_start, expires_at);

ALTER TABLE vacation_balance_periods
    DROP INDEX IF EXISTS uk_vbp_user_period,
    ADD UNIQUE INDEX IF NOT EXISTS uk_vbp_user_period_type (user_id, period_label, balance_type);

ALTER TABLE vacation_balance_movements
    MODIFY COLUMN movement_type ENUM('accrual','take','adjustment','reversal','opening_balance','import','expiry','conversion','exception') NOT NULL,
    MODIFY COLUMN source ENUM('liquidation','request','planner','manual','import','system','cancellation') NOT NULL,
    ADD COLUMN IF NOT EXISTS operation_key VARCHAR(120) NULL AFTER request_id,
    ADD COLUMN IF NOT EXISTS schedule_snapshot LONGTEXT NULL AFTER schedule_dates,
    ADD UNIQUE INDEX IF NOT EXISTS uk_vbm_operation (operation_key);

ALTER TABLE requests
    ADD COLUMN IF NOT EXISTS vacation_counted_days DECIMAL(5,1) NULL AFTER admin_notes,
    ADD COLUMN IF NOT EXISTS vacation_rule_snapshot LONGTEXT NULL AFTER vacation_counted_days,
    ADD COLUMN IF NOT EXISTS vacation_exception_reason VARCHAR(500) NULL AFTER vacation_rule_snapshot,
    ADD COLUMN IF NOT EXISTS vacation_exception_by INT NULL AFTER vacation_exception_reason,
    ADD COLUMN IF NOT EXISTS vacation_exception_at DATETIME NULL AFTER vacation_exception_by;

ALTER TABLE users
    MODIFY COLUMN vacation_days_available DECIMAL(6,1) NOT NULL DEFAULT 0.0;

-- Catalogo de convenios. El periodo devengado es el anio calendario.
INSERT INTO collective_agreements
    (code, name, description, jurisdiction, legal_reference, period_start_month, period_start_day,
     notice_days, start_rule, split_policy, minimum_request_days, is_active)
VALUES
    ('CEC', 'Empleados de Comercio - CCT 130/75',
     'Vacaciones conforme LCT. El CCT exige comunicacion con 60 dias de anticipacion.',
     'Republica Argentina', 'CCT 130/75, arts. 74 y 75', 1, 1, 60, 'lct', 'lct_7', 7, 1),
    ('FARMACIA-430-05', 'Empleados de Farmacia Cordoba - CCT 430/05',
     'Farmacias de la provincia de Cordoba. Inicio lunes o siguiente habil si fuera feriado.',
     'Provincia de Cordoba', 'CCT 430/05, art. 24', 1, 1, 60, 'monday_or_next_business', 'lct_7', 7, 1),
    ('SOECRA-761-19', 'SOECRA Cementerios - CCT 761/19',
     'Cementerios, crematorios, salas velatorias y panteones comprendidos por el convenio.',
     'Republica Argentina, segun representatividad de las partes', 'CCT 761/19, arts. 47 y 49', 1, 1, 30, 'lct', 'soecra_14_plus_7', 7, 1),
    ('UTEDYC-2023', 'UTEDYC - FEDEDAC - AREDA 2023',
     'Entidades deportivas y asociaciones civiles. Reemplaza al CCT 736/16.',
     'Republica Argentina', 'Resolucion ST 1661/2023, arts. 12 y 13', 1, 1, 30, 'lct', 'lct_7', 7, 1),
    ('SANIDAD-122-75', 'Sanidad - CCT 122/75',
     'Clinicas, sanatorios, hospitales privados y establecimientos geriatricos.',
     'Republica Argentina', 'CCT 122/75, arts. 21 y 22', 1, 1, 30, 'lct', 'lct_7', 7, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name), description = VALUES(description), jurisdiction = VALUES(jurisdiction),
    legal_reference = VALUES(legal_reference), period_start_month = VALUES(period_start_month),
    period_start_day = VALUES(period_start_day), notice_days = VALUES(notice_days),
    start_rule = VALUES(start_rule), split_policy = VALUES(split_policy),
    minimum_request_days = VALUES(minimum_request_days), is_active = VALUES(is_active);

INSERT INTO collective_agreement_rules
    (agreement_id, min_months, max_months, days_entitled, day_count_mode,
     allows_split, allows_carryover, min_consecutive_days, notes)
SELECT id, 0, 60, 14, 'calendar', 1, 1, 7, 'Hasta 5 años inclusive' FROM collective_agreements WHERE code='CEC'
UNION ALL SELECT id, 61, 120, 21, 'calendar', 1, 1, 7, 'Mas de 5 y hasta 10 años' FROM collective_agreements WHERE code='CEC'
UNION ALL SELECT id, 121, 240, 28, 'calendar', 1, 1, 7, 'Mas de 10 y hasta 20 años' FROM collective_agreements WHERE code='CEC'
UNION ALL SELECT id, 241, NULL, 35, 'calendar', 1, 1, 7, 'Mas de 20 años' FROM collective_agreements WHERE code='CEC'

UNION ALL SELECT id, 0, 60, 17, 'calendar', 1, 1, 7, 'Hasta 5 años inclusive' FROM collective_agreements WHERE code='FARMACIA-430-05'
UNION ALL SELECT id, 61, 120, 26, 'calendar', 1, 1, 7, 'Mas de 5 y hasta 10 años' FROM collective_agreements WHERE code='FARMACIA-430-05'
UNION ALL SELECT id, 121, 240, 35, 'calendar', 1, 1, 7, 'Mas de 10 y hasta 20 años' FROM collective_agreements WHERE code='FARMACIA-430-05'
UNION ALL SELECT id, 241, NULL, 44, 'calendar', 1, 1, 7, 'Mas de 20 años' FROM collective_agreements WHERE code='FARMACIA-430-05'

UNION ALL SELECT id, 0, 60, 14, 'business_mon_sat', 1, 1, 14, 'Hasta 5 años inclusive; sabado habil' FROM collective_agreements WHERE code='SOECRA-761-19'
UNION ALL SELECT id, 61, 120, 21, 'business_mon_sat', 1, 1, 14, 'Fraccion 14 + remanente 7' FROM collective_agreements WHERE code='SOECRA-761-19'
UNION ALL SELECT id, 121, 240, 28, 'business_mon_sat', 1, 1, 14, 'Fracciones 14 + 14' FROM collective_agreements WHERE code='SOECRA-761-19'
UNION ALL SELECT id, 241, NULL, 35, 'business_mon_sat', 1, 1, 14, 'Fracciones 14 + 14 + remanente 7' FROM collective_agreements WHERE code='SOECRA-761-19'

UNION ALL SELECT id, 0, 60, 16, 'calendar', 1, 1, 7, 'Hasta 5 años inclusive' FROM collective_agreements WHERE code='UTEDYC-2023'
UNION ALL SELECT id, 61, 120, 21, 'calendar', 1, 1, 7, 'Mas de 5 y hasta 10 años' FROM collective_agreements WHERE code='UTEDYC-2023'
UNION ALL SELECT id, 121, 240, 28, 'calendar', 1, 1, 7, 'Mas de 10 y hasta 20 años' FROM collective_agreements WHERE code='UTEDYC-2023'
UNION ALL SELECT id, 241, NULL, 35, 'calendar', 1, 1, 7, 'Mas de 20 años' FROM collective_agreements WHERE code='UTEDYC-2023'

UNION ALL SELECT id, 0, 60, 14, 'calendar', 1, 1, 7, 'Hasta 5 años inclusive' FROM collective_agreements WHERE code='SANIDAD-122-75'
UNION ALL SELECT id, 61, 120, 21, 'calendar', 1, 1, 7, 'Mas de 5 y hasta 10 años' FROM collective_agreements WHERE code='SANIDAD-122-75'
UNION ALL SELECT id, 121, 240, 28, 'calendar', 1, 1, 7, 'Mas de 10 y hasta 20 años' FROM collective_agreements WHERE code='SANIDAD-122-75'
UNION ALL SELECT id, 241, NULL, 35, 'calendar', 1, 1, 7, 'Mas de 20 años' FROM collective_agreements WHERE code='SANIDAD-122-75'
ON DUPLICATE KEY UPDATE
    max_months=VALUES(max_months), days_entitled=VALUES(days_entitled),
    day_count_mode=VALUES(day_count_mode), allows_split=VALUES(allows_split),
    allows_carryover=VALUES(allows_carryover), min_consecutive_days=VALUES(min_consecutive_days),
    notes=VALUES(notes);

-- Conserva filas existentes y recompone el saldo con la nueva columna de ajustes.
UPDATE vacation_balance_periods
SET days_pending = GREATEST(0, days_entitled + adjustment_days - days_taken),
    status = CASE WHEN days_entitled + adjustment_days - days_taken <= 0 THEN 'closed' ELSE 'open' END;
