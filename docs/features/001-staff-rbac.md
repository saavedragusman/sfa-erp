# 001 · Staff RBAC (BR-01)

Rama: `feat/001-staff-rbac` · Zona roja: permisos (4R en el cierre de fase) · Estado: **puerta aprobada el 2026-11-09 con ajustes; ejecución en curso**

## 1. Spec
Pedido del usuario, literal:

> Implementa BR-01 siguiendo AGENTS.md (zona roja: permisos → Feature Document + puerta).
> Crea docs/features/001-staff-rbac.md y detente al terminar Diseño técnico y Tareas.

BR-01, literal (`docs/business/BUSINESS_RULES.md`):

> Crear o editar personal exige `staff.manage`. Asignar el rol `admin` exige además `staff.manage_admins`. El seeder solo da estos permisos a `admin`, y a `manager` solo el primero.
> Action → Excepción: `CreateStaffMemberAction` → `AuthorizationException`
> Test RED: `it('BR-01 forbids users without staff.manage from creating staff')`

## 2. Alcance
- **Cubre:** BR-01 (ampliada por DEC-11), BR-56, BR-57 (solo "cambiar personal y permisos") y BR-59 (nueva, por DEC-09).
- **Fuera de alcance:**
  - BR-02 (permisos financieros).
  - El resto de las acciones sensibles de BR-57 (facturas, caja, tasas, stock, clínica, INSAI): se auditan en sus propias features con el servicio que crea esta.
  - Baja o desactivación del personal: BR-01 solo habla de crear y editar.
- **Lectura técnica:** "personal" = un `User` del sistema con un rol de personal. Un empleado de nómina sin acceso (HR, DEC-01) no es personal en este sentido.

## 3. Decisiones (puerta aprobada 2026-11-09)
```text
[DEC-09] BR-59 · Resuelta: B (link para definir contraseña, enviado solo después del commit), 2026-11-09.
         Se convierte en la BR-59 nueva, con su test RED.
[DEC-10] BR-01 · Resuelta: varios roles de staff por persona; `owner` nunca se combina con roles de staff, 2026-11-09.
         Pendiente de confirmar con la clínica. Cambia la opción A que yo había recomendado (un rol por persona).
[DEC-11] BR-01 · Resuelta: A. Editar o degradar a un admin existente exige también staff.manage_admins, 2026-11-09.
         Amplía BR-01 con su test.
```
Asunciones técnicas mías, sin decisión de negocio:
- El personal necesita al menos un rol de staff (un `User` sin roles no es "personal"). Si la clínica quiere personal sin rol, se abre una DEC.
- El plazo de expiración del link usa la configuración del broker de contraseñas de Laravel. El setting ⚙horas de BR-08 aún no existe, y se migra cuando llegue `ClinicSettings`.
- Los `owner` los crea `RegisterOwnerAction` (BR-08): las Actions de staff nunca asignan ni conservan `owner`.

## 4. Diseño técnico
**Piezas** (todo en inglés; la UI, en español). `Settings` es el módulo de las Actions, porque M0 es "Ajustes, permisos y auditoría":
- `Enums/Permission` (catálogo único) arranca con `StaffManage = 'staff.manage'` y `StaffManageAdmins = 'staff.manage_admins'`. Cada BR posterior agrega los suyos.
- `Enums/RoleName`: es un backed enum con `admin`, `manager`, `reception`, `doctor` y `owner`, más el método `isStaff()`.
- `Models/User` recibe `HasRoles`.
- `RolesAndPermissionsSeeder` es idempotente (`firstOrCreate` + `syncPermissions`) y mantiene un único mapa rol→permisos que los BR posteriores extienden. Hoy `admin` tiene ambos y `manager` tiene `staff.manage`.
- `audit_logs` (BR-56): migración nueva con `actor_id` (FK `restrict`, nullable), `action`, `entity_type`, `entity_id`, `ip` (`inet`), `before` y `after` (`jsonb` con GIN, según §6) y `created_at timestamptz`. Un trigger `BEFORE UPDATE OR DELETE` lanza una excepción. El truncado no se cubre, porque BR-56 solo pide UPDATE y DELETE.
- `Support/Audit/AuditLogger::record(?User $actor, string $action, Model $entity, ?array $before, ?array $after)`:
  - Inserta en la transacción abierta por la Action.
  - Toma la IP de la request actual.
  - Elimina recursivamente las claves `password`, `remember_token` y cualquier clave que contenga `token` (BR-56: nunca guarda contraseñas ni tokens).
- `Policies/UserPolicy`: sus habilidades son `createStaff`, `updateStaff` y `assignAdminRole`, y solo preguntan por permisos (`can(...)`).
- `Actions/Settings/CreateStaffMemberAction` y `UpdateStaffMemberAction`: son `final` y tienen un único `execute(User $actor, StaffMemberData $data[, User $member])`. Siguen este orden:
  1. Autorizan con `Gate::forUser($actor)->authorize(...)` antes de escribir.
  2. Abren `DB::transaction()` (User + roles + auditoría).
  3. Despachan el link después del commit.
- Regla de autorización:
  - `staff.manage` es siempre obligatorio.
  - `staff.manage_admins` se exige si el objetivo **es admin hoy** o los roles nuevos **incluyen admin**. Esto cubre asignar, editar y degradar.
- `DTOs/Settings/StaffMemberData` es `readonly` y lleva `name`, `email` y `roles` (`list<RoleName>`).
- `Exceptions/Domain/InvalidStaffRolesException` (`RULE = 'BR-01'`) salta con `owner` o con una lista vacía de roles.
- Link de contraseña (BR-59):
  - Un token del broker (`Password::broker()->createToken`) y una `Notification` con `ShouldQueue` y `afterCommit = true`.
  - El panel Filament aún no tiene `->passwordReset()`: se habilita en la Fase 3 y la URL del link la construye una ruta nombrada. Así la Action no conoce Filament.

**Invariantes de seguridad**
- No hay `hasRole`, `Gate::before` ni superadmin implícito. Un `admin` sin `staff.manage_admins` queda denegado, y un test lo fija.
- La denegación sale por Policy como `Illuminate\Auth\Access\AuthorizationException`, tal como pide BR-01.
- Las pruebas de autorización se hacen contra la Action, no contra la UI (§6).

**Dependencia pendiente de aprobación.** Pest no está instalado: solo está permitido el plugin en `composer.json`, y hay dos `ExampleTest` de PHPUnit. Sin Pest no existen `it()` ni `arch()`, que exigen la Constitución y AGENTS. La tarea 0.2 lo instala, y por AGENTS §2 se espera tu aprobación explícita.

**Pantalla (Fase 4).** `StaffResource` de Filament delega en las Actions vía `handleRecordCreation/Update`. Se oculta sin `staff.manage`, usa textos en español y habilita `->passwordReset()` en el panel.

## 5. Tareas
Cada tarea sigue TDD estricto (RED → GREEN → refactor) con nombres que empiezan por su BR.

**Fase 0 · Línea base**
- [x] 0.1 spatie (composer, config y migración) está commiteado en `b9a74a8` y sus tablas migran contra PostgreSQL `testing`.
- [x] 0.2 Instalar Pest (`pestphp/pest`, `pest-plugin-laravel` y `pest-plugin-arch`) y convertir los dos `ExampleTest`. **Requiere tu aprobación (AGENTS §2).** Commit: `chore(deps): add pest [BR-01]`.

**Fase 1 · Catálogo y roles** (depende de 0.2)
- [x] 1.1 RED:
  - `BR-01 seeds staff.manage to admin and manager`.
  - `BR-01 seeds staff.manage_admins only to admin`.
  - `BR-01 grants no staff permission to reception, doctor or owner`.
- [x] 1.2 `Permission`, `RoleName`, `HasRoles` en `User`, `RolesAndPermissionsSeeder` y su llamada desde `DatabaseSeeder`.
- [x] 1.3 Test de comportamiento previo: `BR-01 keeps user factory and password hashing unchanged`.

**Fase 2 · Auditoría (BR-56 + servicio)** (zona roja; depende de 0.2)
- [x] 2.1 RED: `BR-56 rejects updating or deleting an audit log row` y `BR-56 never stores passwords or tokens`.
- [x] 2.2 Migración de `audit_logs` con trigger, `AuditLog` y `AuditLogger` (BR-56).
- [x] 2.3 RED y luego implementación de `BR-57 audit logger records actor, ip and before/after values in the caller transaction`: prueba que un rollback elimina la fila.

**Fase 3 · Autorización, Actions y link** (zona roja; depende de 1.x y 2.x)
- [x] 3.1 RED:
  - `BR-01 forbids users without staff.manage from creating staff` (catálogo).
  - `BR-01 forbids manager from assigning the admin role`.
  - `BR-01 lets manager create non-admin staff with several roles`.
  - `BR-01 lets admin create staff with the admin role`.
  - `BR-01 denies an admin who lacks staff.manage_admins`.
  - `BR-01 rejects owner combined with staff roles`.
  - `BR-01 persists nothing when authorization fails`.
- [x] 3.2 RED:
  - `BR-01 forbids users without staff.manage from updating staff`.
  - `BR-01 forbids manager from editing or demoting an existing admin` (DEC-11).
  - `BR-01 forbids manager from promoting staff to admin`.
- [x] 3.3 RED: `BR-57 audits staff creation and updates with before and after values` y `BR-57 rolls back staff changes when audit fails`.
- [x] 3.4 RED: `BR-59 sends set-password link to new staff only after commit` (y no la envía si la transacción revierte).
- [x] 3.5 Implementar `StaffMemberData`, `UserPolicy`, `InvalidStaffRolesException`, las dos Actions y la notificación.
- [x] 3.6 `BUSINESS_RULES.md`: ampliar BR-01 y agregar BR-59, DEC-09 a DEC-11 con "Resuelta: X, 2026-11-09". Va en el mismo commit que sus tests.
- [x] 3.7 Larastan nivel 8 sin baseline nuevo, `pint --test` y la suite completa.

**Fase 4 · Pantalla Filament** (depende de 3.x)
- [ ] 4.1 `StaffResource` en el panel (`->passwordReset()` ya se habilitó en la Fase 3, porque el link de BR-59 lo necesita), con un test contra el backend (HTTP) de que sin `staff.manage` no se accede.

**Cierre de cada fase** (§4.4):
1. Pruebas de la fase, suite completa, `types:check` y `pint --test`.
2. Un commit con el ID de la regla y sin push.
3. `gentle-ai review assess` sobre el rango de la fase. Si sale `high`, propongo 4R y espero tu decisión.
4. `mem_save` de las decisiones técnicas.
5. Actualizar la Evidencia.

## 6. Evidencia
| Fase | Test → resultado | Commit | Revisión (assess) |
|---|---|---|---|
| 0 | `sail pest`: 2/2 verde; `phpunit.xml` intacto; `pint --test` verde | `b9a74a8`, `d4b2c14`, `ba84a62`, `a53e8bf` | medio, `under_budget` |
| 1 | `--filter=BR-01`: 5/5; suite 7/7; pint verde; Larastan 8 verde | `d5aea1e` | medio, `under_budget` |
| 2 | BR-56 (2) y BR-57 (2): suite 11/11 (dos corridas, valida `migrate:fresh`); pint y Larastan 8 verdes | `76f5b34`, `dec7216` | 4R sobre `d5aea1e^..HEAD` (fases 1 y 2): **aprobada**, sin bloqueantes y con 11 hallazgos informativos. Pedidos tuyos corregidos con su RED en `dec7216`: TRUNCATE bloqueado y `secret` redactado. Suite 13/13, pint y Larastan 8 verdes |
| 3 | 52/52 verde (dos corridas); `pint` y Larastan 8 verdes; REDs observados antes de implementar (18 fallos por clases inexistentes y 9 por el fix previo); diferencial: `/vet/login` sigue en 200 tras habilitar `->passwordReset()` | `ceffde3` | pendiente de `assess` |
| 4 | — | — | — |

**Hallazgos de la 4R (informativos) tras el commit `a10ffb2`:**
- Corregidos con su test RED:
  - `R1-seeder-default-user`: el seeder ya no crea usuarios. El primer admin sale de `app:create-admin` (`BootstrapAdminAction`, con auditoría sin actor).
  - `R1/R2/R3-redact-*`: lista explícita de claves (`password`, `password_confirmation`, `remember_token`, `token`, `secret`, `api_key`, `hash` y `*_hash`) más los `$hidden` del modelo.
  - `R2-seeder-guard-literal`: el guard sale de `auth.defaults.guard`.
- Código muerto: `R2-isstaff-unused` se eliminó (sin test: no tiene comportamiento). `RoleName::isStaff()` se reintroduce solo si la Fase 3 lo necesita.
- `R3-down-order` y `R4-audit-down-order`: `down()` ya borra la tabla y luego la función, y el test `migrate:rollback` + `migrate` fija el orden. **No fue RED**: ese test ya pasaba antes del cambio, así que el fallo no era reproducible.
- Aceptados, sin cambios:
  - `R3-ip-null-cli`: `ip` nulo fuera de HTTP es correcto.
  - `R4-audit-fk-restrict`: los usuarios se desactivan, no se borran.
  - `R4-audit-gin-write-cost`: los GIN sobre `before` y `after` los exige CONSTITUTION §6.
- Consecuencia asumida de la lista explícita: claves como `api_token`, `client_secret` o `*_secret` **ya no se redactan**. Si la clínica o una feature futura las usa, hay que agregarlas a la lista.
