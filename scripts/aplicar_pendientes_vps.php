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
$scalar = function ($sql, array $params = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
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

// ── 13 · CCT 430/05 Empleados de Farmacia Córdoba (escala de vacaciones) ────
$paso('CCT 430/05 Farmacia Córdoba (convenio + 4 reglas de escala)',
    function () use ($scalar) {
        return (int)$scalar("SELECT COUNT(*) FROM collective_agreement_rules r
            JOIN collective_agreements a ON a.id = r.agreement_id
            WHERE a.code = 'FARMACIA-430-05'") >= 4;
    },
    function () use ($pdo, $scalar) {
        $aid = $scalar("SELECT id FROM collective_agreements WHERE code = 'FARMACIA-430-05'");
        if ($aid === null) {
            $pdo->exec("INSERT INTO collective_agreements
                (code, name, description, jurisdiction, legal_reference,
                 period_start_month, period_start_day, notice_days, start_rule,
                 split_policy, minimum_request_days, is_active)
                VALUES
                ('FARMACIA-430-05',
                 'Empleados de Farmacia Cordoba - CCT 430/05',
                 'Farmacias de la provincia de Cordoba. Inicio lunes o siguiente habil si fuera feriado.',
                 'Provincia de Cordoba',
                 'CCT 430/05, art. 24',
                 1, 1, 60, 'monday_or_next_business', 'lct_7', 7.0, 1)");
            $aid = $pdo->lastInsertId();
        }
        $aid = (int)$aid;
        $reglas = [
            [0,   60,   17, 'Hasta 5 años inclusive'],
            [61,  120,  26, 'Mas de 5 y hasta 10 años'],
            [121, 240,  35, 'Mas de 10 y hasta 20 años'],
            [241, null, 44, 'Mas de 20 años'],
        ];
        $st = $pdo->prepare("INSERT IGNORE INTO collective_agreement_rules
            (agreement_id, min_months, max_months, days_entitled, day_count_mode,
             allows_split, allows_carryover, min_consecutive_days, notes)
            VALUES (?, ?, ?, ?, 'calendar', 1, 1, 7, ?)");
        foreach ($reglas as [$min, $max, $dias, $nota]) {
            $st->execute([$aid, $min, $max, $dias, $nota]);
        }
        echo "                (agreement_id = $aid en esta base)\n";
    });

echo "\nListo. Ahora: php scripts/verificar_esquema_vps.php\n";
echo "(El paso 'Scope admin Moderna' queda pendiente hasta crear el usuario axel.moderna.)\n";
