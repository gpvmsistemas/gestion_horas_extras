# Suite P&M — Integración Paviotti ↔ Red Farmacias Moderna

Documento de onboarding para desarrolladores que toman la rama **`integracion`**
(repo `gpvmsistemas/gestion_horas_extras`). Cubre todo lo agregado sobre el
sistema base de Paviotti: la capa organizacional, el Registro de Horas, el
módulo de Vacantes/Reclutamiento renovado, la nómina real de Moderna, los
scripts de despliegue y las diferencias de entorno que ya nos mordieron.

El sistema base (asistencia, horas extras, vacaciones v2, capacitación, etc.)
está documentado en [README.md](README.md). El despliegue paso a paso al VPS,
en [DESPLIEGUE_VPS.md](DESPLIEGUE_VPS.md).

---

## 1. Qué es la Suite P&M

Una sola aplicación PHP donde conviven **dos organizaciones aisladas**:

| Organización | Sociedades (`companies.organization_group`) | Identidad visual |
|---|---|---|
| `paviotti` | La Naturaleza, Servicios Sociales, Casa Paviotti, A.M.S.S.I, Ecofarma | Tema base + skin app-staff |
| `moderna` | MODERNA SRL, FRANCE SRL, DISTRIBUIDORA FCF SAS (32 sucursales en 8 ciudades) | `body.org-moderna` (paleta azul de hoursapp) |

Cada usuario queda **bloqueado a su organización** al loguear
(`users.employee_group` → `$_SESSION['user_employee_group']`): no ve empresas,
empleados, datos ni menús del otro grupo, por ningún camino.

## 2. Puesta en marcha local

```bash
git clone https://github.com/gpvmsistemas/gestion_horas_extras.git
cd gestion_horas_extras
git checkout integracion
cp app/config/config.local.example.php app/config/config.local.php   # editar DB_*/URLROOT
php scripts/aplicar_pendientes_vps.php        # aplica TODO el esquema faltante (idempotente)
php scripts/verificar_esquema_vps.php         # debe terminar en ESQUEMA COMPLETO
php scripts/verificar_tablas_codigo.php       # todas las tablas que usa el código vs la base
```

Notas:

- `aplicar_pendientes_vps.php` es **la** herramienta de migración: detecta por
  introspección (SHOW TABLES/COLUMNS/INDEX) qué falta y lo aplica en orden de
  dependencias. Funciona igual en MariaDB y MySQL 8 (ver §8). Re-ejecutarlo es
  siempre seguro.
- Los `.sql` sueltos de la raíz y de `scripts/` quedan como referencia y para
  instalaciones frescas en MariaDB; en MySQL varios fallan por sintaxis
  (`ADD COLUMN IF NOT EXISTS`) — usar el applier.
- Constantes opcionales en `config.local.php`: `DB_PORT`, `RECRUITING_ORGS`
  (ver §5), `OPENAI_API_KEY`, `CLAMSCAN_BIN`, `PDFTOTEXT_BIN`.

Cuentas de prueba del entorno local:

| Login (campo único `usuario+contraseña`) | Rol |
|---|---|
| `axel.admin+SuiteLocal2026` | Admin Paviotti |
| `axel.moderna+Moderna2026` | Admin RRHH Moderna |
| `M37+9335` | Colaboradora Moderna (Ruiz Martín, FRANCE SRL) |

## 3. La capa organizacional (lo más importante)

Archivo pivote: `app/helpers/org_helper.php`.

- `org_locked_group()` — grupo al que el usuario logueado está bloqueado.
- `org_group_of_company($id)` / `org_group_company_ids($grupo)` — resolución
  empresa↔grupo (con caché por request).
- `org_current_group()` / `org_is_moderna()` — organización efectiva de la
  sesión (alimenta tema visual, menús y textos).

**El candado se aplica en TODOS los caminos que fijan la empresa activa** (esto
fue un agujero real que cerramos — no agregar caminos nuevos sin el candado):

1. `setAdminActiveCompany()` (`auth_helper.php`) — selector de Contexto y fichas.
2. `access_set_active_scope()` (`access_control_helper.php`) — POST `access/setContext`.
3. `LoginController::createUserSession()` — scope primario del login.
4. `RecruitingController::gate()` — defensa en profundidad en el módulo ATS.

Además:

- `Company::getAllCompanies()` **filtra por el grupo bloqueado** — cualquier
  pantalla que enumere empresas (dropdowns, reportes) queda aislada sola.
- Contexto **"Todas las empresas"**: opción del selector EMPRESA del topbar que
  activa la vista org-wide (`adminAllCompaniesActive()` / `adminCompanyIds()`
  en `auth_helper.php`, solo perfiles administrador/rrhh). Pantallas que la
  respetan: Usuarios, Solicitudes (listado y aprobación), y los módulos que ya
  son org-wide por diseño (Registro de Horas, Reclutamiento, reportes de
  vacaciones, Roadmap RRHH). El resto opera sobre la empresa ancla.
- Vistas: nada de textos del otro mundo. El bloque "operador Ecofarma" de la
  ficha, por ejemplo, se decide por la organización **del empleado visto**
  (`org_group_of_company($user->company_id)`), no por la del admin.

## 4. Registro de Horas (módulo compartido)

Puerta: sidebar "Registro de Horas". Código: `RegistroHorasController` +
`app/services/RegistroHorasService.php` (motor puro, ~40 tests CLI) + vistas
`app/views/registro_horas/` + JS `public/js/registro-horas-carga.js`,
`rh-cal-picker.js`, `rh-tooltip.js`.

- Bloques en `employee_schedules` con `type='custom'` + `branch_name`;
  expansión virtual de nocturnos legacy (end < start) en lectura.
- **Extras por organización**: Moderna = umbral diario 8 h L-V / 5 h S-D
  acumulado + feriados (nacional o local de la ciudad) que hacen extra todo el
  día por bloque; Paviotti = bloques `overtime` (módulo 50/100 existente).
- Pantallas: Vista general, Por sucursal (con horario de atención editable en
  `company_branches.schedule_text`), Carga (vista previa EN VIVO — el JS
  replica el cálculo del servidor), Por empleado, Duplicación (pares
  arbitrarios y masiva por sucursal), Carga masiva, Borrado masivo (con
  confirmación ELIMINAR server-side y auditoría), **Estados**.
- **Estados** (`employee_status_periods`): guardia/vacaciones/licencia
  bloquean la carga; **certificado adjunto** opcional al crear o después
  (acción `attach`; los finalizados quedan 60 días visibles para adjuntar);
  archivos en `storage/private/certificates/{uid}/`, descarga con control de
  alcance en `registroHoras/certificado/{periodId}`.

## 5. Vacantes / Reclutamiento (ATS)

Se **extendió el módulo existente** (`CareersController`,
`RecruitingController`, `HrSuite`, tablas de `migration_hr_operations_talent.sql`)
al estilo del panel del sistema viejo de Moderna:

- **Portal público por organización**: `/careers/moderna` y `/careers/paviotti`
  con marca propia (`org_careers_brand()`); `/careers` redirige o muestra el
  selector. Vacantes con cierre por fecha aplicado también en el detalle y el
  POST (no solo el listado).
- **Postulación espontánea** `/careers/unete/{org}` (port del `/unete`):
  ciudad + área de interés; se materializa contra la vacante contenedora
  `espontanea-{org}` (excluida del listado; pausarla cierra la recepción).
  La ciudad/área viajan como evento `application_received` de la postulación.
- **Panel** `/recruiting`: centrado en candidatos, org-wide, con KPIs, filtros
  server-side + paginación 50, cambio de estado por fila (select auto-submit
  validado contra el `pipeline_json` de la vacante), ficha modal (JSON,
  auditada), CV, ranking IA opcional (OpenAI, revisión humana obligatoria),
  preingreso con checklist. ABM de búsquedas con ciclo de vida completo
  (borrador/publicada/pausada/cerrada) y `position_id` conectado.
- **Pipeline default en español**: `org_default_pipeline()` =
  nuevo → revisado → entrevista → rechazado/contratado (personalizable por
  búsqueda). `contratado/rechazado` mapean al status hired/rejected.
- **Piloto por organización**: default solo Moderna. Para habilitar Paviotti:
  `define('RECRUITING_ORGS', ['moderna', 'paviotti']);` en `config.local.php`.
- Seguridad heredada del módulo y reforzada: CV en `storage/private/cv/`,
  anti-abuso (captcha/honeypot/rate limit), consentimiento versionado con
  retención 24 meses (`scripts/purge_candidate_data.php` mensual),
  anti-enumeración de emails en duplicados, auditoría de ficha y descargas.

## 6. Nómina y vacaciones de Moderna (datos)

- `scripts/limpieza_moderna.php` (dry-run / `--ejecutar`) — limpia el lado
  Moderna preservando staff y todo Paviotti.
- `scripts/import_nomina.php <xlsx> <credenciales.json> [--sucursal X]` —
  importa la nómina de RRHH (xlsx parseado a mano con ZipArchive). Usuarios
  `M{nro}` + clave = últimos 4 del DNI; llena legajo ampliado (assignments,
  domicilios, obras sociales, contacto de emergencia, ficha personal).
- `scripts/convertir_vacaciones_xls.py` (PC, requiere `xlrd==1.2.0`) +
  `scripts/import_vacaciones.php <json>` — período 2025 al módulo v2:
  saldos + movimientos idempotentes + bloques `vacation` que bloquean la
  carga. **Moderna computa antigüedad desde la FECHA DE INGRESO** (no ingreso
  de recibo), con la escala del CCT 430/05 (resuelto por `code`
  `FARMACIA-430-05`, nunca por id numérico).
- Los archivos de datos reales están **gitignorados** y viajan a mano
  (ver DESPLIEGUE_VPS.md §1): `Nomina...xlsx`, `nomina_credenciales.json`,
  `vacaciones_2025.json`. `hoursapp-moderna/` (el Flask viejo + ~580 MB de
  correos reales con PII en `reclutamiento-moderna/`) también está ignorado y
  NO debe committearse jamás.

Pantallas relacionadas: **Reportes vacaciones** (saldos, org-aislado),
**Vacaciones tomadas** (`/vacationAdmin/tomadas` — un tramo por fila con
estado Pasada/En curso/Futura), y **Roadmap RRHH** (`/admin/hrRoadmap` —
calendario mensual org-wide de ausencias, chips coloreadas por
`companies.brand_color`, filtros empresa/ciudad/sucursal/tipo en cascada).

## 7. Scripts de operación

| Script | Qué hace |
|---|---|
| `aplicar_pendientes_vps.php` | Migrador universal idempotente (MariaDB y MySQL 8) |
| `verificar_esquema_vps.php` | Checklist de esquema con comando exacto por pendiente |
| `verificar_tablas_codigo.php` | Toda tabla referenciada por el código vs la base |
| `crear_admin_moderna.php` | Crea `axel.moderna` + scope administrador (idempotente) |
| `fix_scope_admin_moderna.sql` | Solo el scope (red de seguridad) |
| `limpieza_moderna.php` / `import_nomina.php` / `import_vacaciones.php` | Datos Moderna (dry-run por defecto) |
| `purge_candidate_data.php` | Retención/anonimización mensual de candidatos (cron) |

## 8. Diferencias de entorno — LEER antes de tocar migraciones

Estas cinco ya rompieron producción una vez; el código actual las neutraliza,
pero cualquier SQL/feature nueva debe respetarlas:

1. **MariaDB (local/XAMPP) vs MySQL 8 (VPS)**: `ADD COLUMN IF NOT EXISTS`,
   `CREATE TRIGGER IF NOT EXISTS`, `CREATE INDEX IF NOT EXISTS` y
   `DROP INDEX IF EXISTS` son MariaDB-only. Toda migración nueva se agrega
   como paso del applier con guards de introspección.
2. **sql_mode**: MySQL 8 es estricto por defecto (`''` en columna numérica =
   error 1366; `ONLY_FULL_GROUP_BY`). `Database.php` fija por sesión el modo
   del entorno validado; el applier usa `NO_ENGINE_SUBSTITUTION` (hay fechas
   `0000-00-00` heredadas).
3. **Colaciones**: MySQL 8 crea tablas en `utf8mb4_0900_ai_ci` → error 1267 al
   comparar texto con tablas viejas (`general_ci`). El applier convierte TODA
   la base a `utf8mb4_general_ci` y fija el default; `SET NAMES` usa la misma.
4. **URLROOT y assets**: los ~70 assets se referencian vía `URLROOT`. El
   URLROOT NUNCA debe incluir `/index.php` (el router serviría los assets como
   HTML). El VPS necesita `a2enmod rewrite` + `AllowOverride All` (conf
   `gestion-horas-extra.conf`) — los `.htaccess` del repo hacen el resto.
5. **PHP 7.4 local vs 8.4 VPS**: cosas que en 7.4 son warnings pueden ser
   fatales en 8.4. Probar features nuevas contra el VPS antes de dar por
   cerrado. Además Apache escribe como `www-data` sobre un árbol de otro
   dueño: `public/uploads/` y `storage/` necesitan grupo www-data con
   escritura (setgid) — ver DESPLIEGUE_VPS.md.

## 9. Estado del VPS (25/08/2026)

- `/var/www/html/gestion_horas_extra` en rama `integracion` (remoto
  `gpvmsistemas`; el `.git` es del usuario `lautaro` — git ops con él).
  Tu WIP previo quedó en stash ("WIP VPS pre-integracion 24/08") y en la rama
  de respaldo `lautaro-vps`.
- Esquema COMPLETO (verificador en verde), nómina Moderna (10) y vacaciones
  2025 importadas, colación unificada, php.ini 64M (videos en anuncios),
  URLROOT `http://144.217.165.202/gestion_horas_extra`.
- `main` sigue intacta: el merge `integracion → main` espera tu validación.

## 10. Pendientes / backlog

- **Repo a PRIVADO** (urgente: es RRHH y el historial referencia el esquema de
  credenciales) y HTTPS/dominio para el VPS (hoy HTTP plano; los bots ya
  escanean el puerto 80).
- Agente CrossChex → ingesta de marcaciones Moderna (los usuarios `M{n}` usan
  los números de reloj; revisar colisiones en `findUserByClockId`).
- Reclutamiento v2: ingesta IMAP + IA de la casilla `cv@redmoderna.com.ar`,
  emails al candidato con su link de seguimiento.
- Cierre mensual de extras Moderna (D2), reportes Excel de Por sucursal,
  siembra de feriados nacionales, y las próximas nóminas por área
  (repetir §6 con su Excel y `--sucursal`).
