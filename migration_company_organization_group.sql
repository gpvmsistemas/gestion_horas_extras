-- Grupo corporativo al que pertenece cada empresa.
ALTER TABLE companies ADD COLUMN organization_group ENUM('paviotti', 'moderna') NOT NULL DEFAULT 'paviotti' AFTER name;
