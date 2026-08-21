-- ----------------------------------------------------------------------
-- Estructura societaria real del grupo Moderna (Suite P&M) — 21/08/2026
--
--   MODERNA SRL            -> Farmacia Moderna 1, 2, 3 y 4
--   FRANCE SRL             -> el resto de las sucursales del grupo
--   DISTRIBUIDORA FCF SAS  -> Distribuidora FCF
--
-- Idempotente. Requiere: migration_company_organization_group.sql,
-- migration_ecofarma_branches.sql y el seed de sucursales de Moderna.
-- ----------------------------------------------------------------------

-- 1) Razón social real de la empresa sembrada como 'Moderna'
UPDATE companies
SET name = 'MODERNA SRL'
WHERE name = 'Moderna' AND organization_group = 'moderna';

-- 2) Las otras dos sociedades del grupo
INSERT INTO companies (name, extras_mode, show_overtime, show_cp_extras, organization_group)
SELECT 'FRANCE SRL', 'hours', 1, 0, 'moderna' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'FRANCE SRL');

INSERT INTO companies (name, extras_mode, show_overtime, show_cp_extras, organization_group)
SELECT 'DISTRIBUIDORA FCF SAS', 'hours', 1, 0, 'moderna' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'DISTRIBUIDORA FCF SAS');

-- 3) Reasignar sucursales a su sociedad (solo dentro del grupo moderna)
UPDATE company_branches cb
JOIN companies actual ON actual.id = cb.company_id AND actual.organization_group = 'moderna'
JOIN companies destino ON destino.name = 'MODERNA SRL'
SET cb.company_id = destino.id
WHERE cb.name IN ('Farmacia Moderna 1', 'Farmacia Moderna 2', 'Farmacia Moderna 3', 'Farmacia Moderna 4');

UPDATE company_branches cb
JOIN companies actual ON actual.id = cb.company_id AND actual.organization_group = 'moderna'
JOIN companies destino ON destino.name = 'DISTRIBUIDORA FCF SAS'
SET cb.company_id = destino.id
WHERE cb.name = 'Distribuidora FCF';

UPDATE company_branches cb
JOIN companies actual ON actual.id = cb.company_id AND actual.organization_group = 'moderna'
JOIN companies destino ON destino.name = 'FRANCE SRL'
SET cb.company_id = destino.id
WHERE cb.name NOT IN ('Farmacia Moderna 1', 'Farmacia Moderna 2', 'Farmacia Moderna 3', 'Farmacia Moderna 4', 'Distribuidora FCF')
  AND cb.company_id <> destino.id;

-- 4) Cada usuario del grupo pasa a la sociedad de su sucursal principal
UPDATE users u
JOIN company_branches cb ON cb.id = u.branch_id
SET u.company_id = cb.company_id
WHERE u.employee_group = 'moderna'
  AND u.branch_id IS NOT NULL
  AND u.company_id <> cb.company_id;
