<?php
/**
 * Crea el usuario administrador RRHH de Moderna (axel.moderna) desde la CLI,
 * con la misma ruta de código que el formulario Usuarios → Crear:
 *
 *   - rol admin, empresa MODERNA SRL, employee_group 'moderna'
 *   - scope 'administrador' en user_access_scopes (mismo INSERT idempotente
 *     que scripts/fix_scope_admin_moderna.sql)
 *
 * Idempotente: si el usuario ya existe no lo toca, solo asegura el scope.
 *
 * Uso (pide la contraseña por teclado, sin dejarla en el historial):
 *   php scripts/crear_admin_moderna.php
 * o bien:
 *   php scripts/crear_admin_moderna.php 'CONTRASEÑA'
 */

if (php_sapi_name() !== 'cli') {
    die("Solo CLI.\n");
}
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

$username = getenv('CREAR_ADMIN_USUARIO') ?: 'axel.moderna';

$db = new Database();
$db->query("SELECT id FROM companies WHERE name = 'MODERNA SRL' AND organization_group = 'moderna' LIMIT 1");
$company = $db->single();
if (!$company) {
    fwrite(STDERR, "No existe la empresa MODERNA SRL (grupo moderna). Corré antes: php scripts/aplicar_pendientes_vps.php\n");
    exit(1);
}
$companyId = (int)$company->id;

$userModel = new User();
$existente = $userModel->getUserByUsername($username);

if (!$existente) {
    $pass = $argv[1] ?? '';
    if ($pass === '') {
        $leer = function ($prompt) {
            echo $prompt;
            @shell_exec('stty -echo 2>/dev/null');
            $v = trim((string)fgets(STDIN));
            @shell_exec('stty echo 2>/dev/null');
            echo "\n";
            return $v;
        };
        $pass = $leer("Contraseña para $username: ");
        $pass2 = $leer('Repetila: ');
        if ($pass !== $pass2) {
            die("Las contraseñas no coinciden.\n");
        }
    }
    if (strlen($pass) < 8) {
        die("Usá una contraseña de al menos 8 caracteres (es un admin de producción).\n");
    }

    $ok = $userModel->createUser([
        'username' => $username,
        'full_name' => 'Axel Le Roux',
        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
        'role' => 'admin',
        'company_id' => $companyId,
        'employee_group' => 'moderna',
        'attendance_control_mode' => 'no_clock',
    ]);
    if (!$ok) {
        fwrite(STDERR, "No se pudo crear el usuario.\n");
        exit(1);
    }
    echo "Usuario $username creado (rol admin, MODERNA SRL, grupo moderna).\n";
    echo "Ingreso: {$username}+CONTRASEÑA en el campo único del login.\n";
} else {
    echo "El usuario $username ya existe (id {$existente->id}); no se modifica.\n";
}

// Scope administrador (idéntico a scripts/fix_scope_admin_moderna.sql).
$db->query("INSERT INTO user_access_scopes (user_id, company_id, branch_id, access_role, is_primary, is_active, starts_on)
    SELECT u.id, u.company_id, NULL, 'administrador', 1, 1, CURDATE()
    FROM users u
    WHERE u.username = ?
      AND NOT EXISTS (SELECT 1 FROM user_access_scopes s WHERE s.user_id = u.id AND s.access_role = 'administrador')");
$db->execute([$username]);

$db->query("SELECT COUNT(*) AS n FROM user_access_scopes s JOIN users u ON u.id = s.user_id
    WHERE u.username = ? AND s.access_role = 'administrador'");
$scopes = (int)($db->single([$username])->n ?? 0);
echo $scopes > 0
    ? "Scope 'administrador' verificado.\n"
    : "ATENCIÓN: no se pudo verificar el scope 'administrador'.\n";
echo "Ahora: php scripts/verificar_esquema_vps.php\n";
