<?php
/**
 * Copiar a config.local.php en este mismo directorio (no versionar).
 * Las constantes definidas aquí reemplazan los valores por defecto de config.php.
 *
 * Ejemplo mínimo en producción:
 *   define('URLROOT', 'https://paviotti.com.ar/gestion_horas_extras');
 *   define('DB_PASS', 'contraseña_segura');
 *   define('CLOCK_API_PASSWORD', '...');
 *   define('EXTINTOS_DB_PASS', '...');
 *
 * URLROOT y APP_ENV se detectan solos (local vs hosting). Ver config.local.example.php.
 * Forzar solo si hace falta: define('URLROOT', 'https://paviotti.com.ar/gestion_horas_extras');
 */
define('DB_PASS', 'cambiar');
define('CLOCK_API_PASSWORD', 'cambiar');
define('EXTINTOS_DB_PASS', 'cambiar');

// ATS asistido por IA. Nunca versionar la clave real.
define('OPENAI_API_KEY', '');
define('OPENAI_CV_MODEL', 'gpt-5.6-luna');
// Opcional: ruta absoluta a clamscan para exigir análisis antivirus de CV.
define('CLAMSCAN_BIN', '');
// Opcional: ruta absoluta a pdftotext para extracción local de CV PDF.
define('PDFTOTEXT_BIN', 'pdftotext');
