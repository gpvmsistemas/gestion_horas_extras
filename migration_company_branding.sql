-- Identidad visual por empresa: color principal y logo público.
ALTER TABLE companies ADD COLUMN brand_color VARCHAR(7) NOT NULL DEFAULT '#e91e8c';
ALTER TABLE companies ADD COLUMN logo_path VARCHAR(255) NULL DEFAULT NULL;
