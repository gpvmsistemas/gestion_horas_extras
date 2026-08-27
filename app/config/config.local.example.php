<?php
/**
 * Copiar a config.local.php (este archivo NO se versiona).
 * Solo overrides por máquina: credenciales BD, APIs, etc.
 *
 * URLROOT y APP_DEBUG se detectan solos:
 *   - localhost / XAMPP  → http://localhost/gestion_horas_extras
 *   - paviotti.com.ar    → https://paviotti.com.ar/gestion_horas_extras
 *
 * Forzar manualmente (opcional):
 *   define('URLROOT', 'https://paviotti.com.ar/gestion_horas_extras');
 */

// define('DB_HOST', 'localhost');
// define('DB_USER', 'usuario_hosting');
// define('DB_PASS', 'contraseña');
// define('DB_NAME', 'paviotti_lanaturaleza');

/**
 * Reclutamiento/Vacantes — organizaciones habilitadas (piloto).
 * Sin definir: solo Moderna. Para el go-live de Paviotti:
 */
// define('RECRUITING_ORGS', ['moderna', 'paviotti']);
