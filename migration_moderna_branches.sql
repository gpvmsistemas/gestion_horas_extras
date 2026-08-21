-- Sucursales de Moderna tomadas de la taxonomía de la rama AXEL.
-- La migración es idempotente y mantiene actualizadas localidad, provincia y estado.

INSERT INTO company_branches (company_id, name, locality, province, is_active)
SELECT c.id, seed.name, seed.locality, 'Córdoba', 1
FROM companies c
INNER JOIN (
    SELECT 'Farmacia Moderna 1' AS name, 'Villa María' AS locality
    UNION ALL SELECT 'Farmacia Moderna 2', 'Villa María'
    UNION ALL SELECT 'Farmacia Moderna 7', 'Villa María'
    UNION ALL SELECT 'Farmacia Moderna 8', 'Villa María'
    UNION ALL SELECT 'Farmacia Marañon', 'Villa María'
    UNION ALL SELECT 'Farmacia del Subnivel', 'Villa María'
    UNION ALL SELECT 'Farmacia del Condor', 'Villa María'
    UNION ALL SELECT 'Farmacia Palermo', 'Villa María'
    UNION ALL SELECT 'Farmacia Plaza', 'Villa María'
    UNION ALL SELECT 'Marcellino', 'Villa María'
    UNION ALL SELECT 'Farmacia Domenino', 'Villa María'
    UNION ALL SELECT 'Callcenter', 'Villa María'
    UNION ALL SELECT 'Administración', 'Villa María'
    UNION ALL SELECT 'Obras Sociales', 'Villa María'
    UNION ALL SELECT 'Sistemas', 'Villa María'
    UNION ALL SELECT 'Recursos Humanos', 'Villa María'
    UNION ALL SELECT 'Drogueria Central', 'Villa María'
    UNION ALL SELECT 'Distribuidora FCF', 'Villa María'
    UNION ALL SELECT 'Comercial y MKT', 'Villa María'
    UNION ALL SELECT 'Farmacia Moderna 5', 'Córdoba'
    UNION ALL SELECT 'Farmacia Oulton', 'Córdoba'
    UNION ALL SELECT 'Farmacia Moderna 4', 'Bell Ville'
    UNION ALL SELECT 'Farmacia Moderna 11', 'Bell Ville'
    UNION ALL SELECT 'Farmacia Alladio', 'Bell Ville'
    UNION ALL SELECT 'Farmacia Muscatelo', 'Bell Ville'
    UNION ALL SELECT 'Farmacia Moderna 3', 'Marcos Juarez'
    UNION ALL SELECT 'Farmacia Moderna 10', 'Marcos Juarez'
    UNION ALL SELECT 'Farmacia Novofarma', 'Leones'
    UNION ALL SELECT 'Farmacia Moderna 9', 'La Carlota'
    UNION ALL SELECT 'Santa Teresita', 'Villa Dolores'
    UNION ALL SELECT 'General Paz', 'Villa Dolores'
    UNION ALL SELECT 'Farmacia Moderna 12', 'Saira'
) AS seed
INNER JOIN (SELECT 1) AS guard ON 1 = 1
WHERE c.organization_group = 'moderna'
ON DUPLICATE KEY UPDATE
    locality = VALUES(locality),
    province = VALUES(province),
    is_active = VALUES(is_active);
