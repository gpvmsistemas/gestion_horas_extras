<?php
/**
 * Applier idempotente de TODAS las migraciones pendientes de la Suite P&M.
 *
 * Existe porque el VPS corre MySQL (no MariaDB): los .sql que usan
 * `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` fallan ahí con error 1064.
 * Este script hace la detección él mismo (SHOW TABLES/COLUMNS/INDEX) y
 * ejecuta solo lo que falta, en el orden correcto de dependencias:
 *
 *   company_branches (prerrequisito, la crea migration_ecofarma_branches.sql)
 *   → users.employee_group → users/employee_schedules.branch_id (FK a
 *   company_branches) → attendance_control_mode → relojes (clock_devices y
 *   tablas asociadas) → control de acceso (user_access_scopes) → Registro de
 *   Horas → sociedades Moderna (MODERNA/FRANCE/FCF) → sucursales Moderna →
 *   reasignación de sucursales por sociedad → video en anuncios → horario de
 *   atención → ficha personal → CCT 430/05 Farmacia Córdoba.
 *
 * Re-ejecutable: cada paso se saltea si ya está aplicado. Nunca pisa datos
 * cargados (p. ej. los horarios de atención solo se siembran donde están
 * vacíos, y las sucursales Moderna solo si el grupo no tiene ninguna).
 *
 * Uso (con la base de app/config/config.local.php):
 *   php scripts/aplicar_pendientes_vps.php
 */

if (php_sapi_name() !== 'cli') {
    die("Solo CLI.\n");
}
$root = dirname(__DIR__);
require $root . '/app/config/config.php';
if (file_exists($root . '/app/config/config.local.php')) {
    require $root . '/app/config/config.local.php';
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . (defined('DB_PORT') ? ';port=' . DB_PORT : '') . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$hasTab = function ($tabla) use ($pdo) {
    $st = $pdo->prepare('SHOW TABLES LIKE ?');
    $st->execute([$tabla]);
    return (bool)$st->fetch();
};
$hasCol = function ($tabla, $columna) use ($pdo) {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$tabla` LIKE ?");
    $st->execute([$columna]);
    return (bool)$st->fetch();
};
$hasIdx = function ($tabla, $indice) use ($pdo) {
    $st = $pdo->query("SHOW INDEX FROM `$tabla`");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['Key_name'] ?? '') === $indice) {
            return true;
        }
    }
    return false;
};
$hasUniqueOn = function ($tabla, $columna) use ($pdo) {
    $st = $pdo->query("SHOW INDEX FROM `$tabla`");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['Column_name'] ?? '') === $columna && (int)($row['Non_unique'] ?? 1) === 0) {
            return true;
        }
    }
    return false;
};
$scalar = function ($sql, array $params = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
};
$colInfo = function ($tabla, $columna) use ($pdo) {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$tabla` LIKE ?");
    $st->execute([$columna]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
};
$colType = fn($tabla, $columna) => strtolower((string)(($colInfo($tabla, $columna)['Type'] ?? '')));
$colNullable = fn($tabla, $columna) => (($colInfo($tabla, $columna)['Null'] ?? '') === 'YES');
$addCol = function ($tabla, $columna, $definicion) use ($pdo, $hasCol) {
    if ($hasCol($tabla, $columna)) {
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN $definicion");
    } catch (PDOException $e) {
        // El ancla del AFTER no existe en este esquema: agrega al final.
        $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN " . preg_replace('/ AFTER \S+$/i', '', $definicion));
    }
};

$fallo = 0;
$paso = function ($nombre, callable $yaAplicada, callable $aplicar) use (&$fallo) {
    try {
        if ($yaAplicada()) {
            echo "  [ya aplicada] $nombre\n";
            return;
        }
        $aplicar();
        echo "  [APLICADA  ✓] $nombre\n";
    } catch (Throwable $e) {
        $fallo++;
        echo "  [ERROR      ] $nombre\n";
        echo '                ' . $e->getMessage() . "\n";
        echo "                Corregí esto y volvé a correr el script (los pasos ya aplicados se saltean).\n";
        exit(1);
    }
};

echo "Aplicando migraciones pendientes en " . DB_NAME . "@" . DB_HOST . "\n\n";

// ── Prerrequisito ────────────────────────────────────────────────────────────
if (!$hasTab('company_branches')) {
    fwrite(STDERR, "Falta company_branches. Ejecutá primero: mysql BASE < migration_ecofarma_branches.sql\n");
    exit(1);
}

// ── 1 · users.employee_group ────────────────────────────────────────────────
$paso('users.employee_group',
    fn() => $hasCol('users', 'employee_group'),
    function () use ($pdo) {
        $pdo->exec("ALTER TABLE users
            ADD COLUMN employee_group ENUM('paviotti', 'moderna') NOT NULL DEFAULT 'paviotti' AFTER company_id,
            ADD INDEX idx_users_employee_group (employee_group)");
    });

// ── 2 · branch_id en users y employee_schedules (planner scope) ─────────────
$paso('users.branch_id (+FK a company_branches)',
    fn() => $hasCol('users', 'branch_id'),
    function () use ($pdo) {
        $pdo->exec('ALTER TABLE users
            ADD COLUMN branch_id INT NULL AFTER company_id,
            ADD INDEX idx_users_company_branch (company_id, branch_id),
            ADD CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE SET NULL');
    });

$paso('employee_schedules.branch_id (+FK)',
    fn() => $hasCol('employee_schedules', 'branch_id'),
    function () use ($pdo) {
        $pdo->exec('ALTER TABLE employee_schedules
            ADD COLUMN branch_id INT NULL AFTER user_id,
            ADD INDEX idx_employee_schedules_branch_date (branch_id, schedule_date),
            ADD CONSTRAINT fk_employee_schedules_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE SET NULL');
    });

// ── 3 · users.attendance_control_mode ───────────────────────────────────────
$paso('users.attendance_control_mode',
    fn() => $hasCol('users', 'attendance_control_mode'),
    function () use ($pdo) {
        $pdo->exec("ALTER TABLE users
            ADD COLUMN attendance_control_mode ENUM('required','flexible','no_clock') NOT NULL DEFAULT 'required' AFTER branch_id,
            ADD INDEX idx_users_attendance_control (company_id, attendance_control_mode)");
    });

// ── 4 · Relojes: en el VPS clock_devices quedó creada pero el corte por el FK
//        dejó sin crear las tablas asociadas — se verifican una por una. ─────
$paso('Relojes (clock_devices + sucursales + mapeos + asignaciones)',
    function () use ($hasTab) {
        return $hasTab('clock_devices') && $hasTab('clock_device_branches')
            && $hasTab('employee_branch_assignments') && $hasTab('user_clock_device_mappings');
    },
    function () use ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS clock_devices (
            id INT NOT NULL AUTO_INCREMENT,
            external_name VARCHAR(160) NOT NULL,
            display_name VARCHAR(160) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY uk_clock_devices_external_name (external_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec('CREATE TABLE IF NOT EXISTS clock_device_branches (
            clock_device_id INT NOT NULL,
            branch_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (clock_device_id, branch_id),
            CONSTRAINT fk_clock_device_branches_device FOREIGN KEY (clock_device_id) REFERENCES clock_devices(id) ON DELETE CASCADE,
            CONSTRAINT fk_clock_device_branches_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS employee_branch_assignments (
            user_id INT NOT NULL,
            branch_id INT NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, branch_id),
            CONSTRAINT fk_employee_branch_assignments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_employee_branch_assignments_branch FOREIGN KEY (branch_id) REFERENCES company_branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_clock_device_mappings (
            user_id INT NOT NULL,
            clock_device_id INT NOT NULL,
            employee_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (clock_device_id, employee_id),
            KEY idx_user_clock_device_mappings_user (user_id),
            CONSTRAINT fk_user_clock_device_mappings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_clock_device_mappings_device FOREIGN KEY (clock_device_id) REFERENCES clock_devices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    });

$paso('Seed de relojes desde marcaciones_cache',
    fn() => !$hasTab('marcaciones_cache'),
    function () use ($pdo) {
        $pdo->exec("INSERT INTO clock_devices (external_name, display_name)
            SELECT DISTINCT TRIM(device_name), TRIM(device_name)
            FROM marcaciones_cache
            WHERE device_name IS NOT NULL AND TRIM(device_name) <> ''
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)");
    });

$paso('Seed de employee_branch_assignments desde users.branch_id',
    fn() => false,
    function () use ($pdo) {
        $pdo->exec('INSERT IGNORE INTO employee_branch_assignments (user_id, branch_id, is_primary)
            SELECT id, branch_id, 1 FROM users WHERE branch_id IS NOT NULL');
    });

// ── 5 · Control de acceso (todas sus sentencias son auto-guardadas) ─────────
$paso('Control de acceso (user_access_scopes + políticas + auditoría)',
    fn() => false,
    function () use ($pdo, $root) {
        $sql = file_get_contents($root . '/migration_access_control_scopes.sql');
        if ($sql === false) {
            throw new RuntimeException('No se encontró migration_access_control_scopes.sql');
        }
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    });

// ── 5b · Programa RRHH Integral (hr_operations_talent) ──────────────────────
// 27 tablas: auditoría, capacidades, cierres, vencimientos, EPP, activos,
// ATS/vacantes, desempeño, onboarding, flags. El .sql es MariaDB-flavored
// (CREATE TRIGGER IF NOT EXISTS, ADD COLUMN IF NOT EXISTS, CREATE INDEX IF
// NOT EXISTS): acá se ejecuta sentencia por sentencia con las adaptaciones.
$paso('RRHH Integral (auditoría, capacidades, EPP, activos, ATS, desempeño)',
    function () use ($hasTab, $hasCol, $scalar) {
        foreach (['audit_events', 'access_capabilities', 'employee_expirations', 'ppe_deliveries',
                  'assets', 'job_vacancies', 'candidates', 'job_applications', 'career_consents',
                  'performance_reviews', 'onboarding_checklists', 'scheduled_job_runs', 'hr_feature_flags'] as $t) {
            if (!$hasTab($t)) {
                return false;
            }
        }
        return $hasCol('users', 'employment_status')
            && (int)$scalar('SELECT COUNT(*) FROM career_consents') >= 1
            && (int)$scalar('SELECT COUNT(*) FROM access_capabilities') >= 20;
    },
    function () use ($pdo, $root, $hasTab, $hasCol, $hasIdx, $scalar) {
        $sql = file_get_contents($root . '/migration_hr_operations_talent.sql');
        if ($sql === false) {
            throw new RuntimeException('No se encontró migration_hr_operations_talent.sql');
        }
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $st) {
            $st = trim($st);
            if ($st === '' || strpos($st, '--') === 0 && strpos($st, "\n") === false) {
                continue;
            }
            // CREATE TRIGGER IF NOT EXISTS (IF NOT EXISTS es MariaDB / MySQL 8.0.29+)
            if (preg_match('/CREATE TRIGGER IF NOT EXISTS\s+(\w+)/i', $st, $m)) {
                $existe = (int)$scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS
                    WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?', [$m[1]]);
                if (!$existe) {
                    $pdo->exec(preg_replace('/IF NOT EXISTS\s+/i', '', $st, 1));
                }
                continue;
            }
            // ALTER TABLE t ADD COLUMN IF NOT EXISTS c1 ..., ADD COLUMN IF NOT EXISTS c2 ...
            if (preg_match('/ALTER TABLE\s+`?(\w+)`?\s+(ADD COLUMN IF NOT EXISTS.*)$/is', $st, $m)) {
                $tabla = $m[1];
                if (!$hasTab($tabla)) {
                    echo "                (aviso: falta la tabla $tabla en esta base; se omite su ALTER)\n";
                    continue;
                }
                $partes = preg_split('/,?\s*ADD COLUMN IF NOT EXISTS\s+/i', $m[2]);
                array_shift($partes);
                foreach ($partes as $def) {
                    $def = rtrim(trim($def), ';,');
                    if ($def === '' || !preg_match('/^(\w+)/', $def, $c) || $hasCol($tabla, $c[1])) {
                        continue;
                    }
                    $pdo->exec("ALTER TABLE `$tabla` ADD COLUMN $def");
                }
                continue;
            }
            // CREATE INDEX IF NOT EXISTS idx ON tabla(...)
            if (preg_match('/CREATE INDEX IF NOT EXISTS\s+(\w+)\s+ON\s+`?(\w+)`?/i', $st, $m)) {
                if (!$hasIdx($m[2], $m[1])) {
                    $pdo->exec(preg_replace('/IF NOT EXISTS\s+/i', '', $st, 1));
                }
                continue;
            }
            // Resto: CREATE TABLE IF NOT EXISTS e INSERTs idempotentes, tal cual.
            $pdo->exec($st);
        }
    });

// ── 6 · Registro de Horas ───────────────────────────────────────────────────
$paso('employee_schedules.branch_name',
    fn() => $hasCol('employee_schedules', 'branch_name'),
    fn() => $pdo->exec('ALTER TABLE employee_schedules ADD COLUMN branch_name VARCHAR(120) NULL DEFAULT NULL AFTER notes'));

$paso('Índice idx_es_user_date en employee_schedules',
    fn() => $hasIdx('employee_schedules', 'idx_es_user_date'),
    fn() => $pdo->exec('ALTER TABLE employee_schedules ADD INDEX idx_es_user_date (user_id, schedule_date)'));

$paso('holidays.city (feriados locales)',
    fn() => $hasCol('holidays', 'city'),
    fn() => $pdo->exec('ALTER TABLE holidays ADD COLUMN city VARCHAR(80) NULL DEFAULT NULL AFTER name'));

// ── 7 · Sociedades Moderna ──────────────────────────────────────────────────
$paso('Sociedad MODERNA SRL',
    fn() => (int)$scalar("SELECT COUNT(*) FROM companies WHERE name = 'MODERNA SRL'") > 0,
    function () use ($pdo, $scalar) {
        $placeholder = $scalar("SELECT id FROM companies WHERE name = 'Moderna' AND organization_group = 'moderna'");
        if ($placeholder !== null) {
            $pdo->exec("UPDATE companies SET name = 'MODERNA SRL' WHERE id = " . (int)$placeholder);
        } else {
            $pdo->exec("INSERT INTO companies (name, extras_mode, show_overtime, show_cp_extras, organization_group)
                VALUES ('MODERNA SRL', 'hours', 1, 0, 'moderna')");
        }
    });

$paso('Sociedades FRANCE SRL y DISTRIBUIDORA FCF SAS',
    function () use ($scalar) {
        return (int)$scalar("SELECT COUNT(*) FROM companies WHERE name IN ('FRANCE SRL', 'DISTRIBUIDORA FCF SAS')") >= 2;
    },
    function () use ($pdo) {
        $pdo->exec("INSERT INTO companies (name, extras_mode, show_overtime, show_cp_extras, organization_group)
            SELECT 'FRANCE SRL', 'hours', 1, 0, 'moderna' FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'FRANCE SRL')");
        $pdo->exec("INSERT INTO companies (name, extras_mode, show_overtime, show_cp_extras, organization_group)
            SELECT 'DISTRIBUIDORA FCF SAS', 'hours', 1, 0, 'moderna' FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'DISTRIBUIDORA FCF SAS')");
    });

// ── 8 · Sucursales Moderna ──────────────────────────────────────────────────
// Solo si el grupo no tiene NINGUNA sucursal: con las 3 sociedades ya creadas,
// re-sembrar duplicaría (el UNIQUE es por company_id + name). Se siembran
// todas bajo MODERNA SRL; el paso siguiente las reparte por sociedad.
$paso('Sucursales Moderna (32 sucursales)',
    function () use ($scalar) {
        return (int)$scalar("SELECT COUNT(*) FROM company_branches cb
            JOIN companies c ON c.id = cb.company_id
            WHERE c.organization_group = 'moderna'") > 0;
    },
    function () use ($pdo, $scalar) {
        $modernaId = (int)$scalar("SELECT id FROM companies WHERE name = 'MODERNA SRL'");
        if ($modernaId <= 0) {
            throw new RuntimeException('No se encontró la sociedad MODERNA SRL.');
        }
        $seed = [
            ['Farmacia Moderna 1', 'Villa María'], ['Farmacia Moderna 2', 'Villa María'],
            ['Farmacia Moderna 7', 'Villa María'], ['Farmacia Moderna 8', 'Villa María'],
            ['Farmacia Marañon', 'Villa María'], ['Farmacia del Subnivel', 'Villa María'],
            ['Farmacia del Condor', 'Villa María'], ['Farmacia Palermo', 'Villa María'],
            ['Farmacia Plaza', 'Villa María'], ['Marcellino', 'Villa María'],
            ['Farmacia Domenino', 'Villa María'], ['Callcenter', 'Villa María'],
            ['Administración', 'Villa María'], ['Obras Sociales', 'Villa María'],
            ['Sistemas', 'Villa María'], ['Recursos Humanos', 'Villa María'],
            ['Drogueria Central', 'Villa María'], ['Distribuidora FCF', 'Villa María'],
            ['Comercial y MKT', 'Villa María'],
            ['Farmacia Moderna 5', 'Córdoba'], ['Farmacia Oulton', 'Córdoba'],
            ['Farmacia Moderna 4', 'Bell Ville'], ['Farmacia Moderna 11', 'Bell Ville'],
            ['Farmacia Alladio', 'Bell Ville'], ['Farmacia Muscatelo', 'Bell Ville'],
            ['Farmacia Moderna 3', 'Marcos Juarez'], ['Farmacia Moderna 10', 'Marcos Juarez'],
            ['Farmacia Novofarma', 'Leones'], ['Farmacia Moderna 9', 'La Carlota'],
            ['Santa Teresita', 'Villa Dolores'], ['General Paz', 'Villa Dolores'],
            ['Farmacia Moderna 12', 'Saira'],
        ];
        $st = $pdo->prepare("INSERT IGNORE INTO company_branches (company_id, name, locality, province, is_active)
            VALUES (?, ?, ?, 'Córdoba', 1)");
        foreach ($seed as [$name, $locality]) {
            $st->execute([$modernaId, $name, $locality]);
        }
    });

// ── 9 · Reparto de sucursales y respaldos por sociedad (idempotente) ────────
$paso('Reasignación de sucursales por sociedad + ubicación administrativa',
    fn() => false,
    function () use ($pdo, $hasCol) {
        $pdo->exec("UPDATE company_branches cb
            JOIN companies actual ON actual.id = cb.company_id AND actual.organization_group = 'moderna'
            JOIN companies destino ON destino.name = 'MODERNA SRL'
            SET cb.company_id = destino.id
            WHERE cb.name IN ('Farmacia Moderna 1', 'Farmacia Moderna 2', 'Farmacia Moderna 3', 'Farmacia Moderna 4')");
        $pdo->exec("UPDATE company_branches cb
            JOIN companies actual ON actual.id = cb.company_id AND actual.organization_group = 'moderna'
            JOIN companies destino ON destino.name = 'DISTRIBUIDORA FCF SAS'
            SET cb.company_id = destino.id
            WHERE cb.name = 'Distribuidora FCF'");
        $pdo->exec("UPDATE company_branches cb
            JOIN companies actual ON actual.id = cb.company_id AND actual.organization_group = 'moderna'
            JOIN companies destino ON destino.name = 'FRANCE SRL'
            SET cb.company_id = destino.id
            WHERE cb.name NOT IN ('Farmacia Moderna 1', 'Farmacia Moderna 2', 'Farmacia Moderna 3', 'Farmacia Moderna 4', 'Distribuidora FCF')
              AND cb.company_id <> destino.id");
        if ($hasCol('users', 'employee_group') && $hasCol('users', 'branch_id')) {
            $pdo->exec("UPDATE users u
                JOIN company_branches cb ON cb.id = u.branch_id
                SET u.company_id = cb.company_id
                WHERE u.employee_group = 'moderna'
                  AND u.branch_id IS NOT NULL
                  AND u.company_id <> cb.company_id");
        }
        $pdo->exec("INSERT INTO company_locations (company_id, locality, province)
            SELECT c.id, 'Villa María', 'Córdoba' FROM companies c
            WHERE c.organization_group = 'moderna'
              AND NOT EXISTS (SELECT 1 FROM company_locations cl WHERE cl.company_id = c.id)");
    });

// ── 10 · Video en anuncios ──────────────────────────────────────────────────
$paso('announcements.video_path',
    fn() => $hasCol('announcements', 'video_path'),
    fn() => $pdo->exec('ALTER TABLE announcements ADD COLUMN video_path VARCHAR(255) NULL DEFAULT NULL AFTER image_path'));

// ── 11 · Horario de atención por sucursal ───────────────────────────────────
$paso('company_branches.schedule_text',
    fn() => $hasCol('company_branches', 'schedule_text'),
    fn() => $pdo->exec('ALTER TABLE company_branches ADD COLUMN schedule_text VARCHAR(255) NULL AFTER province'));

$paso('Seed de horarios de atención (solo sucursales sin horario cargado)',
    fn() => false,
    function () use ($pdo) {
        $horarios = [
            'Farmacia Moderna 1'    => 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:30 a 13:30 y 16:30 a 21:30',
            'Farmacia Moderna 2'    => 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:00 a 13:00',
            'Farmacia Moderna 3'    => 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:00 a 13:00 y 16:30 a 21:30 | Domingo: 08:00 a 13:00',
            'Farmacia Moderna 4'    => 'Lunes a Viernes: 08:00 a 22:00 | Sábado: 08:00 a 14:00 y 17:00 a 22:00',
            'Farmacia Moderna 5'    => 'Lunes a Viernes: 08:00 a 22:30 | Sábado: 09:00 a 14:00 y 17:00 a 22:30 | Domingo: 17:00 a 22:00',
            'Farmacia Moderna 7'    => 'Lunes a Sábado: 08:00 a 13:00 y 16:30 a 21:30',
            'Farmacia Moderna 8'    => 'Lunes a Viernes: 08:00 a 20:30 | Sábado: 08:00 a 13:00',
            'Farmacia Moderna 9'    => 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:00 | Sábado: 08:00 a 13:00 y 16:30 a 21:30',
            'Farmacia Moderna 10'   => 'Lunes a Viernes: 08:00 a 13:00 y 17:00 a 21:30 | Sábado: 08:00 a 13:00 y 16:30 a 21:30',
            'Farmacia Moderna 11'   => 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:30 | Sábado: 08:00 a 13:00 y 16:30 a 21:30',
            'Farmacia Moderna 12'   => 'Lunes a Viernes: 08:30 a 12:30 y 17:00 a 21:00 | Sábado: 08:30 a 13:30',
            'Marcellino'            => '24 hs',
            'Farmacia del Condor'   => 'Lunes a Viernes: 08:00 a 21:30 | Sábado: 08:30 a 13:30',
            'Farmacia del Subnivel' => 'Lunes a Viernes: 08:00 a 21:00 | Sábado: 08:00 a 13:00',
            'Farmacia Marañon'      => 'Lunes a Viernes: 08:00 a 20:30 | Sábado: 08:00 a 13:00',
            'Farmacia Palermo'      => 'Lunes a Sábado: 08:30 a 13:30 y 16:30 a 21:30',
            'Farmacia Plaza'        => 'Lunes a Sábado: 08:00 a 13:00 y 16:30 a 21:30',
            'Farmacia Oulton'       => 'Lunes a Viernes: 08:00 a 21:00 | Sábado: 08:00 a 13:00',
            'Farmacia Alladio'      => 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:00 | Sábado: 08:00 a 13:00',
            'Farmacia Muscatelo'    => 'Lunes a Viernes: 08:30 a 13:00 y 17:00 a 21:00 | Sábado: 08:00 a 13:00',
            'Santa Teresita'        => 'Lunes a Viernes: 08:30 a 13:00 y 17:00 a 22:00 | Sábado: 09:00 a 13:00',
            'General Paz'           => 'Lunes a Viernes: 08:30 a 12:30 y 17:00 a 21:00 | Sábado: 09:00 a 13:00',
            'Farmacia Novofarma'    => 'Lunes a Viernes: 08:00 a 13:00 y 16:30 a 21:00 | Sábado: 08:00 a 13:00',
        ];
        $st = $pdo->prepare("UPDATE company_branches
            SET schedule_text = ?
            WHERE name = ? AND (schedule_text IS NULL OR schedule_text = '')");
        foreach ($horarios as $name => $texto) {
            $st->execute([$texto, $name]);
        }
    });

// ── 12 · Ficha personal ─────────────────────────────────────────────────────
$fichaCols = [
    'marital_status'                 => "ADD COLUMN marital_status VARCHAR(30) NULL AFTER birth_date",
    'children_count'                 => "ADD COLUMN children_count TINYINT UNSIGNED NULL AFTER marital_status",
    'emergency_contact_relationship' => "ADD COLUMN emergency_contact_relationship VARCHAR(60) NULL AFTER emergency_contact_name",
    'hr_notes'                       => "ADD COLUMN hr_notes TEXT NULL AFTER emergency_contact_phone",
];
foreach ($fichaCols as $colName => $clause) {
    $paso("users.$colName (ficha personal)",
        fn() => $hasCol('users', $colName),
        function () use ($pdo, $clause) {
            try {
                $pdo->exec("ALTER TABLE users $clause");
            } catch (PDOException $e) {
                // Si el ancla del AFTER no existe en este esquema, agrega al final.
                $pdo->exec('ALTER TABLE users ' . preg_replace('/ AFTER \S+$/', '', $clause));
            }
        });
}

// ── 13 · Vacaciones v2 — alineación de esquema (migration_vacation_management_v2.sql
//         nunca se aplicó completa en el VPS: su ADD COLUMN IF NOT EXISTS es
//         de MariaDB. El verificador solo miraba que exista la tabla base. ───
if (!$hasTab('vacation_balance_periods') || !$hasTab('vacation_balance_movements')
    || !$hasTab('collective_agreements') || !$hasTab('collective_agreement_rules')) {
    fwrite(STDERR, "Faltan las tablas base de vacaciones v2 (las crea la migración base de Lautaro, paso 38 del paquete hosting).\n");
    exit(1);
}

$paso('Vacaciones v2 — columnas de collective_agreements',
    function () use ($hasCol, $hasUniqueOn) {
        foreach (['code', 'description', 'jurisdiction', 'legal_reference', 'period_start_month',
                  'period_start_day', 'notice_days', 'start_rule', 'split_policy',
                  'minimum_request_days', 'is_active'] as $c) {
            if (!$hasCol('collective_agreements', $c)) {
                return false;
            }
        }
        return $hasUniqueOn('collective_agreements', 'code');
    },
    function () use ($pdo, $addCol, $hasCol, $hasUniqueOn) {
        if (!$hasCol('collective_agreements', 'code')) {
            $addCol('collective_agreements', 'code', "code VARCHAR(40) NOT NULL DEFAULT '' AFTER id");
            $pdo->exec("UPDATE collective_agreements SET code = 'CEC' WHERE code = '' AND name LIKE '%Comercio%'");
        }
        $addCol('collective_agreements', 'description', 'description TEXT NULL AFTER name');
        $addCol('collective_agreements', 'jurisdiction', 'jurisdiction VARCHAR(180) NULL AFTER description');
        $addCol('collective_agreements', 'legal_reference', 'legal_reference VARCHAR(255) NULL AFTER jurisdiction');
        $addCol('collective_agreements', 'period_start_month', 'period_start_month TINYINT NOT NULL DEFAULT 10 AFTER legal_reference');
        $addCol('collective_agreements', 'period_start_day', 'period_start_day TINYINT NOT NULL DEFAULT 1 AFTER period_start_month');
        $addCol('collective_agreements', 'notice_days', 'notice_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER period_start_day');
        $addCol('collective_agreements', 'start_rule', "start_rule VARCHAR(40) NOT NULL DEFAULT 'lct' AFTER notice_days");
        $addCol('collective_agreements', 'split_policy', "split_policy VARCHAR(40) NOT NULL DEFAULT 'lct_7' AFTER start_rule");
        $addCol('collective_agreements', 'minimum_request_days', 'minimum_request_days DECIMAL(5,1) NOT NULL DEFAULT 7.0 AFTER split_policy');
        $addCol('collective_agreements', 'is_active', 'is_active TINYINT(1) NOT NULL DEFAULT 1');
        if (!$hasUniqueOn('collective_agreements', 'code')) {
            $pdo->exec('ALTER TABLE collective_agreements ADD UNIQUE INDEX uk_ca_code (code)');
        }
    });

$paso('Vacaciones v2 — reglas: day_count_mode e índice único',
    function () use ($colType, $hasIdx) {
        return $colType('collective_agreement_rules', 'day_count_mode') === "enum('weekdays','calendar','business_mon_sat')"
            && $hasIdx('collective_agreement_rules', 'uk_car_agreement_min');
    },
    function () use ($pdo, $addCol, $hasCol, $hasIdx, $colType, $scalar) {
        $addCol('collective_agreement_rules', 'max_months', 'max_months INT NULL AFTER min_months');
        $addCol('collective_agreement_rules', 'allows_split', 'allows_split TINYINT(1) NOT NULL DEFAULT 1');
        $addCol('collective_agreement_rules', 'allows_carryover', 'allows_carryover TINYINT(1) NOT NULL DEFAULT 1');
        $addCol('collective_agreement_rules', 'min_consecutive_days', 'min_consecutive_days INT NULL');
        $addCol('collective_agreement_rules', 'notes', 'notes TEXT NULL');
        if (!$hasCol('collective_agreement_rules', 'day_count_mode')) {
            $addCol('collective_agreement_rules', 'day_count_mode',
                "day_count_mode ENUM('weekdays','calendar','business_mon_sat') NOT NULL DEFAULT 'calendar' AFTER days_entitled");
        } elseif ($colType('collective_agreement_rules', 'day_count_mode') !== "enum('weekdays','calendar','business_mon_sat')") {
            $pdo->exec("ALTER TABLE collective_agreement_rules
                MODIFY COLUMN day_count_mode ENUM('weekdays','calendar','business_mon_sat') NOT NULL DEFAULT 'calendar'");
        }
        if (!$hasIdx('collective_agreement_rules', 'uk_car_agreement_min')) {
            $dupes = (int)$scalar('SELECT COUNT(*) FROM (SELECT agreement_id, min_months FROM collective_agreement_rules
                GROUP BY agreement_id, min_months HAVING COUNT(*) > 1) d');
            if ($dupes > 0) {
                throw new RuntimeException("Hay $dupes pares (agreement_id, min_months) duplicados en collective_agreement_rules; resolvelos a mano antes de crear el índice único.");
            }
            $pdo->exec('ALTER TABLE collective_agreement_rules ADD UNIQUE INDEX uk_car_agreement_min (agreement_id, min_months)');
        }
    });

$paso('Vacaciones v2 — columnas de vacation_balance_periods',
    function () use ($hasCol, $hasIdx) {
        foreach (['balance_type', 'adjustment_days', 'expires_at', 'count_mode_snapshot', 'origin_notes'] as $c) {
            if (!$hasCol('vacation_balance_periods', $c)) {
                return false;
            }
        }
        return $hasIdx('vacation_balance_periods', 'uk_vbp_user_period_type')
            && !$hasIdx('vacation_balance_periods', 'uk_vbp_user_period');
    },
    function () use ($pdo, $addCol, $hasCol, $hasIdx, $scalar) {
        $teniaAjustes = $hasCol('vacation_balance_periods', 'adjustment_days');
        $addCol('vacation_balance_periods', 'balance_type',
            "balance_type ENUM('annual','historical','conventional_credit') NOT NULL DEFAULT 'annual' AFTER period_end");
        $addCol('vacation_balance_periods', 'adjustment_days',
            'adjustment_days DECIMAL(5,1) NOT NULL DEFAULT 0.0 AFTER days_taken');
        $addCol('vacation_balance_periods', 'count_mode_snapshot',
            "count_mode_snapshot ENUM('weekdays','calendar','business_mon_sat') NOT NULL DEFAULT 'calendar' AFTER agreement_rule_id");
        $addCol('vacation_balance_periods', 'expires_at', 'expires_at DATE NULL AFTER status');
        $addCol('vacation_balance_periods', 'origin_notes', 'origin_notes VARCHAR(500) NULL AFTER expires_at');
        if (!$hasIdx('vacation_balance_periods', 'idx_vbp_reporting')) {
            $pdo->exec('ALTER TABLE vacation_balance_periods ADD INDEX idx_vbp_reporting (status, balance_type, period_start, expires_at)');
        }
        if (!$hasIdx('vacation_balance_periods', 'uk_vbp_user_period_type')) {
            $dupes = (int)$scalar('SELECT COUNT(*) FROM (SELECT user_id, period_label, balance_type FROM vacation_balance_periods
                GROUP BY user_id, period_label, balance_type HAVING COUNT(*) > 1) d');
            if ($dupes > 0) {
                throw new RuntimeException("Hay $dupes grupos (user_id, period_label, balance_type) duplicados en vacation_balance_periods; resolvelos a mano.");
            }
            $pdo->exec('ALTER TABLE vacation_balance_periods ADD UNIQUE INDEX uk_vbp_user_period_type (user_id, period_label, balance_type)');
        }
        if ($hasIdx('vacation_balance_periods', 'uk_vbp_user_period')) {
            $pdo->exec('ALTER TABLE vacation_balance_periods DROP INDEX uk_vbp_user_period');
        }
        if (!$teniaAjustes) {
            // Recomposición única del saldo con la nueva columna de ajustes.
            $pdo->exec("UPDATE vacation_balance_periods
                SET days_pending = GREATEST(0, days_entitled + adjustment_days - days_taken),
                    status = CASE WHEN days_entitled + adjustment_days - days_taken <= 0 THEN 'closed' ELSE 'open' END");
        }
    });

$paso('Vacaciones v2 — columnas de vacation_balance_movements',
    function () use ($hasCol, $hasIdx, $colType) {
        return $hasCol('vacation_balance_movements', 'operation_key')
            && $hasCol('vacation_balance_movements', 'schedule_snapshot')
            && $hasIdx('vacation_balance_movements', 'uk_vbm_operation')
            && $colType('vacation_balance_movements', 'movement_type') === "enum('accrual','take','adjustment','reversal','opening_balance','import','expiry','conversion','exception')"
            && $colType('vacation_balance_movements', 'source') === "enum('liquidation','request','planner','manual','import','system','cancellation')";
    },
    function () use ($pdo, $addCol, $hasIdx, $colType) {
        if ($colType('vacation_balance_movements', 'movement_type') !== "enum('accrual','take','adjustment','reversal','opening_balance','import','expiry','conversion','exception')") {
            $pdo->exec("ALTER TABLE vacation_balance_movements
                MODIFY COLUMN movement_type ENUM('accrual','take','adjustment','reversal','opening_balance','import','expiry','conversion','exception') NOT NULL");
        }
        if ($colType('vacation_balance_movements', 'source') !== "enum('liquidation','request','planner','manual','import','system','cancellation')") {
            $pdo->exec("ALTER TABLE vacation_balance_movements
                MODIFY COLUMN source ENUM('liquidation','request','planner','manual','import','system','cancellation') NOT NULL");
        }
        $addCol('vacation_balance_movements', 'operation_key', 'operation_key VARCHAR(120) NULL AFTER request_id');
        $addCol('vacation_balance_movements', 'schedule_dates', 'schedule_dates LONGTEXT NULL');
        $addCol('vacation_balance_movements', 'schedule_snapshot', 'schedule_snapshot LONGTEXT NULL AFTER schedule_dates');
        if (!$hasIdx('vacation_balance_movements', 'uk_vbm_operation')) {
            $pdo->exec('ALTER TABLE vacation_balance_movements ADD UNIQUE INDEX uk_vbm_operation (operation_key)');
        }
    });

$paso('Vacaciones v2 — columnas de requests',
    function () use ($hasCol) {
        foreach (['vacation_counted_days', 'vacation_rule_snapshot', 'vacation_exception_reason',
                  'vacation_exception_by', 'vacation_exception_at'] as $c) {
            if (!$hasCol('requests', $c)) {
                return false;
            }
        }
        return true;
    },
    function () use ($addCol) {
        $addCol('requests', 'vacation_counted_days', 'vacation_counted_days DECIMAL(5,1) NULL AFTER admin_notes');
        $addCol('requests', 'vacation_rule_snapshot', 'vacation_rule_snapshot LONGTEXT NULL AFTER vacation_counted_days');
        $addCol('requests', 'vacation_exception_reason', 'vacation_exception_reason VARCHAR(500) NULL AFTER vacation_rule_snapshot');
        $addCol('requests', 'vacation_exception_by', 'vacation_exception_by INT NULL AFTER vacation_exception_reason');
        $addCol('requests', 'vacation_exception_at', 'vacation_exception_at DATETIME NULL AFTER vacation_exception_by');
    });

$paso('users.vacation_days_available en DECIMAL(6,1)',
    fn() => strpos($colType('users', 'vacation_days_available'), 'decimal') === 0,
    function () use ($pdo, $hasCol, $addCol) {
        if (!$hasCol('users', 'vacation_days_available')) {
            $addCol('users', 'vacation_days_available', 'vacation_days_available DECIMAL(6,1) NOT NULL DEFAULT 0.0');
        } else {
            $pdo->exec('ALTER TABLE users MODIFY COLUMN vacation_days_available DECIMAL(6,1) NOT NULL DEFAULT 0.0');
        }
    });

$paso('employee_schedules: start/end nulos (bloques de vacaciones)',
    fn() => $colNullable('employee_schedules', 'start_time') && $colNullable('employee_schedules', 'end_time'),
    fn() => $pdo->exec('ALTER TABLE employee_schedules
        MODIFY COLUMN start_time TIME NULL,
        MODIFY COLUMN end_time TIME NULL'));

// ── 14 · Catálogo de convenios (5 CCT + escalas; upsert por code, sin
//         VALUES() en ON DUPLICATE, que MySQL 8.4 ya no soporta) ────────────
$paso('Catálogo de convenios (CEC, Farmacia 430/05, SOECRA, UTEDYC, Sanidad)',
    function () use ($scalar) {
        return (int)$scalar("SELECT COUNT(*) FROM collective_agreements
                WHERE code IN ('CEC','FARMACIA-430-05','SOECRA-761-19','UTEDYC-2023','SANIDAD-122-75')") >= 5
            && (int)$scalar("SELECT COUNT(*) FROM collective_agreement_rules r
                JOIN collective_agreements a ON a.id = r.agreement_id
                WHERE a.code = 'FARMACIA-430-05'") >= 4;
    },
    function () use ($pdo, $scalar) {
        $convenios = [
            ['CEC', 'Empleados de Comercio - CCT 130/75',
             'Vacaciones conforme LCT. El CCT exige comunicacion con 60 dias de anticipacion.',
             'Republica Argentina', 'CCT 130/75, arts. 74 y 75', 60, 'lct', 'lct_7'],
            ['FARMACIA-430-05', 'Empleados de Farmacia Cordoba - CCT 430/05',
             'Farmacias de la provincia de Cordoba. Inicio lunes o siguiente habil si fuera feriado.',
             'Provincia de Cordoba', 'CCT 430/05, art. 24', 60, 'monday_or_next_business', 'lct_7'],
            ['SOECRA-761-19', 'SOECRA Cementerios - CCT 761/19',
             'Cementerios, crematorios, salas velatorias y panteones comprendidos por el convenio.',
             'Republica Argentina, segun representatividad de las partes', 'CCT 761/19, arts. 47 y 49', 30, 'lct', 'soecra_14_plus_7'],
            ['UTEDYC-2023', 'UTEDYC - FEDEDAC - AREDA 2023',
             'Entidades deportivas y asociaciones civiles. Reemplaza al CCT 736/16.',
             'Republica Argentina', 'Resolucion ST 1661/2023, arts. 12 y 13', 30, 'lct', 'lct_7'],
            ['SANIDAD-122-75', 'Sanidad - CCT 122/75',
             'Clinicas, sanatorios, hospitales privados y establecimientos geriatricos.',
             'Republica Argentina', 'CCT 122/75, arts. 21 y 22', 30, 'lct', 'lct_7'],
        ];
        $escalas = [
            'CEC' => [
                [0, 60, 14, 'calendar', 7, 'Hasta 5 años inclusive'],
                [61, 120, 21, 'calendar', 7, 'Mas de 5 y hasta 10 años'],
                [121, 240, 28, 'calendar', 7, 'Mas de 10 y hasta 20 años'],
                [241, null, 35, 'calendar', 7, 'Mas de 20 años'],
            ],
            'FARMACIA-430-05' => [
                [0, 60, 17, 'calendar', 7, 'Hasta 5 años inclusive'],
                [61, 120, 26, 'calendar', 7, 'Mas de 5 y hasta 10 años'],
                [121, 240, 35, 'calendar', 7, 'Mas de 10 y hasta 20 años'],
                [241, null, 44, 'calendar', 7, 'Mas de 20 años'],
            ],
            'SOECRA-761-19' => [
                [0, 60, 14, 'business_mon_sat', 14, 'Hasta 5 años inclusive; sabado habil'],
                [61, 120, 21, 'business_mon_sat', 14, 'Fraccion 14 + remanente 7'],
                [121, 240, 28, 'business_mon_sat', 14, 'Fracciones 14 + 14'],
                [241, null, 35, 'business_mon_sat', 14, 'Fracciones 14 + 14 + remanente 7'],
            ],
            'UTEDYC-2023' => [
                [0, 60, 16, 'calendar', 7, 'Hasta 5 años inclusive'],
                [61, 120, 21, 'calendar', 7, 'Mas de 5 y hasta 10 años'],
                [121, 240, 28, 'calendar', 7, 'Mas de 10 y hasta 20 años'],
                [241, null, 35, 'calendar', 7, 'Mas de 20 años'],
            ],
            'SANIDAD-122-75' => [
                [0, 60, 14, 'calendar', 7, 'Hasta 5 años inclusive'],
                [61, 120, 21, 'calendar', 7, 'Mas de 5 y hasta 10 años'],
                [121, 240, 28, 'calendar', 7, 'Mas de 10 y hasta 20 años'],
                [241, null, 35, 'calendar', 7, 'Mas de 20 años'],
            ],
        ];
        $insConv = $pdo->prepare('INSERT INTO collective_agreements
            (code, name, description, jurisdiction, legal_reference, period_start_month, period_start_day,
             notice_days, start_rule, split_policy, minimum_request_days, is_active)
            VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?, ?, 7.0, 1)');
        $updConv = $pdo->prepare('UPDATE collective_agreements
            SET name = ?, description = ?, jurisdiction = ?, legal_reference = ?, period_start_month = 1,
                period_start_day = 1, notice_days = ?, start_rule = ?, split_policy = ?,
                minimum_request_days = 7.0, is_active = 1
            WHERE id = ?');
        $insRegla = $pdo->prepare('INSERT INTO collective_agreement_rules
            (agreement_id, min_months, max_months, days_entitled, day_count_mode,
             allows_split, allows_carryover, min_consecutive_days, notes)
            VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?)');
        $updRegla = $pdo->prepare('UPDATE collective_agreement_rules
            SET max_months = ?, days_entitled = ?, day_count_mode = ?, allows_split = 1,
                allows_carryover = 1, min_consecutive_days = ?, notes = ?
            WHERE id = ?');
        foreach ($convenios as [$code, $name, $desc, $juris, $legal, $notice, $startRule, $splitPolicy]) {
            $aid = $scalar('SELECT id FROM collective_agreements WHERE code = ?', [$code]);
            if ($aid === null) {
                $insConv->execute([$code, $name, $desc, $juris, $legal, $notice, $startRule, $splitPolicy]);
                $aid = $pdo->lastInsertId();
            } else {
                $updConv->execute([$name, $desc, $juris, $legal, $notice, $startRule, $splitPolicy, $aid]);
            }
            $aid = (int)$aid;
            foreach ($escalas[$code] as [$min, $max, $dias, $modo, $minConsec, $nota]) {
                $rid = $scalar('SELECT id FROM collective_agreement_rules WHERE agreement_id = ? AND min_months = ?', [$aid, $min]);
                if ($rid === null) {
                    $insRegla->execute([$aid, $min, $max, $dias, $modo, $minConsec, $nota]);
                } else {
                    $updRegla->execute([$max, $dias, $modo, $minConsec, $nota, (int)$rid]);
                }
            }
            if ($code === 'FARMACIA-430-05') {
                echo "                (FARMACIA-430-05 = agreement_id $aid en esta base)\n";
            }
        }
    });

echo "\nListo. Ahora: php scripts/verificar_esquema_vps.php\n";
echo "(El paso 'Scope admin Moderna' queda pendiente hasta crear el usuario axel.moderna.)\n";
