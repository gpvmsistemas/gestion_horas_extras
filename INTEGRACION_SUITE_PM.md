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

**Configuración obligatoria por empresa**: el importador deja el CCT solo en el
snapshot de cada período importado; los usuarios `M{n}` no tienen convenio
propio. Para que el panel de liquidación calcule 2026, para que el reporte
muestre "Convenio" y para que los empleados puedan **pedir vacaciones desde el
portal** (el portal exige convenio efectivo), hay que asignar el CCT 430/05
como **convenio por defecto de cada sociedad Moderna** en
`Vacaciones → Convenios → Convenio por empresa` (FRANCE SRL hoy; MODERNA SRL y
DISTRIBUIDORA cuando tengan nómina). Se guarda en `company_agreement_defaults`
y solo puede tocarse desde la empresa activa en sesión (org-aislado). En el
local ya está hecho para FRANCE SRL (10/09/2026); **en el VPS falta**.

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

## 10. Registro de cambios respecto del sistema base

La rama tiene 57 commits sobre `main`. Los primeros (~`6947aab..af1be3f`) son
trabajo propio de Lautaro ya conocido (vacaciones v2, reglas de feriados,
legajo ampliado, programa RRHH integral, control de asistencia); desde
`ad3303c` empieza la integración. Cada mensaje de commit explica el porqué:
`git log --reverse main..integracion` para el detalle completo.

**Fundación organizacional**
- `ad3303c` `b9c01a9` `94d3781` — bifurcación Paviotti/Moderna: employee_group,
  selector de contexto bloqueado, tema visual por organización (variables CSS).
- `e1eea66` `1bf453c` — estructura societaria real de Moderna (3 empresas,
  32 sucursales) y alcance org-wide.
- `9e0ec6e` — la ficha abre para todas las sociedades del grupo con aislamiento.
- `97598df` — `getAllCompanies()` filtrado por grupo (cerró ~20 pantallas que
  enumeraban empresas de ambos mundos) + reporte de vacaciones aislado (IDOR
  de saldos por `company_id` ajeno).
- `2f97087` — contexto **"Todas las empresas"** en el selector.
- `1d224f4` — cero menciones de Ecofarma en vistas Moderna (y viceversa).

**Registro de Horas** (módulo nuevo, no existía en el base)
- `c3ccdcd` `723d77e` `a55063a` — módulo con lógica real por organización,
  conectado a sucursales/feriados relacionales; feriados evaluados por bloque.
- `5adcd8d` — 9 correcciones de la auditoría contra hoursapp (portabilidad).
- `9f0bec7` `9c0e0a5` `e114174` `009c257` — edición/borrados/duplicaciones,
  vista Por sucursal, calendarios de selección masiva, tooltips con color,
  origen de cada hora extra, horario de atención editable, carga con vista
  previa EN VIVO (JS espejo del cálculo del servidor).
- `82af093` — el portal del empleado muestra la sucursal del bloque (no el
  enum `custom`).
- `8c14e67` `92df752` — estados con certificado adjunto (al crear o después,
  con finalizados visibles 60 días) + Roadmap RRHH.

**Reclutamiento / Vacantes** (extiende TU módulo ATS, no lo reemplaza)
- `3f062d0` — portal público por organización + ciclo de vida completo de
  vacantes + validación de etapas contra el pipeline (bug del base: etapas de
  texto libre visibles al candidato).
- `b961f6d` — panel centrado en candidatos estilo Moderna (KPIs, filtros,
  estado por fila), postulación espontánea `/unete`, pipeline en español, y
  los 9 hallazgos de la revisión adversarial — incluidos DOS del sistema base:
  el candado organizacional bypasseable vía `access/setContext` y el scope del
  login, y el `input date` que borraba `closes_at` en cada edición.
- `bcd7fa5` — filtro de estado sin etapas legacy en inglés.

**Datos Moderna**
- `bef05f0` — ficha personal + limpieza + importador de nóminas.
- `831ba1e` — importador de vacaciones al módulo v2; el calendario pinta los
  bloques de vacaciones.
- `71cfaf0` — vista "Vacaciones tomadas".

**Fixes al sistema base que te conviene conocer**
- `db9cb9e` — `updateUser` con campos numéricos vacíos daba 1366 en MySQL 8
  (editar un usuario = 500); `Database` fija sql_mode por sesión.
- `1a7ca87` `3632f30` — colación unificada `utf8mb4_general_ci` (error 1267
  entre tablas nuevas 0900 y viejas general_ci; Alertas RRHH caía).
- `92a4a18` — un `@media` sin cerrar en `style.css` dejaba muerto TODO el
  final del archivo en escritorio (tema Moderna, calendarios, tooltips).
- `6d47c71` — estado `:checked` de los botones outline nunca estuvo tematizado
  (caía al azul Bootstrap); fondo del botón Cerrar sesión.
- `2ef5fbd` — "Bienes y constancias" del portal accedía variables que `view()`
  no define (notices + foreach inválido).
- `c9bee62` — videos en los avisos emergentes (streaming autenticado).

**Infraestructura de despliegue** (nacida de las 5 divergencias del §8)
- `a27e664` `1776a78` `5ca578d` `00d8e37` — guía de despliegue, applier
  idempotente para MySQL, alineación completa de vacaciones v2 (en el VPS
  estaba a medias) y del programa hr_operations_talent (nunca aplicado allí).
- `6465084` `b7b4c04` — alta CLI de `axel.moderna`.
- `2ef5fbd` — verificador exhaustivo código↔esquema.

## 10b. Trabajo de Lautaro en el VPS (25/08 → 10/09) — ya integrado

Lautaro desarrolló directamente sobre el árbol del VPS (4 commits allí + 43
archivos sueltos). Se reconstruyó acá como el commit `59dee47` (rama
`lautaro-vps-sept`, base `bcd7fa5`) y se mergeó a `integracion`. Qué trae:

- **PWA + Web Push** para el portal del empleado: `public/manifest.php`,
  `public/sw.js`, `pwa_helper.php`, `WebPushService` (librería
  `minishlink/web-push` vía Composer), tabla `push_subscriptions`, banners de
  instalación y de permiso de notificaciones. Requiere claves VAPID en
  `config.local.php` (`php scripts/generate_vapid_keys.php`) y `composer install`
  (`vendor/` está ignorado; sin claves el envío se omite en silencio).
- **Hijos/as del colaborador** (`employee_children`, fecha y sexo) en el perfil.
- **Licencias por convenio colectivo** (`collective_agreement_leave_types`,
  categorías médica/familiar/maternidad/...), licencias "solo aviso"
  (`requires_approval`) y **dorso del certificado** en solicitudes
  (`requests.certificate_back_path`).
- **Planilla de vacaciones** (`app/views/vacation/planilla.php`) y panel.
- **Importador de legajos Paviotti** (`scripts/import_legajos_paviotti.php`).
- Ajustes en alertas RRHH, solicitudes, notificaciones, perfil mobile-first.

Sus migraciones están cableadas al applier (pasos 12a, 16, 17, 18 —
`push_subscriptions` lo agregamos nosotros). La unificación de colación corre
al final para normalizar también sus tablas (venían en `utf8mb4_unicode_ci`).

**Regla de compatibilidad PHP**: el VPS corre 8.4 y el local 7.4. `config.php`
trae polyfills de `str_starts_with`/`str_ends_with`/`str_contains` para que el
código escrito contra 8.4 corra en 7.4; sintaxis exclusiva de 8 (`match`,
`?->`, promoción de constructor, tipos unión) rompe el local — usar `php -l`
con 7.4 antes de subir.

**Regla de flujo**: no codear sobre el árbol del VPS. Trabajar en la PC, subir
por rama, y que el VPS solo reciba `git pull origin main` + applier. Si vuelve
a pasar, el rescate es el de esta vez: rama + commit en el VPS, `git archive`,
y reconstrucción sobre la base real (`INTEGRACION_SUITE_PM.md` §10b).

## 10c. Verificación del módulo de vacaciones tras el merge de Lautaro (10/09/2026)

Se validó de punta a punta, en el local con los datos importados, que el módulo
sigue funcionando como se planteó originalmente:

- **Antigüedad desde fecha de ingreso** (`users.hire_date`) y escala del CCT
  430/05: el panel de liquidación 2026 reproduce exactamente los días del
  período 2025 importado (Ruiz 231 m → 35, Martinez 335 m → 44, Felber 72 m →
  26, Rivata 26 m → 17, etc.). Lautaro no tocó `getSeniorityMonths`.
- **Datos importados intactos**: 9 períodos / 32 movimientos / 162 bloques;
  ficha, reporte de saldos, planilla y Vacaciones tomadas los muestran igual.
- **Bloqueo de carga**: `classifyDates` sigue marcando los días con bloque
  `vacation` (Rivata 05/10 y 11/10/2026, Ruiz 23/03/2026 → "Vacaciones").
- **Circuito de solicitud** (empleado Chiodi, 10 pendientes): vista previa
  aplica mínimo 7 días, saldo insuficiente, inicio en lunes y aviso de 60 días
  (`requires_override`); la aprobación sin motivo de excepción queda Pendiente,
  con motivo genera movimientos `take` + `exception`, descuenta el período
  (17/14/3), crea 7 bloques `vacation`, aparece en Tomadas como "Futura", en el
  Roadmap de noviembre y bloquea la carga de horas. Datos de prueba revertidos.
- **Aislamiento**: el admin Paviotti no ve empleados Moderna en panel, tomadas,
  reportes (ni forzando `company_id=7`), planilla ni setup.
- **README de Lautaro**: su contenido está completo en `README.md` (solo se
  reemplazó el título/intro por el de Suite P&M y se agregó el puntero a este
  documento).

Corregido en esta pasada: textos con "Ecofarma / Servicios Sociales / Casa
Paviotti" que se colaban en `Convenios` y en el portal de solicitudes para
Moderna (`e836d98`).

**Auditoría adversarial del diff de Lautaro (4 dimensiones × verificación) y
correcciones aplicadas en `ce13a84`:**

- **Escala por años cumplidos** (`seniorityMonthsForScale`): la escala se
  comparaba con meses completos, así que 5 años y 15 días (Felber al
  31/12/2025) caía en "hasta 5 años inclusive" → 17 en vez de los 26 del
  informe. Ahora un mes empezado cuenta como cumplido para elegir la regla;
  la antigüedad mostrada sigue en meses completos. 5 años justos sigue → 17.
- **Períodos importados protegidos**: `liquidatePeriod` ya no recalcula un
  período con `origin_notes` "Importado…" o movimientos `source='import'`
  (devuelve `skipped`; el batch "Recalcular existentes" los cuenta como
  omitidos). Antes, un click en el rayo del panel o el batch con alcance
  "Recalcular" pisaba 26→17, dejaba el saldo en 0 y cerraba el período
  (verificado con rollback). Corrección de un importado = Carga / ajustes.
- **No se liquida un período cerrado antes del ingreso** (Vila, ingreso
  08/2026, en 2025 → bloqueado, antes daba 17 días).
- **Período objetivo por defecto = año en curso**: el helper devolvía año+1
  desde octubre; como el label es el año de devengo, en octubre 2026 el botón
  de la ficha y el panel habrían creado "2027" con antigüedad a fecha futura y
  saldo consumible de inmediato.
- **Aprobar adjuntando el certificado en el mismo POST** fallaba con "falta
  el certificado" (objeto stale en `processRequest`); ahora relee la solicitud.
- **Seed de licencias** con `INSERT IGNORE` (re-ejecutar no pisa lo que RRHH
  editó en Convenios) y **Enfermedad con goce de sueldo** (LCT 208/209; venía
  `is_paid=0` y el portal la mostraba "Sin goce") → paso 18b del applier.
- **Script standalone `apply_leave_type_requires_approval_migration.php`**
  con guarda: re-ejecutarlo volvía a marcar ENFERMEDAD y aprobaba en masa.
- **`import_vacaciones.php`** no pisa un período que ya tiene movimientos del
  sistema (solicitudes aprobadas / liquidación del motor): lo omite y avisa.

Confirmado por la auditoría y **dejado como está** (decisiones de diseño, a
revisar con Lautaro): (a) el catálogo de convenios es global — un RRHH de
Paviotti puede editar reglas/licencias del CCT 430/05 que usa Moderna (y
Ecofarma también es farmacia, así que compartirlo tiene sentido); (b) la
migración replica las 12 licencias LCT en los 5 convenios, con lo que Moderna
recibe el flujo de licencias (Enfermedad "solo aviso") sin opt-in — RRHH
Moderna puede desactivar tipos o exigir aprobación desde Convenios; (c) el
reporte de saldos resuelve el convenio con `COALESCE(users, areas, default
empresa)` y no mira `employee_company_assignments.agreement_id` que Lautaro
sumó al servicio (hoy inerte: todas en NULL); (d) sin control de solapamiento
entre licencias/vacaciones; (e) los avisos de enfermedad nacen aprobados y no
tienen bandeja ni alerta para certificado faltante; (f) los KPI "Sin liquidar
2026 / Histórico" son literales: los 9 de Moderna tienen solo el 2025
importado, así que 2026 figura sin liquidar hasta que RRHH lo liquide (o
importe el informe 2026 — no ambas cosas para el mismo período).

**Brecha conocida (diseño previo, no regresión)**: las licencias que el empleado
solicita desde el portal (incluidas las de "solo aviso" tipo Enfermedad, que
quedan Aprobadas al instante) **no** generan `employee_status_periods` ni
bloques `leave`; por lo tanto no aparecen en el Roadmap RRHH ni bloquean la
carga de horas. Solo las vacaciones (vía ledger) y los estados que RRHH carga
en `Registro de horas → Estados` lo hacen. Si se quiere que una licencia
aprobada bloquee horas y se vea en el roadmap, hay que crear el estado desde
`RequestController::create` (auto-aviso) y `AdminController::approveRequest`
(licencias con aprobación), adjuntando el certificado de la solicitud.

## 11. Pendientes / backlog

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

---

## Anexo A — Lógica interna por módulo (para modificar con confianza)

### A.1 Registro de Horas — el motor (`RegistroHorasService`)

**Modelo de datos.** Cada tramo trabajado es una fila de `employee_schedules`
con `type='custom'` (la carga puede materializar turnos como custom),
`start_time`/`end_time`, `branch_name` (texto de la sucursal donde se trabajó,
que puede diferir de la sucursal "administrativa" del empleado) y `branch_id`
opcional. Los tipos `vacation`/`leave` son bloques SIN horas que bloquean la
carga; `overtime` es el circuito 50/100 de Paviotti.

**Medianoche.** Un turno que cruza medianoche NUNCA se guarda como una fila
`end < start`: `splitRange()` lo parte en dos filas (`inicio→23:59` del día D y
`00:00→fin` del día D+1). `blockMinutes()` trata `23:59`, `23:59:00` y
`23:59:59` como fin de día (1440). Los datos HISTÓRICOS de hoursapp sí traían
filas `end < start`: `expandLegacyNocturnal()` las expande virtualmente en
LECTURA a las dos mitades, conservando `orig_date/orig_start/orig_end` para que
la edición pre-llene el turno original completo. Al sobrescribir un día,
`saveDay()` además recorta la cola nocturna del día anterior
(`UPDATE end_time='23:59:00' WHERE end < start`).

**Cálculo de extras (`computeDay`).** Por día y empleado devuelve
`hours`, `extra` y `block_extra_detail` por bloque con su motivo:

- *Organización Moderna*: umbral diario ACUMULADO — 8 h lunes a viernes,
  5 h sábado/domingo. Los minutos por encima del umbral son extra
  (`tipo=umbral`, guarda umbral y acumulado). Si el día es feriado nacional
  **o local de la ciudad de la sucursal del bloque** (`holidays.city` +
  reglas por localidad), TODO el bloque es extra (`tipo=feriado`) y NO
  consume umbral. El feriado se evalúa POR BLOQUE: un empleado puede tener
  un bloque feriado (sucursal de una ciudad con feriado local) y otro normal
  el mismo día.
- *Organización Paviotti*: las extras salen de bloques `overtime` explícitos
  (`tipo=overtime`); el cálculo 50/100 vive en el módulo histórico.

**Bloqueos (`classifyDates`).** Antes de cualquier escritura se clasifican las
fechas: bloqueadas por bloques `vacation`/`leave` o por `employee_status_periods`
(guardia/vacaciones/licencia), con `blocked_reason` legible. La carga
individual, masiva y las duplicaciones OMITEN esas fechas y lo informan en la
verificación previa. La vista previa en vivo (`previewData`) muestra el
bloqueo antes de guardar.

**Vista previa en vivo.** `carga.php` embebe `rhLiveConfig` (JSON) y
`registro-horas-carga.js` replica `splitRange`/`blockMinutes`/`computeDay`
en el navegador — la paridad numérica con PHP está verificada con vectores;
si tocás la regla en PHP, tocala también en el JS (o al revés).

**Escrituras atómicas.** `replaceBlock()` = borrar + insertar particionado en
una transacción; el borrado masivo arma un preview server-side, exige tipear
`ELIMINAR`, resuelve colas nocturnas contra el set completo de fechas y
audita con `AuditService` (fechas incluidas). La duplicación (pares 1→N, N→N
cronológico, por semana, o masiva por sucursal) siempre re-clasifica las
fechas del plan en el momento de ejecutar (no confía en el preview).

**Endpoints JSON.** `fechasConHoras` (días con horas + feriados para pintar
los calendarios de selección, ventana anclada al día 1 del mes ±6) y
`previewData` — ambos validan sucursal contra el catálogo de la organización
y aplican el sub-alcance de encargados.

### A.2 Estados y certificados

`employee_status_periods(user_id, status, start_date, end_date, notes,
attachment_path)`. Alta con rango máx. 1 año; el certificado es opcional en el
alta y **adjuntable después** (acción `attach`, reemplaza borrando el archivo
anterior). El listado muestra vigentes/futuros y además los FINALIZADOS de los
últimos 60 días (para adjuntar certificados que llegan tarde). Archivos en
`storage/private/certificates/{uid}/` (nunca bajo `public/`); la descarga
(`registroHoras/certificado/{id}`) pasa por `resolveEmployee()` que valida
organización y sub-alcance. Borrar un período borra su archivo.

### A.3 Reclutamiento — flujo de punta a punta

**Postulación pública (`CareersController::processApplication`)** — el mismo
núcleo sirve al apply de una vacante y al formulario espontáneo:

1. Honeypot (`website`) y rate limit por IP hasheada (5 req / 10 min,
   `career_rate_limits`).
2. Captcha de sesión (challenge aleatorio, respuesta = challenge + 3).
3. CV: extensión + MIME real (finfo) pdf/docx, 5 MB, ClamAV opcional
   (`CLAMSCAN_BIN`).
4. Consentimiento activo (`career_consents`, versionado) obligatorio.
5. Candidato deduplicado por email (renueva `retention_until` +24 meses).
6. CV a `storage/private/cv/{candidate}/` con sha256; token de seguimiento
   de 48 hex — solo se guarda su hash y se muestra UNA vez.
7. La postulación nace en la PRIMERA etapa del pipeline de su vacante.
8. Duplicado (misma vacante + candidato): respuesta indistinguible del alta
   (anti-enumeración de emails), sin nuevo token.

**Espontánea**: vacante contenedora `espontanea-{org}` creada on-demand
(excluida del listado público); ciudad y área de interés viajan como evento
`application_received` — visibles en la ficha del candidato.

**Panel**: `applicationsOrg()` consulta TODAS las sociedades del grupo con
filtros server-side; el cambio de etapa valida contra el `pipeline_json` de la
vacante (una etapa huérfana tras editar el pipeline se muestra como fuera del
pipeline pero no se puede setear); `contratado|hired` y `rechazado|rejected`
derivan el status. La IA (`score`) extrae texto del CV (DOCX vía ZipArchive,
PDF vía pdftotext), lo manda a OpenAI con schema estricto y guarda
score/evidencia — decisión SIEMPRE humana. `onboard` crea el usuario
`preingreso` inactivo + checklist de 8 tareas y redirige al legajo.

**Retención**: `purge_candidate_data.php` (cron mensual, lock en
`scheduled_job_runs`) anonimiza candidatos vencidos no contratados y borra CV.

### A.4 Importadores de Moderna — idempotencia

- `import_nomina.php`: resuelve por LEGAJO (`employee_company_assignments.
  employee_number` del grupo moderna); upsert de usuario + legajo + domicilio
  + cobertura; cargos normalizados contra `job_positions` (find-or-create).
  Re-ejecutar actualiza, no duplica.
- `import_vacaciones.php`: por cada colaborador, upsert del período anual
  (clave usuario+label), movimientos `accrual`/`opening_balance`/`take` con
  `operation_key` único (`imp{año}:tipo:{user}[:{desde}]`) — INSERT IGNORE →
  re-ejecutar no duplica; cada día tomado se materializa como bloque
  `vacation` (skip si ya existe); sincroniza `users.vacation_days_available`
  (compat v1). El `agreement_rule_id` se resuelve por MESES desde la FECHA DE
  INGRESO al fin del período (regla Moderna) contra la escala del CCT 430/05,
  y el convenio SIEMPRE por `code='FARMACIA-430-05'` (el id numérico difiere
  entre entornos).

### A.5 Roadmap RRHH y Vacaciones tomadas

- Roadmap: dos fuentes unificadas y deduplicadas por `usuario|tipo|fecha` —
  `employee_status_periods` (rangos solapados con el mes) y los bloques
  `vacation`/`leave` de `employee_schedules`. Colores: `companies.brand_color`;
  si varias empresas comparten el color (default `#e91e8c`), se les asigna una
  paleta determinística. Filtros empresa/ciudad/sucursal validados
  server-side (una sucursal incoherente con la empresa/ciudad elegida se
  descarta); el alcance por sucursal considera `users.branch_id` Y
  `employee_branch_assignments`.
- Vacaciones tomadas: lee los movimientos `take` de vacaciones v2 — `desde` y
  `hasta` salen del propio `schedule_dates` (JSON de días) — y clasifica cada
  tramo en Pasada / En curso / Futura contra hoy.

### A.6 Contexto "Todas las empresas" — contrato

`$_SESSION['admin_company_all']` es un FLAG sobre la empresa ancla (que nunca
se pierde). `adminCompanyIds()` devuelve `[ancla]` o todas las del grupo; las
pantallas org-wide consultan con `IN (...)` y las acciones (p. ej. aprobar una
solicitud) validan pertenencia contra ESA lista, no contra la ancla. Elegir
una empresa puntual en el selector apaga el flag. Encargados/supervisores no
acceden al modo (perfil, no solo UI). Si sumás una pantalla al modo: cambiar
la consulta a `IN`, validar las acciones contra `adminCompanyIds()`, y nada
más — el selector y el flag ya están.
