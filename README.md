# Paviotti RRHH — gestión de personal, asistencia y horas extras

Sistema web interno de Recursos Humanos para múltiples empresas. Centraliza empleados, planificación horaria, marcaciones, asistencia, horas extras, solicitudes, vacaciones, legajo laboral, recibos, adelantos, capacitación, encuestas, comunicaciones y módulos operativos particulares.

Este documento está dirigido a desarrolladores, administradores y agentes nuevos que necesiten comprender, mantener o desplegar el proyecto sin contexto previo.

> [!IMPORTANT]
> El repositorio contiene una aplicación funcional y una base local ya evolucionada, pero actualmente **no contiene todas las migraciones SQL históricas que el propio código referencia**. La evolución de vacaciones v2 sí está versionada en `migration_vacation_management_v2.sql`; para una instalación completa desde cero todavía hace falta recuperar las migraciones anteriores o un dump autorizado y saneado.

> [!CAUTION]
> Es un sistema de RR. HH. y procesa información personal y laboral sensible. Nunca copiar datos reales, DNI, recibos, firmas, certificados, credenciales o dumps productivos a Git, tickets, prompts, logs o entornos no autorizados.

## 1. Resumen rápido para un agente nuevo

- Aplicación PHP MVC propia, sin framework.
- Apache enruta todas las URLs hacia `public/index.php` mediante `.htaccess`.
- MySQL/MariaDB mediante PDO.
- Roles principales: `admin`, `supervisor` y `empleado`.
- Multiempresa: las operaciones administrativas utilizan la empresa activa de la sesión.
- El núcleo históricamente más utilizado es horas extras y cierres mensuales.
- La asistencia combina planificación (`employee_schedules`) con marcaciones (`clock_events`).
- Las marcaciones pueden sincronizarse desde una API externa de relojes.
- Vacaciones usa convenios, reglas por antigüedad, períodos y un ledger de movimientos.
- Varios módulos dependen de tablas opcionales y se deshabilitan si su esquema no está disponible.
- `AdminController.php` concentra gran parte de la lógica histórica y debe modificarse con cuidado.
- Vacaciones v2 tiene una prueba de aceptación transaccional; el resto del sistema aún no posee una suite automatizada completa ni `composer.lock`.
- Antes de cualquier despliegue, leer las secciones **Estado de migraciones**, **Seguridad** y **Problemas conocidos**.

## 2. Capacidades funcionales

| Dominio | Funciones principales | Estado observado |
| --- | --- | --- |
| Empresas, sucursales y áreas | Alta/edición de empresas, sucursales o subempresas operativas, áreas globales o por empresa y selección de empresa activa | Funcional |
| Usuarios y legajo | Datos personales, laborales, empresa, área, convenio, fecha de ingreso, estado y documentos | Funcional |
| Planificación | Semana, turnos partidos, plantillas, patrones, vacaciones/licencias y cambios de turno | Funcional |
| Marcaciones | Sincronización, mapeo de reloj a usuario, caché, entradas/salidas y dispositivos | Funcional si la API está configurada |
| Asistencia | Tardanzas, ausencias, salida anticipada, falta de salida, licencias y justificaciones | Funcional |
| Horas extras | Carga del empleado, cálculo 50/100 %, revisión, cierre y exportación | Funcional con reglas a revisar |
| Solicitudes | Vacaciones, cambio de turno, llegada tardía, salida temprana, examen y otros motivos | Funcional |
| Vacaciones | Convenios precargados, períodos anuales, saldos históricos, créditos, FIFO, solicitudes parciales y tablero multiempresa | Implementado en vacaciones v2; las reglas deben validarse ante cambios normativos |
| Incidencias | Llamado de atención, sanción, suspensión, telegrama de despido y adjuntos | Registro documental; no es un offboarding completo |
| Sugerencias | Buzón por empresa sin guardar el usuario emisor | Básico; sin workflow de respuesta |
| Recibos | Carga, notificación, visualización, firma, fecha e IP | Implementado |
| Adelantos | Solicitud, aprobación, cuotas, descuento y finalización | Implementado |
| Capacitación | Cursos, lecciones, materiales, preguntas, quiz, tareas y recompensas | Implementado |
| Encuestas | Audiencias, anonimato, preguntas, publicación y resultados | Implementado |
| Notificaciones | Campana interna, anuncios y correo SMTP | Implementado |
| Reconocimiento | Estrellas entre compañeros, ranking y recompensas | Implementado |
| Casa Paviotti | Extras por tarea, tarifas, cierres y conexión a base de extintos | Específico del negocio |
| Ecofarma | Consultas y exportación de comisiones | Específico del negocio |
| PRODE | Pronósticos, partidos y ranking | Esquema presente; requiere semillas |
| Seguridad y auditoría | Capacidades por módulo/alcance, eventos append-only con hash encadenado y correlación | Implementado por `migration_hr_operations_talent.sql` |
| Cierre de asistencia | Preparación, congelado, reapertura auditada y paquete CSV/PDF sin importes | Implementado en `/admin/attendanceClosures` |
| EPP y activos | Stock, entregas, acuses, custodia y movimientos patrimoniales | Implementado en `/ppe` y `/assets` |
| Vencimientos | Tipos configurables, responsables, tablero y tarea idempotente | Implementado en `/expirations` |
| Reclutamiento ATS | Portal público, CV privado, pipeline, IA asistida y preingreso | Implementado en `/careers` y `/recruiting` |
| Desempeño | Plantillas y ciclos 90°/180° con objetivos y competencias | Implementado en `/performance` |
| KPIs RRHH | Dotación, ausentismo, tardanzas, ATS, vencimientos y patrimonio, sin costos | Implementado en `/hrMetrics` |

### Alcances que no deben asumirse

- No es un sistema completo de liquidación de sueldos.
- Calcula y clasifica horas, pero no liquida integralmente haberes.
- Vacaciones administra días y movimientos; no calcula por sí solo el pago completo de vacaciones.
- `telegrama_despido` es un tipo de incidencia. No existe un proceso completo de desvinculación, liquidación final, activos, certificados y revocación de accesos.
- Sugerencias no equivale a un sistema formal de reclamos con número de caso, responsable, SLA, respuesta e historial.

## 3. Tecnologías y requisitos

- PHP `>= 7.4` según `composer.json`.
- Recomendado para nuevos entornos: PHP 8.2 o superior, previa ejecución del lint y pruebas funcionales.
- Apache 2.4 con `mod_rewrite`.
- `AllowOverride All` o una configuración equivalente para que funcionen los `.htaccess`.
- MySQL o MariaDB con soporte `utf8mb4`.
- Extensiones PHP habituales: `pdo_mysql`, `mbstring`, `fileinfo`, `curl`, `json`, `openssl` y `session`.
- Composer.
- PHPMailer `^6.9` para correo.
- Navegador moderno.

El entorno local analizado utiliza XAMPP sobre Windows. Los scripts `.sh` requieren Bash, WSL, Git Bash o un entorno Linux.

## 4. Estructura del proyecto

```text
gestion_horas_extras/
├── .htaccess                    # Envía las peticiones a public/
├── composer.json
├── app/
│   ├── bootstrap.php            # Configuración, helpers y autoload
│   ├── Core.php                 # Router controlador/método/parámetros
│   ├── config/
│   │   ├── config.php
│   │   ├── config.example.php
│   │   └── config.local.example.php
│   ├── controllers/             # Controladores HTTP
│   ├── helpers/                 # Funciones de sesión, permisos y dominio
│   ├── models/                  # Acceso a datos y lógica histórica
│   ├── services/                # Casos de uso y lógica de dominio
│   └── views/                   # Vistas PHP
├── public/
│   ├── index.php                # Front controller y cabeceras HTTP
│   ├── .htaccess                # Rewrite hacia index.php?url=...
│   ├── css/
│   ├── js/
│   ├── img/
│   └── uploads/                 # Archivos subidos; tratar como sensibles
└── scripts/
    ├── hosting_smoke_test.php
    ├── validate_hosting_migration.sql
    ├── build_migration_hosting_full.sh
    ├── build_migration_hosting_seeds.sh
    ├── seed_prode_wc2026.php
    └── otros scripts de importación/actualización
```

### Flujo de una petición

1. El `.htaccess` raíz reescribe la URL hacia `public/`.
2. `public/.htaccess` envía rutas no físicas a `public/index.php?url=...`.
3. `public/index.php` carga `app/bootstrap.php`, añade cabeceras de seguridad e instancia `Core`.
4. `Core` interpreta la URL como `/controlador/metodo/parametro1/...`.
5. Se carga `{Controlador}Controller` y se invoca el método público solicitado.
6. El controlador valida sesión, rol, empresa, CSRF y datos.
7. Modelos/servicios acceden a MySQL mediante `Database`.
8. La respuesta se renderiza con una vista PHP o se devuelve como JSON/CSV/archivo.

Ejemplos:

```text
/login                  -> LoginController::index()
/login/process          -> LoginController::process()
/admin/dashboard        -> AdminController::dashboard()
/employee/miMes         -> EmployeeController::miMes()
/vacationAdmin/reports  -> VacationAdminController::reports()
```

### Interfaz, navegación y diseño visual

El layout compartido vive en `app/views/inc/header.php` y
`app/views/inc/footer.php`; los estilos generales están en
`public/css/style.css`. La interfaz utiliza Bootstrap 5 como base, un sidebar
oscuro y una identidad visual dinámica por empresa. Cuando hay contexto activo,
el color principal se aplica tanto al portal del empleado como a los controles y
superficies del panel administrativo. Algunos módulos agregan hojas específicas:

- `public/css/notifications.css`
- `public/css/learning.css`
- `public/css/prode.css`

Reglas que deben preservarse al crear o modificar pantallas:

- El selector del navbar representa la **empresa activa de todo el panel** y
  debe permanecer visible en desktop y mobile.
- Para administradores, el selector superior es jerárquico: **grupo
  organizacional → empresa → sucursal**. Elegir una sucursal deja el contexto
  operativo en sesión y el listado de Usuarios muestra sólo las personas
  asignadas a esa sede (incluye asignaciones múltiples).
- La marca, el nombre del footer y el título de la pestaña se derivan del
  contexto activo cuando corresponde.
- El logo y color de la empresa activa se resuelven desde `companies`; no usar
  colores hardcodeados para acciones principales si ya existe una variable
  `--clr-primary` equivalente.
- Al cambiar de empresa se conserva la pantalla de colección actual. Si la URL
  identifica una ficha o recurso de la empresa anterior, se vuelve a su
  listado para no mezclar contextos.
- Los estados activos del sidebar comparan segmentos completos de ruta; no usar
  coincidencias parciales que puedan activar dos opciones simultáneamente.
- Los botones del menú móvil mantienen `aria-expanded`, permiten cerrar con
  `Escape` y devuelven el foco al disparador.
- Toda navegación interactiva debe conservar foco visible. Las animaciones y
  transiciones deben respetar `prefers-reduced-motion`.
- El documento debe tener un único `h1` y una jerarquía posterior coherente.
- En móvil, los controles táctiles principales deben medir aproximadamente
  44 px o más y la página no debe generar scroll horizontal.
- Evitar nuevos atributos `style` y bloques `<style>` dentro de vistas; preferir
  clases reutilizables y variables de `style.css`.

Las dependencias visuales se cargan desde el layout. Deben fijarse a una versión
concreta y comprobarse por HTTP antes de actualizar. FullCalendar 6 carga sus
estilos desde el bundle JavaScript; no agregar `main.min.css`, ya que esa ruta
no existe en la distribución utilizada.

## 5. Roles, permisos y aislamiento

> Compatibilidad: `users.role` continúa funcionando para instalaciones anteriores. Cuando
> está instalada `migration_access_control_scopes.sql`, el perfil efectivo se resuelve por
> empresa/sucursal activa desde `user_access_scopes`.

### Perfiles con alcance

- **Operario:** portal propio, sujeto a los módulos heredados de empresa, sucursal y excepción individual.
- **Encargado:** control operativo de la sucursal asignada; no administra empleados ni permisos.
- **Coordinador:** alcance operativo completo de una empresa, incluidas horas extras e incidencias.
- **RRHH:** gestión de empleados, horarios, vacaciones y legajo en sus empresas asignadas.
- **Administrador:** configuración global, integraciones, relojes y administración de permisos.

Las políticas de portal se resuelven en este orden: configuración global, empresa,
sucursal y excepción del usuario. Cada alcance admite `Heredar`, `Sí` o `No`; el valor
más específico definido prevalece. Los cambios se auditan en `access_audit_log`.

### `admin`

- Acceso completo a RR. HH.
- Puede cambiar la empresa activa.
- Configura empresas, usuarios, horarios, convenios, vacaciones, correo e integraciones.
- Administra recibos, anuncios, encuestas, capacitación y cierres.

### `supervisor`

- Usa parte del panel administrativo.
- Está restringido a su empresa y, cuando corresponde, a su área.
- No debe acceder a configuraciones exclusivas de RR. HH.
- Los métodos sensibles deben usar `requireAdminOnly()` cuando no son aptos para supervisores.

### `empleado`

- Portal personal.
- Puede ver su planificación, mes, solicitudes, horas extras, recibos y módulos habilitados.
- Las funciones visibles se controlan mediante settings/feature flags del portal.

### Helpers de autorización importantes

- `isLoggedIn()`
- `hasRole()` / `hasAnyRole()`
- `isAdmin()` / `isSupervisor()` / `isStaffAdmin()`
- `requireAdminOnly()`
- `requireAdminCompany()`
- `requireUserInAdminCompany()`
- `requireSupervisorUserAccess()`
- `requireEmployeeRole()`
- `adminCompanyId()`
- `supervisorAreaId()`
- `access_can($capability, $companyId, $branchId)`
- `require_capability($capability)`

Las capacidades operativas se asignan sobre cada `user_access_scope`. RRHH, coordinador y encargado reciben solamente los valores predeterminados de su perfil; EPP y activos patrimoniales nunca se conceden por el cargo y requieren una autorización explícita. La pantalla `/admin/editUser/{id}#permisos` permite permitir, denegar o heredar cada capacidad.

Toda ruta nueva debe validar explícitamente rol y pertenencia a empresa. No confiar sólo en que la interfaz oculta un enlace.

## Programa integral RRHH, Operaciones y Talento

La migración `migration_hr_operations_talent.sql` agrega los dominios nuevos y registra su checksum en `schema_migrations`. Se aplica con respaldo previo:

```powershell
php scripts/apply_hr_operations_talent_migration.php
```

Rutas principales:

| Ruta | Alcance |
| --- | --- |
| `/admin/attendanceClosures` | Preparar, cerrar/reabrir y exportar novedades mensuales versionadas |
| `/expirations` | Documentos y certificaciones próximos a vencer |
| `/ppe` | Catálogo, stock por sucursal y entrega de EPP/ropa |
| `/assets` | Inventario serializado, asignación, transferencia, mantenimiento y baja |
| `/recruiting` | Vacantes, candidatos, pipeline, CV autorizado, IA y preingreso |
| `/careers` | Portal público sin cuenta y seguimiento mediante token privado |
| `/performance` | Plantillas, ciclos y evaluaciones 90°/180° |
| `/hrMetrics` | Indicadores operativos y estratégicos sin importes |
| `/audit` | Consulta de la cadena de auditoría |
| `/employee/hrDocuments` | Acuses con clave, activos vigentes y sanciones notificadas |

### Reglas relevantes

- El paquete mensual es informativo: nunca calcula salarios ni importes.
- Los cierres guardan snapshot, número de versión y SHA-256. Toda reapertura exige motivo.
- “Acuse de recibo con clave” registra usuario, fecha, IP, versión/hash; no se presenta como firma digital legal.
- Los CV permanecen en `storage/private/cv`, admiten PDF/DOCX hasta 5 MB, se validan por MIME real y pueden pasar por ClamAV cuando `CLAMSCAN_BIN` está configurado.
- La postulación posee rate limiting persistente, consentimiento versionado y token de seguimiento. Los no contratados se anonimizan al cumplir 24 meses.
- La extracción del CV es local. A OpenAI se envía texto reducido y redactado, nunca el archivo. El análisis usa Responses API, salida estructurada y `store: false`; puntúa sólo criterios publicados, no rechaza candidatos y siempre exige revisión humana.
- Antes de producción deben revisarse el consentimiento y la configuración de retención/monitoreo de la cuenta de API contra la documentación vigente de OpenAI.
- `audit_events` impide `UPDATE` y `DELETE` mediante triggers. En producción, usar además un usuario de base exclusivo para la aplicación sin privilegios de modificación/borrado sobre esa tabla y una política de conservación de 10 años.

### Tareas programadas

```bash
# Diario: actualiza estados y registra la corrida idempotente
php scripts/process_hr_expirations.php

# Mensual: elimina CV y anonimiza candidatos vencidos no contratados
php scripts/purge_candidate_data.php

# Control operativo o previo a auditoría
php scripts/verify_audit_chain.php
```

Cada tarea usa `scheduled_job_runs` para evitar duplicados. Una corrida fallida queda registrada y debe investigarse antes de reintentar con una nueva clave de operación.

### Configuración ATS

En `app/config/config.local.php` (no versionado):

```php
define('OPENAI_API_KEY', '...');
define('OPENAI_CV_MODEL', 'gpt-5.6-luna');
define('PDFTOTEXT_BIN', 'C:/ruta/pdftotext.exe');
define('CLAMSCAN_BIN', 'C:/ruta/clamscan.exe'); // recomendado en producción
```

Sin clave o sin servicio disponible, el ATS continúa operativo en modo manual. Sin `CLAMSCAN_BIN`, se mantiene MIME real/tamaño/rate limiting, pero el despliegue productivo debe proveer un antivirus antes de habilitar el portal.

## 6. Mapa de rutas

La siguiente lista muestra las rutas más importantes. Los métodos que modifican datos normalmente exigen `POST` y token CSRF.

### Autenticación

| Método/ruta | Uso |
| --- | --- |
| `GET /login` | Formulario de ingreso |
| `POST /login/process` | Autenticación |
| `POST /login/logout` | Cierre de sesión; exige token CSRF |

El formato actual del ingreso es `usuario+contraseña` dentro de un único campo de tipo password.

### Administración central `/admin/...`

| Ruta | Uso |
| --- | --- |
| `/admin/dashboard` | Panel general |
| `POST /admin/setCompany` | Cambiar empresa activa; exige token CSRF |
| `/admin/users` | Listado de empleados |
| `/admin/createUser` | Alta de usuario |
| `/admin/editUser/{id}` | Edición de usuario |
| `/admin/toggleUserStatus/{id}` | Activar/desactivar |
| `/admin/employeeProfile/{id}` | Ficha integral |
| `/admin/employeeDetails/{id}` | Detalle histórico de extras |
| `/admin/addEmployeeIncident/{id}` | Registrar incidencia |
| `/admin/deleteEmployeeIncident/{userId}/{incidentId}` | Eliminar incidencia |
| `/admin/streamEmployeeIncident/{userId}/{incidentId}` | Visualizar adjunto protegido |
| `/admin/downloadEmployeeIncident/{userId}/{incidentId}` | Descargar adjunto protegido |
| `/admin/weeklyPlanner` | Planificador semanal |
| `/admin/applyTemplate` | Aplicar plantilla |
| `/admin/applySchedulePattern` | Repetir patrón horario |
| `/admin/saveDayAjax` | Guardar día del planificador |
| `/admin/shiftManager` | Gestión de turnos |
| `/admin/createSplitShift` | Crear turno partido |
| `/admin/holidays` | Feriados por empresa |
| `/admin/templates` | Plantillas horarias |
| `/admin/marcaciones` | Marcaciones del período |
| `/admin/marcacionesTodas` | Marcaciones detalladas |
| `/admin/mapeoIncompleto` | Empleados sin mapeo de reloj |
| `/admin/mapeoApi` | Mapeo API ↔ usuario local |
| `/admin/sync` | Pantalla de sincronización |
| `/admin/runApiSync` | Ejecutar sincronización |
| `/admin/processSync` | Procesar datos sincronizados |
| `/admin/attendance` | Tablero de asistencia |
| `/admin/recomputeAttendance` | Recalcular asistencia |
| `/admin/saveAttendanceJustification` | Guardar justificación |
| `/admin/calendar` | Calendario/roadmap individual |
| `/admin/pendingOvertime` | Horas extras pendientes |
| `/admin/editEntry/{id}` | Editar entrada extra |
| `/admin/deleteEntry/{id}` | Eliminar entrada extra |
| `/admin/createClosure` | Crear cierre de horas |
| `/admin/history` | Historial de cierres |
| `/admin/closureDetails/{id}` | Detalle de cierre |
| `/admin/exportClosure/{id}` | Exportar cierre |
| `/admin/reports` | Reportes generales |
| `/admin/exportReportCsv` | Exportación de horas |
| `/admin/requests` | Solicitudes y cambios de turno |
| `/admin/processRequest` | Aprobar/rechazar/gestionar solicitud |
| `/admin/hrAlerts` | Alertas de RR. HH. |
| `/admin/suggestions` | Buzón de sugerencias |
| `/admin/companies` | Empresas |

### Portal del empleado

| Ruta | Uso |
| --- | --- |
| `/employee/index` | Inicio del portal |
| `/employee/dashboard` | Carga y resumen de horas extras |
| `/employee/add` | Guardar horas extras |
| `/employee/misHorarios` | Planificación mensual |
| `/employee/miMes` | Planificación + asistencia + solicitudes |
| `/employee/profile` | Perfil personal |
| `/employee/updateProfile` | Actualizar perfil |
| `/employee/notifications` | Notificaciones |
| `/employee/payStubs` | Recibos |
| `/employee/payStubSign/{id}` | Abrir recibo para firma |
| `/employee/signPayStub/{id}` | Registrar firma |
| `/employee/downloadPayStub/{id}` | Descargar recibo permitido |

### Solicitudes y cambios de turno

| Ruta | Uso |
| --- | --- |
| `/request/index` | Historial y formularios |
| `/request/create` | Crear licencia/ausencia/vacaciones |
| `/request/vacationPreview` | Vista previa JSON de días computables, FIFO y saldo posterior |
| `/request/createShiftSwap` | Proponer cambio de turno |
| `/request/streamCertificate/{id}` | Ver certificado propio |

Tipos observados en la base local: Vacaciones, Cambio de Turno, Llegada Tardía, Salida Temprana, Día por Examen y Otro.

### Vacaciones

| Ruta | Uso |
| --- | --- |
| `/vacationAdmin/agreements` | Convenios colectivos |
| `/vacationAdmin/editAgreement/{id}` | Convenio y reglas |
| `/vacationAdmin/vacationSetup/{userId}` | Carga inicial por empleado |
| `/vacationAdmin/calculateVacationPreview/{userId}` | Previsualizar cálculo |
| `/vacationAdmin/addHistoricalBalance/{userId}` | Reconocer deuda histórica con motivo y auditoría |
| `/vacationAdmin/addConventionalCredit/{userId}` | Cargar crédito separado con vencimiento |
| `/vacationAdmin/convertBalance/{userId}` | Convertir unidades corridos/hábiles con fundamento |
| `/vacationAdmin/liquidateUser/{userId}` | Liquidar período individual |
| `/vacationAdmin/liquidateCompanyBatch` | Liquidación masiva |
| `/vacationAdmin/reports` | Tablero multiempresa “Vacaciones pendientes” |
| `/vacationAdmin/exportVacationBalancesCsv` | Exportación que conserva filtros y orden |

#### Vacaciones v2: reglas de negocio

- Los períodos ordinarios son años calendario (`2025`, `2026`, etc.).
- Los saldos se separan por tipo: `annual`, `historical` y `conventional_credit`.
- Un empleado puede pedir parcialmente desde 7 días computables. Pedir 7 de 21 deja 14 pendientes; el período solo cierra al llegar a cero.
- La aprobación consume por FIFO: primero el período abierto más antiguo. Cada movimiento guarda el período, las fechas imputadas y el horario previo del planificador.
- Cancelar/rechazar una solicitud ya aprobada restaura los mismos períodos y los horarios que existían antes de aprobarla.
- Saldos históricos: RR. HH. carga año, días y motivo; no vencen.
- Créditos convencionales: se mantienen separados y pueden vencer. Ejecutar diariamente `php scripts/expire_vacation_credits.php` para cerrar y auditar créditos vencidos.
- Si el saldo fue generado con una unidad distinta de la regla vigente (`calendar`, `weekdays`, `business_mon_sat`), la aprobación se bloquea hasta que RR. HH. realice una conversión auditada.
- Advertencias convencionales (anticipación, inicio o fraccionamiento SOECRA) no impiden presentar la solicitud, pero exigen una justificación de excepción al aprobar.
- La pantalla de RR. HH. admite empresa, convenio, área, empleado/documento, período, tipo, actividad, rango de días, históricos y próximos vencimientos; la consulta está agregada para evitar N+1.

Convenios precargados por la migración, sin asignación automática:

| Código | Regla anual | Conteo / particularidad |
| --- | --- | --- |
| `CEC` | 14 / 21 / 28 / 35 | Corridos; aviso 60 días |
| `FARMACIA-430-05` | 17 / 26 / 35 / 44 | Corridos; aviso 60 días; lunes o siguiente hábil |
| `SOECRA-761-19` | 14 / 21 / 28 / 35 | Lunes a sábado sin domingos ni feriados; tramos 14 + 7 |
| `UTEDYC-2023` | 16 / 21 / 28 / 35 | Corridos; crédito transitorio separado |
| `SANIDAD-122-75` | LCT 14 / 21 / 28 / 35 | Vacación ordinaria separada de licencias convencionales especiales |

Las fuentes de análisis fueron los convenios entregados con el proyecto (CCT 130/75, CCT 430/05 Córdoba, CCT 761/19, UTEDYC–FEDEDAC–AREDA 2023 y CCT 122/75). Esta configuración no reemplaza la revisión laboral/contable ante reformas, homologaciones o casos particulares.

### Recibos, anuncios y notificaciones

| Ruta | Uso |
| --- | --- |
| `/notificationsAdmin/index` | Hub de comunicaciones |
| `/notificationsAdmin/announcements` | Avisos emergentes |
| `/notificationsAdmin/announcementForm/{id?}` | Alta/edición de aviso |
| `/notificationsAdmin/broadcasts` | Notificaciones masivas |
| `/notificationsAdmin/broadcastForm/{id?}` | Alta/edición de envío |
| `/notificationsAdmin/payStubs` | Administración de recibos |
| `/notificationsAdmin/uploadPayStub` | Cargar recibo |

### Adelantos de sueldo

| Ruta | Uso |
| --- | --- |
| `/salaryAdvance/index` | Portal del empleado |
| `/salaryAdvance/store` | Crear solicitud |
| `/salaryAdvanceAdmin/index` | Bandeja administrativa |
| `/salaryAdvanceAdmin/installments/{id}` | Cuotas |
| `/salaryAdvanceAdmin/history/{userId}` | Historial |
| `/salaryAdvanceAdmin/receipt/{advanceId}/{installment}` | Comprobante |
| `/salaryAdvanceAdmin/process` | Aprobar, rechazar o descontar |

### Capacitación y reconocimiento

| Prefijo | Funciones |
| --- | --- |
| `/training/...` | Cursos, lecciones, quiz, notas, comunidad, recompensas y tareas del usuario |
| `/trainingAdmin/...` | Cursos, áreas, materiales, asignaciones, reportes, tareas y recompensas |
| `/peerStar/...` | Entrega y consulta de estrellas |
| `/peerStarAdmin/...` | Ranking y exportación |

### Encuestas y sugerencias

| Prefijo | Funciones |
| --- | --- |
| `/survey/...` | Encuestas pendientes y respuesta |
| `/surveyAdmin/...` | Diseño, publicación, cierre y resultados |
| `/suggestion/index` | Formulario de sugerencias |
| `/suggestion/submit` | Envío anónimo a nivel usuario |

### Módulos específicos

| Prefijo | Funciones |
| --- | --- |
| `/cpTask/...` | Carga de extras Casa Paviotti |
| `/cpTaskAdmin/...` | Tarifas, pendientes, cierres y reportes CP |
| `/ecofarma/...` | Comisiones y exportaciones Ecofarma |
| `/prode/...` | Pronósticos del empleado |
| `/prodeAdmin/...` | Partidos y ranking |
| `/systemConfig/...` | Configuración protegida por clave adicional |

## 7. Modelo de datos conceptual

No se incluye un esquema completo porque las migraciones fuente están ausentes. Tablas principales observadas:

### Identidad y organización

- `companies`
- `company_locations`
- `company_branches`
- `employee_branch_assignments`
- `user_access_scopes`
- `organization_feature_policies`
- `user_feature_overrides`
- `access_audit_log`
- `areas`
- `users`
- `user_clock_mappings`
- `user_login_logs`

#### Empresas, sucursales y subempresas

Una empresa registrada en `companies` puede contener una o más unidades operativas en
`company_branches`. En la interfaz se las llama **sucursales**, aunque también sirven
para representar una **subempresa** que dependa operativamente de una empresa principal.

Al crear una empresa desde `/admin/companies` se debe elegir su **grupo
organizacional**: `PAVIOTTI` o `MODERNA`. El valor se guarda en
`companies.organization_group`; es una clasificación de la empresa y no reemplaza el
grupo histórico que pueda tener cada empleado. Para instalaciones existentes ejecutar
`php scripts/apply_company_organization_group_migration.php`.

```text
Empresa: Ecofarma
├─ Sucursal: Ecofarma Central — Villa María — Córdoba
├─ Sucursal: Ecofarma Azul — Villa María — Córdoba
├─ Sucursal: Ecofarma Cruz Verde Central — San Francisco — Córdoba
├─ Sucursal: Ecofarma Cruz Verde Catedral — San Francisco — Córdoba
├─ Sucursal: Ecofarma Cruz Verde Jujuy — San Francisco — Córdoba
└─ Sucursal: Ecofarma Dermolife — San Francisco — Córdoba
```

- `company_branches.company_id` identifica la empresa principal.
- Cada sucursal guarda `name`, `locality`, `province` e `is_active`.
- La administración se realiza desde `/admin/editCompany/{id}`: el primer campo de cada fila es el nombre real de la sucursal; localidad y provincia se eligen por separado.
- Quitar una fila de la pantalla la desactiva en lugar de borrarla, para conservar referencias e historial futuros.
- `company_locations` sigue representando la ubicación general o principal de la empresa; no reemplaza a sus sucursales.

#### Identidad visual por empresa

Cada empresa puede tener un logo y un color principal propios. Se configuran en
`/admin/editCompany/{id}`, dentro de **Información de la empresa**. El logo debe
ser PNG, JPG, GIF o WEBP de hasta 2 MB; se guarda como archivo público en
`public/img/companies/` y su ruta queda en `companies.logo_path`.

El color se guarda en `companies.brand_color` como hexadecimal (`#RRGGBB`). Al
elegir una empresa como contexto activo, `company_brand_helper.php` expone las
variables CSS `--clr-primary`, `--clr-primary-d`, `--clr-primary-l` y
`--clr-primary-xl`; de esa forma el sidebar, botones, encabezados y portal de
empleados adoptan la identidad correcta. Por ejemplo, **La Naturaleza** usa el
verde inicial `#2E7D32`.

Para instalaciones existentes ejecutar una sola vez:

```bash
php scripts/apply_company_branding_migration.php
```

El script agrega `brand_color` y `logo_path` sin borrar datos existentes. La
migración declarativa equivalente es `migration_company_branding.sql`.

El aislamiento principal de RR. HH. continúa siendo por `users.company_id`, y las sedes
operativas se asignan mediante `employee_branch_assignments`. Un empleado puede pertenecer
a una o más sucursales de su empresa; una de ellas queda marcada como principal y se conserva
en `users.branch_id` por compatibilidad y para reglas generales (por ejemplo, vacaciones).

- La asignación se gestiona desde `/admin/editUser/{id}`: se marcan todas las sedes donde trabaja el empleado y se elige una principal.
- `/admin/weeklyPlanner` agrega un selector de sucursal dentro de la empresa activa. Solo muestra los empleados asignados a esa sede y guarda cada horario con su `branch_id`.
- Esto permite planificar a una misma persona por la mañana en una sucursal y por la tarde en otra: cada horario mantiene la sede en la que fue creado.
- Las solicitudes aprobadas y las plantillas aplicadas desde el planificador se filtran por la sede seleccionada. Los feriados geográficos se resuelven con la localidad de esa sucursal.
- Los empleados que todavía no tienen una asignación de sucursal deben completarse desde Usuarios antes de aparecer en el planificador de una sede.

Migraciones requeridas, en este orden:

1. `migration_ecofarma_branches.sql` (catálogo de sucursales).
2. `migration_branch_planner_scope.sql` (columna de sucursal en usuarios y horarios).
3. `migration_employee_multiple_branches.sql` (asignaciones múltiples; migra la sucursal principal existente).

#### Legajo integral, multiempresa laboral y cobertura médica

La migración `migration_employee_record_complete.sql` agrega una capa de legajo
normalizada sin romper los campos históricos de `users`. Mientras el resto del sistema
continúa usando `users.company_id`, `users.area_id` y `users.branch_id` como contexto
principal, la ficha puede conservar otras relaciones laborales y su vigencia.

El módulo incorpora:

- `job_positions`: catálogo de puestos globales o por empresa;
- `employee_company_assignments`: empresa, legajo, puesto, área, convenio, supervisor,
  modalidad, contratación, centro de costo, estado y fechas de cada relación laboral;
- `employee_addresses`: domicilio estructurado, texto original, coordenadas, precisión y
  estado de verificación;
- `health_insurers` y `health_insurance_plans`: catálogo administrable de obras sociales,
  prepagas, mutuales y planes;
- `employee_health_coverages`: afiliación, plan, número, carácter, aportes, vigencia y estado.

La edición vive en `/admin/editUser/{id}`, la consulta consolidada en la pestaña
**Legajo** de `/admin/employeeProfile/{id}` y los catálogos en
`/admin/employeeCatalogs`. Alta y edición siguen funcionando aunque la migración no esté
instalada: los campos ampliados se deshabilitan con una advertencia.

Reglas importantes:

- `admin`, `supervisor` y `empleado` son roles de acceso; el puesto/cargo se guarda por
  separado y no concede permisos.
- `users.is_active` habilita el inicio de sesión; el estado laboral (`preingreso`,
  `activo`, `licencia`, `suspendido`, `finalizado`) describe la relación laboral y no debe
  reemplazar silenciosamente el estado de la cuenta.
- Puede haber varias relaciones con empresas, pero solo una es principal. El aislamiento
  actual de los módulos operativos continúa guiándose por esa empresa principal.
- Las asignaciones a sucursales siguen siendo N:M dentro de la empresa principal mediante
  `employee_branch_assignments`; una sede queda marcada como principal.
- Los nombres comerciales iniciales de coberturas son referencias editables. RR. HH. debe
  verificar razón social, RNOS/código oficial, CUIT y plan antes de derivar aportes.
- Guardar una dirección estructurada actualiza también `users.address` para conservar las
  pantallas legacy.
- Las coordenadas del domicilio son información personal restringida. No deben exponerse a
  supervisores ni utilizarse como control automático de asistencia sin política, fundamento
  legal y autorización explícita.

Aplicación, luego de backup y de las migraciones históricas:

```powershell
Get-Content -Raw migration_employee_record_complete.sql |
  C:\xamppcubo\mysql\bin\mysql.exe -u root -D paviotti_lanaturaleza --default-character-set=utf8mb4
```

### Permisos por alcance

`migration_access_control_scopes.sql` agrega perfiles por empresa o sucursal, políticas
de módulos del portal, excepciones individuales y auditoría. La migración crea una
asignación principal compatible para cada usuario existente: `empleado` → Operario,
`supervisor` → Encargado y `admin` → Administrador. Puede ejecutarse con:

```powershell
php scripts\apply_access_control_migration.php
```

Luego, el editor de empresa muestra la matriz de módulos para la empresa y cada sucursal;
la ficha de usuario permite configurar perfiles y excepciones individuales.

La migración crea las tablas con `IF NOT EXISTS`, precarga nombres comerciales mediante
upsert y migra la empresa principal actual solo cuando aún no existe esa relación. No elimina ni sobrescribe
relaciones laborales históricas.

### Horarios, reloj y asistencia

- `employee_schedules`
- `shifts` y rangos asociados
- `clock_events`
- `marcaciones_cache`
- `schedules`
- `attendance_day_summary`
- `attendance_justifications`

En `/admin/weeklyPlanner`, el botón **Jornada L–V** toma una fecha base con el turno correcto y la replica en todos los lunes a viernes de un rango de hasta 93 días. Es útil para jornadas fijas (por ejemplo, una persona que trabaja siempre de lunes a viernes). La operación respeta la sucursal seleccionada: no borra ni reemplaza horarios que el mismo empleado tenga ese día en otra sede.
- `holidays`
- `shift_swaps`

### Horas extras

- `overtime_entries`
- `closures`
- tablas de detalle de cierre según la evolución del esquema

El estado normal de una hora extra es `pending`; al generar un cierre pasa a estado archivado/asociado al cierre.

### Solicitudes y legajo

- `request_types`
- `requests`
- `employee_incidents`
- `user_notes`
- `suggestions`

### Vacaciones

- `collective_agreements`
- reglas de convenio
- relación convenio/empresa
- `vacation_balance_periods`
- `vacation_balance_movements`
- caché de saldo en `users` para compatibilidad histórica

### Comunicaciones y recibos

- `announcements`
- `notification_broadcasts`
- `user_notifications`
- `pay_stubs`
- `mail_settings`
- `system_settings`

### Módulos adicionales

- Capacitación: cursos, lecciones, recursos, inscripciones, preguntas, respuestas, discusiones, tareas y recompensas.
- Adelantos: `salary_advances` y `salary_advance_installments`.
- Casa Paviotti: tipos de tarea, tarifas, entradas, extras externos y cierres.
- Encuestas: encuestas, preguntas, asignaciones, respuestas y opciones.
- PRODE: ediciones, grupos, partidos, entradas y predicciones.

## 8. Instalación local con XAMPP

### 8.1 Prerrequisitos

1. Instalar XAMPP con Apache, PHP y MySQL/MariaDB.
2. Habilitar `mod_rewrite`.
3. Verificar que el directorio permita overrides de Apache.
4. Instalar Composer.
5. Contar con un esquema de base autorizado.

### 8.2 Ubicar el proyecto

Ejemplo:

```powershell
cd C:\xampp\htdocs
git clone <URL_AUTORIZADA> gestion_horas_extras
cd gestion_horas_extras
```

No colocar tokens personales dentro de la URL del remote. Utilizar Git Credential Manager, SSH o el mecanismo corporativo aprobado.

### 8.3 Dependencias PHP

```powershell
composer install
```

En el estado actual no existe `composer.lock`; Composer resolverá la versión compatible más reciente de PHPMailer. Se recomienda generar y versionar el lock después de validar la aplicación.

### 8.4 Configuración local

Copiar el ejemplo sin versionar el resultado:

```powershell
Copy-Item app\config\config.local.example.php app\config\config.local.php
```

Contenido mínimo recomendado:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'usuario_rrhh');
define('DB_PASS', 'CAMBIAR');
define('DB_NAME', 'paviotti_lanaturaleza');
define('URLROOT', 'http://localhost/gestion_horas_extras');
```

No usar `root` sin contraseña fuera de una máquina local aislada.

### 8.5 Base de datos

Actualmente existen dos opciones:

1. **Preferida:** recuperar y revisar todas las migraciones mencionadas por `scripts/build_migration_hosting_full.sh`, versionarlas y construir el esquema desde cero.
2. **Transitoria:** restaurar un dump autorizado de una base ya migrada, sin datos personales si el entorno no es productivo.

No ejecutar `build_migration_hosting_full.sh` esperando que funcione mientras los archivos `migration_*.sql` sigan ausentes.

### 8.6 Permisos de archivos

El usuario de Apache/PHP debe poder escribir en las carpetas necesarias bajo `public/uploads`, pero no en todo el repositorio.

Carpetas funcionales:

- `avatars`
- `pay_stubs`
- `pay_stub_signatures`
- `justifications`
- `request_certificates`
- `employee_incidents`
- `announcements`
- `courses`

Los documentos privados deberían vivir fuera de `public/` o estar bloqueados por servidor y ser servidos únicamente por controladores autenticados.

### 8.7 Abrir la aplicación

```text
http://localhost/gestion_horas_extras/
```

Si aparece un 404, revisar `mod_rewrite`, `AllowOverride`, ambos `.htaccess` y `URLROOT`.

## 9. Despliegue en hosting/producción

### 9.1 Antes del despliegue

1. Confirmar rama y commit exactos.
2. Crear backup verificable de base y uploads.
3. Verificar que el repositorio no contenga datos personales ni secretos.
4. Ejecutar lint PHP.
5. Instalar dependencias con Composer.
6. Probar el cambio en staging con una base saneada.
7. Definir un plan de rollback.

### 9.2 Artefacto

En un entorno con `composer.lock`:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Configurar `app/config/config.local.php` fuera de Git con:

- base de datos;
- `URLROOT` HTTPS;
- API de relojes;
- base externa de Casa Paviotti, si aplica;
- cualquier override de entorno.

### 9.3 Apache y TLS

- Forzar HTTPS.
- Desactivar listado de directorios.
- Ocultar versión de PHP/Apache.
- Confirmar que las carpetas privadas responden 403 ante acceso directo.
- Añadir CSP compatible con los recursos utilizados.
- No depender únicamente de `.htaccess` si el hosting no permite overrides.

### 9.4 Migraciones

- Ejecutar sólo migraciones revisadas y versionadas.
- Mantener backup previo.
- Registrar quién, cuándo y sobre qué base ejecutó cada migración.
- Ejecutar `scripts/validate_hosting_migration.sql` después de migrar.
- Ejecutar el smoke test de sólo lectura.

```bash
php scripts/hosting_smoke_test.php nombre_base
```

### 9.5 Semillas opcionales

PRODE necesita datos semilla:

```bash
php scripts/seed_prode_wc2026.php
php scripts/download_prode_flags.php
```

No ejecutar semillas productivas sin revisar si son idempotentes y apropiadas para esa base.

### 9.6 Validación posterior

- Login de cada rol.
- Cambio de empresa para admin.
- Restricción por área para supervisor.
- Ficha del empleado.
- Planificador semanal y turno partido.
- Sincronización de una ventana pequeña de marcaciones.
- Tablero de asistencia.
- Carga y cierre controlado de una hora extra de prueba.
- Solicitud, aprobación y rechazo.
- Acceso protegido a recibos, certificados e incidencias.
- Envío SMTP de prueba.
- Logs de Apache/PHP sin errores.

## 10. Configuración e integraciones

### Configuración por archivo

`app/config/config.php` define defaults. `app/config/config.local.php` puede sobrescribir constantes antes de que se apliquen esos defaults.

Variables importantes:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- `URLROOT`, `APP_ENV`, `APP_DEBUG`
- `CLOCK_API_BASE_URL`, `CLOCK_API_EMAIL`, `CLOCK_API_PASSWORD`
- `EXTINTOS_DB_HOST`, `EXTINTOS_DB_NAME`, `EXTINTOS_DB_USER`, `EXTINTOS_DB_PASS`
- tolerancias de asistencia

### Configuración desde la interfaz

`/systemConfig` guarda settings generales, integraciones, asistencia, horas extras, portal del empleado, adelantos y correo. El panel exige rol admin y una segunda clave temporal.

Los valores viven en `system_settings` y `mail_settings`. Actualmente algunos secretos se almacenan en texto plano; no asumir que la base es un almacén seguro de secretos.

### API de relojes

`ClockApiClient` consume endpoints para:

- autenticación/token;
- marcaciones;
- legajos;
- catálogos y relojes;
- sucursales y comisiones Ecofarma.

#### Alcance de dispositivos y personas

- Ejecutar `migration_clock_devices_scope.sql` para registrar cada dispositivo y asignarlo a una o más sucursales. Una sucursal determina indirectamente la empresa del reloj.
- Las personas **no** necesitan tener un ID de reloj: solo se crea un mapeo para quien efectivamente ficha.
- El mapeo nuevo se identifica por **dispositivo + legajo**. Así una persona que rota por varias sucursales puede tener uno o varios legajos, incluso si otro reloj reutiliza el mismo número.
- `admin/marcacionesTodas` filtra las fichadas sin mapear por el alcance del reloj y ofrece filtro de sucursal. Un reloj sin sucursal asignada permanece sin adjudicar: debe revisarse desde **Relojes y sucursales**.
- Ejecutar también `migration_attendance_control_mode.sql`. Cada empleado queda inicialmente en **Obligatorio**; RR. HH. puede cambiarlo a **Flexible** o **Sin reloj** desde su ficha.

El default histórico usa HTTP. En producción debe utilizarse HTTPS con certificado válido. No transmitir credenciales, tokens ni marcaciones por HTTP plano.

### Base externa Casa Paviotti

`DeceasedLookupService` consulta una base externa de fallecidos/extintos para formularios operativos. Validar host, tablas, permisos de sólo lectura y tratamiento de datos antes de habilitarla.

### Correo

PHPMailer utiliza `mail_settings`. Configurar:

- host y puerto;
- TLS/SSL;
- usuario y contraseña;
- remitente;
- estado habilitado.

Usar `/systemConfig/mailTest` para una prueba controlada.

### Automatización/sincronización

`app/sync.bat` es un artefacto histórico: apunta a `C:\xampp54` y a `cron_sync.php`, archivo que no existe en este repositorio. **No usarlo sin reconstruir y validar el proceso.**

La sincronización disponible actualmente se inicia desde rutas administrativas. Si se necesita un cron real, crear un entrypoint CLI autenticado por entorno, idempotente, observable y con bloqueo para evitar ejecuciones simultáneas.

## 11. Reglas funcionales críticas

### Horas extras

La implementación actual clasifica:

- feriados y domingos: 100 %;
- sábados desde las 12:00: 100 %;
- franja 22:00–06:00: 100 %;
- resto: 50 %;
- cada categoría se redondea por separado a medias horas.

Estas reglas no deben considerarse definitivamente correctas. Como regla general argentina, el artículo 201 de la LCT establece el 100 % los sábados después de las 13:00, domingos y feriados. Los convenios pueden introducir particularidades.

Referencia oficial: <https://www.argentina.gob.ar/normativa/nacional/25552/actualizacion>

Pendientes recomendados:

- parametrizar el corte del sábado;
- parametrizar reglas nocturnas por convenio;
- definir una única política de redondeo;
- distinguir horas trabajadas de horas realmente extraordinarias;
- conciliar planificación, marcaciones y declaración;
- alertar límites mensuales/anuales;
- crear pruebas de borde y cruces de medianoche.

### Asistencia

`PlanVsActualService` compara:

- bloques planificados;
- primera entrada y última salida;
- minutos trabajados calculados a partir de marcaciones;
- tolerancia de llegada y salida;
- licencias/vacaciones aprobadas.

En turnos partidos se agregan minutos y extremos del día. Un agente debe revisar cuidadosamente los casos de ausencia en un bloque intermedio, marcaciones impares, turnos nocturnos y zonas horarias.

### Vacaciones

La implementación v2 usa años calendario, calcula la antigüedad al 31 de diciembre y permite conteo corrido, lunes a viernes o lunes a sábado excluyendo feriados. Los saldos ordinarios, históricos y convencionales se registran por separado; el consumo parcial es FIFO y las aprobaciones/cancelaciones son transaccionales con bloqueo de filas, idempotencia y restauración del planificador.

Los períodos y movimientos son la fuente de verdad. `users.vacation_days_available` es únicamente un caché recalculado después de cada movimiento.

Referencia oficial: <https://www.argentina.gob.ar/normativa/nacional/ley-20744-25552/actualizacion>

## 12. Seguridad y privacidad

### Controles existentes

- Password hashing con `password_hash`/`password_verify`.
- Regeneración de sesión tras login.
- Cookies `HttpOnly` y `SameSite=Lax`.
- CSRF en operaciones sensibles.
- Roles, empresa activa y controles por área.
- Consultas preparadas en la mayoría del acceso a datos.
- Validación de extensión, MIME y tamaño en uploads.
- Controladores autenticados para recibos, firmas, certificados e incidencias.
- Cabeceras `SAMEORIGIN`, `nosniff`, `Referrer-Policy` y `Permissions-Policy`.

### Problemas críticos conocidos

1. La carpeta histórica `public/uploads/documents` no está incluida en la protección automática ni en `.gitignore`. En la revisión se comprobó acceso HTTP directo a documentación personal.
2. Algunos binarios personales ya fueron versionados. Deben retirarse también del historial Git y tratarse como una exposición de datos.
3. La API de reloj tiene un endpoint HTTP como default.
4. Contraseñas de API, SMTP y base externa se guardan en texto plano.
5. No hay rate limiting, bloqueo temporal ni MFA en login.
6. `public/generar_hash.php` es una herramienta administrativa pública y no debería desplegarse.
7. Los errores de conexión PDO pueden mostrar detalles internos.
8. No existe auditoría unificada e inmutable de operaciones y accesos documentales.
9. La respuesta HTTP expone versiones del runtime si el servidor no las oculta.

### Reglas para agentes y desarrolladores

- No abrir ni copiar documentos reales salvo que sea imprescindible y esté autorizado.
- Para diagnósticos, preferir esquema y conteos agregados.
- No mostrar valores de `config.local.php`, secretos de la base ni URLs de Git con credenciales.
- No versionar `vendor`, dumps, logs, sesiones o uploads.
- No usar datos productivos en desarrollo.
- No borrar incidencias o documentos sin una política de retención aprobada.
- Antes de modificar permisos, resolver el usuario, empresa y área objetivo.
- Todo endpoint mutante debe exigir método HTTP apropiado y CSRF.
- Toda descarga sensible debe verificar propiedad o autorización en servidor.

## 13. Estado de migraciones

`scripts/build_migration_hosting_full.sh` espera archivos como:

- `migration_v2.sql`
- `migration_companies_grupo.sql`
- `migration_attendance_summary.sql`
- `migration_learning.sql`
- `migration_notifications_paystubs.sql`
- `migration_collective_agreements.sql`
- `migration_surveys.sql`
- `migration_casapav_tasks.sql`
- `migration_system_settings.sql`
- `migration_prode_wc2026.sql`
- y más migraciones intermedias.

La mayoría de esos archivos históricos no están actualmente en el repositorio. `migration_vacation_management_v2.sql` sí está versionada y se incorpora como paso 38 del generador de hosting; `migration_employee_record_complete.sql` se incorpora como paso 39.

Plan recomendado:

1. Localizar las migraciones originales en backups o repositorios autorizados.
2. Revisar orden, idempotencia y compatibilidad con la base actual.
3. Crear una tabla de control de versiones de esquema.
4. Versionar las migraciones sin datos reales ni secretos.
5. Probar una reconstrucción completa en una base vacía.
6. Comparar el esquema reconstruido con producción.
7. Documentar rollback o irreversibilidad por migración.

Hasta completar esto, una copia de la aplicación no equivale a un sistema desplegable desde cero.

## 14. Verificaciones y comandos útiles

### Estado Git

```powershell
git status --short
git log -8 --oneline --decorate
```

### Inventario

```powershell
rg --files
rg '^\s*public function ' app\controllers app\models
```

### Lint de todos los PHP

```powershell
$failed = @()
$files = rg --files -g '*.php'
foreach ($file in $files) {
    php -l $file
    if ($LASTEXITCODE -ne 0) { $failed += $file }
}
$failed
```

### Composer

```powershell
composer validate --no-check-publish
composer install
```

### Smoke test de base, sólo lectura

```powershell
php scripts\hosting_smoke_test.php
```

El smoke test comprueba tablas esenciales, datos históricos mínimos, rol supervisor y semillas PRODE.

### Pruebas de vacaciones v2

```powershell
php scripts\test_vacation_acceptance.php
```

La prueba usa la base local configurada, crea datos temporales dentro de una transacción y siempre ejecuta rollback. Valida solicitud parcial, FIFO 2025→2026, reversión exacta, restauración del planificador, separación de saldos históricos, reglas SOECRA y reporte agregado.

### Prueba HTTP básica

```powershell
curl.exe -I http://localhost/gestion_horas_extras/
```

En producción reemplazar por HTTPS y comprobar las cabeceras de seguridad.

### QA visual y de navegación

Probar como mínimo anchos de 375, 768, 992 y 1440 px, con una sesión de cada
rol. Para administración, repetir las pantallas principales después de cambiar
de empresa desde el navbar.

- No debe existir desborde horizontal en login, topbar, tablas encapsuladas o
  navegación inferior.
- El selector de empresa debe estar visible y mostrar la empresa activa también
  en mobile.
- El cambio desde una ficha individual debe volver a un listado coherente; no
  debe intentar mostrar el ID perteneciente a la empresa anterior.
- Solo una entrada del sidebar debe aparecer activa.
- El menú móvil debe abrir, cerrar por botón, overlay y tecla `Escape`, y
  actualizar `aria-expanded`.
- Tabular con teclado debe producir un indicador de foco visible.
- Activar la preferencia de reducción de movimiento del sistema y comprobar que
  no queden animaciones prolongadas.
- Revisar la consola y la pestaña Network: no debe aparecer el antiguo 404 de
  `fullcalendar@6.1.11/main.min.css`.
- Verificar que cerrar sesión emita `POST`; acceder a `/login/logout` mediante
  `GET` no debe destruir una sesión.

Comandos rápidos para detectar regresiones conocidas:

```powershell
rg -n 'href=.*login/logout' app\views -g '*.php'
rg -n 'fullcalendar.*\.css' app public -g '*.php' -g '*.css'
git diff --check
```

### Búsqueda de referencias a migraciones faltantes

```powershell
rg -n -i 'migration_[a-z0-9_./-]+\.sql|MIGRATIONS.md' app scripts
```

### Búsqueda preventiva de datos sensibles

No imprimir contenido de archivos. Listar únicamente rutas:

```powershell
git ls-files public/uploads
git ls-files | rg -i '(dni|document|recibo|signature|certificate|\.sql$|\.env$)'
```

## 15. Problemas conocidos y deuda técnica

### Prioridad crítica

- Bloquear y retirar documentación personal accesible desde `public/uploads/documents`.
- Remover esos archivos del historial Git y realizar el procedimiento interno de incidente.
- Corregir la regla de horas extras del sábado y validar reglas nocturnas/convenios.
- Recuperar y versionar migraciones.
- Migrar integraciones a TLS.

### Prioridad alta

- Añadir rate limiting y registro de intentos fallidos.
- Retirar `public/generar_hash.php` del artefacto productivo.
- Cifrar o externalizar secretos.
- Crear auditoría de cambios y accesos.
- Añadir pruebas automatizadas de reglas laborales.
- Generar y versionar `composer.lock`.
- Reemplazar errores `die(...)` por manejo controlado y logging seguro.

### Mantenibilidad

- Dividir `AdminController` por dominios: usuarios, asistencia, horas extras, solicitudes, horarios, incidencias y reportes.
- Mantener la inyección de una única conexión en toda nueva operación compuesta de vacaciones.
- Introducir repositorios o servicios con dependencias inyectadas.
- Documentar estados y transiciones de solicitudes, horas, cierres, adelantos y recibos.
- Crear pruebas de integración con una base efímera.
- Añadir CI para lint, pruebas y validación de Composer.
- Reducir estilos inline históricos y dividir `public/css/style.css` por capas o
  dominios sin perder los tokens compartidos.
- Cargar librerías pesadas únicamente en las vistas que las utilizan y evaluar
  una estrategia local para no depender por completo de CDNs.
- Unificar progresivamente Font Awesome y Bootstrap Icons para evitar
  diferencias de grosor y alineación.

### Producto

- Crear un workflow formal de reclamos.
- Crear un workflow de desvinculación/offboarding.
- Incorporar respuesta y estado al buzón de sugerencias.
- Separar claramente módulos RR. HH. de módulos recreativos u operativos.
- Añadir métricas de adopción y fallos de integración.

## 16. Guía para realizar cambios

1. Leer el controlador, modelo, helpers, servicios y vista relacionados antes de editar.
2. Identificar rol, empresa, área y feature flags aplicables.
3. Revisar si el esquema requerido existe en todos los entornos.
4. Evitar migraciones implícitas desde el código de aplicación.
5. Usar consultas preparadas y listas blancas para SQL dinámico.
6. Preservar compatibilidad con los estados históricos guardados en español e inglés según cada tabla.
7. Proteger toda operación POST con CSRF.
8. Validar archivos por extensión, MIME real, tamaño y autorización de descarga.
9. Para operaciones compuestas, inyectar una conexión compartida y usar transacciones reales.
10. Añadir pruebas para caminos exitosos, errores, duplicados, límites y aislamiento multiempresa.
11. Ejecutar lint y smoke test.
12. Documentar nuevas rutas, tablas, settings y tareas programadas en este README.

## 17. Checklist de entrega

- [ ] No hay secretos, tokens ni datos personales en Git.
- [ ] `git status` sólo muestra cambios esperados.
- [ ] Todos los archivos PHP pasan `php -l`.
- [ ] Composer valida y las dependencias están instaladas.
- [ ] Las migraciones necesarias están presentes, revisadas y probadas.
- [ ] Existe backup y rollback.
- [ ] Login y permisos funcionan para los tres roles.
- [ ] El aislamiento por empresa y área fue probado.
- [ ] El selector de empresa funciona en desktop y mobile, y no conserva IDs de otra empresa.
- [ ] No hay rutas visuales con 404 ni errores nuevos en la consola.
- [ ] Sidebar, topbar y navegación inferior tienen foco visible y funcionan con teclado.
- [ ] Logout usa POST con CSRF desde todas sus ubicaciones.
- [ ] Los archivos sensibles no son accesibles directamente.
- [ ] Las integraciones utilizan TLS.
- [ ] El smoke test pasa.
- [ ] Los logs no contienen secretos ni datos personales innecesarios.
- [ ] Este README refleja cualquier ruta, tabla o configuración nueva.

## 18. Diagnóstico de problemas comunes

### Redirección infinita o URL incorrecta

- Revisar `URLROOT`.
- Comprobar detección de entorno en `app/helpers/app_env_helper.php`.
- Verificar ambos `.htaccess`.
- Confirmar que Apache permita overrides.

### “Módulo no instalado”

- El modelo probablemente ejecutó un `SHOW TABLES`/`SHOW COLUMNS` y no encontró el esquema esperado.
- Buscar el nombre de migración mostrado por el mensaje.
- No ocultar el error creando columnas manuales sin recuperar la migración completa.

### No aparecen marcaciones

- Revisar URL y credenciales de la API.
- Verificar HTTPS y conectividad.
- Revisar mapeo reloj ↔ usuario.
- Comprobar `clock_events` y `marcaciones_cache` mediante conteos, sin exponer datos.
- Ejecutar una sincronización de rango pequeño.

### La asistencia no coincide

- Revisar horarios planificados del día.
- Revisar dirección de cada marcación.
- Comprobar turnos partidos y marcas impares.
- Recalcular un rango limitado.
- Revisar licencias, vacaciones y justificaciones aprobadas.

### Vacaciones sin saldo

- Confirmar fecha de ingreso formal.
- Confirmar convenio del empleado o convenio por defecto de la empresa.
- Verificar reglas por antigüedad.
- Liquidar/importar el período.
- Revisar movimientos y no corregir únicamente el saldo cacheado en `users`.
- Si existe un crédito vencido, ejecutar `php scripts/expire_vacation_credits.php` y revisar el movimiento `expiry`.
- Si aparece una incompatibilidad de unidades, usar la conversión auditada desde la ficha de vacaciones; no editar saldos directamente.

### Correo no disponible

- Confirmar esquema de notificaciones y `mail_settings`.
- Revisar PHPMailer/vendor.
- Configurar SMTP con TLS.
- Usar el envío de prueba desde configuración.

## 19. Referencias internas principales

- Router: `app/Core.php`
- Bootstrap: `app/bootstrap.php`
- Configuración: `app/config/config.php`
- Sesión y CSRF: `app/helpers/session_helper.php`
- Roles y multiempresa: `app/helpers/auth_helper.php`
- Base PDO: `app/models/Database.php`
- Horas extras: `app/models/Overtime.php`
- Marcaciones: `app/models/Schedule.php`, `app/models/SyncModel.php`
- Plan vs. real: `app/models/PlanVsActualService.php`
- Vacaciones: `app/services/VacationEntitlementService.php`, `app/services/VacationLedgerService.php`
- Archivos protegidos: `app/helpers/uploads_security_helper.php`
- Configuración dinámica: `app/services/SystemSettingsService.php`
- Smoke test: `scripts/hosting_smoke_test.php`

---

Última revisión integral del documento: agosto de 2026. Antes de usar reglas laborales para liquidación, validarlas con el convenio aplicable y con responsables legales/contables de la organización.
