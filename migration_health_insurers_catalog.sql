-- Catálogo comercial de coberturas solicitado para el legajo digital.
-- Es idempotente por uq_health_insurer_display_name. Las razones sociales,
-- códigos oficiales y CUIT deben ser validados por RR. HH. antes de derivar aportes.

INSERT INTO health_insurers (legal_name, display_name, insurer_type) VALUES
('AMICOS', 'AMICOS', 'mutual'),
('AMUR', 'AMUR', 'mutual'),
('APM', 'APM', 'obra_social'),
('ASPURC', 'ASPURC', 'obra_social'),
('Avalian', 'Avalian', 'prepaga'),
('Caja Notarial', 'Caja Notarial', 'obra_social'),
('CPCE', 'CPCE', 'mutual'),
('DASUTEN', 'DASUTEN', 'obra_social'),
('Federada Salud', 'Federada Salud', 'prepaga'),
('Jerárquicos Salud', 'Jerárquicos Salud', 'mutual'),
('OPDEA', 'OPDEA', 'obra_social'),
('OSDE', 'OSDE', 'prepaga'),
('OSFATUN', 'OSFATUN', 'obra_social'),
('OSITAC', 'OSITAC', 'obra_social'),
('OSPECOR', 'OSPECOR', 'obra_social'),
('OSPES', 'OSPES', 'obra_social'),
('OSPJN', 'OSPJN', 'obra_social'),
('OSSACRA', 'OSSACRA', 'obra_social'),
('Medifé', 'Medifé', 'prepaga'),
('Poder Judicial', 'Poder Judicial', 'obra_social'),
('Prevención Salud', 'Prevención Salud', 'prepaga'),
('Sancor Salud', 'Sancor Salud', 'prepaga'),
('SMAI', 'SMAI', 'mutual'),
('SOS Red de Asistencia', 'SOS Red de Asistencia', 'prepaga'),
('Swiss Medical', 'Swiss Medical', 'prepaga')
ON DUPLICATE KEY UPDATE
    legal_name = VALUES(legal_name),
    insurer_type = VALUES(insurer_type),
    is_active = 1,
    updated_at = NOW();
