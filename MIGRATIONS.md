# Migraciones del sistema

Este archivo documenta las migraciones disponibles en el repositorio y, especialmente, la evolución de vacaciones v2. Varias migraciones históricas citadas por el código todavía no están versionadas; por eso no debe asumirse que este repositorio reconstruye una base vacía completa.

## Legajo integral

Archivo: `migration_employee_record_complete.sql`.

Requiere las tablas históricas `users`, `companies`, `areas` y `collective_agreements`.
Crea catálogos de puestos y cobertura médica, relaciones laborales multiempresa,
domicilios estructurados y afiliaciones. Conserva los campos principales de `users` por
compatibilidad y migra cada relación vigente solamente si aún no existe.

Después de aplicarla, verificar alta/edición de usuario, `/admin/employeeCatalogs` y la
pestaña Legajo de la ficha. No se proporciona rollback destructivo: restaurar el backup si
la migración falla.

El generador `scripts/build_migration_hosting_full.sh` la incorpora como paso `39`, después
de vacaciones v2 y de las tablas históricas de convenios requeridas por sus claves foráneas.

## Permisos por empresa y sucursal

Archivo: `migration_access_control_scopes.sql`.

Requiere `users`, `companies` y `company_branches`. Crea asignaciones de acceso por
empresa/sucursal, políticas heredables de módulos del portal, excepciones por empleado y
auditoría. La migración conserva `users.role` como compatibilidad y genera el perfil
inicial Operario, Encargado o Administrador a partir del rol histórico.

## Vacaciones v2

Archivo: `migration_vacation_management_v2.sql`

Objetivo:

- precargar cinco convenios sin asignarlos automáticamente;
- usar períodos anuales y separar saldos ordinarios, históricos y créditos convencionales;
- soportar FIFO, solicitudes parciales, excepciones y reversión exacta;
- agregar metadatos necesarios para el tablero multiempresa;
- impedir movimientos duplicados mediante `operation_key`;
- guardar las fechas imputadas y el horario anterior para restaurarlo en una cancelación.

### Requisitos previos

La base debe tener como mínimo las tablas creadas por las migraciones históricas de convenios y vacaciones:

- `collective_agreements`
- `collective_agreement_rules`
- `company_agreement_defaults`
- `vacation_balance_periods`
- `vacation_balance_movements`
- `requests`
- `users`, `companies`, `areas`, `holidays` y `employee_schedules`

Si falta alguna, recuperar primero las migraciones históricas. No crear columnas aisladas manualmente porque se perderían índices, enumeraciones y reglas de integridad.

### Aplicación

Realizar un backup autorizado y ejecutar:

```powershell
Get-Content -Raw migration_vacation_management_v2.sql |
  C:\xamppcubo\mysql\bin\mysql.exe -u root -D paviotti_lanaturaleza --default-character-set=utf8mb4
```

En Linux/hosting:

```bash
mysql -u USUARIO -p BASE_DE_DATOS < migration_vacation_management_v2.sql
```

La migración es idempotente: los convenios usan `ON DUPLICATE KEY UPDATE`, las reglas poseen una clave por convenio/tramo y las columnas/índices se agregan con `IF NOT EXISTS`. Repetirla no duplica convenios ni reglas y conserva los identificadores de las reglas existentes.

### Cambios de esquema

`collective_agreements` incorpora jurisdicción, referencia legal, días de aviso, regla de inicio, política de fraccionamiento y mínimo de solicitud.

`collective_agreement_rules` incorpora el modo `business_mon_sat` y mantiene un tramo único por `(agreement_id, min_months)`.

`vacation_balance_periods` incorpora:

- `balance_type`: `annual`, `historical` o `conventional_credit`;
- `adjustment_days`;
- `count_mode_snapshot`;
- `expires_at` y `origin_notes`;
- unicidad `(user_id, period_label, balance_type)`.

`vacation_balance_movements` incorpora movimientos `expiry`, `conversion` y `exception`, orígenes de sistema/cancelación, clave idempotente y snapshot del planificador.

`requests` incorpora días computados, snapshot de la regla y datos auditables de la excepción.

`users.vacation_days_available` pasa a decimal para conservar medios días; sigue siendo solo un caché recalculable.

### Datos precargados

- Comercio CCT 130/75: 14/21/28/35 corridos, aviso 60 días.
- Farmacia Córdoba CCT 430/05: 17/26/35/44 corridos, aviso 60 días, inicio lunes o siguiente hábil.
- SOECRA CCT 761/19: 14/21/28/35 hábiles de lunes a sábado, sin domingos ni feriados.
- UTEDYC–FEDEDAC–AREDA 2023: 16/21/28/35 corridos.
- Sanidad CCT 122/75: escala LCT; licencias especiales continúan como solicitudes separadas.

La migración no asigna convenios a empresas, áreas o empleados. RR. HH. debe hacerlo explícitamente para no inferir encuadramientos laborales.

### Verificación

```powershell
php -l app\services\VacationLedgerService.php
php scripts\test_vacation_acceptance.php
```

Consultas de control:

```sql
SELECT code, name, notice_days, start_rule, split_policy
FROM collective_agreements ORDER BY code;

SELECT ca.code, r.min_months, r.max_months, r.days_entitled, r.day_count_mode
FROM collective_agreement_rules r
JOIN collective_agreements ca ON ca.id = r.agreement_id
ORDER BY ca.code, r.min_months;
```

La aceptación debe cubrir al menos:

- 7 de 21 deja 14;
- 10 días consumen 7 de 2025 y 3 de 2026;
- cancelar restaura períodos y horario anterior;
- sábado SOECRA cuenta, domingo no;
- el primer tramo SOECRA de 7 exige excepción y 14 + 7 es válido;
- saldo anual e histórico del mismo año quedan separados;
- el reporte agregado filtra y ordena sin consultas por empleado.

### Tarea programada

Los créditos vencidos quedan excluidos del disponible y deben cerrarse con movimiento auditable `expiry`. Programar una ejecución diaria:

```bash
php scripts/expire_vacation_credits.php
```

El script es idempotente mediante `operation_key=expiry:{period_id}`.

### Hosting

`scripts/build_migration_hosting_full.sh` incluye esta migración como paso `38`. Antes de generar un paquete completo, confirmar que todos los archivos históricos enumerados por el script estén disponibles en el entorno de build.

### Rollback

No se incluye rollback automático porque eliminar columnas de auditoría puede destruir información de solicitudes y movimientos. Ante un fallo:

1. detener aprobaciones de vacaciones;
2. restaurar el backup previo;
3. conservar una copia de los movimientos generados para conciliación;
4. corregir y volver a ejecutar la migración en un entorno de prueba;
5. nunca “arreglar” solamente `users.vacation_days_available`: es un caché, no la fuente de verdad.

## Programa integral RRHH, Operaciones y Talento (2026-08)

Archivo: `migration_hr_operations_talent.sql`.

Aplicación recomendada, después de un dump autorizado:

```powershell
php scripts/apply_hr_operations_talent_migration.php
```

La migración es repetible y deja el checksum en `schema_migrations` con la clave `2026_08_hr_operations_talent`. Incluye permisos granulares, auditoría append-only, cierres de asistencia, sanciones, vencimientos, EPP, activos, ATS, onboarding, desempeño, metadatos de capacitación, KPIs, tareas programadas y feature flags por empresa.

Verificación mínima:

```powershell
php -l app/controllers/RecruitingController.php
php -l app/controllers/PerformanceController.php
php tests/test_simple_pdf.php
php scripts/verify_audit_chain.php
php scripts/process_hr_expirations.php
```

Rollback: restaurar el dump previo. No se provee un SQL destructivo porque los dominios contienen constancias, custodias y auditoría que no deben perderse.

## Licencias por convenio colectivo (2026-08)

Archivo: `migration_collective_agreement_leave_types.sql`.

Crea el catálogo `collective_agreement_leave_types`, vincula solicitudes mediante
`requests.agreement_leave_type_id` y precarga licencias base LCT para los cinco convenios
ya existentes (más extensiones SOECRA y Sanidad).

Aplicación:

```bash
mysql -u USUARIO -p BASE_DE_DATOS < migration_collective_agreement_leave_types.sql
```

O en entornos que usan el consolidador:

```bash
php scripts/aplicar_pendientes_vps.php
```

O solo este módulo:

```bash
php scripts/apply_agreement_leave_types_migration.php
```

Verificación:

```sql
SELECT ca.code, COUNT(l.id) AS licencias
FROM collective_agreements ca
LEFT JOIN collective_agreement_leave_types l ON l.agreement_id = ca.id AND l.is_active = 1
GROUP BY ca.id, ca.code
ORDER BY ca.code;
```

Administración: `/vacationAdmin/agreements` y `/vacationAdmin/editAgreement/{id}`.
El portal del empleado muestra las licencias del convenio efectivo en `/request/index`.

## Dorso de certificado en solicitudes (2026-08)

Archivo: `migration_request_certificate_back.sql`.

Agrega `requests.certificate_back_path` para adjuntar frente y dorso del certificado médico.

```bash
php scripts/apply_request_certificate_back_migration.php
```

## Licencias solo aviso (sin aprobación RRHH) (2026-08)

Archivo: `migration_leave_type_requires_approval.sql`.

Agrega `collective_agreement_leave_types.requires_approval`. Las licencias con valor `0` (por defecto **Enfermedad**) se registran al enviar sin pasar por la bandeja de aprobación.

```bash
php scripts/apply_leave_type_requires_approval_migration.php
```

## Catálogo ampliado de obras sociales y prepagas (2026-08)

Archivo: `migration_health_insurers_catalog.sql`.

Precarga las 25 coberturas comerciales solicitadas para el legajo digital. Es repetible: reactiva y actualiza registros existentes sin duplicarlos. Requiere haber aplicado previamente `migration_employee_record_complete.sql`.

Aplicación local recomendada:

```powershell
php scripts/apply_health_insurers_catalog.php
```

Los nombres cargados son referencias comerciales. RR. HH. debe validar razón social, CUIT y código oficial antes de utilizarlos para derivación de aportes.

## Web Push / PWA (2026-08)

Archivo: `scripts/migration_push_subscriptions.sql`.

Crea `push_subscriptions` para notificaciones push del portal empleado (PWA instalable).

```bash
php scripts/apply_push_subscriptions_migration.php
php scripts/generate_vapid_keys.php
```

Copiar las claves VAPID generadas a `app/config/config.local.php`. Sin claves VAPID la PWA sigue siendo instalable, pero no se envían pushes.

Tras el despliegue, cada empleado puede activar notificaciones desde el portal (banner en inicio). Los envíos administrativos (`notification_broadcasts`, recibos y cursos publicados) disparan push automáticamente si el empleado está suscripto.
