# 003 · Visor de auditoría (BR-60, nueva)

Rama: `feat/003-audit-viewer` (apilada sobre `feat/002-finance-and-rates`) · Zona roja: permisos y auditoría (4R en el cierre de fase) · Estado: **cerrada el 2026-11-09 (fases 1 a 3); falta solo el push y el merge, que decide el humano**

## 1. Spec
Pedido del usuario, literal:

> una duda, la aufitoias tendra su modulo en el dashboard corrrecto?
> si para 003

Contexto verificado: `audit_logs` se escribe (BR-56, BR-57) pero ningún documento define una pantalla para consultarla. El ROADMAP exige que un requisito nuevo entre primero como BR con su test RED.

## 2. Alcance
- **Cubre:** BR-60 (nueva) y BR-56 en lo que toca a que la pantalla no pueda modificar nada.
- **Fuera de alcance:**
  - Exportar a CSV o PDF.
  - Retención o purga (BR-56 prohíbe borrar filas).
  - Alertas sobre eventos de auditoría.
  - Auditar el hecho de consultar la auditoría.
- **BR-60 (propuesta, se agrega a `BUSINESS_RULES.md` al aprobar la puerta):** consultar `audit_logs` exige `audit.view`, y la pantalla es de solo lectura: no crea, edita ni borra filas.

## 3. Decisiones (puerta aprobada 2026-11-09)
```text
[DEC-PENDIENTE] BR-60
Contexto: la auditoría guarda quién cambió permisos, personal, ajustes y tasas, con antes y después.
Duda: ¿qué roles reciben `audit.view`?
Opciones: A) Solo admin.
          B) admin y manager.
Recomendación técnica: A. La auditoría registra cambios del propio manager (por ejemplo, tasas y personal);
          que ese mismo rol la lea le permite revisar su propio rastro. Si la clínica quiere que el gerente
          supervise, se amplía después con una línea en el seeder.
Resuelta: A, 2026-11-09.
```
Asunciones técnicas mías, sin decisión de negocio:
- Las horas se muestran en `ClinicSettings::timezone` (Constitución §6); en la BD siguen en UTC.
- La pantalla muestra los valores tal como se guardaron: la redacción de secretos ocurre al escribir (BR-56), no al leer.
- Una acción sin etiqueta en español se muestra con su clave (`exchange_rate.updated`), nunca se oculta.

## 4. Diseño técnico
- **`Enums/Permission`** suma `AuditView = 'audit.view'`. El seeder lo da solo a `admin` (DEC-14 = A).
- **`Policies/AuditLogPolicy`:** `viewAny` y `view` solo preguntan por `audit.view`; `create`, `update`, `delete`, `deleteAny` y los demás devuelven `false`. Filament permite toda habilidad que la Policy no define, así que cada una va explícita (lección de la feature 001).
- **`Models/AuditLog`** suma la relación `actor()` (`belongsTo User`), sin tocar la tabla.
- **Migración nueva** con índices para filtrar sin recorrer la tabla: `created_at`, `actor_id` y `action`. La tabla es append-only por trigger, y los índices no lo afectan. Se acepta su costo de escritura (mismo criterio que el GIN, R4-audit-gin-write-cost).
- **`Queries/Settings/AuditLogsQuery::builder()`:** `AuditLog` con el actor cargado, ordenado por `id` descendente.
- **`Support/Audit/AuditActionLabel`:** mapa acción → etiqueta en español (`staff.created`, `staff.updated`, `settings.updated`, `exchange_rate.created`, `exchange_rate.updated`); si no existe, devuelve la clave.
- **`Filament/Resources/AuditLogs/AuditLogResource`** (solo lectura):
  - Páginas `index` y `view`; sin `create` ni `edit`.
  - Tabla con fecha (en la zona de la clínica), actor ("Sistema" si es nulo), acción, entidad (tipo e id) e IP.
  - Filtros por actor, acción, tipo de entidad y rango de fechas.
  - La vista de detalle muestra `before` y `after` como JSON legible.
  - Sin acciones de fila ni masivas, y paginada.
  - Todas las habilidades de escritura devuelven `Response::deny()` de forma explícita.
- **Navegación:** grupo "Ajustes" junto a Personal y Ajustes de la clínica; oculto sin `audit.view`.

**Invariantes de seguridad:** sin `hasRole` ni `Gate::before`; la denegación sale por Policy; un `admin` sin `audit.view` queda denegado; ninguna ruta de la pantalla puede escribir en `audit_logs`.

## 5. Tareas
**Fase 1 · Permiso y Policy (BR-60)**
- [x] 1.1 RED: `BR-60 seeds audit.view only to the roles of DEC-14`, `BR-60 denies viewing audit logs without audit.view`, `BR-60 denies every write ability on audit logs even for admin`. Test de comportamiento previo: los permisos existentes no cambian.
- [x] 1.2 `Permission::AuditView`, seeder y `AuditLogPolicy`.
- [x] 1.3 `BUSINESS_RULES.md`: BR-60 y DEC-14 resuelta (mismo commit que los tests).

**Fase 2 · Consulta e índices** (zona roja; depende de 1.x)
- [x] 2.1 RED: `BR-60 lists audit logs newest first with their actor`, `BR-60 labels known actions in Spanish and falls back to the key`, y las migraciones crean los tres índices y hacen rollback limpio.
- [x] 2.2 Relación `actor()`, migración de índices, `AuditLogsQuery` y `AuditActionLabel`.

**Fase 3 · Pantalla de solo lectura** (zona roja; depende de 2.x)
- [x] 3.1 RED:
  - `BR-60 shows the audit screen only with audit.view` (200 y 403 por HTTP).
  - `BR-60 exposes no create, edit or delete route`.
  - `BR-60 filters by actor, action and date range`.
  - `BR-60 shows before and after values on the detail page`.
  - `BR-60 shows times in the clinic timezone`.
- [x] 3.2 `AuditLogResource`, páginas, tabla, filtros y detalle.

**Cierre**
- [ ] 4.1 Confirmación humana (push y merge).

## 6. Evidencia
| Fase | Commit | Pruebas | Revisión (`assess`) |
|---|---|---|---|
| 1 · Permiso y Policy | `fc3c3f3` | `AuditViewPermissionTest` (12): solo admin; todas las habilidades de escritura denegadas | medium, sin 4R |
| 2 · Consulta e índices | `8c81f9b` | `AuditLogsQueryTest` (4) y rollback de los índices; sin N+1 (2 consultas) | medium, sin 4R |
| 3 · Pantalla | `334c099` | `AuditLogResourceTest` (9): 200/403 por HTTP, sin rutas de escritura, filtros, antes/después, zona horaria | medium, sin 4R |

- Suite 180/180, Larastan nivel 8 y Pint en verde. El rango completo (650 líneas) también dio medium, `under_budget`.
- Hallazgo real de la fase 3: `/vet/audit-logs/create` casaba con `/{record}` y daba **500** (`invalid input syntax for type bigint`) en vez de 404. `ViewAuditLog::resolveRecord` ahora lanza `ModelNotFoundException` ante una clave no numérica, y un test lo fija.
- Los filtros de fecha usan los límites del día en la zona de la clínica, no en UTC.
- Pendiente por la asunción de §3: auditar el hecho de consultar la auditoría queda fuera de alcance.
