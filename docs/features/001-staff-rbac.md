# 001 · Staff RBAC (BR-01)

Rama: `feat/001-staff-rbac` · Zona roja: permisos (4R en el cierre de fase) · Estado: **esperando la puerta (§4.3)**

## 1. Spec
Pedido del usuario, literal:

> Implementa BR-01 siguiendo AGENTS.md (zona roja: permisos → Feature Document + puerta).
> Crea docs/features/001-staff-rbac.md y detente al terminar Diseño técnico y Tareas.

BR-01, literal (`docs/business/BUSINESS_RULES.md`):

> Crear o editar personal exige `staff.manage`. Asignar el rol `admin` exige además `staff.manage_admins`. El seeder solo da estos permisos a `admin`, y a `manager` solo el primero.
> Action → Excepción: `CreateStaffMemberAction` → `AuthorizationException`
> Test RED: `it('BR-01 forbids users without staff.manage from creating staff')`

## 2. Alcance
- **Cubre:** BR-01.
- **Fuera de alcance:**
  - BR-02 (permisos financieros).
  - BR-56 y BR-57: `audit_logs` y la auditoría de "cambiar permisos o personal" todavía no existen. Cuando BR-57 llegue, las Actions de esta feature se auditan dentro de su transacción. No se dejan stubs.
  - Baja o desactivación del personal: BR-01 solo habla de crear y editar.
- **Lectura técnica:** "personal" = un `User` del sistema con un rol de personal. Un empleado de nómina sin acceso (HR, DEC-01) no es personal en este sentido.

## 3. Decisiones
Cada `[DEC-PENDIENTE]` bloquea solo las tareas que dependen de él. Los IDs `DEC-09` a `DEC-11` son propuestos y se vuelcan a la tabla de `BUSINESS_RULES.md` al resolverlos. La numeración es una asunción mía: confírmala o corrígela.

```text
[DEC-PENDIENTE] BR-01 · DEC-09
Contexto: CreateStaffMemberAction necesita crear el User, y BR-01 no define sus datos ni su credencial inicial.
Duda: ¿Qué datos lleva el personal y cómo recibe su acceso?
Opciones:
  A) name, email y rol. El actor fija la contraseña inicial. Es simple, pero circula una contraseña entre personas.
  B) name, email y rol. El personal recibe un link para definir su contraseña, enviado solo después del commit, como en BR-08. Es más seguro y reutiliza el patrón, pero exige correo operativo.
Recomendación técnica: B.

[DEC-PENDIENTE] BR-01 · DEC-10
Contexto: "Asignar el rol admin" presupone que se asignan roles, y la Constitución §4 define 5 roles.
Duda: ¿Cuántos roles tiene una persona y cuáles puede asignar quien tiene solo staff.manage?
Opciones:
  A) Un solo rol por persona. Tanto admin como manager (con staff.manage) pueden asignar manager, reception y doctor. `admin` exige staff.manage_admins y `owner` nunca se asigna aquí. Es lo más simple y deja claro quién tiene qué.
  B) Varios roles por persona. Más flexible, pero la unión de permisos es más difícil de razonar y de auditar.
Recomendación técnica: A.

[DEC-PENDIENTE] BR-01 · DEC-11
Contexto: BR-01 exige staff.manage_admins al asignar el rol admin, pero no dice nada del caso en que se edita a alguien que ya es admin.
Duda: ¿Editar a un admin existente (sus datos, o quitarle el rol) exige también staff.manage_admins?
Opciones:
  A) Sí, para cualquier edición de un admin y para cualquier cambio que lo deje como admin. Cierra la escalación: un manager no puede quitarle el rol ni cambiarle el correo a un admin.
  B) No, solo se exige al asignar el rol. Un manager podría cambiar el email de un admin y tomar su cuenta por el reset.
Recomendación técnica: A.
```

## 4. Diseño técnico
**Piezas** (todo en inglés; la UI, si se llega, en español). `Settings` es el módulo de las Actions, porque M0 es "Ajustes, permisos y auditoría" y HR es nómina:
- `Enums/Permission.php`: es el catálogo único (§4) y arranca con `StaffManage = 'staff.manage'` y `StaffManageAdmins = 'staff.manage_admins'`. Cada BR posterior agrega los suyos.
- `Enums/RoleName.php`: es un backed enum con los 5 roles (`admin`, `manager`, `reception`, `doctor`, `owner`).
- `Models/User` recibe `HasRoles`.
- `Database/Seeders/RolesAndPermissionsSeeder`: es idempotente (`firstOrCreate` + `syncPermissions`) y limpia la caché de permisos. Mantiene un único mapa rol→permisos que los BR posteriores extienden. Hoy: `admin` = ambos, `manager` = `staff.manage`, y `reception`, `doctor` y `owner` = ninguno. Se invoca desde `DatabaseSeeder`.
- `Policies/UserPolicy`: sus habilidades son `createStaff`, `updateStaff` y `assignAdminRole`, y solo preguntan por permisos (`can(Permission::…->value)`). Los nombres evitan chocar con `RegisterOwnerAction` (BR-08), que crea Users `owner` sin `staff.manage`.
- `Actions/Settings/CreateStaffMemberAction` y `UpdateStaffMemberAction`: son `final`, con un único `execute(User $actor, StaffMemberData $data)`. Autorizan al inicio con `Gate::forUser($actor)->authorize(...)`, y **antes de cualquier escritura**. Escriben dentro de `DB::transaction()` (User + rol).
- `DTOs/Settings/StaffMemberData`: es `readonly`; sus campos dependen de DEC-09 y DEC-10.

**Invariantes de seguridad**
- No hay `hasRole`, `Gate::before` ni superadmin implícito (§4). Un `admin` sin `staff.manage_admins` queda denegado, y un test lo fija.
- La denegación sale por Policy y lanza `Illuminate\Auth\Access\AuthorizationException`, como pide BR-01. La regla 4 de §3 pide excepciones de dominio, pero aquí el `Gate` ya cubre el caso, así que no se crea una `Domain\*` para BR-01.
- Las pruebas de autorización se hacen contra la Action, no contra la UI (§6).

**Base de datos**
- Tablas de spatie (`roles`, `permissions`, `model_has_*`), ya publicadas como migración nueva sin `team`.
- No se tocan migraciones aplicadas en `main` (§6).

**Estado previo del árbol:** `composer.json/lock`, `config/permission.php` y la migración de spatie están sin commitear, y los cubre la Fase 0.
- `AGENTS.md`, `CLAUDE.md` y `docs/` no son parte de esta feature y no entran en sus commits.
- Tampoco lo hace el borrado de `.gitignore:Zone.Identifier`.

**Pantalla (Fase 3, opcional).** `StaffResource` de Filament delega en las Actions vía `handleRecordCreation/Update`, y se oculta sin `staff.manage`. Es UI nueva, no la pide BR-01, y queda para decidirse en la puerta.

## 5. Tareas
Cada tarea sigue TDD estricto (RED → GREEN → refactor), y los nombres de los tests empiezan por `BR-01`.

**Fase 0 · Línea base**
- [ ] 0.1 Verificar spatie (composer, config y migración) contra PostgreSQL `testing`, y commitear con `chore(deps): add spatie/laravel-permission [BR-01]`.

**Fase 1 · Catálogo y roles** (depende de 0.1; sin DEC)
- [ ] 1.1 RED: `BR-01 seeds staff.manage to admin and manager`, `BR-01 seeds staff.manage_admins only to admin` y `BR-01 grants no staff permission to reception, doctor or owner`.
- [ ] 1.2 `Permission`, `RoleName`, `HasRoles` en `User`, `RolesAndPermissionsSeeder` y su llamada desde `DatabaseSeeder` (depende de 1.1).
- [ ] 1.3 Un test que prueba que el comportamiento anterior de `User` se mantiene: `BR-01 keeps user factory and password hashing unchanged`.

**Fase 2 · Autorización y Actions** (zona roja; depende de 1.x y de **DEC-09 y DEC-10**; la edición, también de DEC-11)
- [ ] 2.1 RED en `tests/Feature/Settings/CreateStaffMemberActionTest.php`:
  - `BR-01 forbids users without staff.manage from creating staff` (nombre del catálogo).
  - `BR-01 forbids manager from assigning the admin role`.
  - `BR-01 lets manager create non-admin staff`.
  - `BR-01 lets admin create staff with the admin role`.
  - `BR-01 denies an admin who lacks staff.manage_admins` (prueba que no hay superadmin implícito).
  - `BR-01 persists nothing when authorization fails`.
- [ ] 2.2 `StaffMemberData`, `UserPolicy` y `CreateStaffMemberAction` (depende de 2.1).
- [ ] 2.3 RED y luego implementación de `UpdateStaffMemberAction`:
  - `BR-01 forbids users without staff.manage from updating staff`.
  - `BR-01 forbids manager from promoting someone to admin`.
  - `BR-01 forbids manager from editing an existing admin` (depende de DEC-11; si se resuelve en B, este test cambia).
- [ ] 2.4 Larastan nivel 8 sin baseline nuevo, `pint --test` y la suite completa.

**Fase 3 · Pantalla (opcional, se decide en la puerta)**
- [ ] 3.1 `StaffResource` de Filament que delega en las Actions, con textos de UI en español (depende de 2.x).

**Cierre de cada fase** (§4.4):
1. Pruebas de la fase, suite completa, `types:check` y `pint --test`.
2. Un commit con `[BR-01]` y sin push.
3. `gentle-ai review assess` sobre el rango de la fase. Si sale `high`, propongo 4R y espero tu decisión.
4. `mem_save` de las decisiones técnicas.
5. Actualizar la Evidencia.

## 6. Evidencia
| Fase | Test → resultado | Commit | Revisión (assess) |
|---|---|---|---|
| 0 | — | — | — |
| 1 | — | — | — |
| 2 | — | — | — |
| 3 | — | — | — |
