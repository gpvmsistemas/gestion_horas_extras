-- ─────────────────────────────────────────────────────────────────────────────
-- Scope de acceso para el administrador de Moderna (axel.moderna).
--
-- Sin ninguna fila en user_access_scopes, access_user_can_manage_company()
-- devuelve false y setAdminActiveCompany() no puede cambiar a FRANCE SRL /
-- DISTRIBUIDORA FCF: abrir la ficha de un empleado de esas sociedades
-- redirigía con "No se pudo abrir la ficha (empresa del usuario inválida)".
-- Un scope 'administrador' habilita todas las empresas; el aislamiento
-- Moderna↔Paviotti lo garantiza la guardia por employee_group agregada en
-- setAdminActiveCompany (auth_helper.php).
--
-- Aplicar (local y luego en el VPS):
--   mysql -u... paviotti_lanaturaleza < scripts/fix_scope_admin_moderna.sql
-- ─────────────────────────────────────────────────────────────────────────────

INSERT INTO user_access_scopes (user_id, company_id, branch_id, access_role, is_primary, is_active, starts_on)
SELECT u.id, u.company_id, NULL, 'administrador', 1, 1, CURDATE()
FROM users u
WHERE u.username = 'axel.moderna'
  AND NOT EXISTS (SELECT 1 FROM user_access_scopes s WHERE s.user_id = u.id AND s.access_role = 'administrador');
