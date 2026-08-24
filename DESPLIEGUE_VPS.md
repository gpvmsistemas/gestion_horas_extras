# Despliegue de la Suite P&M al VPS — paso a paso

Estado de partida: el VPS corre la versión previa de Lautaro (su repo personal
`zerofarias/gestion_horas_extras` como `origin`); todo el trabajo de la
integración vive en la rama **`integracion`** de
`gpvmsistemas/gestion_horas_extras`. Esta guía lleva el VPS a ese estado.

> **Regla de oro**: cada script de datos tiene *dry-run por defecto* — primero
> sin `--ejecutar`, se lee el plan, y recién después se ejecuta.

---

## 0 · Antes de empezar

1. **Coordinar con Lautaro**: el despliegue pisa la versión que él tiene en el
   VPS. Ideal: que valide el merge y estas pantallas en local primero.
2. **Backup de la base en el VPS** (obligatorio):
   ```bash
   mysqldump -u USUARIO -p BASE > ~/backup_pre_integracion_$(date +%F).sql
   ```
3. **Backup del código** (por si hay cambios sin commitear en el VPS):
   ```bash
   cd /ruta/al/proyecto && git status --short   # si hay algo, avisarle a Lautaro
   ```
4. Recordatorios de seguridad pendientes:
   - Pasar el repo `gpvmsistemas/gestion_horas_extras` a **PRIVADO** (lo hace
     un admin de la organización).
   - Borrar `/tmp/lautaro_vps.tar.gz` del VPS y su copia en Downloads de la PC
     (contienen dumps de producción con datos personales).

## 1 · Preparar en la PC (archivos que NO viajan por git)

Estos archivos están **gitignorados** (datos personales / credenciales) y se
llevan a mano:

| Archivo | Qué es |
|---|---|
| `Nomina Comercial SISTEMAS (1).xlsx` | Nómina de RRHH (Marketing y Comercialización) |
| `nomina_credenciales.json` | Usuario por legajo (M37, M47, …) |
| `vacaciones_2025.json` | Ya convertido del informe .xls (`scripts/convertir_vacaciones_xls.py`, requiere `pip install xlrd==1.2.0` — solo en la PC) |

Subirlos (ojo: IP real, sin `<>`):

```bash
scp "Nomina Comercial SISTEMAS (1).xlsx" nomina_credenciales.json vacaciones_2025.json usuario@IP_DEL_VPS:/tmp/
```

## 2 · Código en el VPS

> El `.git` del proyecto en el VPS pertenece al usuario `lautaro`: estos
> comandos deben correrse con ese usuario (o que Lautaro los ejecute).

```bash
cd /ruta/al/proyecto

# 2.1 Agregar el remoto de la organización (una sola vez)
git remote add pym https://github.com/gpvmsistemas/gestion_horas_extras.git 2>/dev/null || true

# 2.2 Traer y activar la rama de integración
git fetch pym integracion
git checkout -B integracion pym/integracion   # ⚠ pisa el árbol de trabajo: ver backup del paso 0.3
```

Cuando Lautaro valide y se mergee a `main`, el VPS pasa a seguir
`pym/main` y conviene dejar `origin` apuntando a `gpvmsistemas` (unificación
pendiente del backlog).

## 3 · Configuración PHP del VPS

Para los videos en anuncios (hasta 50 MB):

```ini
; php.ini
upload_max_filesize = 64M
post_max_size = 64M
```

Reiniciar PHP-FPM/Apache después de tocarlo.

Verificar además que `app/config/config.local.php` del VPS tenga las
credenciales correctas de SU base (no viaja por git).

## 4 · Esquema de base de datos

**No hace falta adivinar qué migraciones le faltan al VPS** — el verificador
lo dice, en orden y con el comando exacto de cada pendiente:

```bash
php scripts/verificar_esquema_vps.php
```

Aplicar cada `PENDIENTE` **en el orden listado** y volver a correr el
verificador hasta ver `ESQUEMA COMPLETO`. Los grupos, en orden:

1. **Base organizacional**: employee_group, organization_group, branding,
   branch_id, attendance_control_mode, clock_devices, access_scopes.
2. **Sucursales y feriados**: company_branches (Ecofarma), sucursales Moderna,
   employee_branch_assignments, holiday_rules, reglas locales de Córdoba.
3. **Legajo/convenios/vacaciones**: CCTs (incluye la escala 430/05 Farmacia),
   tipos vacation/leave, vacaciones v2, legajo ampliado, obras sociales.
4. **Suite P&M**: registro de horas, 3 sociedades Moderna, videos en anuncios,
   estados del empleado, horario de atención, ficha personal.

*(El último ítem del verificador — scope de axel.moderna — queda pendiente
hasta el paso 5; es normal.)*

## 5 · Cuenta RRHH de Moderna

1. Con un admin existente, crear el usuario **`axel.moderna`** en
   *Usuarios → Crear*: rol **Administrador**, empresa **MODERNA SRL**, grupo
   organizacional **Moderna**, con su contraseña.
2. Darle el scope de acceso (permite abrir fichas de FRANCE/FCF, con el
   aislamiento por organización ya garantizado en código):
   ```bash
   mysql -u USUARIO -p BASE < scripts/fix_scope_admin_moderna.sql
   ```
3. Re-correr el verificador del paso 4: ahora debe dar `ESQUEMA COMPLETO`.

## 6 · Datos de Moderna (en este orden)

```bash
# 6.1 Limpieza del lado Moderna (demos/restos; staff y Paviotti intactos)
php scripts/limpieza_moderna.php              # dry-run: leer el plan
php scripts/limpieza_moderna.php --ejecutar

# 6.2 Nómina (Marketing y Comercialización → FRANCE SRL, sucursal Comercial y MKT)
php scripts/import_nomina.php "/tmp/Nomina Comercial SISTEMAS (1).xlsx" /tmp/nomina_credenciales.json
php scripts/import_nomina.php "/tmp/Nomina Comercial SISTEMAS (1).xlsx" /tmp/nomina_credenciales.json --ejecutar

# 6.3 Vacaciones período 2025 (saldos + movimientos + bloques en calendario)
php scripts/import_vacaciones.php /tmp/vacaciones_2025.json
php scripts/import_vacaciones.php /tmp/vacaciones_2025.json --ejecutar

# 6.4 Borrar los archivos sensibles del VPS
shred -u /tmp/"Nomina Comercial SISTEMAS (1).xlsx" /tmp/nomina_credenciales.json /tmp/vacaciones_2025.json 2>/dev/null \
  || rm -f /tmp/"Nomina Comercial SISTEMAS (1).xlsx" /tmp/nomina_credenciales.json /tmp/vacaciones_2025.json
```

Ambos importadores son **idempotentes**: re-ejecutarlos no duplica.

## 7 · Verificación funcional (checklist)

- [ ] Login `axel.moderna+...` → cabecera "Suite Red Farmacias Moderna", sin
      módulos de Paviotti a la vista.
- [ ] Login de un colaborador importado (p. ej. `M37+9335`) → portal de
      empleado con marca Moderna.
- [ ] *Registro de Horas → Carga*: los 10 aparecen con sucursal "Comercial y
      MKT"; la vista previa en vivo calcula y marca feriados/conflictos.
- [ ] *Por sucursal*: tabla semanal con horario de atención editable (lápiz).
- [ ] Ficha de un colaborador de FRANCE (p. ej. M37) → abre, pestaña
      Vacaciones muestra el período 2025 con su saldo.
- [ ] Cargar horas a Rivata en 05–11/10/2026 → los días figuran bloqueados
      por Vacaciones.
- [ ] Un admin de Paviotti NO puede abrir fichas de Moderna (redirige), y
      viceversa.
- [ ] Subir un anuncio con video corto (prueba de los límites de PHP).

## 8 · Después

- Merge `integracion` → `main` cuando Lautaro valide, y dejar el VPS siguiendo
  `main` del repo de la organización.
- Repo a privado (si no se hizo en el paso 0).
- Próximas nóminas de otras áreas: repetir el paso 6 con el nuevo Excel y su
  JSON de credenciales (`--sucursal "Nombre"` si cambia la sucursal destino).
